import VendorLayout from '../../Layouts/VendorLayout'
import StatCard from '../../Components/StatCard'
import PageHeader from '../../Components/PageHeader'

export default function Dashboard({ vendor, stats }) {
  return (
    <VendorLayout>
      <div className="space-y-6">
        <PageHeader title="Dashboard" subtitle={`Vendor ID: ${vendor.vendor_id} • Tier: ${vendor.tier}`} />
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <StatCard label="Orders today" value={stats.orders_today} />
          <StatCard label="KG today" value={stats.kg_today} />
          <StatCard label="Capacity (KG)" value={stats.capacity_kg} sub="(hook up to capacity tables)" />
          <StatCard label="Earnings today" value={stats.earnings_today} sub="(placeholder)" />
        </div>
      </div>
    </VendorLayout>
  )
}
