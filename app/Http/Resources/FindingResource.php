<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FindingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'execution_id'   => $this->execution_id,
            'finding_order'  => $this->finding_order,
            'title'          => $this->title,
            'severity'       => $this->severity,
            'owasp_category' => $this->owasp_category,
            'cwe_id'         => $this->cwe_id,
            'description'    => $this->description,
            'impact'         => $this->impact,
            'recommendation' => $this->recommendation,
            'poc_steps'      => $this->poc_steps ?? [],
            'request'        => $this->request,
            'response'       => $this->response,
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
        ];
    }
}
