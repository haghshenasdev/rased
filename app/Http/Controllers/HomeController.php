<?php

namespace App\Http\Controllers;

use App\Models\SourceItem;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request): \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
    {
        $query = SourceItem::query()
            ->with('source')
            ->latest('published_at');

        /*
         * جستجو
         */
        if ($request->filled('search')) {

            $search = trim(
                $request->input('search')
            );

            $query->where(function ($q) use ($search) {

                $q->where(
                    'title',
                    'like',
                    "%{$search}%"
                );

                $q->orWhere(
                    'matched_content',
                    'like',
                    "%{$search}%"
                );

                $q->orWhere(
                    'matched_keyword',
                    'like',
                    "%{$search}%"
                );
            });
        }

        /*
         * فیلتر زمانی
         */
        if ($request->get('period') === 'today') {

            $query->whereDate(
                'published_at',
                today()
            );
        }

        if ($request->get('period') === 'yesterday') {

            $query->whereDate(
                'published_at',
                today()->subDay()
            );
        }

        if ($request->get('period') === 'week') {

            $query->where(
                'published_at',
                '>=',
                now()->subDays(7)
            );
        }

        $items = $query
            ->paginate(20)
            ->withQueryString();

        return view(
            'home',
            compact('items')
        );
    }
}
