<?php

namespace App\Http\Controllers;

use App\Models\Source;
use App\Models\SourceItem;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $query = SourceItem::query()
            ->with(['source.parent', 'category', 'original'])
            ->latest('published_at');

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%")
                    ->orWhere('matched_keyword', 'like', "%{$search}%");
            });
        }

        $period = $request->get('period');
        $now = now('Asia/Tehran');
        if ($period === 'today') {
            $query->whereBetween('published_at', [$now->copy()->startOfDay(), $now->copy()->endOfDay()]);
        } elseif ($period === 'yesterday') {
            $day = $now->copy()->subDay();
            $query->whereBetween('published_at', [$day->startOfDay(), $day->endOfDay()]);
        } elseif ($period === 'week') {
            $query->where('published_at', '>=', $now->copy()->subDays(7));
        }

        $items = $query->paginate(24)->withQueryString();

        $sources = Source::query()
            ->where('is_active', true)
            ->with('parent')
            ->orderBy('parent_id')
            ->orderBy('name')
            ->get();

        return view('home', compact('items', 'sources'));
    }
}
