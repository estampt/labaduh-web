<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('countries', function (Blueprint $table) { 
			$table->id();
			$table->mediumInteger('region_id')->unsigned();
            $table->string('name', 255);
            $table->string('iso_code', 255);
            $table->string('currency', 3);
            $table->string('official_language', 255);
            $table->bigInteger('population');
            $table->string('image_url', 255);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            

 // === Columns converted from your SQL ===
            $table->string('phonecode', 255)->nullable();
            $table->string('capital', 255)->nullable();
            $table->string('currency_name', 255)->nullable();
            $table->string('native', 255)->nullable();
            $table->string('nationality', 255)->nullable();
            $table->text('timezones')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('emoji', 191)->nullable();
            $table->string('emojiU', 191)->nullable(); 
            $table->tinyInteger('flag')->default(1);
            $table->string('wikiDataId', 255)->nullable()
                  ->comment('Rapid API GeoDB Cities');
            $table
                ->foreign('region_id')
                ->references('id')
                ->on('regions')
                ->onDelete('cascade')
               ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('countries');
    }
};
