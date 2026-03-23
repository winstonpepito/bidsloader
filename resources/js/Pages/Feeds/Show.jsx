import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function FeedShow({ feed, logs }) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Feed Load: {feed.fbo_date}
                    </h2>
                    <Link href={route('feeds.index')} className="text-sm text-indigo-600 hover:text-indigo-800">
                        &larr; Back to Feeds
                    </Link>
                </div>
            }
        >
            <Head title={`Feed ${feed.fbo_date}`} />

            <div className="py-6">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    {/* Summary Card */}
                    <div className="overflow-hidden rounded-lg bg-white shadow">
                        <div className="px-6 py-5">
                            <dl className="grid grid-cols-1 gap-5 sm:grid-cols-4">
                                <div className="rounded-lg bg-gray-50 px-4 py-5">
                                    <dt className="truncate text-sm font-medium text-gray-500">Status</dt>
                                    <dd className="mt-1">
                                        <span className={`inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                            feed.status === 'completed' ? 'bg-green-100 text-green-800' :
                                            feed.status === 'failed' ? 'bg-red-100 text-red-800' :
                                            feed.status === 'processing' ? 'bg-blue-100 text-blue-800' :
                                            'bg-gray-100 text-gray-800'
                                        }`}>
                                            {feed.status}
                                        </span>
                                    </dd>
                                </div>
                                <div className="rounded-lg bg-gray-50 px-4 py-5">
                                    <dt className="truncate text-sm font-medium text-gray-500">Entries Loaded</dt>
                                    <dd className="mt-1 text-2xl font-semibold text-gray-900">{feed.entries_loaded}</dd>
                                </div>
                                <div className="rounded-lg bg-gray-50 px-4 py-5">
                                    <dt className="truncate text-sm font-medium text-gray-500">Errors</dt>
                                    <dd className="mt-1 text-2xl font-semibold text-gray-900">{feed.errors_count}</dd>
                                </div>
                                <div className="rounded-lg bg-gray-50 px-4 py-5">
                                    <dt className="truncate text-sm font-medium text-gray-500">Loaded At</dt>
                                    <dd className="mt-1 text-sm font-semibold text-gray-900">
                                        {feed.created_at ? new Date(feed.created_at).toLocaleString() : '-'}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                        {feed.notes && (
                            <div className="border-t border-gray-200 px-6 py-4">
                                <h4 className="text-sm font-medium text-gray-900">Notes</h4>
                                <p className="mt-1 text-sm text-gray-500">{feed.notes}</p>
                            </div>
                        )}
                    </div>

                    {/* Logs */}
                    <div className="mt-6 overflow-hidden rounded-lg bg-white shadow">
                        <div className="border-b border-gray-200 px-6 py-4">
                            <h3 className="text-lg font-medium text-gray-900">Load Logs</h3>
                        </div>
                        <div className="divide-y divide-gray-200">
                            {logs.length === 0 ? (
                                <p className="px-6 py-4 text-sm text-gray-500">No logs available.</p>
                            ) : (
                                logs.map((log) => (
                                    <div key={log.id} className="px-6 py-3 flex items-start gap-3">
                                        <span className={`mt-0.5 inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${
                                            log.level === 'error' ? 'bg-red-100 text-red-800' :
                                            log.level === 'warning' ? 'bg-yellow-100 text-yellow-800' :
                                            'bg-blue-100 text-blue-800'
                                        }`}>
                                            {log.level}
                                        </span>
                                        <div className="flex-1">
                                            <p className="text-sm text-gray-900">{log.message}</p>
                                            <p className="text-xs text-gray-400">
                                                {new Date(log.created_at).toLocaleString()}
                                            </p>
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
