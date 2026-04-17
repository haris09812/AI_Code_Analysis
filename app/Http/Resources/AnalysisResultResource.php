<?php
// app/Http/Resources/AnalysisResultResource.php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AnalysisResultResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'file_path'        => $this->file_path,
            'file_extension'   => $this->file_extension,
            'classification'   => $this->classification->value,
            'confidence_score' => $this->confidence_score,
            'explanation'      => $this->explanation,
            'signals'          => $this->signals ?? [],
            'suggestion'       => $this->suggestion,
            'source'           => $this->source,
        ];
    }
}
