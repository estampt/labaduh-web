import VendorLayout from '../../Layouts/VendorLayout'
import PageHeader from '../../Components/PageHeader'

export default function JobOffers({ offers }) {
  return (
    <VendorLayout>
      <div className="space-y-6">
        <PageHeader title="Job Offers" subtitle="Incoming requests (hook up to job_offers table)" />
        <pre className="rounded-2xl bg-white p-4 text-xs shadow-sm ring-1 ring-gray-100 overflow-auto">
{JSON.stringify(offers, null, 2)}
        </pre>
      </div>
    </VendorLayout>
  )
}
