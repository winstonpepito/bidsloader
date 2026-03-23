import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

function todayStr() {
    const d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}

function formatDate(val) {
    if (!val) return '-';
    const d = new Date(val);
    if (isNaN(d)) return val;
    return d.toLocaleDateString();
}

function NeedsReviewBadge({ value }) {
    if (!value || value === 0) return null;
    return (
        <span className="inline-flex rounded-full bg-amber-100 px-2 text-xs font-semibold leading-5 text-amber-800">
            Needs Review
        </span>
    );
}

export default function LiveBidsIndex({ bids, filters }) {
    const [date, setDate] = useState(filters.date || todayStr());
    const [search, setSearch] = useState(filters.search || '');

    function handleDateChange(newDate) {
        setDate(newDate);
        router.get(route('live-bids.index'), { date: newDate, search }, { preserveState: true });
    }

    function handleSearch(e) {
        e.preventDefault();
        router.get(route('live-bids.index'), { date, search }, { preserveState: true });
    }

    function handleClear() {
        setSearch('');
        router.get(route('live-bids.index'), { date, search: '' }, { preserveState: true });
    }

    function goToPage(url) {
        if (url) router.get(url, {}, { preserveState: true });
    }

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Live Federal Bids</h2>}
        >
            <Head title="Live Federal Bids" />

            <div className="py-6">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    {/* Date picker and search */}
                    <div className="mb-6 rounded-lg bg-white p-4 shadow">
                        <div className="flex flex-wrap items-end gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700">Federal Date</label>
                                <input
                                    type="date"
                                    value={date}
                                    onChange={(e) => handleDateChange(e.target.value)}
                                    className="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                />
                            </div>
                            <form onSubmit={handleSearch} className="flex flex-1 items-end gap-2 min-w-[200px]">
                                <div className="flex-1">
                                    <label className="block text-sm font-medium text-gray-700">Search</label>
                                    <input
                                        type="text"
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        placeholder="Title or solicitation number..."
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    />
                                </div>
                                <button
                                    type="submit"
                                    className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500"
                                >
                                    Search
                                </button>
                                {search && (
                                    <button
                                        type="button"
                                        onClick={handleClear}
                                        className="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-300"
                                    >
                                        Clear
                                    </button>
                                )}
                            </form>
                        </div>
                    </div>

                    {/* Summary */}
                    <div className="mb-6">
                        <div className="rounded-lg bg-emerald-50 border border-emerald-200 p-4 shadow-sm">
                            <p className="text-sm font-medium text-emerald-700">
                                Bids for {new Date(date + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}
                            </p>
                            <p className="mt-1 text-sm text-emerald-600">
                                Page {bids.current_page} &mdash; showing {bids.data.length} bids
                            </p>
                        </div>
                    </div>

                    {/* Table */}
                    <div className="overflow-hidden rounded-lg bg-white shadow">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Title</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Sol #</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">NAICS</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Fed Date</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">End Date</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Review</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200 bg-white">
                                    {bids.data.map((bid) => (
                                        <tr key={bid.id} className="hover:bg-gray-50">
                                            <td className="px-6 py-4">
                                                <div className="text-sm font-medium text-gray-900">
                                                    {bid.url ? (
                                                        <a
                                                            href={bid.url}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="text-indigo-600 hover:text-indigo-900"
                                                        >
                                                            {bid.title?.substring(0, 80)}{bid.title?.length > 80 ? '...' : ''}
                                                        </a>
                                                    ) : (
                                                        <span>{bid.title?.substring(0, 80)}{bid.title?.length > 80 ? '...' : ''}</span>
                                                    )}
                                                </div>
                                                {bid.email && (
                                                    <div className="text-xs text-gray-400 mt-0.5">{bid.email}</div>
                                                )}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                                {bid.solicitation_number || '-'}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                                {bid.naics_code || '-'}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                                {formatDate(bid.fed_date)}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                                {formatDate(bid.end_date)}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4">
                                                <NeedsReviewBadge value={bid.needs_review} />
                                            </td>
                                        </tr>
                                    ))}
                                    {bids.data.length === 0 && (
                                        <tr>
                                            <td colSpan={6} className="px-6 py-12 text-center text-sm text-gray-500">
                                                No bids found for this date.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* Prev / Next navigation */}
                        <div className="flex items-center justify-between border-t border-gray-200 bg-white px-6 py-3">
                            <button
                                onClick={() => goToPage(bids.prev_url)}
                                disabled={!bids.prev_url}
                                className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                &larr; Previous
                            </button>
                            <span className="text-sm text-gray-500">Page {bids.current_page}</span>
                            <button
                                onClick={() => goToPage(bids.next_url)}
                                disabled={!bids.has_more}
                                className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                Next &rarr;
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
