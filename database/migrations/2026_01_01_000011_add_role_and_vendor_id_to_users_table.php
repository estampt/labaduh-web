<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'role')) {
                // Place after 'password' if possible; if not, Laravel will append at end.
                $table->enum('role', ['customer','vendor','admin'])->default('customer')->after('password')->index();
            }
            if (!Schema::hasColumn('users', 'vendor_id')) {
                $table->foreignId('vendor_id')->nullable()->after('role')->constrained('vendors')->nullOnDelete()->index();
            }
        });
    }

    public function down(): void {
        // Be defensive: some DBs/environments may not have the FK/index (partial runs, manual edits, etc.)
        if (!Schema::hasTable('users')) return;

        // Drop FK if it exists (MySQL).
        if (Schema::hasColumn('users', 'vendor_id')) {
            try {
                // Default Laravel FK naming convention:
                DB::statement('ALTER TABLE `users` DROP FOREIGN KEY `users_vendor_id_foreign`');
            } catch (\Throwable $e) {
                // Ignore if FK doesn't exist.
            }

            Schema::table('users', function (Blueprint $table) {
                // Drop index if present (Laravel naming convention: users_vendor_id_index)
                try { $table->dropIndex('users_vendor_id_index'); } catch (\Throwable $e) {}
                // Drop the column
                try { $table->dropColumn('vendor_id'); } catch (\Throwable $e) {}
            });
        }

        if (Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                // Drop index if present
                try { $table->dropIndex('users_role_index'); } catch (\Throwable $e) {}
                try { $table->dropColumn('role'); } catch (\Throwable $e) {}
            });
        }
    }
};
