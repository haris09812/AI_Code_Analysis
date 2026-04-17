<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AnalysisJobResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'job_id'          => $this->id,
            'status'          => $this->status->value,
            'progress'        => $this->progress,       
            'total_files'     => $this->total_files,
            'processed_files' => $this->processed_files,
            'error_message'   => $this->error_message,
            'started_at'      => $this->started_at?->toISOString(),
            'completed_at'    => $this->completed_at?->toISOString(),
            'repository'      => new RepositoryResource($this->whenLoaded('repository')),
        ];
    }
}
