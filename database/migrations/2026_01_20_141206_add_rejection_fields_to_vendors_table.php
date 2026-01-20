<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
            $table->foreignId('rejected_by')->nullable()->after('approved_by')->constrained('users')->nullOnDelete();
            $table->string('rejection_reason', 1000)->nullable()->after('rejected_by');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn(['rejected_at', 'rejection_reason']);
        });
    }
};
