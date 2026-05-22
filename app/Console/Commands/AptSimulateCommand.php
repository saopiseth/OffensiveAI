<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AptSimulationService;
use Illuminate\Console\Command;

class AptSimulateCommand extends Command
{
    protected $signature = 'apt:simulate
                            {scenario? : Scenario type (e.g. "ransomware infiltration simulation")}
                            {--list : Show preset scenario types}';

    protected $description = 'Generate an APT simulation workflow via Claude AI';

    private const PRESETS = [
        'phishing campaign simulation',
        'ransomware infiltration simulation',
        'insider threat simulation',
        'nation-state espionage simulation',
        'compromised endpoint investigation',
        'supply chain attack simulation',
        'cloud infrastructure breach simulation',
        'financial sector APT simulation',
    ];

    public function handle(AptSimulationService $service): int
    {
        if ($this->option('list')) {
            $this->info('Available preset scenarios:');
            foreach (self::PRESETS as $i => $p) {
                $this->line('  ' . ($i + 1) . '. ' . $p);
            }
            return self::SUCCESS;
        }

        $scenarioType = $this->argument('scenario');

        if (!$scenarioType) {
            $scenarioType = $this->choice(
                'Select a scenario type (or enter a custom one)',
                array_merge(self::PRESETS, ['[custom]']),
                0
            );

            if ($scenarioType === '[custom]') {
                $scenarioType = $this->ask('Enter your custom scenario type');
            }
        }

        $this->info("Generating APT simulation for: \"{$scenarioType}\"");
        $this->warn('Calling Claude API — this may take 15-30 seconds…');

        $bar = $this->output->createProgressBar(5);
        $bar->setFormat(' %current%/%max% [%bar%] %message%');
        $bar->setMessage('Analyzing threat landscape…');
        $bar->start();

        try {
            $adminId = User::where('email', 'admin@redto.app')->value('id');

            $bar->setMessage('Designing attack stages…');
            $bar->advance();

            $result = $service->simulate($scenarioType, $adminId);

            $bar->setMessage('Mapping MITRE ATT&CK…');
            $bar->advance();
            $bar->setMessage('Building workflow graph…');
            $bar->advance();
            $bar->setMessage('Generating advisory report…');
            $bar->advance();
            $bar->setMessage('Done.');
            $bar->advance();
            $bar->finish();
            $this->newLine(2);

            $wf       = $result['workflow'];
            $scenario = $result['scenario'];
            $advisory = $result['advisory'];

            $this->info("✅ Workflow created: {$wf->name}");
            $this->line("   ID       : {$wf->id}");
            $this->line('   Nodes    : ' . count($wf->graph_data['nodes']));
            $this->line('   Edges    : ' . count($wf->graph_data['edges']));
            $this->line('   Tokens   : ' . number_format($result['tokens_used']));
            $this->newLine();

            $this->table(
                ['Field', 'Value'],
                [
                    ['Threat Actor',  $scenario['threat_actor']  ?? '—'],
                    ['Target Type',   $scenario['target_type']   ?? '—'],
                    ['Objective',     $scenario['objective']      ?? '—'],
                    ['Risk Level',    strtoupper($advisory['risk_assessment'] ?? '—')],
                    ['Priority',      $advisory['incident_response_priority'] ?? '—'],
                ]
            );

            if (!empty($result['stages'])) {
                $this->newLine();
                $this->info('Attack stages generated:');
                $this->table(
                    ['Stage', 'Tactic', 'Technique', 'MITRE ID'],
                    array_map(fn($s) => [
                        $s['label'],
                        $s['tactic'],
                        $s['technique'],
                        $s['mitre_technique_id'],
                    ], $result['stages'])
                );
            }

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $bar->finish();
            $this->newLine(2);
            $this->error('Simulation failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
