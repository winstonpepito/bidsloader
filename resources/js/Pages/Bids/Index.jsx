import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Pagination from '@/Components/Pagination';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function BidsIndex({ bids, categories, subscriptionTypes, filters }) {
    const [search, setSearch] = useState(filters.search || '');

    function handleSearch(e) {
        e.preventDefault();
        router.get(route('bids.index'), { ...filters, search }, { preserveState: true });
    }

    function handleFilterChange(key, value) {
        router.get(route('bids.index'), { ...filters, [key]: value, search }, { preserveState: true });
    }

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Bids</h2>}
        >
            <Head title="Bids" />

            <div className="py-6">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    {/* Filters */}
                    <div className="mb-6 rounded-lg bg-white p-4 shadow">
                        <form onSubmit={handleSearch} className="flex flex-wrap items-end gap-4">
                            <div className="flex-1 min-w-[200px]">
                                <label className="block text-sm font-medium text-gray-700">Search</label>
                                <input
                                    type="text"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Title, solicitation #, agency..."
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700">Status</label>
                                <select
                                    value={filters.status || ''}
                                    onChange={(e) => handleFilterChange('status', e.target.value)}
                                    className="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                >
                                    <option value="">All</option>
                                    <option value="active">Active</option>
                                    <option value="expired">Expired</option>
                                    <option value="review">Needs Review</option>
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700">Category</label>
                                <select
                                    value={filters.category_id || ''}
                                    onChange={(e) => handleFilterChange('category_id', e.target.value)}
                                    className="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                >
                                    <option value="">All Categories</option>
                                    {categories.map((cat) => (
                                        <option key={cat.id} value={cat.id}>{cat.code} - {cat.name}</option>
                                    ))}
                                </select>
                            </div>
                            <button type="submit" className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                                Search
                            </button>
                        </form>
                    </div>

                    {/* Results */}
                    <div className="overflow-hidden rounded-lg bg-white shadow">
                        <div className="border-b border-gray-200 px-6 py-4">
                            <p className="text-sm text-gray-500">
                                Showing {bids.from || 0} to {bids.to || 0} of {bids.total} bids
                            </p>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Title</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Sol #</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Agency</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Type</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">End Date</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200 bg-white">
                                    {bids.data.map((bid) => (
                                        <tr key={bid.id} className="hover:bg-gray-50">
                                            <td className="px-6 py-4">
                                                <Link href={route('bids.show', bid.id)} className="text-sm font-medium text-indigo-600 hover:text-indigo-900">
                                                    {bid.title?.substring(0, 60)}{bid.title?.length > 60 ? '...' : ''}
                                                </Link>
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{bid.solicitation_number}</td>
                                            <td className="px-6 py-4 text-sm text-gray-500">{bid.agency?.substring(0, 30)}</td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{bid.subscription_type?.name}</td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                                {bid.end_date ? new Date(bid.end_date).toLocaleDateString() : '-'}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4">
                                                {bid.needs_review ? (
                                                    <span className="inline-flex rounded-full bg-yellow-100 px-2 text-xs font-semibold leading-5 text-yellow-800">Review</span>
                                                ) : bid.end_date && new Date(bid.end_date) >= new Date() ? (
                                                    <span className="inline-flex rounded-full bg-green-100 px-2 text-xs font-semibold leading-5 text-green-800">Active</span>
                                                ) : (
                                                    <span className="inline-flex rounded-full bg-gray-100 px-2 text-xs font-semibold leading-5 text-gray-800">Expired</span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                    {bids.data.length === 0 && (
                                        <tr>
                                            <td colSpan="6" className="px-6 py-8 text-center text-sm text-gray-500">No bids found.</td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        <Pagination links={bids.links} />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
