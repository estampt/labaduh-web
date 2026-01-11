import AdminLayout from '../../Layouts/AdminLayout'
import PageHeader from '../../Components/PageHeader'
import StatCard from '../../Components/StatCard'

export default function Pricing({ systemPricing, courier }) {
  return (
    <AdminLayout>
      <div className="space-y-6">
        <PageHeader title="Pricing" subtitle="System fallback pricing & courier markup" />
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <StatCard label="Min KG / line" value={systemPricing.min_kg_per_line} />
          <StatCard label="Rate / KG" value={systemPricing.rate_per_kg} />
          <StatCard label="Delivery base" value={systemPricing.delivery_base_fee} />
          <StatCard label="Delivery / KM" value={systemPricing.delivery_fee_per_km} />
        </div>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <StatCard label="Courier provider" value={courier.default_provider} />
          <StatCard label="Courier markup (%)" value={courier.markup_percent} />
        </div>
      </div>
    </AdminLayout>
  )
}
