<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('job_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_request_id')->constrained('job_requests')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('shop_id')->nullable()->constrained('vendor_shops')->nullOnDelete();

            $table->enum('status', ['sent','seen','accepted','rejected','expired','cancelled'])->default('sent')->index();
            $table->timestamp('expires_at')->nullable()->index();

            $table->timestamps();

            $table->unique(['job_request_id','vendor_id']); // 1 offer per vendor per request
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_offers');
    }
};
