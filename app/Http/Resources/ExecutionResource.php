<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExecutionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->id,
            'type'     => $this->type,
            'run_name' => $this->run_name,
            'status'   => $this->status,
            'input_data' => $this->input_data,
            'output_data' => $this->output_data,
            'error_message' => $this->error_message,
            'retry_count' => $this->retry_count,
            'duration' => $this->duration,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'created_at' => $this->created_at,
            'user'             => $this->whenLoaded('user'),
            'skill'            => $this->whenLoaded('skill',    fn() => ['id' => $this->skill?->id,    'name' => $this->skill?->name]),
            'workflow'         => $this->whenLoaded('workflow', fn() => ['id' => $this->workflow?->id, 'name' => $this->workflow?->name]),
            'target'           => $this->whenLoaded('target',   fn() => $this->target ? [
                'id'    => $this->target->id,
                'name'  => $this->target->name,
                'type'  => $this->target->type,
                'value' => $this->target->value,
            ] : null),
            'report'              => $this->output_data['report'] ?? null,
            'report_status'       => $this->report_status,
            'report_error'        => $this->report_error,
            'report_generated_at' => $this->report_generated_at,
            'logs'                => $this->whenLoaded('logs'),
            'child_executions' => $this->whenLoaded('childExecutions', fn() =>
                $this->childExecutions->map(fn($child) => [
                    'id'            => $child->id,
                    'status'        => $child->status,
                    'started_at'    => $child->started_at,
                    'completed_at'  => $child->completed_at,
                    'duration'      => $child->duration,
                    'error_message' => $child->error_message,
                    'skill'         => $child->skill ? ['id' => $child->skill->id, 'name' => $child->skill->name] : null,
                    'logs'          => $child->logs?->values() ?? [],
                ])
            ),
        ];
    }
}
