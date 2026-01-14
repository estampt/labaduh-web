<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('vendor_documents')) {
            return;
        }

        Schema::create('vendor_documents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();

            $table->string('type', 60);
            $table->string('file_path');
            $table->string('status', 30)->default('pending');

            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();

            $table->index(['vendor_id', 'type']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_documents');
    }
};
