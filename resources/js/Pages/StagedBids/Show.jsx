import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';

function InfoRow({ label, value }) {
    if (!value) return null;
    return (
        <div className="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
            <dt className="text-sm font-medium text-gray-500">{label}</dt>
            <dd className="mt-1 text-sm text-gray-900 sm:col-span-2 sm:mt-0">{value}</dd>
        </div>
    );
}

function StatusBadge({ status }) {
    const styles = {
        pending: 'bg-amber-100 text-amber-800',
        approved: 'bg-green-100 text-green-800',
        rejected: 'bg-red-100 text-red-800',
    };
    return (
        <span className={`inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium ${styles[status] || 'bg-gray-100 text-gray-800'}`}>
            {status}
        </span>
    );
}

export default function StagedBidShow({ stagedBid }) {
    const { flash } = usePage().props;
    const isPending = stagedBid.review_status === 'pending';

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">Staged Bid Review</h2>
                    <Link href={route('staged-bids.index')} className="text-sm text-indigo-600 hover:text-indigo-800">
                        &larr; Back to Staging
                    </Link>
                </div>
            }
        >
            <Head title={stagedBid.title || 'Staged Bid Review'} />

            <div className="py-6">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    {/* Flash messages */}
                    {flash?.success && (
                        <div className="mb-4 rounded-md bg-green-50 p-4">
                            <p className="text-sm font-medium text-green-800">{flash.success}</p>
                        </div>
                    )}
                    {flash?.error && (
                        <div className="mb-4 rounded-md bg-red-50 p-4">
                            <p className="text-sm font-medium text-red-800">{flash.error}</p>
                        </div>
                    )}

                    {/* Action bar */}
                    {isPending && (
                        <div className="mb-6 flex items-center gap-4 rounded-lg border-2 border-amber-200 bg-amber-50 p-4">
                            <div className="flex-1">
                                <p className="text-sm font-semibold text-amber-800">This bid is pending review.</p>
                                <p className="text-sm text-amber-700">Review the details below and approve or reject this bid.</p>
                            </div>
                            <button
                                onClick={() => router.post(route('staged-bids.approve', stagedBid.id), {}, { preserveState: false })}
                                className="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-500"
                            >
                                Approve
                            </button>
                            <button
                                onClick={() => router.post(route('staged-bids.reject', stagedBid.id), {}, { preserveState: false })}
                                className="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500"
                            >
                                Reject
                            </button>
                        </div>
                    )}

                    {/* Bid details */}
                    <div className="overflow-hidden rounded-lg bg-white shadow">
                        <div className="border-b border-gray-200 px-6 py-5">
                            <h3 className="text-lg font-medium leading-6 text-gray-900">{stagedBid.title}</h3>
                            <div className="mt-2 flex items-center gap-3">
                                <StatusBadge status={stagedBid.review_status} />
                                {stagedBid.entry_type && (
                                    <span className="inline-flex rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-800">
                                        {stagedBid.entry_type}
                                    </span>
                                )}
                                {stagedBid.subscription_type && (
                                    <span className="inline-flex rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800">
                                        {stagedBid.subscription_type.name}
                                    </span>
                                )}
                                {stagedBid.end_date && new Date(stagedBid.end_date) >= new Date() ? (
                                    <span className="inline-flex rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">Active</span>
                                ) : stagedBid.end_date ? (
                                    <span className="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800">Expired</span>
                                ) : null}
                            </div>
                        </div>
                        <div className="px-6 py-5">
                            <dl className="divide-y divide-gray-200">
                                <InfoRow label="Solicitation Number" value={stagedBid.solicitation_number} />
                                <InfoRow label="Agency" value={stagedBid.agency} />
                                <InfoRow label="Office" value={stagedBid.office} />
                                <InfoRow label="Category" value={stagedBid.category ? `${stagedBid.category.code} - ${stagedBid.category.name}` : null} />
                                <InfoRow label="NAICS Code" value={stagedBid.naics_code} />
                                <InfoRow label="Set-Aside" value={stagedBid.set_aside_code} />
                                <InfoRow label="Entity" value={stagedBid.entity?.name} />
                                <InfoRow label="State" value={stagedBid.state ? `${stagedBid.state.name} (${stagedBid.state.code})` : null} />
                                <InfoRow label="Location" value={stagedBid.location} />
                                <InfoRow label="ZIP" value={stagedBid.zip} />
                                <InfoRow label="Federal Date" value={stagedBid.fed_date ? new Date(stagedBid.fed_date).toLocaleDateString() : null} />
                                <InfoRow label="End Date" value={stagedBid.end_date ? new Date(stagedBid.end_date).toLocaleDateString() : null} />
                                <InfoRow label="Email" value={stagedBid.email} />
                                {stagedBid.url && (
                                    <div className="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
                                        <dt className="text-sm font-medium text-gray-500">URL</dt>
                                        <dd className="mt-1 text-sm sm:col-span-2 sm:mt-0">
                                            <a href={stagedBid.url} target="_blank" rel="noopener noreferrer" className="text-indigo-600 hover:text-indigo-800 break-all">
                                                {stagedBid.url}
                                            </a>
                                        </dd>
                                    </div>
                                )}
                                <InfoRow label="Performance Address" value={stagedBid.pop_address} />
                                <InfoRow label="Performance ZIP" value={stagedBid.pop_zip} />
                                <InfoRow label="Performance Country" value={stagedBid.pop_country} />
                                <InfoRow label="Third Party ID" value={stagedBid.third_party_identifier} />
                                <InfoRow label="Source" value={stagedBid.source?.name} />

                                {stagedBid.purchasing_agent && (
                                    <>
                                        <InfoRow label="Contact Name" value={stagedBid.purchasing_agent.name} />
                                        <InfoRow label="Contact Email" value={stagedBid.purchasing_agent.email} />
                                        <InfoRow label="Contact Info" value={stagedBid.purchasing_agent.contact_info} />
                                    </>
                                )}
                            </dl>
                        </div>
                    </div>

                    {/* Description */}
                    <div className="mt-6 overflow-hidden rounded-lg bg-white shadow">
                        <div className="border-b border-gray-200 px-6 py-4">
                            <h3 className="text-lg font-medium text-gray-900">Description</h3>
                        </div>
                        <div className="px-6 py-5">
                            <div
                                className="prose max-w-none text-sm text-gray-700"
                                dangerouslySetInnerHTML={{ __html: stagedBid.description || '<em>No description available.</em>' }}
                            />
                        </div>
                    </div>

                    {/* Bottom action bar */}
                    {isPending && (
                        <div className="mt-6 flex items-center justify-end gap-4">
                            <button
                                onClick={() => router.post(route('staged-bids.reject', stagedBid.id), {}, { preserveState: false })}
                                className="rounded-md bg-white px-4 py-2 text-sm font-semibold text-red-600 shadow-sm ring-1 ring-inset ring-red-300 hover:bg-red-50"
                            >
                                Reject
                            </button>
                            <button
                                onClick={() => router.post(route('staged-bids.approve', stagedBid.id), {}, { preserveState: false })}
                                className="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-500"
                            >
                                Approve &amp; Push to Live
                            </button>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
