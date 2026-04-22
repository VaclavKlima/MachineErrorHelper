<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('repair_hints');
    }

    public function down(): void
    {
        if (Schema::hasTable('repair_hints')) {
            return;
        }

        Schema::create('repair_hints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('error_code_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('error_code_definition_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('diagnostic_entry_id')->nullable()->constrained('diagnostic_entries')->cascadeOnDelete();
            $table->string('title');
            $table->longText('body')->nullable();
            $table->json('steps')->nullable();
            $table->text('safety_warning')->nullable();
            $table->json('tools_required')->nullable();
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('sort_order')->default(100);
            $table->timestamps();
        });
    }
};
