<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {

            $table->uuid('id')->primary();

            // Thread
            $table->uuid('thread_id');

            // Sender
            $table->unsignedBigInteger('sender_id');

            // ✅ NEW: Shop reference
            $table->unsignedBigInteger('shop_id')->nullable();

            // Message content
            $table->text('body');

            $table->timestamp('sent_at')->useCurrent();

            $table->timestamps();

            // Indexes
            $table->index(['thread_id', 'sent_at']);
            $table->index(['sender_id']);
            $table->index(['shop_id']); // ✅ NEW

            // Foreign keys
            $table->foreign('thread_id')
                ->references('id')
                ->on('message_threads')
                ->cascadeOnDelete();

            $table->foreign('shop_id')
                ->references('id')
                ->on('vendor_shops')
                ->nullOnDelete(); // safer than cascade
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
