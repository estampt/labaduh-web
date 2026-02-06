<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();            // e.g. broadcast.min_radius_km
            $table->text('value')->nullable();          // stored as string (or JSON string)
            $table->string('type')->default('string');  // string|int|float|bool|json
            $table->string('group')->nullable();        // broadcast|pricing|...
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['group']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
