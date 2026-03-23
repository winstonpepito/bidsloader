import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

function StatCard({ title, value, color = 'indigo', href }) {
    const colorMap = {
        indigo: 'bg-indigo-500',
        green: 'bg-green-500',
        red: 'bg-red-500',
        yellow: 'bg-yellow-500',
        blue: 'bg-blue-500',
        gray: 'bg-gray-500',
    };

    const content = (
        <div className="overflow-hidden rounded-lg bg-white shadow">
            <div className="p-5">
                <div className="flex items-center">
                    <div className="flex-shrink-0">
                        <div className={`h-12 w-12 rounded-md ${colorMap[color]} flex items-center justify-center`}>
                            <span className="text-xl font-bold text-white">{typeof value === 'number' ? (value > 999 ? `${(value/1000).toFixed(1)}k` : value) : value}</span>
                        </div>
                    </div>
                    <div className="ml-5 w-0 flex-1">
                        <dl>
                            <dt className="truncate text-sm font-medium text-gray-500">{title}</dt>
                            <dd className="text-2xl font-semibold text-gray-900">{value.toLocaleString()}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    );

    return href ? <Link href={href}>{content}</Link> : content;
}

export default function Dashboard({ stats, recentFeeds, recentBids, bidsByDay }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    FBO Feed Dashboard
                </h2>
            }
        >
            <Head title="Dashboard" />

            <div className="py-6">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    {/* Stats Grid */}
                    <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        <StatCard title="Total Bids" value={stats.totalBids} color="indigo" href={route('bids.index')} />
                        <StatCard title="Active Bids" value={stats.activeBids} color="green" href={route('bids.index', { status: 'active' })} />
                        <StatCard title="Pending Staging" value={stats.pendingStaged} color="yellow" href={route('staged-bids.index')} />
                        <StatCard title="Feed Errors" value={stats.totalErrors} color="red" href={route('errors.index')} />
                    </div>

                    <div className="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        <StatCard title="Total Feeds Loaded" value={stats.totalFeeds} color="blue" href={route('feeds.index')} />
                        <StatCard title="Completed Feeds" value={stats.completedFeeds} color="green" />
                        <StatCard title="Failed Feeds" value={stats.failedFeeds} color="red" href={route('feeds.index', { status: 'failed' })} />
                        <StatCard title="Expired Bids" value={stats.expiredBids} color="gray" href={route('bids.index', { status: 'expired' })} />
                    </div>

                    {/* Recent Activity */}
                    <div className="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
                        {/* Recent Feeds */}
                        <div className="overflow-hidden rounded-lg bg-white shadow">
                            <div className="border-b border-gray-200 px-6 py-4">
                                <div className="flex items-center justify-between">
                                    <h3 className="text-lg font-medium text-gray-900">Recent Feed Loads</h3>
                                    <Link href={route('feeds.index')} className="text-sm text-indigo-600 hover:text-indigo-800">
                                        View all
                                    </Link>
                                </div>
                            </div>
                            <div className="divide-y divide-gray-200">
                                {recentFeeds.length === 0 ? (
                                    <p className="px-6 py-4 text-sm text-gray-500">No feeds loaded yet.</p>
                                ) : (
                                    recentFeeds.map((feed) => (
                                        <div key={feed.id} className="flex items-center justify-between px-6 py-3">
                                            <div>
                                                <p className="text-sm font-medium text-gray-900">{feed.fbo_date}</p>
                                                <p className="text-xs text-gray-500">{feed.entries_loaded} entries, {feed.errors_count} errors</p>
                                            </div>
                                            <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                                feed.status === 'completed' ? 'bg-green-100 text-green-800' :
                                                feed.status === 'failed' ? 'bg-red-100 text-red-800' :
                                                feed.status === 'processing' ? 'bg-blue-100 text-blue-800' :
                                                'bg-gray-100 text-gray-800'
                                            }`}>
                                                {feed.status}
                                            </span>
                                        </div>
                                    ))
                                )}
                            </div>
                        </div>

                        {/* Recent Bids */}
                        <div className="overflow-hidden rounded-lg bg-white shadow">
                            <div className="border-b border-gray-200 px-6 py-4">
                                <div className="flex items-center justify-between">
                                    <h3 className="text-lg font-medium text-gray-900">Recent Bids</h3>
                                    <Link href={route('bids.index')} className="text-sm text-indigo-600 hover:text-indigo-800">
                                        View all
                                    </Link>
                                </div>
                            </div>
                            <div className="divide-y divide-gray-200">
                                {recentBids.length === 0 ? (
                                    <p className="px-6 py-4 text-sm text-gray-500">No bids loaded yet.</p>
                                ) : (
                                    recentBids.map((bid) => (
                                        <Link key={bid.id} href={route('bids.show', bid.id)} className="block hover:bg-gray-50">
                                            <div className="px-6 py-3">
                                                <p className="truncate text-sm font-medium text-indigo-600">{bid.title}</p>
                                                <div className="mt-1 flex items-center gap-2">
                                                    <span className="text-xs text-gray-500">{bid.solicitation_number}</span>
                                                    {bid.agency && <span className="text-xs text-gray-400">| {bid.agency}</span>}
                                                </div>
                                            </div>
                                        </Link>
                                    ))
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
