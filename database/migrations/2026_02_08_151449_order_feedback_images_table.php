<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_feedback_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_feedback_id');
            $table->string('image_url', 2048);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['order_feedback_id', 'sort_order']);

            $table->foreign('order_feedback_id')
                ->references('id')->on('order_feedbacks')
                ->cascadeOnDelete();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_feedback_images');
    }
};
