<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diagnosis_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('diagnosis_requests', 'ai_detected_codes')) {
                $table->json('ai_detected_codes')->nullable()->after('raw_ocr_text');
            }

            if (! Schema::hasColumn('diagnosis_requests', 'user_entered_codes')) {
                $table->json('user_entered_codes')->nullable()->after('ai_detected_codes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('diagnosis_requests', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('diagnosis_requests', 'user_entered_codes')) {
                $columns[] = 'user_entered_codes';
            }

            if (Schema::hasColumn('diagnosis_requests', 'ai_detected_codes')) {
                $columns[] = 'ai_detected_codes';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
