import { Link } from '@inertiajs/react'

const NavItem = ({ href, label }) => (
  <Link href={href} className="block rounded-xl px-3 py-2 text-sm text-gray-700 hover:bg-gray-100">
    {label}
  </Link>
)

export default function VendorLayout({ children }) {
  return (
    <div className="min-h-screen bg-gray-50">
      <div className="mx-auto flex max-w-7xl gap-6 p-6">
        <aside className="w-64 shrink-0">
          <div className="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
            <div className="text-lg font-bold">Labaduh Vendor</div>
            <div className="mt-4 space-y-1">
              <NavItem href="/vendor/dashboard" label="Dashboard" />
              <NavItem href="/vendor/job-offers" label="Job Offers" />
              <NavItem href="/vendor/orders" label="Orders" />
              <NavItem href="/vendor/pricing" label="Pricing" />
              <NavItem href="/vendor/shops" label="Shops" />
              <NavItem href="/vendor/subscription" label="Subscription" />
            </div>
          </div>
        </aside>

        <main className="flex-1">
          {children}
        </main>
      </div>
    </div>
  )
}
