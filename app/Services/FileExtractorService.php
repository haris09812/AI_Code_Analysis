<?php
// app/Services/FileExtractorService.php

namespace App\Services;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class FileExtractorService
{
    // Yeh folders bilkul skip karo
    protected array $skipDirs = [
        'node_modules', 'vendor', 'dist', 'build', 'config',
        '.git', '.github', '__pycache__', '.idea', '.vscode',
        'coverage', 'public/build', 'storage', 'bootstrap/cache'
    ];

    // Sirf yeh extensions analyze karo
    protected array $allowedExtensions = [
        'php', 'js', 'ts', 'jsx', 'tsx',
        'py', 'java', 'go', 'rb', 'cs',
        'cpp', 'c', 'rs', 'vue', 'swift',
        'kt', 'scala', 'r', 'dart'
    ];

    // Yeh file patterns skip karo
    protected array $skipPatterns = [
        '*.min.js', '*.min.css',
        '*.lock', '.env',
        '*.map', '*.bundle.js'
    ];

    // Max file size — 200KB se bada skip karo
    protected int $maxFileSizeBytes = 204800;

    /**
     * Clone path se saari valid source files extract karo
     * Returns: array of file info arrays
     */
    public function extract(string $clonePath): array
    {
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $clonePath,
                RecursiveDirectoryIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if ($this->shouldSkip($file, $clonePath)) continue;

            $relativePath = str_replace([$clonePath . DIRECTORY_SEPARATOR, '\\'], ['', '/'], $file->getPathname());

            $files[] = [
                'rel_path'  => $relativePath,
                'full_path' => $file->getPathname(), // Keep this to read later in the Job
                'extension' => strtolower($file->getExtension()),
                'size_kb'   => round($file->getSize() / 1024, 2),
            ];
        }

        usort($files, fn($a, $b) => $b['size_kb'] <=> $a['size_kb']);

        return $files;
    }

    /**
     * Sirf file tree return karo — content ke bina (UI file explorer ke liye)
     */
    public function extractTree(string $clonePath): array
    {
        $files = $this->extract($clonePath);

        return array_map(fn($f) => [
            'rel_path'  => $f['rel_path'],
            'extension' => $f['extension'],
            'size_kb'   => $f['size_kb'],
        ], $files);
    }

    /**
     * File skip karni chahiye ya nahi
     */
    protected function shouldSkip(SplFileInfo $file, string $basePath): bool
    {
        // File nahi hai toh skip
        if (!$file->isFile()) return true;

        // Banned directories check
        $relativePath = str_replace($basePath, '', $file->getPath());
        $relativePath = str_replace('\\', '/', $relativePath);

        foreach ($this->skipDirs as $dir) {
            if (str_contains($relativePath, "/{$dir}") || str_contains($relativePath, "/{$dir}/")) {
                return true;
            }
        }

        // Extension check
        $ext = strtolower($file->getExtension());
        if (!in_array($ext, $this->allowedExtensions)) return true;

        // Skip patterns check
        $filename = $file->getFilename();
        foreach ($this->skipPatterns as $pattern) {
            $regex = '/^' . str_replace(['*', '.'], ['.*', '\.'], $pattern) . '$/i';
            if (preg_match($regex, $filename)) return true;
        }

        // Size check — bohot badi files skip karo
        if ($file->getSize() > $this->maxFileSizeBytes) return true;

        // Empty files skip karo
        if ($file->getSize() === 0) return true;

        return false;
    }

    /**
     * Stats return karo — kitni files, extensions breakdown
     */
    public function getStats(array $files): array
    {
        $extensions = [];
        foreach ($files as $file) {
            $ext = $file['extension'];
            $extensions[$ext] = ($extensions[$ext] ?? 0) + 1;
        }

        arsort($extensions);

        return [
            'total_files'       => count($files),
            'total_size_kb'     => round(array_sum(array_column($files, 'size_kb')), 2),
            'extensions'        => $extensions,
        ];
    }
}
