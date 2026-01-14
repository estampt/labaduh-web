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

        $table->string('file_path', 2048);
        $table->string('original_filename', 512)->nullable();
        $table->string('mime_type', 100)->nullable();
        $table->unsignedBigInteger('file_size')->default(0);

        // Versioning for updates
        $table->integer('version')->default(1);
        $table->boolean('is_current')->default(true)->index();

        // Validity period
        $table->date('issue_date')->nullable();
        $table->date('expiry_date')->nullable()->index();

        // Upload info
        $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamp('uploaded_at')->useCurrent();

        // Review process
        $table->enum('status', [
            'draft',
            'pending',
            'under_review',
            'changes_requested',
            'approved',
            'rejected',
            'expired',
        ])->default('draft')->index();

        $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamp('reviewed_at')->nullable();
        $table->text('review_notes')->nullable();
        $table->string('rejection_reason', 1000)->nullable();

        $table->timestamps();
        $table->softDeletes(); // Consider soft deletes for audit trail

        // Modified unique constraint to allow versioning
        $table->unique(['vendor_id', 'document_type', 'version']);

        // Composite indexes for common queries
        $table->index(['vendor_id', 'status', 'is_current']);
        $table->index(['expiry_date', 'status']);
    });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_documents');
    }
};
