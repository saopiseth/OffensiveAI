<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('execution_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('execution_id');
            $table->uuid('skill_step_id')->nullable();
            $table->integer('step_order')->default(0);
            $table->string('step_name')->nullable();
            $table->string('status'); // running, completed, failed
            $table->text('prompt_rendered')->nullable();
            $table->text('input_data')->nullable();
            $table->text('output_data')->nullable();
            $table->text('error_message')->nullable();
            $table->string('ai_provider')->nullable();
            $table->string('model_used')->nullable();
            $table->integer('tokens_used')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->timestamps();

            $table->foreign('execution_id')->references('id')->on('executions')->onDelete('cascade');
            $table->foreign('skill_step_id')->references('id')->on('skill_steps')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('execution_logs');
    }
};
