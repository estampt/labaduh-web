<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('job_requests', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('customer_id')->index();

            $table->decimal('pickup_lat', 10, 7);
            $table->decimal('pickup_lng', 10, 7);
            $table->decimal('dropoff_lat', 10, 7);
            $table->decimal('dropoff_lng', 10, 7);

            $table->date('pickup_date');
            $table->time('pickup_time_start');
            $table->time('pickup_time_end');

            $table->date('delivery_date');
            $table->time('delivery_time_start');
            $table->time('delivery_time_end');

            $table->decimal('estimated_kg', 10, 2)->default(0);

            $table->enum('assignment_status', ['draft','broadcasting','assigned','expired','cancelled'])->default('draft')->index();
            $table->unsignedBigInteger('assigned_vendor_id')->nullable()->index();
            $table->unsignedBigInteger('assigned_shop_id')->nullable()->index();

            $table->json('match_snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['customer_id','assignment_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_requests');
    }
};
