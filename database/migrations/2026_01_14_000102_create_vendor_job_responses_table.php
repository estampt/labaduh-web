<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vendor_job_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_offer_id')->constrained('job_offers')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();

            $table->enum('response', ['accepted','rejected'])->index();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['vendor_id','response']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_job_responses');
    }
};
