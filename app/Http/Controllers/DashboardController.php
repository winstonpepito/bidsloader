<?php

namespace App\Http\Controllers;

use App\Models\Bid;
use App\Models\FboFeedError;
use App\Models\LoadedFboFeed;
use App\Models\StagedBid;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $totalBids = Bid::count();
        $activeBids = Bid::active()->count();
        $expiredBids = Bid::expired()->count();
        $needsReview = Bid::where('needs_review', true)->count();

        $totalFeeds = LoadedFboFeed::count();
        $completedFeeds = LoadedFboFeed::where('status', 'completed')->count();
        $failedFeeds = LoadedFboFeed::where('status', 'failed')->count();

        $totalErrors = FboFeedError::count();
        $pendingStaged = StagedBid::pending()->count();

        $recentFeeds = LoadedFboFeed::orderByDesc('fbo_date')
            ->limit(10)
            ->get();

        $recentBids = Bid::with(['source', 'subscriptionType', 'category'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $bidsByDay = Bid::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get();

        return Inertia::render('Dashboard', [
            'stats' => [
                'totalBids' => $totalBids,
                'activeBids' => $activeBids,
                'expiredBids' => $expiredBids,
                'needsReview' => $needsReview,
                'totalFeeds' => $totalFeeds,
                'completedFeeds' => $completedFeeds,
                'failedFeeds' => $failedFeeds,
                'totalErrors' => $totalErrors,
                'pendingStaged' => $pendingStaged,
            ],
            'recentFeeds' => $recentFeeds,
            'recentBids' => $recentBids,
            'bidsByDay' => $bidsByDay,
        ]);
    }
}
