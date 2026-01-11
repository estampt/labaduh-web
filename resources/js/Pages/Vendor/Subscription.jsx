import VendorLayout from '../../Layouts/VendorLayout'
import PageHeader from '../../Components/PageHeader'
import Badge from '../../Components/Badge'

export default function Subscription({ current, plans }) {
  return (
    <VendorLayout>
      <div className="space-y-6">
        <PageHeader title="Subscription" subtitle="Upgrade to get prioritized matching" />
        <div className="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
          <div className="text-sm text-gray-500">Current plan</div>
          <div className="mt-2 flex items-center gap-2">
            <Badge tone="green">{current.tier}</Badge>
            <div className="text-sm text-gray-600">{current.renews_at ? `Renews at ${current.renews_at}` : 'No renewal date'}</div>
          </div>
        </div>

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
          {plans.map(p => (
            <div key={p.tier} className="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
              <div className="text-lg font-semibold capitalize">{p.tier}</div>
              <div className="mt-1 text-sm text-gray-600">Price: {p.price}</div>
              <div className="mt-1 text-sm text-gray-600">Priority boost: +{p.boost}</div>
              <button className="mt-4 w-full rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-black">
                Choose
              </button>
            </div>
          ))}
        </div>
      </div>
    </VendorLayout>
  )
}
