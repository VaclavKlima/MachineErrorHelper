<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_color_meanings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('ai_key');
            $table->string('hex_color', 7);
            $table->json('ai_aliases')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['machine_id', 'ai_key']);
            $table->index(['machine_id', 'is_active', 'priority']);
        });

        Schema::table('diagnosis_candidates', function (Blueprint $table) {
            $table->foreignId('dashboard_color_meaning_id')
                ->nullable()
                ->after('matched_diagnostic_entry_id')
                ->constrained('dashboard_color_meanings')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('diagnosis_candidates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dashboard_color_meaning_id');
        });

        Schema::dropIfExists('dashboard_color_meanings');
    }
};
