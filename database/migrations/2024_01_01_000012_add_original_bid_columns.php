<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->string('code', 2)->primary();
            $table->string('code3', 3)->nullable();
            $table->string('display_name', 100)->nullable();
            $table->integer('priority')->default(0);
            $table->integer('weight')->default(0);
            $table->timestamps();
        });

        Schema::create('setaside_codes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->unique();
            $table->timestamps();
        });

        Schema::create('category_aliases', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->boolean('needs_review')->default(true);
            $table->boolean('review_bids')->default(true);
            $table->foreignId('category_id')->constrained('categories');
            $table->foreignId('source_id')->constrained('sources');
            $table->timestamps();

            $table->index(['name', 'source_id']);
        });

        Schema::create('entity_aliases', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->boolean('needs_review')->default(true);
            $table->foreignId('entity_id')->constrained('entities');
            $table->foreignId('source_id')->constrained('sources');
            $table->timestamps();

            $table->index(['name', 'source_id']);
        });

        Schema::table('bids', function (Blueprint $table) {
            $table->string('country_id', 2)->nullable()->after('pop_country');
            $table->unsignedBigInteger('setaside_code_id')->nullable()->after('set_aside_code');
            $table->unsignedBigInteger('category_alias_id')->nullable()->after('category_id');
            $table->unsignedBigInteger('entity_alias_id')->nullable()->after('entity_id');
            $table->string('nsn', 100)->nullable()->after('naics_code');
            $table->integer('under_review')->nullable()->after('needs_review');
            $table->unsignedBigInteger('bid_url_id')->nullable()->after('purchasing_agent_id');
            $table->unsignedBigInteger('user_id')->nullable()->after('bid_url_id');

            $table->foreign('country_id')->references('code')->on('countries')->nullOnDelete();
            $table->foreign('setaside_code_id')->references('id')->on('setaside_codes')->nullOnDelete();
            $table->foreign('category_alias_id')->references('id')->on('category_aliases')->nullOnDelete();
            $table->foreign('entity_alias_id')->references('id')->on('entity_aliases')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('staged_bids', function (Blueprint $table) {
            $table->string('country_id', 2)->nullable()->after('pop_country');
            $table->unsignedBigInteger('setaside_code_id')->nullable()->after('set_aside_code');
            $table->unsignedBigInteger('category_alias_id')->nullable()->after('category_id');
            $table->unsignedBigInteger('entity_alias_id')->nullable()->after('entity_id');
            $table->string('nsn', 100)->nullable()->after('naics_code');
            $table->integer('under_review')->nullable()->after('needs_review');
            $table->unsignedBigInteger('bid_url_id')->nullable()->after('purchasing_agent_id');
            $table->unsignedBigInteger('user_id')->nullable()->after('bid_url_id');

            $table->foreign('country_id')->references('code')->on('countries')->nullOnDelete();
            $table->foreign('setaside_code_id')->references('id')->on('setaside_codes')->nullOnDelete();
            $table->foreign('category_alias_id')->references('id')->on('category_aliases')->nullOnDelete();
            $table->foreign('entity_alias_id')->references('id')->on('entity_aliases')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('staged_bids', function (Blueprint $table) {
            $table->dropForeign(['country_id']);
            $table->dropForeign(['setaside_code_id']);
            $table->dropForeign(['category_alias_id']);
            $table->dropForeign(['entity_alias_id']);
            $table->dropForeign(['user_id']);
            $table->dropColumn([
                'country_id', 'setaside_code_id', 'category_alias_id',
                'entity_alias_id', 'nsn', 'under_review', 'bid_url_id', 'user_id',
            ]);
        });

        Schema::table('bids', function (Blueprint $table) {
            $table->dropForeign(['country_id']);
            $table->dropForeign(['setaside_code_id']);
            $table->dropForeign(['category_alias_id']);
            $table->dropForeign(['entity_alias_id']);
            $table->dropForeign(['user_id']);
            $table->dropColumn([
                'country_id', 'setaside_code_id', 'category_alias_id',
                'entity_alias_id', 'nsn', 'under_review', 'bid_url_id', 'user_id',
            ]);
        });

        Schema::dropIfExists('entity_aliases');
        Schema::dropIfExists('category_aliases');
        Schema::dropIfExists('setaside_codes');
        Schema::dropIfExists('countries');
    }
};
