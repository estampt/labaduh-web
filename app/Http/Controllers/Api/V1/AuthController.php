<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorShop;
use App\Models\VendorDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a customer or vendor.
     *
     * Vendor registration (multipart/form-data):
     * - user_type=vendor
     * - business_name, lat, lng, address_label (optional)
     * - business_registration (file, required)
     * - government_id (file, required)
     * - supporting_documents[] (files, optional)
     */
    public function register(Request $request)
    {
        $userType = $request->input('user_type', 'customer');

        $rules = [
            'name' => ['required','string','max:255'],
            'email' => ['required','email','max:255','unique:users,email'],
            'password' => ['required','string','min:8'],
            'user_type' => ['nullable', Rule::in(['customer','vendor'])],
        ];

        if ($userType === 'vendor') {
            $rules = array_merge($rules, [
                'business_name' => ['required','string','max:255'],
                'lat' => ['required','numeric','between:-90,90'],
                'lng' => ['required','numeric','between:-180,180'],
                'address_label' => ['nullable','string','max:255'],

                'business_registration' => ['required','file','mimes:jpg,jpeg,png,pdf','max:10240'],
                'government_id' => ['required','file','mimes:jpg,jpeg,png,pdf','max:10240'],
                'supporting_documents' => ['nullable','array'],
                'supporting_documents.*' => ['file','mimes:jpg,jpeg,png,pdf','max:10240'],
            ]);
        }

        $data = $request->validate($rules);

        return DB::transaction(function () use ($request, $data, $userType) {

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => $userType === 'vendor' ? 'vendor' : 'customer',
            ]);

            $vendor = null;
            $shop = null;

            if ($userType === 'vendor') {
                $vendor = Vendor::create([
                    'user_id' => $user->id,
                    'business_name' => $data['business_name'],
                    'status' => 'pending',
                ]);

                if (Schema::hasColumn('users', 'vendor_id')) {
                    $user->vendor_id = $vendor->id;
                    $user->save();
                }

                $shop = VendorShop::create([
                    'vendor_id' => $vendor->id,
                    'name' => $data['business_name'],
                    'address_line' => $data['address_label'] ?? $data['business_name'],
                    'latitude' => (float) $data['lat'],
                    'longitude' => (float) $data['lng'],
                    'is_active' => true,
                ]);

                $basePath = "vendors/{$vendor->id}/documents";

                $bizPath = $request->file('business_registration')->store($basePath, 'public');
                $govPath = $request->file('government_id')->store($basePath, 'public');

                VendorDocument::create([
                    'vendor_id' => $vendor->id,
                    'type' => 'business_registration',
                    'file_path' => $bizPath,
                    'status' => 'pending',
                ]);

                VendorDocument::create([
                    'vendor_id' => $vendor->id,
                    'type' => 'government_id',
                    'file_path' => $govPath,
                    'status' => 'pending',
                ]);

                if ($request->hasFile('supporting_documents')) {
                    foreach ($request->file('supporting_documents') as $file) {
                        $path = $file->store($basePath, 'public');
                        VendorDocument::create([
                            'vendor_id' => $vendor->id,
                            'type' => 'supporting_document',
                            'file_path' => $path,
                            'status' => 'pending',
                        ]);
                    }
                }
            }

            $token = $user->createToken('api')->plainTextToken;

            return response()->json([
                'user' => $user,
                'token' => $token,
                'vendor' => $vendor,
                'shop' => $shop,
            ], 201);
        });
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required','email'],
            'password' => ['required','string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => ['Invalid credentials.']]);
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $request->user()]);
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();
        return response()->json(['message' => 'Logged out']);
    }
}
