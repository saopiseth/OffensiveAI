<?php

namespace App\Services;

use App\Models\Execution;
use App\Models\ExecutionLog;
use App\Models\Target;

class ExecutionService
{
    public function __construct(
        private AiProviderService      $aiProvider,
        private BurpSuiteService       $burpService,
        private CodeRepositoryService  $repoService,
    ) {}

    // ─── Skill execution ──────────────────────────────────────────────────────

    public function executeSkill(Execution $execution): void
    {
        $execution->update(['status' => 'running', 'started_at' => now()]);

        try {
            $skill = $execution->skill()->with('steps')->first();
            if (! $skill) {
                $execution->update([
                    'status'        => 'failed',
                    'error_message' => 'Skill not found or has been deleted.',
                    'completed_at'  => now(),
                ]);
                return;
            }
            $steps = $skill->steps()->where('is_active', true)->orderBy('execution_order')->get();

            $context     = $this->buildContext($execution);
            $stepOutputs = [];

            foreach ($steps as $step) {
                $log = ExecutionLog::create([
                    'execution_id'  => $execution->id,
                    'skill_step_id' => $step->id,
                    'step_order'    => $step->execution_order,
                    'step_name'     => $step->name,
                    'status'        => 'running',
                ]);

                try {
                    $prompt = $step->renderPrompt(array_merge($context, $stepOutputs));

                    // Use the step's configured provider; fall back to the active provider
                    $providerSetting = null;
                    if (! empty($step->ai_provider)) {
                        $providerSetting = $this->aiProvider->getProviderByName($step->ai_provider);
                    }

                    $result = $this->aiProvider->complete($prompt, $providerSetting, [
                        'model'         => $step->model,
                        'temperature'   => $step->temperature,
                        'max_tokens'    => $step->max_tokens,
                        'system_prompt' => $step->system_prompt ?? null,
                    ]);

                    $stepOutputs['step_' . $step->execution_order] = $result['content'];
                    $stepOutputs['last_output'] = $result['content'];

                    $log->update([
                        'status'          => 'completed',
                        'prompt_rendered' => $prompt,
                        'output_data'     => $result['content'],
                        'ai_provider'     => $result['provider'],
                        'model_used'      => $result['model'],
                        'tokens_used'     => $result['tokens_used'],
                        'duration_ms'     => $result['duration_ms'],
                    ]);
                } catch (\Throwable $e) {
                    $log->update(['status' => 'failed', 'error_message' => mb_substr($e->getMessage(), 0, 5000)]);
                    throw $e;
                }
            }

            $execution->update([
                'status'       => 'completed',
                'output_data'  => $stepOutputs,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $execution->update([
                'status'        => 'failed',
                'error_message' => mb_substr($e->getMessage(), 0, 5000),
                'completed_at'  => now(),
            ]);
        }
    }

    // ─── Workflow execution ───────────────────────────────────────────────────

    public function executeWorkflow(Execution $execution): void
    {
        $execution->update(['status' => 'running', 'started_at' => now()]);

        try {
            $workflow  = $execution->workflow()->firstOrFail();
            $graphData = $workflow->graph_data ?? [];
            $isApt     = isset($graphData['apt_meta']);
            $target    = $execution->target_id ? Target::find($execution->target_id) : null;
            $context   = $this->buildContext($execution, $target);
            $outputs   = [];
            $stepOrder = 1;

            foreach ($graphData['nodes'] ?? [] as $node) {
                if (! $this->isSkillNode($node)) {
                    continue;
                }

                if ($isApt && !empty($node['data']['prompt_template'])) {
                    // APT E2E mode — run node's embedded prompt directly
                    $output = $this->runAptNode($execution, $node, $context, $target, $stepOrder);
                } else {
                    // Standard mode — run via DB skill steps
                    $skillId = $this->resolveSkillId($node);
                    if (! $skillId) continue;

                    // Skip node if the referenced skill no longer exists
                    if (! \App\Models\Skill::where('id', $skillId)->exists()) {
                        \Illuminate\Support\Facades\Log::warning("Workflow {$execution->workflow_id}: skipping node — skill {$skillId} not found.");
                        continue;
                    }

                    $childExecution = Execution::create([
                        'user_id'              => $execution->user_id,
                        'skill_id'             => $skillId,
                        'target_id'            => $execution->target_id,
                        'parent_execution_id'  => $execution->id,
                        'type'                 => 'skill',
                        'status'               => 'pending',
                        'input_data'           => array_merge($context, $outputs),
                    ]);

                    $this->executeSkill($childExecution);
                    $output = $childExecution->fresh()->output_data;
                }

                $outputs[$node['id']] = $output;
                $stepOrder++;
            }

            $execution->update([
                'status'       => 'completed',
                'output_data'  => $outputs,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $execution->update([
                'status'        => 'failed',
                'error_message' => mb_substr($e->getMessage(), 0, 5000),
                'completed_at'  => now(),
            ]);
            return;
        }

        // Mark report as pending then dispatch; frontend polls report_status.
        $execution->update(['report_status' => 'pending']);
        \App\Jobs\GenerateExecutionReportJob::dispatch($execution->id);
    }

    // ─── APT node runner ──────────────────────────────────────────────────────

    private function runAptNode(
        Execution $execution,
        array     $node,
        array     $context,
        ?Target   $target,
        int       $stepOrder
    ): array {
        $data     = $node['data'];
        $template = $data['prompt_template'];

        $vars = array_merge($context, [
            'stage_name' => $data['label']             ?? '',
            'technique'  => $data['technique']          ?? '',
            'input'      => ($target?->value ?? 'N/A') . "\n" . ($data['simulation_input'] ?? ''),
        ]);

        $prompt = $template;
        foreach ($vars as $key => $value) {
            $prompt = str_replace('{{' . $key . '}}', (string) $value, $prompt);
        }

        $result = $this->aiProvider->complete($prompt, null, [
            'model'       => 'claude-opus-4-7',
            'max_tokens'  => 2048,
            'temperature' => 0.3,
        ]);

        ExecutionLog::create([
            'execution_id'    => $execution->id,
            'skill_step_id'   => null,
            'step_order'      => $stepOrder,
            'step_name'       => $data['label'] ?? ('APT Stage ' . $stepOrder),
            'status'          => 'completed',
            'prompt_rendered' => $prompt,
            'output_data'     => $result['content'],
            'ai_provider'     => $result['provider'],
            'model_used'      => $result['model'],
            'tokens_used'     => $result['tokens_used'],
            'duration_ms'     => $result['duration_ms'],
        ]);

        return ['output' => $result['content'], 'stage' => $data['label'] ?? ''];
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function buildContext(Execution $execution, ?Target $target = null): array
    {
        $raw = $execution->input_data ?? [];

        // Guard against accidental double-wrapping: { input_data: { target: ... } }
        $ctx = (isset($raw['input_data']) && is_array($raw['input_data']))
            ? $raw['input_data']
            : $raw;

        // Inject target variables if attached
        $resolvedTarget = $target ?? ($execution->target_id ? Target::find($execution->target_id) : null);

        if ($resolvedTarget) {
            $ctx = array_merge($ctx, [
                'target'       => $resolvedTarget->value,
                'target_name'  => $resolvedTarget->name,
                'target_type'  => $resolvedTarget->type,
            ]);
        }

        // Format credentials array into a readable {{credentials}} variable
        if (isset($ctx['credentials']) && is_array($ctx['credentials']) && count($ctx['credentials']) > 0) {
            $ctx['credentials'] = $this->formatCredentials($ctx['credentials']);
        }

        // Fetch live Burp Suite data when integration is enabled
        if (!empty($ctx['burp']['enabled'])) {
            $burpCtx = $this->burpService->fetchContext($ctx['burp']);
            $ctx     = array_merge($ctx, $burpCtx);
        } else {
            $ctx['burp_proxy']     = $ctx['burp_proxy']     ?? '';
            $ctx['burp_issues']    = $ctx['burp_issues']    ?? '';
            $ctx['burp_endpoints'] = $ctx['burp_endpoints'] ?? '';
            $ctx['burp_summary']   = $ctx['burp_summary']   ?? '';
        }
        unset($ctx['burp']);

        // Fetch code repository when configured
        if (!empty($ctx['repo']['enabled'])) {
            $repoCtx = $this->repoService->fetchContext($ctx['repo']);
            $ctx     = array_merge($ctx, $repoCtx);
        } else {
            $ctx['repo_url']       = $ctx['repo_url']       ?? '';
            $ctx['repo_branch']    = $ctx['repo_branch']    ?? '';
            $ctx['repo_provider']  = $ctx['repo_provider']  ?? '';
            $ctx['repo_structure'] = $ctx['repo_structure'] ?? '';
            $ctx['repo_code']      = $ctx['repo_code']      ?? '';
            $ctx['repo_summary']   = $ctx['repo_summary']   ?? '';
        }
        unset($ctx['repo']);

        return $ctx;
    }

    private function formatCredentials(array $credentials): string
    {
        $rows = [];
        foreach ($credentials as $cred) {
            $role     = trim($cred['role']     ?? '');
            $username = trim($cred['username'] ?? '');
            $password = $cred['password'] ?? '';

            if ($username === '' && $password === '') continue;

            $label = $role !== '' ? $role : 'User';
            $rows[] = "- **{$label}**: username=`{$username}` / password=`{$password}`";
        }

        if (empty($rows)) return '';

        return "Test accounts provided for this engagement:\n" . implode("\n", $rows);
    }

    private function isSkillNode(array $node): bool
    {
        $nodeType = $node['type'] ?? '';
        $dataType = $node['data']['type'] ?? '';

        return $nodeType === 'skillNode'
            || $dataType === 'skill'
            || ($nodeType === 'custom' && $this->resolveSkillId($node) !== null);
    }

    private function resolveSkillId(array $node): ?string
    {
        // Support both camelCase (APT/seeder) and snake_case (WorkflowBuilder) keys
        return $node['data']['skillId'] ?? $node['data']['skill_id'] ?? null;
    }
}
