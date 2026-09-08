<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('website_page_views', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('conference_id')->nullable()->constrained()->nullOnDelete();
            $table->string('visitor_hash', 64)->index();
            $table->string('path', 500);
            $table->string('page_type', 50)->default('other')->index();
            $table->string('method', 10)->default('GET');
            $table->unsignedSmallInteger('status_code')->default(200);
            $table->string('referrer', 500)->nullable();
            $table->string('referrer_type', 50)->default('direct');
            $table->string('device_type', 20)->default('desktop');
            $table->string('browser', 50)->nullable();
            $table->string('platform', 50)->nullable();
            $table->timestamp('visited_at')->index();
            $table->timestamps();

            $table->index(['conference_id', 'visited_at']);
            $table->index(['page_type', 'visited_at']);
            $table->index(['visitor_hash', 'visited_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_page_views');
    }
};
