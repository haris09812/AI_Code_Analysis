<?php

namespace App\Services;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class FileExtractorService
{
    protected array $skipDirs;

    protected array $allowedExtensions;

    protected array $skipPatterns;

    protected int $maxFileSizeBytes;

    public function __construct()
    {
        $this->skipDirs = config('analyzer.skip_dirs', []);
        $this->allowedExtensions = config('analyzer.allowed_extensions', []);
        $this->skipPatterns = config('analyzer.skip_patterns', []);
        $this->maxFileSizeBytes = config('analyzer.max_file_size_kb',200) * 1024;
    }

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

    public function extractTree(string $clonePath): array
    {
        $files = $this->extract($clonePath);

        return array_map(fn($f) => [
            'rel_path'  => $f['rel_path'],
            'extension' => $f['extension'],
            'size_kb'   => $f['size_kb'],
        ], $files);
    }

    protected function shouldSkip(SplFileInfo $file, string $basePath): bool
    {
        if (!$file->isFile()) return true;

        // Banned directories check
        $relativePath = str_replace($basePath, '', $file->getPath());
        $relativePath = str_replace('\\', '/', $relativePath);

        foreach ($this->skipDirs as $dir) {
            if (str_contains($relativePath, "/{$dir}") || str_contains($relativePath, "/{$dir}/")) {
                return true;
            }
        }

        $ext = strtolower($file->getExtension());
        if (!in_array($ext, $this->allowedExtensions)) return true;

        $filename = $file->getFilename();
        foreach ($this->skipPatterns as $pattern) {
            $regex = '/^' . str_replace(['*', '.'], ['.*', '\.'], $pattern) . '$/i';
            if (preg_match($regex, $filename)) return true;
        }

        if ($file->getSize() > $this->maxFileSizeBytes) return true;

        if ($file->getSize() === 0) return true;

        return false;
    }

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
