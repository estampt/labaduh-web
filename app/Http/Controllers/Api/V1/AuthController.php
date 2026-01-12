<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorShop;
use App\Notifications\PendingVendorApprovalNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['required','email','max:255', Rule::unique('users','email')],
            'password' => ['required','string','min:6'],
            'role' => ['sometimes', Rule::in(['customer','vendor','admin'])],

            'vendor' => ['sometimes','array'],
            'vendor.name' => ['required_if:role,vendor','string','max:255'],
            'vendor.email' => ['nullable','email','max:255'],
            'vendor.phone' => ['nullable','string','max:50'],

            'shop' => ['sometimes','array'],
            'shop.name' => ['required_if:role,vendor','string','max:255'],
            'shop.phone' => ['nullable','string','max:50'],
            'shop.address' => ['nullable','string','max:255'],
            'shop.latitude' => ['nullable','numeric'],
            'shop.longitude' => ['nullable','numeric'],
            'shop.service_radius_km' => ['nullable','numeric','min:0'],
            'shop.country_id' => ['nullable','integer'],
            'shop.state_province_id' => ['nullable','integer'],
            'shop.city_id' => ['nullable','integer'],
        ]);

        $role = $data['role'] ?? 'customer';

        return DB::transaction(function () use ($data, $role) {
            $vendorId = null;
            $vendor = null;

            if ($role === 'vendor') {
                $v = $data['vendor'] ?? [];
                $vendor = Vendor::create([
                    'name' => $v['name'],
                    'email' => $v['email'] ?? null,
                    'phone' => $v['phone'] ?? null,
                    'approval_status' => 'pending',
                    'is_active' => false,
                ]);
                $vendorId = $vendor->id;

                $s = $data['shop'] ?? [];
                VendorShop::create([
                    'vendor_id' => $vendorId,
                    'name' => $s['name'] ?? ($vendor->name . ' - Main Branch'),
                    'phone' => $s['phone'] ?? null,
                    'address' => $s['address'] ?? null,
                    'latitude' => $s['latitude'] ?? null,
                    'longitude' => $s['longitude'] ?? null,
                    'service_radius_km' => $s['service_radius_km'] ?? 5,
                    'country_id' => $s['country_id'] ?? null,
                    'state_province_id' => $s['state_province_id'] ?? null,
                    'city_id' => $s['city_id'] ?? null,
                    'is_active' => true,
                ]);
            }

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => $role,
                'vendor_id' => $vendorId,
            ]);

            if ($vendor) {
                $admins = User::where('role', 'admin')->get();
                foreach ($admins as $admin) {
                    $admin->notify(new PendingVendorApprovalNotification($vendor));
                }
            }

            $token = $user->createToken('api')->plainTextToken;
            return response()->json(['user' => $user->load('vendor'), 'token' => $token], 201);
        });
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email'=>['required','email'], 'password'=>['required','string']]);
        $user = User::where('email', $data['email'])->first();
        if (!$user || !Hash::check($data['password'], $user->password)) return response()->json(['message' => 'Invalid credentials.'], 401);
        $token = $user->createToken('api')->plainTextToken;
        return response()->json(['user' => $user->load('vendor'), 'token' => $token]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request)
    {
        return $request->user()->load('vendor');
    }
}
