<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            if (!Schema::hasColumn('vendors', 'documents_submitted_at')) {
                $table->timestamp('documents_submitted_at')->nullable()->after('approved_by');
            }
            if (!Schema::hasColumn('vendors', 'documents_verified_at')) {
                $table->timestamp('documents_verified_at')->nullable()->after('documents_submitted_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            if (Schema::hasColumn('vendors', 'documents_verified_at')) $table->dropColumn('documents_verified_at');
            if (Schema::hasColumn('vendors', 'documents_submitted_at')) $table->dropColumn('documents_submitted_at');
        });
    }
};
