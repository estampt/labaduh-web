import VendorLayout from '../../Layouts/VendorLayout'
import PageHeader from '../../Components/PageHeader'
import Table from '../../Components/Table'

export default function Shops({ shops }) {
  const rows = Array.isArray(shops) ? shops : (shops.data || [])
  const columns = [
    { key: 'id', label: 'ID' },
    { key: 'name', label: 'Shop' },
    { key: 'lat', label: 'Lat' },
    { key: 'lng', label: 'Lng' },
  ]
  return (
    <VendorLayout>
      <div className="space-y-6">
        <PageHeader title="Shops" subtitle="Manage your shop locations (read-only in this pack)" />
        <Table columns={columns} rows={rows} />
      </div>
    </VendorLayout>
  )
}
