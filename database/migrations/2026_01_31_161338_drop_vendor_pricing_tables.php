<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop child tables first (FK dependents)
        Schema::dropIfExists('vendor_service_option_prices');
        Schema::dropIfExists('vendor_shop_service_prices');
        Schema::dropIfExists('vendor_service_prices');
        Schema::dropIfExists('shop_service_prices');
    }

    public function down(): void
    {
        // Optional: leave empty OR recreate if you want rollback support
        // Usually fine to leave empty for destructive migrations
    }
};
