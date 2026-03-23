<?php

namespace App\Http\Controllers;

use App\Jobs\LoadFboFeedJob;
use App\Models\FeedLoadLog;
use App\Models\LoadedFboFeed;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FeedController extends Controller
{
    public function index(Request $request)
    {
        $query = LoadedFboFeed::query();

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $feeds = $query->orderByDesc('fbo_date')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Feeds/Index', [
            'feeds' => $feeds,
            'filters' => $request->only(['status']),
        ]);
    }

    public function show(LoadedFboFeed $feed)
    {
        $logs = FeedLoadLog::where('loaded_fbo_feed_id', $feed->id)
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Feeds/Show', [
            'feed' => $feed,
            'logs' => $logs,
        ]);
    }

    public function triggerLoad(Request $request)
    {
        $request->validate([
            'mode' => 'required|in:lookback,date,date_range,file',
            'date' => 'nullable|date_format:Y-m-d',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d',
            'file_path' => 'nullable|string',
        ]);

        $mode = $request->input('mode');

        $job = match ($mode) {
            'lookback' => new LoadFboFeedJob(),
            'date' => new LoadFboFeedJob(singleDate: $request->input('date')),
            'date_range' => new LoadFboFeedJob(
                startDate: $request->input('start_date'),
                endDate: $request->input('end_date')
            ),
            'file' => new LoadFboFeedJob(filePath: $request->input('file_path')),
        };

        dispatch($job);

        return back()->with('success', 'Feed load job dispatched successfully.');
    }
}
