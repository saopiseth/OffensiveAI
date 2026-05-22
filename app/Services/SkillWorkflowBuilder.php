<?php

namespace App\Services;

use App\Models\Skill;
use App\Models\Workflow;
use App\Models\User;

/**
 * Converts a Skill (and its steps) into a ReactFlow-compatible Workflow.
 *
 * Layout
 * ──────
 * Input node → [Skill node per step, snake layout] → Output node
 *
 * The graph_data JSON matches exactly what WorkflowBuilder.jsx expects:
 *   { nodes: [...], edges: [...] }
 */
class SkillWorkflowBuilder
{
    private const X_START  = 80;
    private const X_STEP   = 280;
    private const Y_BASE   = 200;
    private const ROW_SIZE = 4;
    private const Y_STEP   = 260;

    // ── Public API ────────────────────────────────────────────────────────────

    public function build(Skill $skill, mixed $createdBy = null): Workflow
    {
        $createdBy ??= User::role('admin')->first()?->id ?? User::first()?->id;

        $steps = $skill->steps()->where('is_active', true)->orderBy('execution_order')->get();

        $nodes = [];
        $edges = [];
        $ts    = now()->timestamp;

        // ── Input node ────────────────────────────────────────────────────────

        $inputId  = "node_{$ts}_input";
        $nodes[]  = $this->makeNode($inputId, 'input', 'Input', self::X_START, self::Y_BASE);

        $prevId = $inputId;

        // ── Skill step nodes ──────────────────────────────────────────────────

        foreach ($steps as $i => $step) {
            $nodeId  = "node_{$ts}_step_{$step->execution_order}";
            [$x, $y] = $this->snakePosition($i);

            $nodes[] = $this->makeNode($nodeId, 'skill', $step->name, $x, $y, [
                'skill_id'   => $skill->id,
                'skill_name' => $skill->name,
                'step_order' => $step->execution_order,
            ]);

            $edges[] = $this->makeEdge("{$prevId}__{$nodeId}", $prevId, $nodeId);

            $prevId = $nodeId;
        }

        // If no steps, connect directly to a single skill node
        if ($steps->isEmpty()) {
            $nodeId  = "node_{$ts}_skill";
            $nodes[] = $this->makeNode($nodeId, 'skill', $skill->name, self::X_START + self::X_STEP, self::Y_BASE, [
                'skill_id'   => $skill->id,
                'skill_name' => $skill->name,
            ]);

            $edges[] = $this->makeEdge("{$inputId}__{$nodeId}", $inputId, $nodeId);
            $prevId  = $nodeId;
        }

        // ── Output node ───────────────────────────────────────────────────────

        $lastNode  = end($nodes);
        $outputId  = "node_{$ts}_output";
        $outputX   = $lastNode['position']['x'] + self::X_STEP;
        $outputY   = $lastNode['position']['y'];

        $nodes[] = $this->makeNode($outputId, 'output', 'Output', $outputX, $outputY);
        $edges[] = $this->makeEdge("{$prevId}__{$outputId}", $prevId, $outputId);

        // ── Persist workflow ──────────────────────────────────────────────────

        return Workflow::updateOrCreate(
            ['name' => $skill->name . ' Workflow', 'created_by' => $createdBy],
            [
                'description' => $skill->description,
                'created_by'  => $createdBy,
                'status'      => 'published',
                'is_active'   => true,
                'graph_data'  => ['nodes' => $nodes, 'edges' => $edges],
            ]
        );
    }

    // ── Node / edge builders ──────────────────────────────────────────────────

    private function makeNode(string $id, string $type, string $label, int $x, int $y, array $extra = []): array
    {
        return [
            'id'       => $id,
            'type'     => 'custom',
            'position' => ['x' => $x, 'y' => $y],
            'data'     => array_merge(['type' => $type, 'label' => $label], $extra),
        ];
    }

    private function makeEdge(string $id, string $source, string $target, string $label = ''): array
    {
        $edge = [
            'id'        => $id,
            'source'    => $source,
            'target'    => $target,
            'style'     => ['stroke' => '#6d28d9', 'strokeWidth' => 2],
            'markerEnd' => ['type' => 'arrowclosed', 'color' => '#6d28d9'],
        ];

        if ($label) {
            $edge['label'] = $label;
        }

        return $edge;
    }

    /**
     * Snake layout: even rows left→right, odd rows right→left.
     * Returns [x, y] for step index $i (0-based).
     */
    private function snakePosition(int $i): array
    {
        $row    = (int) floor($i / self::ROW_SIZE);
        $col    = $i % self::ROW_SIZE;
        $y      = self::Y_BASE + $row * self::Y_STEP;

        // Odd rows run right-to-left
        if ($row % 2 === 1) {
            $col = self::ROW_SIZE - 1 - $col;
        }

        $x = self::X_START + ($col + 1) * self::X_STEP;

        return [$x, $y];
    }
}
