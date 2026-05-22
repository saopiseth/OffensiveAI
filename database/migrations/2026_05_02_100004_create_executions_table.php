<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('executions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->uuid('skill_id')->nullable();
            $table->uuid('workflow_id')->nullable();
            $table->string('type')->default('skill'); // skill, workflow
            $table->string('status')->default('pending'); // pending, running, completed, failed
            $table->json('input_data')->nullable();
            $table->json('output_data')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('skill_id')->references('id')->on('skills')->onDelete('set null');
            $table->foreign('workflow_id')->references('id')->on('workflows')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('executions');
    }
};
