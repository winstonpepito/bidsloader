<?php

namespace App\Http\Controllers;

use App\Jobs\ApproveAllBidsJob;
use App\Jobs\LoadFboFeedJob;
use App\Models\Category;
use App\Models\LoadedFboFeed;
use App\Models\StagedBid;
use App\Services\LiveBidWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class StagedBidController extends Controller
{
    public function __construct(
        private readonly LiveBidWriter $liveBidWriter
    ) {}

    public function index(Request $request)
    {
        $query = StagedBid::with(['source', 'subscriptionType', 'category', 'entity']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('solicitation_number', 'like', "%{$search}%")
                    ->orWhere('agency', 'like', "%{$search}%");
            });
        }

        $reviewStatus = $request->input('review_status', 'pending');
        if ($reviewStatus !== 'all') {
            $query->where('review_status', $reviewStatus);
        }

        if ($entryType = $request->input('entry_type')) {
            $query->where('entry_type', $entryType);
        }

        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        }

        $query->orderByDesc('created_at');

        $stagedBids = $query->paginate(25)->withQueryString();
        $categories = Category::orderBy('name')->get();

        $pendingBulkQuery = StagedBid::pending();
        if ($request->filled('entry_type')) {
            $pendingBulkQuery->where('entry_type', $request->input('entry_type'));
        }

        $counts = [
            'pending' => StagedBid::pending()->count(),
            'pending_bulk' => $pendingBulkQuery->count(),
            'approved' => StagedBid::approved()->count(),
            'rejected' => StagedBid::rejected()->count(),
        ];

        return Inertia::render('StagedBids/Index', [
            'stagedBids' => $stagedBids,
            'categories' => $categories,
            'counts' => $counts,
            'filters' => $request->only(['search', 'review_status', 'entry_type', 'category_id']),
            'approveProgress' => Cache::get('approve_all_progress'),
        ]);
    }

    public function show(StagedBid $stagedBid)
    {
        $stagedBid->load([
            'source', 'subscriptionType', 'category', 'categoryAlias',
            'entity', 'entityAlias', 'state', 'country', 'setasideCode',
            'purchasingAgent',
        ]);

        return Inertia::render('StagedBids/Show', [
            'stagedBid' => $stagedBid,
        ]);
    }

    public function approve(StagedBid $stagedBid)
    {
        if ($stagedBid->review_status !== 'pending') {
            return back()->with('error', 'This bid has already been reviewed.');
        }

        if ($this->liveBidWriter->isDuplicateInOracle($stagedBid)) {
            return back()->with('error', 'A bid with this solicitation number already exists in the live database.');
        }

        try {
            $this->liveBidWriter->writeBid($stagedBid);
            $stagedBid->update(['review_status' => 'approved']);
        } catch (\Exception $e) {
            Log::error('Failed to approve bid to Oracle', [
                'staged_bid_id' => $stagedBid->id,
                'error' => $e->getMessage(),
            ]);
            return back()->with('error', 'Failed to write bid to live database: ' . $e->getMessage());
        }

        return back()->with('success', 'Bid approved and pushed to live Oracle database.');
    }

    public function reject(StagedBid $stagedBid)
    {
        if ($stagedBid->review_status !== 'pending') {
            return back()->with('error', 'This bid has already been reviewed.');
        }

        $stagedBid->update(['review_status' => 'rejected']);

        return back()->with('success', 'Bid rejected.');
    }

    public function bulkApprove(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:staged_bids,id',
        ]);

        $ids = $request->input('ids');
        $pendingCount = StagedBid::whereIn('id', $ids)->pending()->count();

        if ($pendingCount === 0) {
            return back()->with('error', 'No pending bids to approve.');
        }

        dispatch(new ApproveAllBidsJob(ids: $ids));

        return back()->with('success', "Approval job dispatched for {$pendingCount} bid(s). Progress will appear on this page.");
    }

    public function bulkReject(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:staged_bids,id',
        ]);

        $count = StagedBid::whereIn('id', $request->input('ids'))
            ->pending()
            ->update(['review_status' => 'rejected']);

        return back()->with('success', "{$count} bid(s) rejected.");
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:staged_bids,id',
        ]);

        $count = StagedBid::whereIn('id', $request->input('ids'))
            ->pending()
            ->delete();

        return back()->with('success', "{$count} pending bid(s) permanently deleted.");
    }

    public function deleteAllPending(Request $request)
    {
        $query = StagedBid::pending();

        if ($entryType = $request->input('entry_type')) {
            $query->where('entry_type', $entryType);
        }

        $count = $query->delete();

        return back()->with('success', "{$count} pending bid(s) permanently deleted.");
    }

    public function triggerLoad(Request $request)
    {
        $request->validate([
            'date' => 'required|date_format:Y-m-d',
        ]);

        $date = $request->input('date');

        if (LoadedFboFeed::isFboDateLoaded($date)) {
            return back()->with('error', "Feed for {$date} is already loaded or currently loading.");
        }

        dispatch(new LoadFboFeedJob(singleDate: $date));

        return back()->with('success', "Feed load dispatched for {$date}. New bids will appear here shortly.");
    }

    public function approveAll(Request $request)
    {
        $progress = Cache::get('approve_all_progress');
        if ($progress && $progress['status'] === 'running') {
            return back()->with('error', 'An approval job is already running. Please wait for it to finish.');
        }

        $entryType = $request->input('entry_type');

        $query = StagedBid::pending();
        if ($entryType) {
            $query->where('entry_type', $entryType);
        }

        $pendingCount = $query->count();

        if ($pendingCount === 0) {
            return back()->with('error', 'No pending bids to approve.');
        }

        dispatch(new ApproveAllBidsJob(entryType: $entryType));

        return back()->with('success', "Approval job dispatched for {$pendingCount} bid(s). Progress will appear on this page.");
    }

    public function approveProgress()
    {
        return response()->json(Cache::get('approve_all_progress'));
    }
}
