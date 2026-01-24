<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('service_options', function (Blueprint $table) {
            // drop FK first, then column
            if (Schema::hasColumn('service_options', 'service_id')) {
                // Laravel default FK name: service_options_service_id_foreign
                try {
                    $table->dropForeign(['service_id']);
                } catch (\Throwable $e) {
                    // ignore if FK name differs
                }
                $table->dropColumn('service_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_options', function (Blueprint $table) {
            if (!Schema::hasColumn('service_options', 'service_id')) {
                $table->foreignId('service_id')
                    ->after('id')
                    ->constrained()
                    ->cascadeOnDelete();
            }
        });
    }
};
