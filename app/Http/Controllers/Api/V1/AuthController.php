<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorShop;
use App\Models\VendorDocument;
use App\Notifications\EmailOtpNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {

        $role = $request->input('role', 'customer');

        $rules = [
            'name' => ['required','string','max:255'],
            'email' => ['required','email','max:255','unique:users,email'],
            'password' => ['required','string','min:8'],
            'role' => ['required', Rule::in(['customer','vendor'])],

            'contact_number' => ['present','nullable','string','max:40'],
            'address_line1' => ['required','string','max:255'],
            'address_line2' => ['present','nullable','string','max:255'],
            'postal_code' => ['nullable','string','max:20'],
            'country_ISO' => ['required','string','max:3'],
            //'country_id' => ['required','integer','exists:countries,id'],
            //'state_province_id' => ['nullable','integer','exists:state_province,id'],
            //'city_id' => ['nullable','integer','exists:cities,id'],
            'latitude' => ['required','numeric','between:-90,90'],
            'longitude' => ['required','numeric','between:-180,180'],

            //'facebook_id' => ['nullable','string','max:255','unique:users,facebook_id'],
            //'google_id' => ['nullable','string','max:255','unique:users,google_id'],
            //'twitter_id' => ['nullable','string','max:255','unique:users,twitter_id'],
            //'apple_id' => ['nullable','string','max:255','unique:users,apple_id'],

            //'badge' => ['nullable','string','max:50'],
        ];

        if ($role === 'vendor') {
            $rules = array_merge($rules, [
                'business_name' => ['required','string','max:255'],
                //'lat' => ['required','numeric','between:-90,90'],
                //'lng' => ['required','numeric','between:-180,180'],
                //'address_label' => ['nullable','string','max:255'],

                'business_registration' => ['required','file','mimes:jpg,jpeg,png,pdf','max:10240'],
                'government_id' => ['required','file','mimes:jpg,jpeg,png,pdf','max:10240'],
                'supporting_documents' => ['nullable','array'],
                'supporting_documents.*' => ['file','mimes:jpg,jpeg,png,pdf','max:10240'],
            ]);
        }

        $data = $request->validate($rules);
        $result = DB::transaction(function () use ($request, $data, $role) {

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => $role === 'vendor' ? 'vendor' : 'customer',


                'contact_number' => $data['contact_number'] ?? null,
                'address_line1' => $data['address_line1'] ?? null,
                'address_line2' => $data['address_line2'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                //'country_id' => $data['country_id'] ?? null,
                //'state_province_id' => $data['state_province_id'] ?? null,
                //'city_id' => $data['city_id'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,

                //'facebook_id' => $data['facebook_id'] ?? null,
                //'google_id' => $data['google_id'] ?? null,
                //'twitter_id' => $data['twitter_id'] ?? null,
                //'apple_id' => $data['apple_id'] ?? null,

                //'badge' => $data['badge'] ?? null,

                'is_verified' => false,
            ]);

            $vendor = null;
            $shop = null;

            if ($role === 'vendor') {
                $vendor = Vendor::create([
                    'user_id' => $user->id,
                    'name' => $data['business_name'],
                    'status' => 'pending',
                ]);

                if (Schema::hasColumn('users', 'vendor_id')) {
                    $user->vendor_id = $vendor->id;
                    $user->save();
                }

                $shop = VendorShop::create([
                    'vendor_id' => $vendor->id,
                    'name' => $data['business_name'],
                    'address_line1' => $data['address_line1'] ?? null,
                    'address_line2' => $data['address_line2'] ?? null,
                    'latitude' => (float) $data['latitude'],
                    'longitude' => (float) $data['longitude'],
                    'is_active' => true,
                ]);

                $basePath = "vendors/{$vendor->id}/documents";

                $bizPath = $request->file('business_registration')->store($basePath, 'public');
                $govPath = $request->file('government_id')->store($basePath, 'public');

                //TODO: Enable Vendor Documents
               /* VendorDocument::create([
                    'vendor_id' => $vendor->id,
                    'document_type' => 'business_registration',
                    'file_path' => $bizPath,
                    'status' => 'pending',
                ]);

                VendorDocument::create([
                    'vendor_id' => $vendor->id,
                    'document_type' => 'government_id',
                    'file_path' => $govPath,
                    'status' => 'pending',
                ]);
*/
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

            // Send OTP email
            $this->issueEmailOtp($user);

            return compact('user', 'token', 'vendor', 'shop');
        });

        return response()->json([
            'user' => $result['user'],
            'token' => $result['token'],
            'vendor' => $result['vendor'],
            'shop' => $result['shop'],
            'verification' => [
                'is_verified' => (bool) $result['user']->is_verified,
                'method' => 'otp',
                'channel' => 'email',
            ],
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required','email'],
            'password' => ['required','string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Issue token if login successful
        |--------------------------------------------------------------------------
        */
        $token = $user->createToken('api')->plainTextToken;

        /*
        |--------------------------------------------------------------------------
        | Access rules BEFORE token issuance
        |--------------------------------------------------------------------------
        */

        // Admin can always log in
        if ($user->role !== 'admin') {

            // Must be OTP verified
            if (!$user->is_verified) {
                return response()->json([
                    'message' => 'Account not verified.',
                    'reason' => 'otp_required',
                ], 403);
            }

            // Vendor must also be approved
            if ($user->role === 'vendor') {

                // Ensure vendor relationship exists
                if (!$user->vendor) {
                    return response()->json([
                        'message' => 'Vendor profile not found.',
                    ], 403);
                }

                // ✅ use approval_status instead of status
                if ($user->vendor->approval_status !== 'approved') {
                    return response()->json([
                        'message' => 'Vendor account is not yet approved.',
                        'reason' => 'vendor_pending',
                        'vendor_approval_status' => $user->vendor->approval_status,
                    ], 403);
                }
            }
        }


        return response()->json([
            'user' => $user,
            'token' => $token,
            'auth' => [
                'role' => $user->role,
                'is_verified' => (bool) $user->is_verified,
                // ✅ return vendors.approval_status here
                'vendor_approval_status' => $user->vendor?->approval_status,
            ],
        ]);
    }




    public function me(Request $request)
    {
        $user = $request->user();

        // Optional: handle unauthenticated
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $vendor = null;

        if (!empty($user->vendor_id)) {
            $vendor = Vendor::select('id', 'approval_status')
                ->where('id', $user->vendor_id)   // users.vendor_id = vendors.id
                ->first();
        }

        return response()->json([
            'data' => [
                // ✅ your Flutter expects data['user'] as Map
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,

                    // ✅ your Flutter reads user['user_type'] or user['type']
                    'user_type' => $user->role ?? null, // or whatever field you use
                    'type' => $user->role ?? null,      // keep both if you want
                ],

                // ✅ your Flutter expects data['vendor'] as Map (or null)
                'vendor' => $vendor ? [
                    'id' => $vendor->id, // vendorId = vendor['id']?.toString()
                    'approval_status' => $vendor->approval_status, // approval = vendor['approval_status']
                ] : null,

                // (optional) keep your verification block if you still need it
                'verification' => [
                    'is_verified' => (bool) ($user->is_verified ?? false),
                    'method' => 'otp',
                ],
            ],
        ], 200);
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();
        return response()->json(['message' => 'Logged out']);
    }

    public function sendEmailOtp(Request $request)
    {
        $user = $request->user();

        if ($user->is_verified) {
            return response()->json(['message' => 'Account already verified.']);
        }

        $this->issueEmailOtp($user);

        return response()->json(['message' => 'Verification code sent to email.']);
    }

    public function verifyEmailOtp(Request $request)
    {
        $data = $request->validate([
            'otp' => ['required','string','min:4','max:10'],
        ]);

        $user = $request->user();

        if ($user->is_verified) {
            return response()->json(['message' => 'Account already verified.']);
        }

        if (!$user->email_otp_hash || !$user->email_otp_expires_at) {
            return response()->json(['message' => 'No OTP pending. Please request a new code.'], 422);
        }

        if ($user->email_otp_expires_at->isPast()) {
            return response()->json(['message' => 'OTP expired. Please request a new code.'], 422);
        }

        if (!Hash::check($data['otp'], $user->email_otp_hash)) {
            return response()->json(['message' => 'Invalid OTP.'], 422);
        }

        $user->forceFill([
            'email_otp_hash' => null,
            'email_otp_expires_at' => null,
            'is_verified' => true,
            'email_verified_at' => now(),
        ])->save();

        return response()->json([
            'message' => 'Verified successfully.',
            'verification' => ['is_verified' => true],
            'user_type' => $user->role,
        ]);
    }

    public function sendPhoneOtp(Request $request)
    {
        $user = $request->user();

        if ($user->is_verified) {
            return response()->json(['message' => 'Account already verified.']);
        }

        return response()->json([
            'message' => 'SMS OTP not enabled yet.',
            'hint' => 'Enable SMS provider later and implement send here.',
        ], 501);
    }

    public function verifyPhoneOtp(Request $request)
    {
        return response()->json([
            'message' => 'SMS OTP not enabled yet.',
        ], 501);
    }

    private function issueEmailOtp(User $user): void
    {
        $otp = (string) random_int(100000, 999999);

        $user->forceFill([
            'email_otp_hash' => Hash::make($otp),
            'email_otp_expires_at' => now()->addMinutes(5),
        ])->save();

        $user->notify(new EmailOtpNotification($otp, 5));
    }
}
