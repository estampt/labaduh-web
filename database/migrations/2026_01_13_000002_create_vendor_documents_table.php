<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vendor_documents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();

            $table->enum('document_type', [
                'business_registration',
                'government_id',
                'tax_registration',
                'business_permit',
                'bank_proof',
                'insurance',
                'other',
            ])->index();

            $table->string('file_path');
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->default(0);

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['pending','approved','rejected'])->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason')->nullable();

            $table->timestamps();

            $table->unique(['vendor_id','document_type']); // one current document per type
            $table->index(['vendor_id','status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_documents');
    }
};
