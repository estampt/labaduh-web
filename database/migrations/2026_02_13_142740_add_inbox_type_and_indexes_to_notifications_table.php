<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('notifications', function (Blueprint $table) {
      $table->string('inbox_type', 20)->nullable()->index()->after('type');

      $table->index(['notifiable_type', 'notifiable_id', 'read_at', 'inbox_type'], 'notifications_unread_typed_idx');
      $table->index(['notifiable_type', 'notifiable_id', 'created_at'], 'notifications_list_idx');
    });
  }

  public function down(): void
  {
    Schema::table('notifications', function (Blueprint $table) {
      $table->dropIndex('notifications_inbox_type_index');
      $table->dropIndex('notifications_unread_typed_idx');
      $table->dropIndex('notifications_list_idx');
      $table->dropColumn('inbox_type');
    });
  }
};
