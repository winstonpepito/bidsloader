<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class LiveBidController extends Controller
{
    private const CONNECTION = 'oracle';
    private const TABLE_BID = 'BID';

    public function index(Request $request)
    {
        set_time_limit(300);

        $date = $request->input('date', now()->format('Y-m-d'));

        $baseWhere = fn ($q) => $q
            ->whereRaw("FEDDATE >= TO_DATE(?, 'YYYY-MM-DD')", [$date])
            ->whereRaw("FEDDATE < TO_DATE(?, 'YYYY-MM-DD') + 1", [$date]);

        $query = DB::connection(self::CONNECTION)
            ->table(self::TABLE_BID)
            ->where($baseWhere);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->whereRaw("UPPER(TITLE) LIKE UPPER(?)", ["%{$search}%"])
                    ->orWhereRaw("UPPER(SOLICITATIONNUMBER) LIKE UPPER(?)", ["%{$search}%"]);
            });
        }

        $query->orderByDesc(DB::raw('FEDDATE'));

        $paginator = $query->simplePaginate(25, [
            DB::raw('ID'),
            DB::raw('TITLE'),
            DB::raw('SOLICITATIONNUMBER'),
            DB::raw('THIRD_PARTY_IDENTIFIER'),
            DB::raw('URL'),
            DB::raw('EMAIL'),
            DB::raw('NAICSCODE'),
            DB::raw('FEDDATE'),
            DB::raw('ENDDATE'),
            DB::raw('NEEDS_REVIEW'),
            DB::raw('CREATED'),
            DB::raw('LAST_MODIFIED'),
        ]);

        $paginator->appends($request->only(['date', 'search']));

        $bids = collect($paginator->items())->map(fn ($row) => [
            'id' => $row->ID ?? $row->id ?? null,
            'title' => $row->TITLE ?? $row->title ?? null,
            'solicitation_number' => $row->SOLICITATIONNUMBER ?? $row->solicitationnumber ?? null,
            'third_party_identifier' => $row->THIRD_PARTY_IDENTIFIER ?? $row->third_party_identifier ?? null,
            'url' => $row->URL ?? $row->url ?? null,
            'email' => $row->EMAIL ?? $row->email ?? null,
            'naics_code' => $row->NAICSCODE ?? $row->naicscode ?? null,
            'fed_date' => $row->FEDDATE ?? $row->feddate ?? null,
            'end_date' => $row->ENDDATE ?? $row->enddate ?? null,
            'needs_review' => $row->NEEDS_REVIEW ?? $row->needs_review ?? 0,
            'created' => $row->CREATED ?? $row->created ?? null,
            'last_modified' => $row->LAST_MODIFIED ?? $row->last_modified ?? null,
        ]);

        return Inertia::render('LiveBids/Index', [
            'bids' => [
                'data' => $bids,
                'has_more' => $paginator->hasMorePages(),
                'prev_url' => $paginator->previousPageUrl(),
                'next_url' => $paginator->nextPageUrl(),
                'current_page' => $paginator->currentPage(),
            ],
            'filters' => [
                'date' => $date,
                'search' => $request->input('search', ''),
            ],
        ]);
    }
}
