<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddActiveShopIdToPushTokensTable  extends Migration
{
    public function up()
    {
        Schema::table('push_tokens', function (Blueprint $table) {
            $table->unsignedBigInteger('active_shop_id')->nullable()->after('user_id');
            $table->index('active_shop_id');
        });
    }

    public function down()
    {
        Schema::table('push_tokens', function (Blueprint $table) {
            $table->dropIndex(['active_shop_id']);
            $table->dropColumn('active_shop_id');
        });
    }
}
