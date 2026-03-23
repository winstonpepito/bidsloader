<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bids', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('solicitation_number', 255)->nullable();
            $table->string('third_party_identifier', 500)->nullable();
            $table->string('url', 1000)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('naics_code', 20)->nullable();
            $table->string('set_aside_code', 100)->nullable();
            $table->string('agency', 255)->nullable();
            $table->string('office', 255)->nullable();
            $table->string('location', 255)->nullable();
            $table->string('zip', 20)->nullable();
            $table->string('pop_address', 500)->nullable();
            $table->string('pop_zip', 20)->nullable();
            $table->string('pop_country', 100)->nullable();
            $table->timestamp('fed_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->boolean('needs_review')->default(false);

            $table->foreignId('source_id')->nullable()->constrained('sources');
            $table->foreignId('subscription_type_id')->nullable()->constrained('subscription_types');
            $table->foreignId('category_id')->nullable()->constrained('categories');
            $table->foreignId('entity_id')->nullable()->constrained('entities');
            $table->foreignId('state_id')->nullable()->constrained('states');
            $table->foreignId('purchasing_agent_id')->nullable()->constrained('purchasing_agents');

            $table->timestamps();

            $table->index('third_party_identifier');
            $table->index('solicitation_number');
            $table->index('fed_date');
            $table->index('end_date');
            $table->index('naics_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bids');
    }
};
