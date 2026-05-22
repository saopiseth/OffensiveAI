<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SkillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'version' => $this->version,
            'category' => $this->category,
            'is_active' => $this->is_active,
            'tags' => $this->tags ?? [],
            'steps_count' => $this->whenLoaded('steps', fn() => $this->steps->count(), $this->steps_count ?? 0),
            'steps' => SkillStepResource::collection($this->whenLoaded('steps')),
            'creator' => $this->whenLoaded('creator', fn() => ['id' => $this->creator->id, 'name' => $this->creator->name]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
