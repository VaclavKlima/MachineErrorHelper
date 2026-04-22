<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            Schema::ensureVectorExtensionExists();
        }

        Schema::create('machines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('manufacturer')->nullable();
            $table->string('model_number')->nullable();
            $table->text('description')->nullable();
            $table->text('dashboard_notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('machine_code_patterns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('regex');
            $table->string('normalization_rule')->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('software_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->string('version');
            $table->date('released_at')->nullable();
            $table->unsignedInteger('sort_order')->default(100);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['machine_id', 'version']);
        });

        Schema::create('manuals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('software_version_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('coverage_mode')->default('complete');
            $table->string('language', 8)->default('en');
            $table->string('file_path');
            $table->string('file_hash', 128)->unique();
            $table->unsignedInteger('page_count')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->text('source_notes')->nullable();
            $table->string('status')->default('uploaded');
            $table->timestamps();
        });

        Schema::create('manual_import_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manual_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('queued');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('extractor_versions')->nullable();
            $table->json('stats')->nullable();
            $table->timestamps();
        });

        Schema::create('manual_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manual_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('page_number');
            $table->longText('text')->nullable();
            $table->longText('ocr_text')->nullable();
            $table->string('image_path')->nullable();
            $table->decimal('extraction_quality', 5, 4)->nullable();
            $table->timestamps();

            $table->unique(['manual_id', 'page_number']);
        });

        Schema::create('manual_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manual_id')->constrained()->cascadeOnDelete();
            $table->foreignId('manual_page_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->string('heading')->nullable();
            $table->longText('content');
            $table->string('content_hash', 128);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['manual_id', 'content_hash']);
            $table->index(['manual_id', 'chunk_index']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            Schema::table('manual_chunks', function (Blueprint $table) {
                $table->vector('embedding', dimensions: 768)->nullable()->index();
            });
        } else {
            Schema::table('manual_chunks', function (Blueprint $table) {
                $table->json('embedding')->nullable();
            });
        }

        Schema::create('error_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('normalized_code');
            $table->string('family')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['machine_id', 'normalized_code']);
        });

        Schema::create('error_code_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('error_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('manual_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manual_chunk_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('effective_from_version_id')->nullable()->constrained('software_versions')->nullOnDelete();
            $table->foreignId('effective_to_version_id')->nullable()->constrained('software_versions')->nullOnDelete();
            $table->foreignId('supersedes_definition_id')->nullable()->constrained('error_code_definitions')->nullOnDelete();
            $table->unsignedInteger('source_page_number')->nullable();
            $table->string('title');
            $table->text('meaning')->nullable();
            $table->text('cause')->nullable();
            $table->string('severity')->nullable();
            $table->text('recommended_action')->nullable();
            $table->decimal('source_confidence', 5, 4)->nullable();
            $table->string('approval_status')->default('candidate');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['error_code_id', 'approval_status']);
        });

        Schema::create('diagnosis_requests', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('software_version_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('selected_error_code_id')->nullable()->constrained('error_codes')->nullOnDelete();
            $table->foreignId('selected_definition_id')->nullable()->constrained('error_code_definitions')->nullOnDelete();
            $table->string('screenshot_path')->nullable();
            $table->string('status')->default('uploaded');
            $table->longText('raw_ocr_text')->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->json('result_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('diagnosis_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diagnosis_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('matched_error_code_id')->nullable()->constrained('error_codes')->nullOnDelete();
            $table->foreignId('matched_definition_id')->nullable()->constrained('error_code_definitions')->nullOnDelete();
            $table->string('candidate_code');
            $table->string('normalized_code');
            $table->string('source');
            $table->decimal('confidence', 5, 4)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['diagnosis_request_id', 'normalized_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnosis_candidates');
        Schema::dropIfExists('diagnosis_requests');
        Schema::dropIfExists('error_code_definitions');
        Schema::dropIfExists('error_codes');
        Schema::dropIfExists('manual_chunks');
        Schema::dropIfExists('manual_pages');
        Schema::dropIfExists('manual_import_runs');
        Schema::dropIfExists('manuals');
        Schema::dropIfExists('software_versions');
        Schema::dropIfExists('machine_code_patterns');
        Schema::dropIfExists('machines');
    }
};
