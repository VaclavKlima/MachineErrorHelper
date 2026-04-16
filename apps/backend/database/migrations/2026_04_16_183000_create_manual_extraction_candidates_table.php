<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_extraction_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('manual_id')->constrained()->cascadeOnDelete();
            $table->foreignId('manual_page_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manual_chunk_id')->nullable()->constrained()->nullOnDelete();
            $table->string('candidate_type')->default('error_code_definition');
            $table->string('code')->nullable();
            $table->string('normalized_code')->nullable();
            $table->string('family')->nullable();
            $table->string('title')->nullable();
            $table->text('meaning')->nullable();
            $table->text('cause')->nullable();
            $table->text('recommended_action')->nullable();
            $table->longText('source_text')->nullable();
            $table->unsignedInteger('source_page_number')->nullable();
            $table->string('extractor');
            $table->decimal('confidence', 5, 4)->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['manual_id', 'status']);
            $table->index(['machine_id', 'normalized_code']);
            $table->index(['status', 'confidence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_extraction_candidates');
    }
};
