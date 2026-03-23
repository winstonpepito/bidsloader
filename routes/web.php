<?php

use App\Http\Controllers\BidController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\FeedErrorController;
use App\Http\Controllers\LiveBidController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StagedBidController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return Inertia::render('Auth/Login', [
        'canResetPassword' => Route::has('password.request'),
        'status' => session('status'),
    ]);
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/bids', [BidController::class, 'index'])->name('bids.index');
    Route::get('/bids/{bid}', [BidController::class, 'show'])->name('bids.show');

    Route::get('/feeds', [FeedController::class, 'index'])->name('feeds.index');
    Route::get('/feeds/{feed}', [FeedController::class, 'show'])->name('feeds.show');
    Route::post('/feeds/trigger', [FeedController::class, 'triggerLoad'])->name('feeds.trigger');

    Route::get('/staged-bids', [StagedBidController::class, 'index'])->name('staged-bids.index');
    Route::post('/staged-bids/load', [StagedBidController::class, 'triggerLoad'])->name('staged-bids.load');
    Route::post('/staged-bids/bulk-approve', [StagedBidController::class, 'bulkApprove'])->name('staged-bids.bulk-approve');
    Route::post('/staged-bids/bulk-reject', [StagedBidController::class, 'bulkReject'])->name('staged-bids.bulk-reject');
    Route::post('/staged-bids/bulk-delete', [StagedBidController::class, 'bulkDelete'])->name('staged-bids.bulk-delete');
    Route::post('/staged-bids/delete-all-pending', [StagedBidController::class, 'deleteAllPending'])->name('staged-bids.delete-all-pending');
    Route::post('/staged-bids/approve-all', [StagedBidController::class, 'approveAll'])->name('staged-bids.approve-all');
    Route::get('/staged-bids/approve-progress', [StagedBidController::class, 'approveProgress'])->name('staged-bids.approve-progress');
    Route::get('/staged-bids/{stagedBid}', [StagedBidController::class, 'show'])->name('staged-bids.show');
    Route::post('/staged-bids/{stagedBid}/approve', [StagedBidController::class, 'approve'])->name('staged-bids.approve');
    Route::post('/staged-bids/{stagedBid}/reject', [StagedBidController::class, 'reject'])->name('staged-bids.reject');

    Route::get('/live-bids', [LiveBidController::class, 'index'])->name('live-bids.index');

    Route::get('/errors', [FeedErrorController::class, 'index'])->name('errors.index');
    Route::get('/errors/{error}', [FeedErrorController::class, 'show'])->name('errors.show');
    Route::delete('/errors/{error}', [FeedErrorController::class, 'destroy'])->name('errors.destroy');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
