<?php

namespace App\Http\Controllers;

use App\Models\FboFeedError;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FeedErrorController extends Controller
{
    public function index(Request $request)
    {
        $query = FboFeedError::query();

        if ($entryType = $request->input('entry_type')) {
            $query->where('entry_type', $entryType);
        }

        if ($search = $request->input('search')) {
            $query->where('error_message', 'like', "%{$search}%");
        }

        $errors = $query->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $entryTypes = FboFeedError::distinct()
            ->whereNotNull('entry_type')
            ->pluck('entry_type');

        return Inertia::render('Errors/Index', [
            'errors' => $errors,
            'entryTypes' => $entryTypes,
            'filters' => $request->only(['entry_type', 'search']),
        ]);
    }

    public function show(FboFeedError $error)
    {
        return Inertia::render('Errors/Show', [
            'error' => $error,
            'decompressedEntry' => $error->decompressed_entry,
            'decompressedStack' => $error->decompressed_stack,
        ]);
    }

    public function destroy(FboFeedError $error)
    {
        $error->delete();
        return back()->with('success', 'Error record deleted.');
    }
}
