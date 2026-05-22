<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('executions', function (Blueprint $table) {
            $table->string('report_status')->nullable()->after('error_message');   // pending|processing|completed|failed
            $table->text('report_error')->nullable()->after('report_status');
            $table->timestamp('report_generated_at')->nullable()->after('report_error');
        });
    }

    public function down(): void
    {
        Schema::table('executions', function (Blueprint $table) {
            $table->dropColumn(['report_status', 'report_error', 'report_generated_at']);
        });
    }
};
