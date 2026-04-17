<?php
// app/Http/Requests/AnalyzeRepositoryRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnalyzeRepositoryRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'github_url' => [
                'required',
                'url',
                'regex:/^https:\/\/github\.com\/[a-zA-Z0-9\-\.]+\/[a-zA-Z0-9\-\._]+\/?$/',
            ],
        ];
    }

    public function messages()
    {
        return [
            'github_url.required' => 'GitHub URL is required.',
            'github_url.url'      => 'Enter Valid URL.',
            'github_url.regex'    => 'Only GitHub repository URLs allowed. Example: https://github.com/owner/repo',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'github_url' => rtrim($this->github_url, '/'),
        ]);
    }
}
