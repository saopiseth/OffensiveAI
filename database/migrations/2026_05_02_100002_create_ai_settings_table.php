<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider')->unique(); // claude, openai
            $table->string('api_key');
            $table->string('default_model');
            $table->float('temperature')->default(0.7);
            $table->integer('max_tokens')->default(2048);
            $table->boolean('is_active')->default(false);
            $table->json('extra_config')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_settings');
    }
};
