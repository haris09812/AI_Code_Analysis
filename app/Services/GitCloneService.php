<?php
// app/Services/GitCloneService.php

namespace App\Services;

use Symfony\Component\Process\Process;
use Exception;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

class GitCloneService
{
    protected string $baseClonePath;
    protected GitHubService $github;
    protected int $maxSizeKb = 100000;

    public function __construct(GitHubService $github)
    {
        $this->github = $github;
        $this->baseClonePath = storage_path('app' . DIRECTORY_SEPARATOR . 'clones');

        if (!File::exists($this->baseClonePath)) {
            File::makeDirectory($this->baseClonePath, 0755, true);
        }
    }


    public function clone(string $repoUrl, int $jobId): string
    {
        $this->validateRepository($repoUrl);

        $clonePath = $this->baseClonePath . DIRECTORY_SEPARATOR . "job_{$jobId}";

        if (is_dir($clonePath)) {
            $this->cleanup($clonePath);
        }

        $command = [
            'git', 'clone',
            '--depth=1',           // latest snapshot, not full history
            '--single-branch',     // default branch
            $repoUrl,
            $clonePath
        ];

        $process = new Process($command);
        $process->setTimeout(900);

        try {
            $process->run(null, ['GIT_TERMINAL_PROMPT' => '0']);
        } catch (ProcessTimedOutException $e) {
            throw new Exception("Clone timeout: Internet slow hai ya repo bohot bara hai.");
        }

        if (!$process->isSuccessful()) {
            $error = $process->getErrorOutput();

            // Private repo detect karo
            if (str_contains($error, '403') || str_contains($error, 'Authentication')) {
                throw new Exception("Private repository hai — OAuth login required.");
            }

            // Repo exist nahi karta
            if (str_contains($error, 'not found') || str_contains($error, '404')) {
                throw new Exception("Repository exist nahi karta ya URL galat hai.");
            }

            throw new Exception("Clone failed: " . $error);
        }

        return $clonePath;
    }

    protected function validateRepository(string $url): void
    {
        $meta = $this->github->getRepoMetadata($url);

        $size = $meta['stats']['size_kb'] ?? 0;
        if ($size > $this->maxSizeKb) {
            throw new Exception("Repo size limit se zyada hai.");
        }
    }


    public function cleanup(string $clonePath): void
    {
        if (!is_dir($clonePath)) return;

        if (PHP_OS_FAMILY === 'Windows') {
            exec("attrib -r -s -h /s /d \"" . $clonePath . "\\*\"");
            exec("rd /s /q \"" . $clonePath . "\"");
        } else {
            exec("rm -rf " . escapeshellarg($clonePath));
        }

        clearstatcache();
    }


    public function getClonePath(int $jobId): string
    {
        return $this->baseClonePath . DIRECTORY_SEPARATOR . "job_{$jobId}";
    }
}
