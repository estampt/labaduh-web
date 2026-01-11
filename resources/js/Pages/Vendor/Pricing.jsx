import VendorLayout from '../../Layouts/VendorLayout'
import PageHeader from '../../Components/PageHeader'
import Table from '../../Components/Table'
import Badge from '../../Components/Badge'

export default function Pricing({ servicePrices, deliveryPrices }) {
  const sp = Array.isArray(servicePrices) ? servicePrices : (servicePrices.data || [])
  const dp = Array.isArray(deliveryPrices) ? deliveryPrices : (deliveryPrices.data || [])

  const cols = [
    { key: 'id', label: 'ID' },
    { key: 'service_id', label: 'Service' },
    { key: 'category_code', label: 'Category', render: (r) => (r.category_code ?? '-') },
    { key: 'pricing_model', label: 'Model', render: (r) => <Badge tone="purple">{r.pricing_model}</Badge> },
    { key: 'min_kg', label: 'Min KG', render: (r) => (r.min_kg ?? '-') },
    { key: 'rate_per_kg', label: 'Rate/KG', render: (r) => (r.rate_per_kg ?? '-') },
    { key: 'block_kg', label: 'Block KG', render: (r) => (r.block_kg ?? '-') },
    { key: 'block_price', label: 'Block Price', render: (r) => (r.block_price ?? '-') },
    { key: 'flat_price', label: 'Flat Price', render: (r) => (r.flat_price ?? '-') },
    { key: 'is_active', label: 'Active', render: (r) => <Badge tone={r.is_active ? 'green' : 'red'}>{String(!!r.is_active)}</Badge> },
  ]

  const dcols = [
    { key: 'id', label: 'ID' },
    { key: 'shop_id', label: 'Shop', render: (r) => (r.shop_id ?? 'vendor-wide') },
    { key: 'base_fee', label: 'Base' },
    { key: 'fee_per_km', label: 'Per KM' },
    { key: 'is_active', label: 'Active', render: (r) => <Badge tone={r.is_active ? 'green' : 'red'}>{String(!!r.is_active)}</Badge> },
  ]

  return (
    <VendorLayout>
      <div className="space-y-6">
        <PageHeader title="Pricing" subtitle="Your service + delivery pricing overrides" />
        <div className="space-y-3">
          <div className="text-sm font-semibold text-gray-900">Service pricing</div>
          <Table columns={cols} rows={sp} />
        </div>
        <div className="space-y-3">
          <div className="text-sm font-semibold text-gray-900">Delivery pricing (in-house)</div>
          <Table columns={dcols} rows={dp} />
        </div>
        <div className="rounded-2xl bg-white p-4 text-sm text-gray-600 shadow-sm ring-1 ring-gray-100">
          This pack includes display-only tables. To edit pricing, wire forms to your existing vendor pricing APIs:
          <code className="ml-2 rounded bg-gray-100 px-2 py-0.5 text-xs">/api/v1/vendor/pricing</code>
        </div>
      </div>
    </VendorLayout>
  )
}
