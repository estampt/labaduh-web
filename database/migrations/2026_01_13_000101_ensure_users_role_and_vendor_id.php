<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role', 30)->default('customer')->after('password');
                $table->index('role');
            }
            if (!Schema::hasColumn('users', 'vendor_id')) {
                $table->foreignId('vendor_id')->nullable()->after('role')
                    ->constrained('vendors')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'vendor_id')) {
                try { $table->dropForeign(['vendor_id']); } catch (\Throwable $e) {}
                $table->dropColumn('vendor_id');
            }
            if (Schema::hasColumn('users', 'role')) {
                try { $table->dropIndex(['role']); } catch (\Throwable $e) {}
                $table->dropColumn('role');
            }
        });
    }
};
