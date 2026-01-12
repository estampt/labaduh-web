# Labaduh – Shop-Centric Core Scaffold (Laravel 12)

This drop-in scaffold adds **multi-shop support** where:
- **1 Vendor can have multiple Shops**
- **Shops drive pricing, capacity, matching, and fulfillment**
- Orders can point to a specific `shop_id`

## What’s included
### Migrations
- `vendor_shops`
- `shop_service_prices` (shop-level service pricing)
- `shop_delivery_prices` (shop-level in-house delivery pricing)
- `shop_capacities` (per-day capacity)
- `shop_time_slots` (pickup/delivery time slots)
- `orders.shop_id` (nullable FK to `vendor_shops`)

### Models
- `VendorShop`
- `ShopServicePrice`
- `ShopDeliveryPrice`
- `ShopCapacity`
- `ShopTimeSlot`

### Controllers (API v1)
- `VendorShopController` (CRUD shops under a vendor)
- `VendorPricingController` (shop pricing upsert)
- `VendorServiceController` (pricing preview now requires `shop_id`)
- `ShopAvailabilityController` (capacity + slots)
- `JobRequestController` (matches **shops**)

### Services
- `ShopPricingService` (line + order pricing)
- `ShopMatchingService` (distance + capacity matching)

### Middleware
- `VendorOwnsShop` (optional ownership check)

---

## 1) Copy these files into your Laravel project
Copy the folder contents into your project root **preserving paths**:
- `database/migrations/*`
- `app/Models/*`
- `app/Services/*`
- `app/Http/Controllers/Api/V1/*`
- `app/Http/Middleware/*`

---

## 2) Register Middleware (optional but recommended)
In `bootstrap/app.php` or `app/Http/Kernel.php` (depending on your setup), register:

```php
'vendor_owns_shop' => \App\Http\Middleware\VendorOwnsShop::class,
```

Then you can protect shop routes with:
```php
->middleware('vendor_owns_shop')
```

---

## 3) Update your existing models (VERY IMPORTANT)

### Vendor model (`app/Models/Vendor.php`)
Add:

```php
public function shops()
{
    return $this->hasMany(\App\Models\VendorShop::class);
}
```

### Order model (`app/Models/Order.php`)
Add:

```php
public function shop()
{
    return $this->belongsTo(\App\Models\VendorShop::class);
}
```

Also ensure `shop_id` is fillable/cast if you use fillable arrays.

---

## 4) Migrate
Run:

```bash
php artisan migrate
```

---

## 5) API usage changes (shop drives everything)

### Vendor creates shops
- `POST /api/v1/vendors/{vendor}/shops`

### Vendor sets pricing (shop-level)
- `POST /api/v1/vendor/pricing/service-prices`
  Body:
  ```json
  {
    "shop_id": 1,
    "prices": [
      {"service_id": 1, "category_code": "WHITES", "kg": 6, "pricing_model": "PER_KG", "price_per_kg": 20, "min_kg": 6}
    ]
  }
  ```

- `POST /api/v1/vendor/pricing/delivery-price`
  Body:
  ```json
  {
    "shop_id": 1,
    "base_fee": 50,
    "per_km_fee": 10
  }
  ```

### Customer pricing preview (shop-level)
- `POST /api/v1/vendors/{vendor}/pricing/preview`
  Body:
  ```json
  {"shop_id": 1, "items":[{"service_id":1,"category_code":"WHITES","kg":6}]}
  ```

### Matching now returns shops
- `POST /api/v1/job-requests/match`

---

## Notes
- Location foreign keys match your master tables:
  - `countries`
  - `state_province` (singular)
  - `cities`
- If your `vendors.status` is not exactly `'approved'`, adjust it in:
  - `app/Services/ShopMatchingService.php`

