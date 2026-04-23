<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manuals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('software_version_id');
        });
    }

    public function down(): void
    {
        Schema::table('manuals', function (Blueprint $table) {
            $table->foreignId('software_version_id')
                ->nullable()
                ->after('machine_id')
                ->constrained()
                ->nullOnDelete();
        });
    }
};
