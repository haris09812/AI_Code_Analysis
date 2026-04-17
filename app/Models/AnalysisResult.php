<?php
// app/Models/AnalysisResult.php

namespace App\Models;

use App\Enums\FileClassification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalysisResult extends Model
{
    protected $fillable = [
        'analysis_job_id',
        'file_path',
        'file_extension',
        'classification',
        'confidence_score',
        'explanation',
        'signals',
        'suggestion',
        'source',
        'raw_llm_response',
    ];

    protected $casts = [
        'classification' => FileClassification::class,
        'signals'        => 'array',
    ];

    public function analysisJob()
    {
        return $this->belongsTo(AnalysisJob::class);
    }
}
