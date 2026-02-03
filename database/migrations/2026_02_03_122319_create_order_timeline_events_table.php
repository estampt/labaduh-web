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
        // database/migrations/xxxx_xx_xx_create_order_timeline_events_table.php

        Schema::create('order_timeline_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained()
                ->cascadeOnDelete();

            // customer-facing step key
            $table->string('key', 50);
            // e.g. order_created, pickup_scheduled, washing

            // who caused this step
            $table->string('actor_type', 20)->nullable();
            // vendor | driver | customer | system

            $table->unsignedBigInteger('actor_id')->nullable();

            $table->json('meta')->nullable(); // optional future use

            $table->timestamp('at')->useCurrent();
            $table->timestamps();

            $table->index(['order_id', 'key']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_timeline_events');
    }
};
