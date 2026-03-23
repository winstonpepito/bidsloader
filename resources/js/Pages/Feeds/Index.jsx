import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Pagination from '@/Components/Pagination';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function FeedsIndex({ feeds, filters }) {
    const [showTrigger, setShowTrigger] = useState(false);
    const { data, setData, post, processing } = useForm({
        mode: 'lookback',
        date: '',
        start_date: '',
        end_date: '',
        file_path: '',
    });

    function handleTrigger(e) {
        e.preventDefault();
        post(route('feeds.trigger'), {
            onSuccess: () => setShowTrigger(false),
        });
    }

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">Feed Loads</h2>
                    <button
                        onClick={() => setShowTrigger(!showTrigger)}
                        className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500"
                    >
                        Trigger Load
                    </button>
                </div>
            }
        >
            <Head title="Feed Loads" />

            <div className="py-6">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    {/* Trigger Form */}
                    {showTrigger && (
                        <div className="mb-6 rounded-lg bg-white p-6 shadow">
                            <h3 className="mb-4 text-lg font-medium text-gray-900">Trigger Feed Load</h3>
                            <form onSubmit={handleTrigger} className="space-y-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Load Mode</label>
                                    <select
                                        value={data.mode}
                                        onChange={(e) => setData('mode', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    >
                                        <option value="lookback">Lookback (load unloaded dates)</option>
                                        <option value="date">Specific Date</option>
                                        <option value="date_range">Date Range</option>
                                        <option value="file">Local File</option>
                                    </select>
                                </div>

                                {data.mode === 'date' && (
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">Date</label>
                                        <input
                                            type="date"
                                            value={data.date}
                                            onChange={(e) => setData('date', e.target.value)}
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        />
                                    </div>
                                )}

                                {data.mode === 'date_range' && (
                                    <div className="grid grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Start Date</label>
                                            <input
                                                type="date"
                                                value={data.start_date}
                                                onChange={(e) => setData('start_date', e.target.value)}
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">End Date</label>
                                            <input
                                                type="date"
                                                value={data.end_date}
                                                onChange={(e) => setData('end_date', e.target.value)}
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                            />
                                        </div>
                                    </div>
                                )}

                                {data.mode === 'file' && (
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">File Path</label>
                                        <input
                                            type="text"
                                            value={data.file_path}
                                            onChange={(e) => setData('file_path', e.target.value)}
                                            placeholder="/path/to/FBOFeed20240101"
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        />
                                    </div>
                                )}

                                <div className="flex gap-3">
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50"
                                    >
                                        {processing ? 'Dispatching...' : 'Dispatch Job'}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setShowTrigger(false)}
                                        className="rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    )}

                    {/* Filters */}
                    <div className="mb-4 flex gap-2">
                        {['', 'completed', 'processing', 'failed', 'pending'].map((status) => (
                            <button
                                key={status}
                                onClick={() => router.get(route('feeds.index'), { status }, { preserveState: true })}
                                className={`rounded-full px-3 py-1 text-sm font-medium ${
                                    (filters.status || '') === status
                                        ? 'bg-indigo-600 text-white'
                                        : 'bg-white text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50'
                                }`}
                            >
                                {status || 'All'}
                            </button>
                        ))}
                    </div>

                    {/* Table */}
                    <div className="overflow-hidden rounded-lg bg-white shadow">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">FBO Date</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Entries Loaded</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Errors</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Loaded At</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200 bg-white">
                                    {feeds.data.map((feed) => (
                                        <tr key={feed.id} className="hover:bg-gray-50">
                                            <td className="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">{feed.fbo_date}</td>
                                            <td className="whitespace-nowrap px-6 py-4">
                                                <span className={`inline-flex rounded-full px-2 text-xs font-semibold leading-5 ${
                                                    feed.status === 'completed' ? 'bg-green-100 text-green-800' :
                                                    feed.status === 'failed' ? 'bg-red-100 text-red-800' :
                                                    feed.status === 'processing' ? 'bg-blue-100 text-blue-800' :
                                                    'bg-gray-100 text-gray-800'
                                                }`}>
                                                    {feed.status}
                                                </span>
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{feed.entries_loaded}</td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                                {feed.errors_count > 0 ? (
                                                    <span className="text-red-600 font-medium">{feed.errors_count}</span>
                                                ) : feed.errors_count}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                                {feed.created_at ? new Date(feed.created_at).toLocaleString() : '-'}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm">
                                                <Link href={route('feeds.show', feed.id)} className="text-indigo-600 hover:text-indigo-900">
                                                    View
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                    {feeds.data.length === 0 && (
                                        <tr>
                                            <td colSpan="6" className="px-6 py-8 text-center text-sm text-gray-500">No feed loads found.</td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        <Pagination links={feeds.links} />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
