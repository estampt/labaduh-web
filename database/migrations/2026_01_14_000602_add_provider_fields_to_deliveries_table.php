<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            if (!Schema::hasColumn('deliveries', 'provider_type')) {
                $table->enum('provider_type', ['third_party','inhouse'])
                    ->default('inhouse')->index()->after('type');
            }
            if (!Schema::hasColumn('deliveries', 'third_party_provider')) {
                $table->string('third_party_provider')->nullable()->after('provider_type');
            }
            if (!Schema::hasColumn('deliveries', 'third_party_reference_id')) {
                $table->string('third_party_reference_id')->nullable()->after('third_party_provider');
            }
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            foreach (['third_party_reference_id','third_party_provider','provider_type'] as $c) {
                if (Schema::hasColumn('deliveries', $c)) $table->dropColumn($c);
            }
        });
    }
};
