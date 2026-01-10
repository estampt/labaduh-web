<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shop_time_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('vendor_shops')->cascadeOnDelete();

            $table->enum('slot_type', ['pickup','delivery'])->index();
            $table->unsignedTinyInteger('day_of_week')->index(); // 0=Sun..6=Sat
            $table->time('time_start');
            $table->time('time_end');
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['shop_id','slot_type','day_of_week','is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_time_slots');
    }
};
