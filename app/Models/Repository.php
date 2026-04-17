<?php
// app/Models/Repository.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Repository extends Model
{
    protected $fillable = [
        'github_url',
        'owner',
        'repo_name',
        'description',
        'stars',
        'forks',
        'open_prs',
        'merged_prs',
        'contributors',
        'recent_commits',
        'repo_age',
        'commits_count',
        'languages',
        'default_branch',
        'is_private',
    ];

    protected $casts = [
        'languages'      => 'array',
        'contributors'   => 'array',
        'recent_commits' => 'array',
        'is_private' => 'boolean',
    ];

    public function analysisJobs()
    {
        return $this->hasMany(AnalysisJob::class);
    }

    public function latestJob()
    {
        return $this->hasOne(AnalysisJob::class)->latestOfMany();
    }
}
