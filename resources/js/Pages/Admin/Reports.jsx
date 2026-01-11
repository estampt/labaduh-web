import AdminLayout from '../../Layouts/AdminLayout'
import PageHeader from '../../Components/PageHeader'

export default function Reports({ charts }) {
  return (
    <AdminLayout>
      <div className="space-y-6">
        <PageHeader title="Reports" subtitle="Analytics & performance (placeholder data)" />
        <pre className="rounded-2xl bg-white p-4 text-xs shadow-sm ring-1 ring-gray-100 overflow-auto">
{JSON.stringify(charts, null, 2)}
        </pre>
      </div>
    </AdminLayout>
  )
}
