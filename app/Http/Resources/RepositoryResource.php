<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RepositoryResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'             => $this->id,
            'github_url'     => $this->github_url,
            'owner'          => $this->owner,
            'repo_name'      => $this->repo_name,
            'description'    => $this->description,
            'stars'          => $this->stars,
            'forks'          => $this->forks,
            'commits_count'  => $this->commits_count,
            'languages'      => $this->languages ?? [],
            'open_prs'       => $this->open_prs,
            'merged_prs'     => $this->merged_prs,
            'contributors'   => $this->contributors ?? [],
            'recent_commits' => $this->recent_commits ?? [],
            'repo_age'       => $this->repo_age,
            'default_branch' => $this->default_branch,
            'is_private'     => $this->is_private,
            'created_at'     => $this->created_at->toISOString(),
        ];
    }
}
