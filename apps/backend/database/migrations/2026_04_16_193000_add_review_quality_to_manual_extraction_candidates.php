<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manual_extraction_candidates', function (Blueprint $table) {
            $table->decimal('review_score', 5, 4)->default(0)->after('confidence');
            $table->string('review_priority')->default('low')->after('review_score');
            $table->string('noise_reason')->nullable()->after('review_priority');

            $table->index(['status', 'review_score']);
            $table->index(['review_priority', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('manual_extraction_candidates', function (Blueprint $table) {
            $table->dropIndex(['status', 'review_score']);
            $table->dropIndex(['review_priority', 'status']);
            $table->dropColumn([
                'review_score',
                'review_priority',
                'noise_reason',
            ]);
        });
    }
};
