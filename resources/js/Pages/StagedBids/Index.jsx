import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Pagination from '@/Components/Pagination';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState, useEffect, useCallback } from 'react';

const ENTRY_TYPES = [
    'PRESOL', 'COMBINE', 'SRCSGT', 'SNOTE', 'AMDCSS', 'MOD', 'ITB',
    'AWARD', 'JA', 'FAIROPP', 'SSALE', 'FSTD',
];

function StatusBadge({ status }) {
    const styles = {
        pending: 'bg-amber-100 text-amber-800',
        approved: 'bg-green-100 text-green-800',
        rejected: 'bg-red-100 text-red-800',
    };

    return (
        <span className={`inline-flex rounded-full px-2 text-xs font-semibold leading-5 ${styles[status] || 'bg-gray-100 text-gray-800'}`}>
            {status}
        </span>
    );
}

function EntryTypeBadge({ type }) {
    if (!type) return null;
    const styles = {
        PRESOL: 'bg-blue-50 text-blue-700 ring-blue-600/20',
        COMBINE: 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
        SRCSGT: 'bg-purple-50 text-purple-700 ring-purple-600/20',
        SNOTE: 'bg-cyan-50 text-cyan-700 ring-cyan-600/20',
        AMDCSS: 'bg-orange-50 text-orange-700 ring-orange-600/20',
        MOD: 'bg-rose-50 text-rose-700 ring-rose-600/20',
    };

    return (
        <span className={`inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset ${styles[type] || 'bg-gray-50 text-gray-700 ring-gray-600/20'}`}>
            {type}
        </span>
    );
}

function todayStr() {
    const d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}

