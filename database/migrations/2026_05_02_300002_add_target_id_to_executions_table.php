<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('executions', function (Blueprint $table) {
            $table->foreignUuid('target_id')->nullable()->after('workflow_id')
                  ->constrained('targets')->nullOnDelete();
            $table->string('scheduled_workflow_id', 36)->nullable()->after('target_id');
        });
    }

    public function down(): void
    {
        Schema::table('executions', function (Blueprint $table) {
            $table->dropForeign(['target_id']);
            $table->dropColumn(['target_id', 'scheduled_workflow_id']);
        });
    }
};
