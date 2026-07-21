<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conferences', function (Blueprint $table) {
            $table->string('email_sender_name')->nullable();
        });
        Schema::table('email_logs', function (Blueprint $table) {
            $table->string('sender_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('email_logs', fn (Blueprint $table) => $table->dropColumn('sender_name'));
        Schema::table('conferences', fn (Blueprint $table) => $table->dropColumn('email_sender_name'));
    }
};
