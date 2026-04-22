<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('code_documentations', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->json('content');
            $table->timestamps();
        });

        Schema::create('code_documentation_diagnostic_entry', function (Blueprint $table) {
            $table->id();
            $table->foreignId('code_documentation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('diagnostic_entry_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['code_documentation_id', 'diagnostic_entry_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('code_documentation_diagnostic_entry');
        Schema::dropIfExists('code_documentations');
    }
};
