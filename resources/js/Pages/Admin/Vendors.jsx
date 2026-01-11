import AdminLayout from '../../Layouts/AdminLayout'
import PageHeader from '../../Components/PageHeader'
import Table from '../../Components/Table'
import Badge from '../../Components/Badge'
import { Link } from '@inertiajs/react'

export default function Vendors({ vendors }) {
  const rows = vendors.data || vendors || []
  const columns = [
    { key: 'id', label: 'ID' },
    { key: 'name', label: 'Vendor' },
    { key: 'status', label: 'Status', render: (r) => <Badge tone={r.status === 'approved' ? 'green' : 'yellow'}>{r.status}</Badge> },
    { key: 'rating', label: 'Rating', render: (r) => (r.rating ?? '-') },
    { key: 'created_at', label: 'Created' },
    { key: 'action', label: '', render: (r) => <Link className="text-sm font-medium text-blue-600 hover:underline" href={`/admin/vendors/${r.id}`}>View</Link> },
  ]
  return (
    <AdminLayout>
      <div className="space-y-6">
        <PageHeader title="Vendors" subtitle="Approve and manage vendor partners" />
        <Table columns={columns} rows={rows} />
      </div>
    </AdminLayout>
  )
}
