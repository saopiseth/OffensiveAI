<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            $table->uuid('converted_from')->nullable()->after('created_by');
            $table->foreign('converted_from')->references('id')->on('skills')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            $table->dropForeign(['converted_from']);
            $table->dropColumn('converted_from');
        });
    }
};
