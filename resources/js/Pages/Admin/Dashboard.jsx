import AdminLayout from '../../Layouts/AdminLayout'
import StatCard from '../../Components/StatCard'
import PageHeader from '../../Components/PageHeader'

export default function Dashboard({ stats }) {
  return (
    <AdminLayout>
      <div className="space-y-6">
        <PageHeader title="Dashboard" subtitle="Overview of platform activity" />
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <StatCard label="Orders today" value={stats.orders_today} />
          <StatCard label="Active vendors" value={stats.active_vendors} />
          <StatCard label="Revenue today" value={stats.revenue_today} sub="(placeholder)" />
          <StatCard label="Failed matches" value={stats.failed_matches} sub="(placeholder)" />
        </div>
      </div>
    </AdminLayout>
  )
}
