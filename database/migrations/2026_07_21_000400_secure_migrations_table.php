<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE "migrations" ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE "migrations" FORCE ROW LEVEL SECURITY');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE "migrations" NO FORCE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE "migrations" DISABLE ROW LEVEL SECURITY');
        }
    }
};
