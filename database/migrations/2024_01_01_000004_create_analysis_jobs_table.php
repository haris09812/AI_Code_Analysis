<?php
// database/migrations/2024_01_01_000002_create_analysis_jobs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repository_id')
                  ->constrained()
                  ->cascadeOnDelete();

            // Status values: pending | cloning | processing | completed | failed
            $table->string('status')->default('pending');

            $table->unsignedInteger('total_files')->default(0);
            $table->unsignedInteger('processed_files')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Status check queries fast karo
            $table->index(['repository_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_jobs');
    }
};