function ApproveProgressBar({ initial }) {
    const [progress, setProgress] = useState(initial);

    const poll = useCallback(() => {
        fetch(route('staged-bids.approve-progress'), { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(data => {
                if (data) setProgress(data);
                if (data?.status === 'completed') {
                    setTimeout(() => router.reload({ preserveState: false }), 1500);
                }
            })
            .catch(() => {});
    }, []);

    useEffect(() => {
        if (!progress || progress.status !== 'running') return;
        const interval = setInterval(poll, 3000);
        return () => clearInterval(interval);
    }, [progress?.status, poll]);

    if (!progress) return null;

    const { status, total, processed, approved, skipped, errors } = progress;
    const pct = total > 0 ? Math.round((processed / total) * 100) : 0;
    const isRunning = status === 'running';
    const isDone = status === 'completed';

    return (
        <div className={`mb-6 rounded-lg border p-4 shadow-sm ${isDone ? 'border-green-200 bg-green-50' : 'border-blue-200 bg-blue-50'}`}>
            <div className="flex items-center justify-between mb-2">
                <h3 className={`text-sm font-semibold ${isDone ? 'text-green-900' : 'text-blue-900'}`}>
                    {isRunning ? 'Approving bids to live database...' : 'Approval complete'}
                </h3>
                <span className={`text-sm font-medium ${isDone ? 'text-green-700' : 'text-blue-700'}`}>
                    {processed} / {total} ({pct}%)
                </span>
            </div>
            <div className="w-full bg-gray-200 rounded-full h-2.5 mb-2">
                <div
                    className={`h-2.5 rounded-full transition-all duration-500 ${isDone ? 'bg-green-500' : 'bg-blue-500'}`}
                    style={{ width: `${pct}%` }}
                />
            </div>
            <div className="flex gap-4 text-xs">
                <span className="text-green-700">Approved: {approved}</span>
                <span className="text-amber-700">Skipped (dup): {skipped}</span>
                {errors > 0 && <span className="text-red-700">Errors: {errors}</span>}
                {isRunning && (
                    <span className="ml-auto text-blue-600 flex items-center gap-1">
                        <svg className="h-3 w-3 animate-spin" viewBox="0 0 24 24" fill="none">
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                        Processing...
                    </span>
                )}
            </div>
        </div>
    );
}

export default function StagedBidsIndex({ stagedBids, categories, counts, filters, approveProgress }) {
    const [search, setSearch] = useState(filters.search || '');
    const [selected, setSelected] = useState([]);
    const [processing, setProcessing] = useState(false);
    const [loadDate, setLoadDate] = useState(todayStr());
    const [loadingFeed, setLoadingFeed] = useState(false);
    const { flash } = usePage().props;

    const isApproving = approveProgress?.status === 'running';

    const allOnPageIds = stagedBids.data.filter(b => b.review_status === 'pending').map(b => b.id);
    const allSelected = allOnPageIds.length > 0 && allOnPageIds.every(id => selected.includes(id));

    function handleSearch(e) {
        e.preventDefault();
        setSelected([]);
        router.get(route('staged-bids.index'), { ...filters, search }, { preserveState: true });
    }

    function handleFilterChange(key, value) {
        setSelected([]);
        router.get(route('staged-bids.index'), { ...filters, [key]: value, search }, { preserveState: true });
    }

    function toggleSelect(id) {
        setSelected(prev => prev.includes(id) ? prev.filter(i => i !== id) : [...prev, id]);
    }

    function toggleSelectAll() {
        if (allSelected) {
            setSelected(prev => prev.filter(id => !allOnPageIds.includes(id)));
        } else {
            setSelected(prev => [...new Set([...prev, ...allOnPageIds])]);
        }
    }

    function handleBulkAction(action) {
        if (selected.length === 0) return;
        setProcessing(true);
        router.post(route(`staged-bids.bulk-${action}`), { ids: selected }, {
            preserveState: false,
            onFinish: () => {
                setProcessing(false);
                setSelected([]);
            },
        });
    }

    function handleApproveAll() {
        if (!confirm('Approve all pending staged bids? This will push them all to the live database.')) return;
        setProcessing(true);
        router.post(route('staged-bids.approve-all'), { entry_type: filters.entry_type || '' }, {
            preserveState: false,
            onFinish: () => {
                setProcessing(false);
                setSelected([]);
            },
        });
    }

    function handleBulkDelete() {
        if (selected.length === 0) return;
        if (!confirm(`Permanently delete ${selected.length} selected pending bid(s)? This cannot be undone.`)) return;
        setProcessing(true);
        router.post(route('staged-bids.bulk-delete'), { ids: selected }, {
            preserveState: false,
            onFinish: () => {
                setProcessing(false);
                setSelected([]);
            },
        });
    }

    function handleDeleteAllPending() {
        const n = counts.pending_bulk ?? counts.pending;
        if (n === 0) return;
        const typeMsg = filters.entry_type ? ` matching entry type “${filters.entry_type}”` : '';
        if (!confirm(`Permanently delete ${n} pending bid(s)${typeMsg}? This cannot be undone.`)) return;
        setProcessing(true);
        router.post(route('staged-bids.delete-all-pending'), { entry_type: filters.entry_type || '' }, {
            preserveState: false,
            onFinish: () => {
                setProcessing(false);
                setSelected([]);
            },
        });
    }

    function handleLoadFeed(e) {
        e.preventDefault();
        setLoadingFeed(true);
        router.post(route('staged-bids.load'), { date: loadDate }, {
            preserveState: false,
            onFinish: () => setLoadingFeed(false),
        });
    }

    const isPendingView = !filters.review_status || filters.review_status === 'pending';

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Staging Review</h2>}
        >
            <Head title="Staging Review" />

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

                    {/* Approve progress bar */}
                    {approveProgress && (approveProgress.status === 'running' || approveProgress.status === 'completed') && (
                        <ApproveProgressBar initial={approveProgress} />
                    )}

                    {/* Load from SAM.gov */}
                    <div className="mb-6 rounded-lg border border-indigo-200 bg-indigo-50 p-4 shadow-sm">
                        <form onSubmit={handleLoadFeed} className="flex flex-wrap items-end gap-4">
                            <div>
                                <h3 className="text-sm font-semibold text-indigo-900">Load Bids from SAM.gov</h3>
                                <p className="mt-0.5 text-xs text-indigo-700">Fetch opportunities for a specific date into staging for review.</p>
                            </div>
                            <div className="flex-1" />
                            <div>
                                <label className="block text-xs font-medium text-indigo-800">Date</label>
                                <input
                                    type="date"
                                    value={loadDate}
                                    onChange={(e) => setLoadDate(e.target.value)}
                                    className="mt-1 block rounded-md border-indigo-300 bg-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                />
                            </div>
                            <button
                                type="submit"
                                disabled={loadingFeed || !loadDate}
                                className="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {loadingFeed ? (
                                    <>
                                        <svg className="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                        </svg>
                                        Loading...
                                    </>
                                ) : (
                                    'Load Bids'
                                )}
                            </button>
                        </form>
                    </div>

                    {/* Status counts */}
                    <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <button
                            onClick={() => handleFilterChange('review_status', 'pending')}
                            className={`rounded-lg p-4 text-left shadow transition ${
                                isPendingView ? 'bg-amber-50 ring-2 ring-amber-400' : 'bg-white hover:bg-gray-50'
                            }`}
                        >
                            <p className="text-sm font-medium text-gray-500">Pending Review</p>
                            <p className="mt-1 text-3xl font-semibold text-amber-600">{counts.pending}</p>
                        </button>
                        <button
                            onClick={() => handleFilterChange('review_status', 'approved')}
                            className={`rounded-lg p-4 text-left shadow transition ${
                                filters.review_status === 'approved' ? 'bg-green-50 ring-2 ring-green-400' : 'bg-white hover:bg-gray-50'
                            }`}
                        >
                            <p className="text-sm font-medium text-gray-500">Approved</p>
                            <p className="mt-1 text-3xl font-semibold text-green-600">{counts.approved}</p>
                        </button>
                        <button
                            onClick={() => handleFilterChange('review_status', 'rejected')}
                            className={`rounded-lg p-4 text-left shadow transition ${
                                filters.review_status === 'rejected' ? 'bg-red-50 ring-2 ring-red-400' : 'bg-white hover:bg-gray-50'
                            }`}
                        >
                            <p className="text-sm font-medium text-gray-500">Rejected</p>
                            <p className="mt-1 text-3xl font-semibold text-red-600">{counts.rejected}</p>
                        </button>
                    </div>

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
                                <label className="block text-sm font-medium text-gray-700">Entry Type</label>
                                <select
                                    value={filters.entry_type || ''}
                                    onChange={(e) => handleFilterChange('entry_type', e.target.value)}
                                    className="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                >
                                    <option value="">All Types</option>
                                    {ENTRY_TYPES.map((t) => (
                                        <option key={t} value={t}>{t}</option>
                                    ))}
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

                    {/* Bulk actions bar */}
                    {isPendingView && (
                        <div className="mb-4 flex flex-wrap items-center gap-3">
                            <span className="text-sm text-gray-600">
                                {selected.length > 0 ? `${selected.length} selected` : 'Select bids to review'}
                            </span>
                            <button
                                onClick={() => handleBulkAction('approve')}
                                disabled={selected.length === 0 || processing || isApproving}
                                className="rounded-md bg-green-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-green-500 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                Approve Selected
                            </button>
                            <button
                                onClick={() => handleBulkAction('reject')}
                                disabled={selected.length === 0 || processing || isApproving}
                                className="rounded-md bg-red-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-red-500 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                Reject Selected
                            </button>
                            <button
                                onClick={handleBulkDelete}
                                disabled={selected.length === 0 || processing || isApproving}
                                className="rounded-md border border-red-800 bg-white px-3 py-1.5 text-sm font-semibold text-red-800 shadow-sm hover:bg-red-50 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                Delete Selected
                            </button>
                            <div className="flex-1" />
                            <button
                                onClick={handleApproveAll}
                                disabled={(counts.pending_bulk ?? counts.pending) === 0 || processing || isApproving}
                                className="rounded-md bg-green-700 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-green-600 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                Approve All Pending ({counts.pending_bulk ?? counts.pending})
                            </button>
                            <button
                                onClick={handleDeleteAllPending}
                                disabled={(counts.pending_bulk ?? counts.pending) === 0 || processing || isApproving}
                                className="rounded-md border-2 border-red-800 bg-red-50 px-3 py-1.5 text-sm font-semibold text-red-900 shadow-sm hover:bg-red-100 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                Delete All Pending ({counts.pending_bulk ?? counts.pending})
                            </button>
                        </div>
                    )}

                    {/* Table */}
                    <div className="overflow-hidden rounded-lg bg-white shadow">
                        <div className="border-b border-gray-200 px-6 py-4">
                            <p className="text-sm text-gray-500">
                                Showing {stagedBids.from || 0} to {stagedBids.to || 0} of {stagedBids.total} staged bids
                            </p>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        {isPendingView && (
                                            <th className="w-12 px-4 py-3">
                                                <input
                                                    type="checkbox"
                                                    checked={allSelected}
                                                    onChange={toggleSelectAll}
                                                    className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                />
                                            </th>
                                        )}
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Title</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Sol #</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Agency</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Type</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">End Date</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                        {isPendingView && (
                                            <th className="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                                        )}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200 bg-white">
                                    {stagedBids.data.map((bid) => (
                                        <tr key={bid.id} className={`hover:bg-gray-50 ${selected.includes(bid.id) ? 'bg-indigo-50' : ''}`}>
                                            {isPendingView && (
                                                <td className="w-12 px-4 py-4">
                                                    {bid.review_status === 'pending' && (
                                                        <input
                                                            type="checkbox"
                                                            checked={selected.includes(bid.id)}
                                                            onChange={() => toggleSelect(bid.id)}
                                                            className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                        />
                                                    )}
                                                </td>
                                            )}
                                            <td className="px-6 py-4">
                                                <Link href={route('staged-bids.show', bid.id)} className="text-sm font-medium text-indigo-600 hover:text-indigo-900">
                                                    {bid.title?.substring(0, 60)}{bid.title?.length > 60 ? '...' : ''}
                                                </Link>
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{bid.solicitation_number}</td>
                                            <td className="px-6 py-4 text-sm text-gray-500">{bid.agency?.substring(0, 30)}</td>
                                            <td className="whitespace-nowrap px-6 py-4">
                                                <EntryTypeBadge type={bid.entry_type} />
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                                {bid.end_date ? new Date(bid.end_date).toLocaleDateString() : '-'}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4">
                                                <StatusBadge status={bid.review_status} />
                                            </td>
                                            {isPendingView && (
                                                <td className="whitespace-nowrap px-6 py-4 text-right text-sm">
                                                    {bid.review_status === 'pending' && (
                                                        <div className="flex justify-end gap-2">
                                                            <button
                                                                onClick={() => router.post(route('staged-bids.approve', bid.id), {}, { preserveState: false })}
                                                                className="text-green-600 hover:text-green-900 font-medium"
                                                            >
                                                                Approve
                                                            </button>
                                                            <button
                                                                onClick={() => router.post(route('staged-bids.reject', bid.id), {}, { preserveState: false })}
                                                                className="text-red-600 hover:text-red-900 font-medium"
                                                            >
                                                                Reject
                                                            </button>
                                                        </div>
                                                    )}
                                                </td>
                                            )}
                                        </tr>
                                    ))}
                                    {stagedBids.data.length === 0 && (
                                        <tr>
                                            <td colSpan={isPendingView ? 8 : 6} className="px-6 py-8 text-center text-sm text-gray-500">
                                                No staged bids found.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        <Pagination links={stagedBids.links} />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
