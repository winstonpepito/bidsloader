<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loaded_fbo_feeds', function (Blueprint $table) {
            $table->id();
            $table->date('fbo_date')->unique();
            $table->integer('entries_loaded')->default(0);
            $table->integer('errors_count')->default(0);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('fbo_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loaded_fbo_feeds');
    }
};
