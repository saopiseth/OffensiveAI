<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class WorkflowResource extends JsonResource
{
    /** Pre-loaded skill map set by WorkflowController::index() to avoid N+1. */
    public static ?Collection $skillMap = null;

    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'graph_data'  => $this->graph_data,
            'is_active'   => $this->is_active,
            'status'      => $this->status,
            'stats'       => $this->computeStats(),
            'creator'     => $this->whenLoaded('creator', fn() => [
                'id'   => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
        ];
    }

    private function computeStats(): array
    {
        $skillMap   = static::$skillMap ?? collect();
        $nodes      = collect($this->graph_data['nodes'] ?? []);

        $skillNodes = $nodes->filter(fn($n) =>
            ($n['data']['type'] ?? '') === 'skill' ||
            ($n['type'] ?? '') === 'skillNode' ||
            isset($n['data']['skill_id']) ||
            isset($n['data']['skillId'])
        );

        $totalInput  = 0;
        $totalOutput = 0;
        $stepCount   = 0;

        foreach ($skillNodes as $node) {
            $skillId = $node['data']['skill_id'] ?? $node['data']['skillId'] ?? null;

            // APT node with embedded prompt
            if (! $skillId && ! empty($node['data']['prompt_template'])) {
                $totalInput  += (int) ceil(strlen($node['data']['prompt_template']) / 4);
                $totalOutput += 2048;
                $stepCount++;
                continue;
            }

            if (! $skillId) continue;

            $skill = $skillMap->get($skillId);
            if (! $skill) continue;

            $skillInput  = 0;
            $skillOutput = 0;
            $i           = 0;

            foreach ($skill->steps as $step) {
                $chainBonus   = $i > 0 ? (int) ceil($skillOutput / $i) : 0;
                $skillInput  += (int) ceil(strlen($step->prompt_template) / 4) + $chainBonus;
                $skillOutput += min($step->max_tokens ?? 2048, 4096);
                $i++;
            }

            $totalInput  += $skillInput;
            $totalOutput += $skillOutput;
            $stepCount   += $skill->steps->count();
        }

        return [
            'skills_count'  => $skillNodes->count(),
            'steps_count'   => $stepCount,
            'input_tokens'  => $totalInput,
            'output_tokens' => $totalOutput,
            'total_tokens'  => $totalInput + $totalOutput,
        ];
    }
}
