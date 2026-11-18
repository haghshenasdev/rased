<?php

namespace App\Http\Controllers;

use App\Models\BaleSubscriber;
use App\Models\Category;
use App\Models\Keyword;
use App\Models\MonitoringLog;
use App\Models\MonitoringRun;
use App\Models\Source;
use App\Models\SourceItem;
use Illuminate\Http\Request;

class PanelController extends Controller
{
    public function index(Request $request)
    {
        $days = (int) $request->query('range', 7);
        $days = in_array($days, [7, 30], true) ? $days : 7;
        $now = now('Asia/Tehran');
        $start = $now->copy()->subDays($days - 1)->startOfDay();
        $end = $now->copy()->endOfDay();

        // Build day buckets from the requested range, oldest first.
        $daily = collect(range($days - 1, 0))->map(fn (int $offset) => $now->copy()->subDays($offset));
        $countsByDate = SourceItem::query()
            ->whereBetween('published_at', [$start, $end])
            ->selectRaw('DATE(published_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $chart = $daily->map(fn ($day) => [
            'label' => $day->format('m/d'),
            'count' => (int) ($countsByDate[$day->toDateString()] ?? 0),
        ])->values();

        $topSources = SourceItem::query()
            ->selectRaw('source_id, COUNT(*) as total')
            ->whereBetween('published_at', [$start, $end])
            ->groupBy('source_id')
            ->orderByDesc('total')
            ->limit(6)
            ->with('source:id,name')
            ->get()
            ->map(fn ($row) => ['name' => $row->source?->name ?? 'نامشخص', 'count' => (int) $row->total]);

        $topKeywords = SourceItem::query()
            ->selectRaw('matched_keyword, COUNT(*) as total')
            ->whereBetween('published_at', [$start, $end])
            ->whereNotNull('matched_keyword')
            ->groupBy('matched_keyword')
            ->orderByDesc('total')
            ->limit(6)
            ->get()
            ->map(fn ($row) => ['name' => $row->matched_keyword, 'count' => (int) $row->total]);

        $stats = [
            'news' => SourceItem::query()->whereBetween('published_at', [$start, $end])->count(),
            'today' => SourceItem::query()->whereBetween('published_at', [$now->copy()->startOfDay(), $end])->count(),
            'sources' => Source::query()->where('is_active', true)->count(),
            'keywords' => Keyword::query()->where('is_active', true)->count(),
            'reposts' => SourceItem::query()->whereBetween('published_at', [$start, $end])->where('is_repost', true)->count(),
            'errors' => MonitoringLog::query()->where('status', 'failed')->where('created_at', '>=', $start)->count(),
            'subscribers' => BaleSubscriber::query()->where('is_active', true)->count(),
        ];

        $recentNews = SourceItem::query()->with(['source', 'category'])->latest('published_at')->limit(6)->get();
        $latestRun = MonitoringRun::query()->latest('started_at')->first();

        return view('panel.index', compact('days', 'stats', 'chart', 'topSources', 'topKeywords', 'recentNews', 'latestRun'));
    }

    public function news(Request $request)
    {
        $query = SourceItem::query()->with(['source', 'category', 'original'])->latest('published_at');

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%")
                    ->orWhere('matched_keyword', 'like', "%{$search}%");
            });
        }

        $period = $request->query('period');
        $now = now('Asia/Tehran');
        if ($period === 'today') {
            $query->whereBetween('published_at', [$now->copy()->startOfDay(), $now->copy()->endOfDay()]);
        } elseif ($period === 'yesterday') {
            $day = $now->copy()->subDay();
            $query->whereBetween('published_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()]);
        } elseif ($period === 'week') {
            $query->where('published_at', '>=', $now->copy()->subDays(7));
        }

        if ($request->filled('source')) $query->where('source_id', $request->integer('source'));
        if ($request->filled('category')) $query->where('category_id', $request->integer('category'));

        $items = $query->paginate(18)->withQueryString();
        $sources = Source::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $categories = Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('panel.news', compact('items', 'sources', 'categories'));
    }
}
