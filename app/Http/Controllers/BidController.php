<?php

namespace App\Http\Controllers;

use App\Models\Bid;
use App\Models\Category;
use App\Models\SubscriptionType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BidController extends Controller
{
    public function index(Request $request)
    {
        $query = Bid::with(['source', 'subscriptionType', 'category', 'entity', 'state']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('solicitation_number', 'like', "%{$search}%")
                    ->orWhere('agency', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            if ($status === 'active') {
                $query->active();
            } elseif ($status === 'expired') {
                $query->expired();
            } elseif ($status === 'review') {
                $query->where('needs_review', true);
            }
        }

        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($subscriptionTypeId = $request->input('subscription_type_id')) {
            $query->where('subscription_type_id', $subscriptionTypeId);
        }

        $sortField = $request->input('sort', 'created_at');
        $sortDir = $request->input('direction', 'desc');
        $query->orderBy($sortField, $sortDir);

        $bids = $query->paginate(25)->withQueryString();
        $categories = Category::orderBy('name')->get();
        $subscriptionTypes = SubscriptionType::all();

        return Inertia::render('Bids/Index', [
            'bids' => $bids,
            'categories' => $categories,
            'subscriptionTypes' => $subscriptionTypes,
            'filters' => $request->only(['search', 'status', 'category_id', 'subscription_type_id', 'sort', 'direction']),
        ]);
    }

    public function show(Bid $bid)
    {
        $bid->load(['source', 'subscriptionType', 'category', 'entity', 'state', 'purchasingAgent']);

        return Inertia::render('Bids/Show', [
            'bid' => $bid,
        ]);
    }
}
