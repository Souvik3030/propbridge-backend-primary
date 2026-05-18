<?php
declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeveloperResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->source_id,
            'uuid'          => $this->id,
            'name'          => $this->name,
            'logo_url'      => $this->logo,
            'project_count' => $this->project_count,
            'projects'      => $this->whenLoaded('projects', fn() =>
                ProjectResource::collection($this->projects)
            ),
        ];
    }
}
