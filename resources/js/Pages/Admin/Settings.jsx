import AdminLayout from '../../Layouts/AdminLayout'
import PageHeader from '../../Components/PageHeader'
import Badge from '../../Components/Badge'

export default function Settings({ flags }) {
  return (
    <AdminLayout>
      <div className="space-y-6">
        <PageHeader title="Settings" subtitle="Feature toggles (read-only in this pack)" />
        <div className="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100 space-y-3">
          <div className="flex items-center justify-between">
            <div className="text-sm text-gray-700">Walk-in enabled</div>
            <Badge tone={flags.walk_in_enabled ? 'green' : 'red'}>{String(flags.walk_in_enabled)}</Badge>
          </div>
          <div className="flex items-center justify-between">
            <div className="text-sm text-gray-700">Third-party enabled</div>
            <Badge tone={flags.third_party_enabled ? 'green' : 'red'}>{String(flags.third_party_enabled)}</Badge>
          </div>
          <div className="flex items-center justify-between">
            <div className="text-sm text-gray-700">In-house enabled</div>
            <Badge tone={flags.inhouse_enabled ? 'green' : 'red'}>{String(flags.inhouse_enabled)}</Badge>
          </div>
        </div>
      </div>
    </AdminLayout>
  )
}
