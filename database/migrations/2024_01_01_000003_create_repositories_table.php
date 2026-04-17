<?php
// database/migrations/2024_01_01_000001_create_repositories_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repositories', function (Blueprint $table) {
            $table->id();
            $table->string('github_url', 191);
            $table->string('owner', 100);        // ← length fix
            $table->string('repo_name', 100);    // ← length fix
            $table->string('description', 191)->nullable();
            $table->unsignedInteger('stars')->default(0);
            $table->unsignedInteger('forks')->default(0);
            $table->unsignedInteger('commits_count')->default(0);
            $table->json('languages')->nullable();
            $table->integer('open_prs')->default(0);
            $table->integer('merged_prs')->default(0);
            $table->json('contributors')->nullable();
            $table->json('recent_commits')->nullable();
            $table->string('repo_age')->nullable();
            $table->string('default_branch', 50)->default('main');
            $table->boolean('is_private')->default(false);
            $table->timestamps();

            $table->unique(['owner', 'repo_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repositories');
    }
};
