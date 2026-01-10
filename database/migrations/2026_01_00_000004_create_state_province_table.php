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
        Schema::create('state_province', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('country_id');
            $table->string('name', 255);
            $table->char('abbreviation', 64);
            $table->tinyInteger('population');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

			$table->decimal('latitude', 10, 8)->nullable(false);
			$table->decimal('longitude', 11, 8)->nullable(false);

            $table
                ->foreign('country_id')
                ->references('id')
                ->on('countries')
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
        Schema::dropIfExists('state_province');
    }
};
