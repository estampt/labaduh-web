import AdminLayout from '../../Layouts/AdminLayout'
import PageHeader from '../../Components/PageHeader'
import StatCard from '../../Components/StatCard'
import Badge from '../../Components/Badge'

export default function VendorDetail({ vendor }) {
  return (
    <AdminLayout>
      <div className="space-y-6">
        <PageHeader
          title={vendor.name}
          subtitle={<span>Status: <Badge tone={vendor.status === 'approved' ? 'green' : 'yellow'}>{vendor.status}</Badge></span>}
        />
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <StatCard label="Orders completed" value={vendor.metrics.orders_completed} />
          <StatCard label="KG processed" value={vendor.metrics.kg_processed} />
          <StatCard label="Unique customers" value={vendor.metrics.unique_customers} />
        </div>
        <div className="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
          <div className="text-sm font-semibold text-gray-900">Notes</div>
          <div className="mt-2 text-sm text-gray-600">Hook up documents, shops, and pricing controls here.</div>
        </div>
      </div>
    </AdminLayout>
  )
}
