<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('findings', function (Blueprint $table) {
            $table->string('title', 500)->change();
            $table->string('owasp_category', 255)->nullable()->change();
            $table->string('cwe_id', 255)->nullable()->change();
            $table->string('severity', 20)->change();
        });
    }

    public function down(): void
    {
        Schema::table('findings', function (Blueprint $table) {
            $table->string('title', 255)->change();
            $table->string('owasp_category', 100)->nullable()->change();
            $table->string('cwe_id', 50)->nullable()->change();
        });
    }
};
