<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('executions', function (Blueprint $table) {
            $table->uuid('parent_execution_id')->nullable()->after('scheduled_workflow_id');
            $table->foreign('parent_execution_id')->references('id')->on('executions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('executions', function (Blueprint $table) {
            $table->dropForeign(['parent_execution_id']);
            $table->dropColumn('parent_execution_id');
        });
    }
};
