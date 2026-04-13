<?php
// database/migrations/2024_01_01_000003_create_analysis_results_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_job_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->string('file_path');               // src/Controllers/AuthController.php
            $table->string('file_extension', 10);      // php, js, py, etc.

            // Classification: ai | human | uncertain
            $table->string('classification', 20);

            $table->unsignedTinyInteger('confidence_score'); // 0-100

            $table->text('explanation');

            // Heuristic signals JSON — UI mein badges ke liye useful
            $table->json('signals')->nullable();
            /*
                signals example:
                {
                    "uniform_naming": true,
                    "excessive_comments": true,
                    "has_debug_artifacts": false,
                    "has_todos_or_wip": false,
                    "boilerplate_heavy": true,
                    "inconsistent_style": false
                }
            */

            // Debug ke liye store karo — production mein nullable rakhna theek hai
            $table->text('raw_llm_response')->nullable();

            $table->timestamps();

            // Results filter/sort ke liye indexes
            $table->index(['analysis_job_id', 'classification']);
            $table->index('confidence_score');
            $table->index(['analysis_job_id', 'confidence_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_results');
    }
};
