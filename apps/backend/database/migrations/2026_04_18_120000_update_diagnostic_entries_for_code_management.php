<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('diagnostic_entries')) {
            return;
        }

        Schema::table('diagnostic_entries', function (Blueprint $table): void {
            if (! Schema::hasColumn('diagnostic_entries', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });

        DB::table('diagnostic_entries')
            ->where('status', 'approved')
            ->update(['status' => 'active']);

        DB::table('diagnostic_entries')
            ->whereIn('status', ['draft', 'archived'])
            ->update(['status' => 'disabled']);

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE diagnostic_entries ALTER COLUMN status SET DEFAULT 'active'");
        } elseif ($driver === 'mysql') {
            DB::statement("ALTER TABLE diagnostic_entries ALTER status SET DEFAULT 'active'");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('diagnostic_entries')) {
            return;
        }

        DB::table('diagnostic_entries')
            ->where('status', 'active')
            ->update(['status' => 'approved']);

        DB::table('diagnostic_entries')
            ->where('status', 'disabled')
            ->update(['status' => 'archived']);

        Schema::table('diagnostic_entries', function (Blueprint $table): void {
            if (Schema::hasColumn('diagnostic_entries', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE diagnostic_entries ALTER COLUMN status SET DEFAULT 'approved'");
        } elseif ($driver === 'mysql') {
            DB::statement("ALTER TABLE diagnostic_entries ALTER status SET DEFAULT 'approved'");
        }
    }
};
