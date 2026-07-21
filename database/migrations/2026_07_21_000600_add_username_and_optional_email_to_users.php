<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable()->unique()->after('name');
            $table->string('email')->nullable()->change();
        });

        DB::table('users')->select(['id', 'name', 'email'])->orderBy('id')->each(function (object $user): void {
            $source = $user->email ? Str::before($user->email, '@') : $user->name;
            $prefix = Str::of($source)->lower()->ascii()->replaceMatches('/[^a-z0-9_-]+/', '-')->trim('-_')->limit(35, '');

            DB::table('users')->where('id', $user->id)->update([
                'username' => ($prefix->isEmpty() ? 'user' : $prefix).'-'.$user->id,
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        DB::table('users')->whereNull('email')->orderBy('id')->each(function (object $user): void {
            DB::table('users')->where('id', $user->id)->update([
                'email' => $user->username.'@rollback.invalid',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
            $table->string('email')->nullable(false)->change();
        });
    }
};
