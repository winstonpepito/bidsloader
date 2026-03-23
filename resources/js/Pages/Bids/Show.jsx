import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

function InfoRow({ label, value }) {
    if (!value) return null;
    return (
        <div className="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
            <dt className="text-sm font-medium text-gray-500">{label}</dt>
            <dd className="mt-1 text-sm text-gray-900 sm:col-span-2 sm:mt-0">{value}</dd>
        </div>
    );
}

export default function BidShow({ bid }) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">Bid Detail</h2>
                    <Link href={route('bids.index')} className="text-sm text-indigo-600 hover:text-indigo-800">
                        &larr; Back to Bids
                    </Link>
                </div>
            }
        >
            <Head title={bid.title || 'Bid Detail'} />

            <div className="py-6">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="overflow-hidden rounded-lg bg-white shadow">
                        <div className="border-b border-gray-200 px-6 py-5">
                            <h3 className="text-lg font-medium leading-6 text-gray-900">{bid.title}</h3>
                            <div className="mt-2 flex items-center gap-3">
                                {bid.needs_review ? (
                                    <span className="inline-flex rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-medium text-yellow-800">Needs Review</span>
                                ) : bid.end_date && new Date(bid.end_date) >= new Date() ? (
                                    <span className="inline-flex rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">Active</span>
                                ) : (
                                    <span className="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800">Expired</span>
                                )}
                                {bid.subscription_type && (
                                    <span className="inline-flex rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-800">
                                        {bid.subscription_type.name}
                                    </span>
                                )}
                            </div>
                        </div>
                        <div className="px-6 py-5">
                            <dl className="divide-y divide-gray-200">
                                <InfoRow label="Solicitation Number" value={bid.solicitation_number} />
                                <InfoRow label="Agency" value={bid.agency} />
                                <InfoRow label="Office" value={bid.office} />
                                <InfoRow label="Category" value={bid.category ? `${bid.category.code} - ${bid.category.name}` : null} />
                                <InfoRow label="NAICS Code" value={bid.naics_code} />
                                <InfoRow label="Set-Aside" value={bid.set_aside_code} />
                                <InfoRow label="Entity" value={bid.entity?.name} />
                                <InfoRow label="State" value={bid.state ? `${bid.state.name} (${bid.state.code})` : null} />
                                <InfoRow label="Location" value={bid.location} />
                                <InfoRow label="ZIP" value={bid.zip} />
                                <InfoRow label="Federal Date" value={bid.fed_date ? new Date(bid.fed_date).toLocaleDateString() : null} />
                                <InfoRow label="End Date" value={bid.end_date ? new Date(bid.end_date).toLocaleDateString() : null} />
                                <InfoRow label="Email" value={bid.email} />
                                {bid.url && (
                                    <div className="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
                                        <dt className="text-sm font-medium text-gray-500">URL</dt>
                                        <dd className="mt-1 text-sm sm:col-span-2 sm:mt-0">
                                            <a href={bid.url} target="_blank" rel="noopener noreferrer" className="text-indigo-600 hover:text-indigo-800 break-all">
                                                {bid.url}
                                            </a>
                                        </dd>
                                    </div>
                                )}
                                <InfoRow label="Performance Address" value={bid.pop_address} />
                                <InfoRow label="Performance ZIP" value={bid.pop_zip} />
                                <InfoRow label="Performance Country" value={bid.pop_country} />
                                <InfoRow label="Third Party ID" value={bid.third_party_identifier} />
                                <InfoRow label="Source" value={bid.source?.name} />

                                {bid.purchasing_agent && (
                                    <>
                                        <InfoRow label="Contact Name" value={bid.purchasing_agent.name} />
                                        <InfoRow label="Contact Email" value={bid.purchasing_agent.email} />
                                        <InfoRow label="Contact Info" value={bid.purchasing_agent.contact_info} />
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
                                dangerouslySetInnerHTML={{ __html: bid.description || '<em>No description available.</em>' }}
                            />
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
