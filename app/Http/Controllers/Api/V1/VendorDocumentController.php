<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\VendorDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class VendorDocumentController extends Controller
{
    private const TYPES = [
        'business_registration',
        'government_id',
        'tax_registration',
        'business_permit',
        'bank_proof',
        'insurance',
        'other',
    ];

    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated.'], 401);
        if ($user->role !== 'vendor' || !$user->vendor_id) {
            return response()->json(['message' => 'Forbidden. Vendor only.'], 403);
        }

        return VendorDocument::where('vendor_id', $user->vendor_id)
            ->orderBy('document_type')
            ->get();
    }

    // Upload or replace a document for the authenticated vendor (even if pending approval)
    public function upsert(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated.'], 401);
        if ($user->role !== 'vendor' || !$user->vendor_id) {
            return response()->json(['message' => 'Forbidden. Vendor only.'], 403);
        }

        $data = $request->validate([
            'document_type' => ['required', Rule::in(self::TYPES)],
            'file' => ['required','file','max:10240','mimes:pdf,jpg,jpeg,png,webp'],
        ]);

        $vendor = Vendor::findOrFail($user->vendor_id);

        return DB::transaction(function () use ($data, $vendor, $user, $request) {

            $file = $request->file('file');
            $folder = "vendor-documents/{$vendor->id}/{$data['document_type']}";
            $path = $file->store($folder); // uses default disk

            $doc = VendorDocument::updateOrCreate(
                ['vendor_id' => $vendor->id, 'document_type' => $data['document_type']],
                [
                    'file_path' => $path,
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'file_size' => $file->getSize() ?? 0,
                    'uploaded_by' => $user->id,
                    'status' => 'pending',
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'rejection_reason' => null,
                ]
            );

            if (is_null($vendor->documents_submitted_at)) {
                $vendor->update(['documents_submitted_at' => now()]);
            }

            return response()->json($doc, 201);
        });
    }
}
