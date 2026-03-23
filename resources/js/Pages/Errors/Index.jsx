import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Pagination from '@/Components/Pagination';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function ErrorsIndex({ errors, entryTypes, filters }) {
    const [search, setSearch] = useState(filters.search || '');

    function handleSearch(e) {
        e.preventDefault();
        router.get(route('errors.index'), { ...filters, search }, { preserveState: true });
    }

    function handleDelete(id) {
        if (confirm('Are you sure you want to delete this error?')) {
            router.delete(route('errors.destroy', id));
        }
    }

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Feed Errors</h2>}
        >
            <Head title="Feed Errors" />

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
                                    placeholder="Error message..."
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700">Entry Type</label>
                                <select
                                    value={filters.entry_type || ''}
                                    onChange={(e) => router.get(route('errors.index'), { ...filters, entry_type: e.target.value }, { preserveState: true })}
                                    className="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                >
                                    <option value="">All Types</option>
                                    {entryTypes.map((type) => (
                                        <option key={type} value={type}>{type}</option>
                                    ))}
                                </select>
                            </div>
                            <button type="submit" className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                                Search
                            </button>
                        </form>
                    </div>

                    {/* Table */}
                    <div className="overflow-hidden rounded-lg bg-white shadow">
                        <div className="border-b border-gray-200 px-6 py-4">
                            <p className="text-sm text-gray-500">
                                {errors.total} error{errors.total !== 1 ? 's' : ''} found
                            </p>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Type</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Error Message</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">FBO Date</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Created</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200 bg-white">
                                    {errors.data.map((error) => (
                                        <tr key={error.id} className="hover:bg-gray-50">
                                            <td className="whitespace-nowrap px-6 py-4">
                                                {error.entry_type && (
                                                    <span className="inline-flex rounded-full bg-red-100 px-2 text-xs font-semibold leading-5 text-red-800">
                                                        {error.entry_type}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-6 py-4 text-sm text-gray-900">
                                                {error.error_message?.substring(0, 100)}{error.error_message?.length > 100 ? '...' : ''}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                                {error.fbo_file_date || '-'}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                                {new Date(error.created_at).toLocaleString()}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm space-x-2">
                                                <Link href={route('errors.show', error.id)} className="text-indigo-600 hover:text-indigo-900">
                                                    View
                                                </Link>
                                                <button onClick={() => handleDelete(error.id)} className="text-red-600 hover:text-red-900">
                                                    Delete
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                    {errors.data.length === 0 && (
                                        <tr>
                                            <td colSpan="5" className="px-6 py-8 text-center text-sm text-gray-500">No errors found.</td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        <Pagination links={errors.links} />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
