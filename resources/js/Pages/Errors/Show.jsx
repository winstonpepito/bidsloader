import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

export default function ErrorShow({ error, decompressedEntry, decompressedStack }) {
    function handleDelete() {
        if (confirm('Are you sure you want to delete this error?')) {
            router.delete(route('errors.destroy', error.id), {
                onSuccess: () => router.visit(route('errors.index')),
            });
        }
    }

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">Error Detail</h2>
                    <Link href={route('errors.index')} className="text-sm text-indigo-600 hover:text-indigo-800">
                        &larr; Back to Errors
                    </Link>
                </div>
            }
        >
            <Head title="Error Detail" />

            <div className="py-6">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    {/* Error Info */}
                    <div className="overflow-hidden rounded-lg bg-white shadow">
                        <div className="border-b border-gray-200 px-6 py-5">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    {error.entry_type && (
                                        <span className="inline-flex rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800">
                                            {error.entry_type}
                                        </span>
                                    )}
                                    <span className="text-sm text-gray-500">
                                        FBO Date: {error.fbo_file_date || 'N/A'}
                                    </span>
                                    <span className="text-sm text-gray-500">
                                        Created: {new Date(error.created_at).toLocaleString()}
                                    </span>
                                </div>
                                <button
                                    onClick={handleDelete}
                                    className="rounded-md bg-red-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-red-500"
                                >
                                    Delete
                                </button>
                            </div>
                        </div>
                        <div className="px-6 py-5">
                            <h4 className="text-sm font-medium text-gray-900">Error Message</h4>
                            <div className="mt-2 rounded-md bg-red-50 p-4">
                                <p className="text-sm text-red-700">{error.error_message}</p>
                            </div>
                        </div>
                    </div>

                    {/* Entry Content */}
                    {decompressedEntry && (
                        <div className="mt-6 overflow-hidden rounded-lg bg-white shadow">
                            <div className="border-b border-gray-200 px-6 py-4">
                                <h3 className="text-lg font-medium text-gray-900">Entry Content</h3>
                            </div>
                            <div className="px-6 py-5">
                                <pre className="overflow-auto rounded-md bg-gray-900 p-4 text-sm text-gray-100">
                                    {decompressedEntry}
                                </pre>
                            </div>
                        </div>
                    )}

                    {/* Stack Trace */}
                    {decompressedStack && (
                        <div className="mt-6 overflow-hidden rounded-lg bg-white shadow">
                            <div className="border-b border-gray-200 px-6 py-4">
                                <h3 className="text-lg font-medium text-gray-900">Stack Trace</h3>
                            </div>
                            <div className="px-6 py-5">
                                <pre className="overflow-auto rounded-md bg-gray-900 p-4 text-xs text-gray-100">
                                    {decompressedStack}
                                </pre>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
