<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fbo_feed_errors', function (Blueprint $table) {
            $table->id();
            $table->string('entry_type', 20)->nullable();
            $table->string('error_message', 1000);
            $table->date('fbo_file_date')->nullable();
            $table->binary('compressed_entry')->nullable();
            $table->binary('compressed_original_entry')->nullable();
            $table->binary('compressed_stack')->nullable();
            $table->timestamps();

            $table->index('fbo_file_date');
            $table->index('entry_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fbo_feed_errors');
    }
};
