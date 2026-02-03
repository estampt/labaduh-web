<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('order_items', function (Blueprint $table) {
      // rename logic not required; keep existing qty as estimated
      $table->decimal('qty_estimated', 10, 2)->nullable()->after('qty');
      $table->decimal('qty_actual', 10, 2)->nullable()->after('qty_estimated');

      $table->decimal('estimated_price', 12, 2)->nullable()->after('computed_price');
      $table->decimal('final_price', 12, 2)->nullable()->after('estimated_price');
    });
  }

  public function down(): void
  {
    Schema::table('order_items', function (Blueprint $table) {
      $table->dropColumn(['qty_estimated','qty_actual','estimated_price','final_price']);
    });
  }
};
