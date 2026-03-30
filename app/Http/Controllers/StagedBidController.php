<?php

namespace App\Http\Controllers;

use App\Exceptions\FBOFeedLoaderException;
use App\Jobs\ApproveAllBidsJob;
use App\Jobs\LoadFboFeedJob;
use App\Models\Category;
use App\Models\LoadedFboFeed;
use App\Models\StagedBid;
use App\Services\FBOFeed\SamApiClient;
use App\Services\LiveBidWriter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
            $stagedBid->update(['review_status' => 'rejected']);

            return back()->with(
                'warning',
                'Bid rejected: a record with this solicitation number already exists in the live database.'
            );
        }

        try {
            $this->liveBidWriter->writeBid($stagedBid);
            $stagedBid->update(['review_status' => 'approved']);
        } catch (\Exception $e) {
            Log::error('Failed to approve bid to Oracle', [
                'staged_bid_id' => $stagedBid->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to write bid to live database: '.$e->getMessage());
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

    /**
     * Download raw SAM.gov search API JSON for a calendar day (no DB / staging).
     */
    public function downloadSamGovJson(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date_format:Y-m-d',
        ]);

        try {
            $client = app(SamApiClient::class);
            $payload = $client->fetchRawExportForDate(
                Carbon::parse($validated['date'])->startOfDay()
            );
        } catch (FBOFeedLoaderException $e) {
            return redirect()
                ->route('staged-bids.index')
                ->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('SAM.gov JSON export failed', ['exception' => $e]);

            return redirect()
                ->route('staged-bids.index')
                ->with('error', 'Failed to download SAM.gov JSON: '.$e->getMessage());
        }

        $filename = 'sam-gov-opportunities-'.$validated['date'].'.json';

        return response()->streamDownload(
            function () use ($payload) {
                echo json_encode(
                    $payload,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
                );
            },
            $filename,
            ['Content-Type' => 'application/json; charset=UTF-8']
        );
    }

    /**
     * Start a browser-orchestrated SAM.gov fetch session (SPA will call fetch repeatedly).
     */
    public function samBrowserStart(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date_format:Y-m-d',
        ]);

        $date = $validated['date'];

        if (LoadedFboFeed::isFboDateLoaded($date)) {
            return response()->json([
                'message' => "Feed for {$date} is already loaded or loading.",
            ], 422);
        }

        $sessionId = (string) Str::uuid();

        $payload = [
            'user_id' => $request->user()->id,
            'date' => $date,
            'opportunities' => [],
            'next_offset' => 0,
            'total_records' => null,
            'complete' => false,
        ];

        Storage::disk('local')->put(
            'sam-browser/'.$sessionId.'.json',
            json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE)
        );

        return response()->json([
            'session_id' => $sessionId,
            'inter_page_delay_ms' => (int) config('fbo.sam_browser_inter_page_delay_ms', 400),
            'page_size' => (int) config('fbo.sam_api_page_size', 1000),
        ]);
    }

    /**
     * Fetch the next page from SAM.gov (server-side proxy with browser-like headers) and append to the session file.
     */
    public function samBrowserFetch(Request $request)
    {
        $validated = $request->validate([
            'session_id' => 'required|uuid',
        ]);

        $sessionId = $validated['session_id'];
        $relative = 'sam-browser/'.$sessionId.'.json';

        if (! Storage::disk('local')->exists($relative)) {
            return response()->json(['message' => 'Session expired or invalid.'], 404);
        }

        $data = json_decode(Storage::disk('local')->get($relative), true) ?? [];

        if (($data['user_id'] ?? null) !== $request->user()->id) {
            abort(403);
        }

        if (! empty($data['complete'])) {
            return response()->json([
                'done' => true,
                'totalRecords' => (int) ($data['total_records'] ?? 0),
                'loadedCount' => count($data['opportunities'] ?? []),
            ]);
        }

        $serverDelay = (int) config('fbo.sam_browser_server_delay_ms', 350);
        if ($serverDelay > 0 && ($data['next_offset'] ?? 0) > 0) {
            usleep($serverDelay * 1000);
        }

        $date = Carbon::parse($data['date'])->startOfDay();
        $offset = (int) ($data['next_offset'] ?? 0);

        try {
            $client = app(SamApiClient::class);
            $page = $client->fetchSearchPageForDate($date, $offset);
        } catch (FBOFeedLoaderException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $opps = $page['opportunitiesData'] ?? [];
        $totalRecords = (int) ($page['totalRecords'] ?? 0);
        $data['opportunities'] = array_merge($data['opportunities'] ?? [], $opps);
        $data['total_records'] = $totalRecords;
        $pageSize = (int) config('fbo.sam_api_page_size', 1000);
        $data['next_offset'] = $offset + $pageSize;
        $data['complete'] = $totalRecords === 0 || $data['next_offset'] >= $totalRecords;

        Storage::disk('local')->put($relative, json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE));

        return response()->json([
            'done' => $data['complete'],
            'totalRecords' => $totalRecords,
            'loadedCount' => count($data['opportunities']),
            'pageFetched' => count($opps),
            'nextOffset' => $data['next_offset'],
            'inter_page_delay_ms' => (int) config('fbo.sam_browser_inter_page_delay_ms', 400),
        ]);
    }

    /**
     * Queue staging load from the completed session file (same pipeline as API load).
     */
    public function samBrowserFinish(Request $request)
    {
        $validated = $request->validate([
            'session_id' => 'required|uuid',
        ]);

        $sessionId = $validated['session_id'];
        $relative = 'sam-browser/'.$sessionId.'.json';

        if (! Storage::disk('local')->exists($relative)) {
            return back()->with('error', 'Session expired or invalid.');
        }

        $data = json_decode(Storage::disk('local')->get($relative), true) ?? [];

        if (($data['user_id'] ?? null) !== $request->user()->id) {
            abort(403);
        }

        if (empty($data['complete'])) {
            return back()->with('error', 'SAM.gov fetch is not complete. Fetch all pages first.');
        }

        $dateStr = $data['date'] ?? '';

        if (LoadedFboFeed::isFboDateLoaded($dateStr)) {
            Storage::disk('local')->delete($relative);

            return back()->with('error', "Feed for {$dateStr} is already loaded or loading.");
        }

        dispatch(new LoadFboFeedJob(
            singleDate: $dateStr,
            samBrowserSessionId: $sessionId,
        ));

        return back()->with('success', "Staging load queued for {$dateStr} (browser-paced SAM.gov fetch). New bids will appear shortly.");
    }
}
