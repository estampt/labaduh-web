# Labaduh – Vendor Registration (API + Documents + Default Shop)

This bundle adds/updates the vendor registration API to accept:

**Fields**
- name, email, password
- user_type=vendor
- business_name
- lat, lng
- address_label (optional)

**Files (multipart/form-data)**
- business_registration (required)
- government_id (required)
- supporting_documents[] (optional, multiple)

It will:
- create `users` row (role/vendor)
- create `vendors` row (status=pending)
- create default `vendor_shops` row (shop name = business_name; coordinates saved)
- store required/optional documents into `storage/app/public/vendors/{vendor_id}/documents`
- create `vendor_documents` rows with status=pending
- return Sanctum token

## Install
1) Copy folders into your Laravel project root:
- `app/`
- `database/migrations/`
- `routes/_snippets/` (manual copy into your `routes/api.php`)
- `config/_snippets/` (optional)

2) Run:
```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
php artisan storage:link
php artisan optimize:clear
```

## Route changes
Open your existing `routes/api.php` and ensure your register route points to `Api\V1\AuthController@register`.
See: `routes/_snippets/api_routes_vendor_register.php`

## Postman
Use `multipart/form-data`:

Text:
- name
- email
- password
- user_type=vendor
- business_name
- lat
- lng
- address_label (optional)

Files:
- business_registration
- government_id
- supporting_documents[] (repeat key for multiple files)

## Notes
- If your `users` table does not have `role` and/or `vendor_id`, the included migration will add them safely.
- If your `vendors` table already has different columns, adjust `Vendor::create()` in the controller.
