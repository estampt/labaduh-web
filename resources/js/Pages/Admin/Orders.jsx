import AdminLayout from '../../Layouts/AdminLayout'
import PageHeader from '../../Components/PageHeader'
import Table from '../../Components/Table'
import Badge from '../../Components/Badge'

export default function Orders({ orders }) {
  const rows = orders.data || orders || []
  const columns = [
    { key: 'id', label: 'ID' },
    { key: 'status', label: 'Status', render: (r) => <Badge tone="blue">{r.status}</Badge> },
    { key: 'fulfillment_mode', label: 'Fulfillment', render: (r) => <Badge tone={r.fulfillment_mode === 'walk_in' ? 'green' : 'gray'}>{r.fulfillment_mode}</Badge> },
    { key: 'total', label: 'Total', render: (r) => (r.total ?? '-') },
    { key: 'created_at', label: 'Created' },
  ]
  return (
    <AdminLayout>
      <div className="space-y-6">
        <PageHeader title="Orders" subtitle="Monitor and manage orders" />
        <Table columns={columns} rows={rows} />
      </div>
    </AdminLayout>
  )
}
