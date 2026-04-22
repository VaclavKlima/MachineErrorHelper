<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('diagnostic_aliases')) {
            Schema::create('diagnostic_aliases', function (Blueprint $table) {
                $table->id();
                $table->foreignId('machine_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('alias_type');
                $table->string('alias_value');
                $table->string('normalized_value');
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['machine_id', 'alias_type', 'alias_value']);
                $table->index(['alias_type', 'normalized_value']);
            });
        }

        if (! Schema::hasTable('diagnostic_entries')) {
            Schema::create('diagnostic_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
                $table->foreignId('manual_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('manual_page_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('manual_chunk_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('manual_extraction_candidate_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('effective_from_version_id')->nullable()->constrained('software_versions')->nullOnDelete();
                $table->foreignId('effective_to_version_id')->nullable()->constrained('software_versions')->nullOnDelete();
                $table->string('module_key')->nullable();
                $table->string('section_title')->nullable();
                $table->string('primary_code')->nullable();
                $table->string('primary_code_normalized')->nullable();
                $table->json('context')->nullable();
                $table->json('identifiers');
                $table->string('title')->nullable();
                $table->text('meaning')->nullable();
                $table->text('cause')->nullable();
                $table->string('severity')->nullable();
                $table->text('recommended_action')->nullable();
                $table->longText('source_text')->nullable();
                $table->unsignedInteger('source_page_number')->nullable();
                $table->string('extractor')->nullable();
                $table->decimal('confidence', 5, 4)->nullable();
                $table->string('status')->default('active');
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['machine_id', 'module_key', 'primary_code_normalized']);
                $table->index(['manual_id', 'source_page_number']);
                $table->index(['status', 'confidence']);
            });
        }

        if (! Schema::hasColumn('manual_extraction_candidates', 'module_key')) {
            Schema::table('manual_extraction_candidates', function (Blueprint $table) {
                $table->string('module_key')->nullable()->after('family');
                $table->string('section_title')->nullable()->after('module_key');
                $table->string('primary_code')->nullable()->after('section_title');
                $table->json('context')->nullable()->after('primary_code');
                $table->json('identifiers')->nullable()->after('context');
            });
        }

        if (! Schema::hasColumn('diagnosis_requests', 'selected_diagnostic_entry_id')) {
            Schema::table('diagnosis_requests', function (Blueprint $table) {
                $table->foreignId('selected_diagnostic_entry_id')
                    ->nullable()
                    ->after('selected_definition_id')
                    ->constrained('diagnostic_entries')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('diagnosis_candidates', 'matched_diagnostic_entry_id')) {
            Schema::table('diagnosis_candidates', function (Blueprint $table) {
                $table->foreignId('matched_diagnostic_entry_id')
                    ->nullable()
                    ->after('matched_definition_id')
                    ->constrained('diagnostic_entries')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('repair_hints', 'diagnostic_entry_id')) {
            Schema::table('repair_hints', function (Blueprint $table) {
                $table->foreignId('diagnostic_entry_id')
                    ->nullable()
                    ->after('error_code_definition_id')
                    ->constrained('diagnostic_entries')
                    ->cascadeOnDelete();
            });
        }

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX IF NOT EXISTS diagnostic_entries_context_gin ON diagnostic_entries USING gin ((context::jsonb))');
            DB::statement('CREATE INDEX IF NOT EXISTS diagnostic_entries_identifiers_gin ON diagnostic_entries USING gin ((identifiers::jsonb))');
            DB::statement('CREATE INDEX IF NOT EXISTS manual_extraction_candidates_context_gin ON manual_extraction_candidates USING gin ((context::jsonb))');
            DB::statement('CREATE INDEX IF NOT EXISTS manual_extraction_candidates_identifiers_gin ON manual_extraction_candidates USING gin ((identifiers::jsonb))');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('manual_extraction_candidates')) {
            Schema::table('manual_extraction_candidates', function (Blueprint $table) {
                $table->dropColumn([
                    'module_key',
                    'section_title',
                    'primary_code',
                    'context',
                    'identifiers',
                ]);
            });
        }

        if (Schema::hasTable('repair_hints')) {
            Schema::table('repair_hints', function (Blueprint $table) {
                $table->dropConstrainedForeignId('diagnostic_entry_id');
            });
        }

        if (Schema::hasTable('diagnosis_candidates')) {
            Schema::table('diagnosis_candidates', function (Blueprint $table) {
                $table->dropConstrainedForeignId('matched_diagnostic_entry_id');
            });
        }

        if (Schema::hasTable('diagnosis_requests')) {
            Schema::table('diagnosis_requests', function (Blueprint $table) {
                $table->dropConstrainedForeignId('selected_diagnostic_entry_id');
            });
        }

        Schema::dropIfExists('diagnostic_entries');
        Schema::dropIfExists('diagnostic_aliases');
    }
};
