<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skill_steps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('skill_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('prompt_template');
            $table->json('input_schema')->nullable();
            $table->json('output_schema')->nullable();
            $table->integer('execution_order')->default(0);
            $table->string('ai_provider')->default('claude');
            $table->string('model')->nullable();
            $table->float('temperature')->default(0.7);
            $table->integer('max_tokens')->default(2048);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('skill_id')->references('id')->on('skills')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skill_steps');
    }
};
