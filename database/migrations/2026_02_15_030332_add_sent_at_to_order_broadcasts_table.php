<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSentAtToOrderBroadcastsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_broadcasts', function (Blueprint $table) {
            $table->timestamp('sent_at')->nullable()->after('status');

            // Optional: Add an index for better performance
            $table->index('sent_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_broadcasts', function (Blueprint $table) {
            $table->dropColumn('sent_at');
            // If you added an index, drop it too
            $table->dropIndex(['sent_at']);
        });
    }
}
