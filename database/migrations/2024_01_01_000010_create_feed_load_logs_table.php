<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feed_load_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loaded_fbo_feed_id')->nullable()->constrained('loaded_fbo_feeds')->cascadeOnDelete();
            $table->enum('level', ['info', 'warning', 'error'])->default('info');
            $table->text('message');
            $table->text('context')->nullable();
            $table->timestamps();

            $table->index('level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_load_logs');
    }
};
