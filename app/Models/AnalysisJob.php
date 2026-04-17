<?php
// app/Models/AnalysisJob.php

namespace App\Models;

use App\Enums\AnalysisStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnalysisJob extends Model
{
    protected $fillable = [
        'repository_id',
        'status',
        'total_files',
        'processed_files',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'status'       => AnalysisStatus::class,
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function repository()
    {
        return $this->belongsTo(Repository::class);
    }

    public function results()
    {
        return $this->hasMany(AnalysisResult::class);
    }

    public function getProgressAttribute()
    {
        if ($this->total_files === 0) return 0;
        return (int) round(($this->processed_files / $this->total_files) * 100);
    }

    // Completed hai ya nahi
    public function isCompleted()
    {
        return $this->status === AnalysisStatus::COMPLETED || $this->status->value === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === AnalysisStatus::FAILED;
    }
}
