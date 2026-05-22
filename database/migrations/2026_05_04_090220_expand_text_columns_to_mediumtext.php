<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('executions', function (Blueprint $table) {
            $table->mediumText('error_message')->nullable()->change();
        });

        Schema::table('execution_logs', function (Blueprint $table) {
            $table->mediumText('prompt_rendered')->nullable()->change();
            $table->mediumText('input_data')->nullable()->change();
            $table->mediumText('output_data')->nullable()->change();
            $table->mediumText('error_message')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('executions', function (Blueprint $table) {
            $table->text('error_message')->nullable()->change();
        });

        Schema::table('execution_logs', function (Blueprint $table) {
            $table->text('prompt_rendered')->nullable()->change();
            $table->text('input_data')->nullable()->change();
            $table->text('output_data')->nullable()->change();
            $table->text('error_message')->nullable()->change();
        });
    }
};
