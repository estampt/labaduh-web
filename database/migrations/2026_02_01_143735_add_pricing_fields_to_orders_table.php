<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('orders', function (Blueprint $table) {
      // Pricing workflow
      $table->string('pricing_status', 24)->default('estimated')->after('status');
      // estimated | final_proposed | approved | rejected | auto_approved

      $table->dateTime('final_proposed_at')->nullable()->after('pricing_status');
      $table->dateTime('approved_at')->nullable()->after('final_proposed_at');

      // Auto-confirm (minutes)
      $table->unsignedInteger('auto_confirm_minutes')->default(30)->after('approved_at');

      // Totals split: estimated vs final
      $table->decimal('estimated_subtotal', 12, 2)->default(0)->after('auto_confirm_minutes');
      $table->decimal('estimated_total', 12, 2)->default(0)->after('estimated_subtotal');

      $table->decimal('final_subtotal', 12, 2)->nullable()->after('estimated_total');
      $table->decimal('final_total', 12, 2)->nullable()->after('final_subtotal');

      $table->text('pricing_notes')->nullable()->after('final_total');
    });
  }

  public function down(): void
  {
    Schema::table('orders', function (Blueprint $table) {
      $table->dropColumn([
        'pricing_status','final_proposed_at','approved_at','auto_confirm_minutes',
        'estimated_subtotal','estimated_total','final_subtotal','final_total','pricing_notes'
      ]);
    });
  }
};
