<?php
// config/analyzer.php

return [

    'batch_size'            => 8,
    'max_file_size_kb'      => 200,
    'content_cap_chars'     => 3000,
    'max_token'             => 800,
    'max_retries'           => 3,

    'allowed_extensions' => [
        'php', 'js', 'ts', 'jsx', 'tsx',
        'py', 'java', 'go', 'rb', 'cs',
        'cpp', 'c', 'rs', 'vue', 'swift',
        'kt', 'scala', 'r', 'dart', 'ipynb'
    ],

    'skip_dirs' => [
        'node_modules', 'vendor', 'dist', 'build', 'config',
        '.git', '.github', '__pycache__', '.idea', '.vscode',
        'coverage', 'storage', 'bootstrap/cache'
    ],

    'skip_patterns' => [
        '*.min.js', '*.min.css',
        '*.lock', '.env',
        '*.map', '*.bundle.js'
    ],

    'xai_api_key'      => env('XAI_API_KEY'),
    'xai_rpm'          => env('XAI_RPM', 30),

    'groq_api_key'     => env('GROQ_API_KEY'),
    'groq_rpm'         => env('GROQ_RPM', 30),

    'deepseek_api_key' => env('DEEPSEEK_API_KEY'),
    'deepseek_rpm'     => env('DEEPSEEK_RPM', 30),


    'llm_max_calls_per_minute' => 10,

];
