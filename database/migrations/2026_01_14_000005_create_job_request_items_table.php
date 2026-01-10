<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('job_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_request_id')->constrained('job_requests')->cascadeOnDelete();
            $table->unsignedBigInteger('service_id')->index();
            $table->decimal('weight_kg', 10, 2)->default(0);
            $table->json('options')->nullable();
            $table->timestamps();

            $table->index(['job_request_id','service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_request_items');
    }
};
