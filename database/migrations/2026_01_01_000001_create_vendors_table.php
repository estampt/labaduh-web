<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            $table->enum('approval_status', ['pending','approved','rejected'])
                  ->default('pending')
                  ->index();

            $table->timestamp('approved_at')->nullable();

            // ✅ Column only — NO foreign key
            $table->unsignedBigInteger('approved_by')->nullable();

            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('vendors');
    }
};