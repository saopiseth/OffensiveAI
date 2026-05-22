<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_active' => $this->is_active,
            'email_verified_at' => $this->email_verified_at,
            'roles' => $this->whenLoaded('roles', fn() => $this->roles->map(fn($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'permissions' => $r->whenLoaded('permissions', fn() => $r->permissions->pluck('name')),
            ])),
            'permissions' => $this->when($this->relationLoaded('roles'), fn() => $this->getAllPermissions()->pluck('name')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
