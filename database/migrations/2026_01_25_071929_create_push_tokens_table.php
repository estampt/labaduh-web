<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('push_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('token', 512)->unique();

            $table->string('platform', 20)->nullable();   // android / ios / web
            $table->string('device_id', 100)->nullable(); // optional
            $table->timestamp('last_seen_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_tokens');
    }
};
