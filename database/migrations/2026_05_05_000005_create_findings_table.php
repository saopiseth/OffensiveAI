<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('findings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('execution_id')->constrained('executions')->cascadeOnDelete();
            $table->unsignedSmallInteger('finding_order')->default(0);
            $table->string('title');
            $table->string('severity', 20)->default('Informational');
            $table->string('owasp_category', 100)->nullable();
            $table->string('cwe_id', 50)->nullable();
            $table->mediumText('description')->nullable();
            $table->mediumText('impact')->nullable();
            $table->mediumText('recommendation')->nullable();
            $table->mediumText('request')->nullable();
            $table->mediumText('response')->nullable();
            $table->timestamps();

            $table->index('execution_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('findings');
    }
};
