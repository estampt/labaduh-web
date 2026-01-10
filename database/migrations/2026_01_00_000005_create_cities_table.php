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
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('country_id');
            $table->string('name', 255);
            $table->bigInteger('population');            
            $table->unsignedBigInteger('state_province_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
			$table->decimal('latitude', 10, 8)->nullable(false);
			$table->decimal('longitude', 11, 8)->nullable(false);

            $table
                ->foreign('state_province_id')
                ->references('id')
                ->on('state_province')
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
        Schema::dropIfExists('cities');
    }
};
