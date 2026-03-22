<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('message_threads', function (Blueprint $table) {
            $table->dropUnique('threads_scope_order_unique');
            $table->dropUnique('threads_scope_shop_customer_unique');

            $table->unique(
                ['scope', 'shop_id', 'customer_user_id', 'order_id'],
                'threads_scope_shop_customer_order_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('message_threads', function (Blueprint $table) {
            $table->dropUnique('threads_scope_shop_customer_order_unique');

            $table->unique(['scope', 'order_id'], 'threads_scope_order_unique');
            $table->unique(
                ['scope', 'shop_id', 'customer_user_id'],
                'threads_scope_shop_customer_unique'
            );
        });
    }
};
