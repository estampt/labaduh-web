<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('service_options', function (Blueprint $table) {
            if (!Schema::hasColumn('service_options', 'kind')) {
                $table->enum('kind', ['option','addon'])->default('option')->after('service_id')->index();
            }
            if (!Schema::hasColumn('service_options', 'group_key')) {
                $table->string('group_key', 50)->nullable()->after('kind')->index();
            }
            if (!Schema::hasColumn('service_options', 'description')) {
                $table->string('description')->nullable()->after('name');
            }
            if (!Schema::hasColumn('service_options', 'is_required')) {
                $table->boolean('is_required')->default(false)->after('price_type');
            }
            if (!Schema::hasColumn('service_options', 'is_multi_select')) {
                $table->boolean('is_multi_select')->default(false)->after('is_required');
            }
            if (!Schema::hasColumn('service_options', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('is_multi_select');
            }
            if (!Schema::hasColumn('service_options', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('sort_order')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_options', function (Blueprint $table) {
            foreach (['is_active','sort_order','is_multi_select','is_required','description','group_key','kind'] as $col) {
                if (Schema::hasColumn('service_options', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
