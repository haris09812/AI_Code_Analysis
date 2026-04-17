<?php
// app/Http/Requests/AnalyzeFileRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnalyzeFileRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'file_path' => 'required|string|max:500',
        ];
    }
}
