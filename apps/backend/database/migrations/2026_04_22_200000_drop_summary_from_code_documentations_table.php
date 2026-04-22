<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('code_documentations', 'summary')) {
            return;
        }

        Schema::table('code_documentations', function (Blueprint $table) {
            $table->dropColumn('summary');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('code_documentations', 'summary')) {
            return;
        }

        Schema::table('code_documentations', function (Blueprint $table) {
            $table->text('summary')->nullable()->after('title');
        });
    }
};
