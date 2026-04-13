<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestRepoFlow extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-repo-flow';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $repoUrl = "https://github.com/haris09812/flutter-ecommerce-using-getx";
        $jobId = rand(100, 999);

        $this->info("Step 1: Cloning Repo...");
        $cloneService = app(\App\Services\GitCloneService::class);
        $path = $cloneService->clone($repoUrl, $jobId);
        $this->info("Cloned to: " . $path);

        $this->info("Step 2: Extracting Metadata (Memory Check)...");
        $extractor = app(\App\Services\FileExtractorService::class);
        $files = $extractor->extract($path);

        $this->comment("Total Files Found: " . count($files));
        $this->comment("Memory Usage: " . round(memory_get_usage() / 1024 / 1024, 2) . " MB");

        // Sample output check
        if (!empty($files)) {
            $this->info("First File: " . $files[0]['rel_path']);
        }

        $this->info("Step 3: Cleanup...");
        $cloneService->cleanup($path);
        $this->info("Folder deleted successfully.");
    }
}
