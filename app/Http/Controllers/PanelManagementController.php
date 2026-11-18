<?php

namespace App\Http\Controllers;

use App\Models\BaleSubscriber;
use App\Models\BlacklistKeyword;
use App\Models\Category;
use App\Models\Keyword;
use App\Models\MonitoringLog;
use App\Models\QueueJob;
use App\Models\Source;
use App\Models\SourceItem;
use App\Jobs\SendSelectedNewsToBaleJob;
use App\Services\Monitoring\Readers\SourceReaderFactory;
use App\Jobs\CheckSourceJob;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class PanelManagementController extends Controller
{
    private const SECTIONS = [
        'sources' => ['title' => 'منابع خبری', 'model' => Source::class, 'editable' => true],
        'keywords' => ['title' => 'کلیدواژه‌ها', 'model' => Keyword::class, 'editable' => true],
        'blacklist-keywords' => ['title' => 'واژه‌های مسدود', 'model' => BlacklistKeyword::class, 'editable' => true],
        'categories' => ['title' => 'دسته‌بندی‌ها', 'model' => Category::class, 'editable' => true],
        'bale-subscribers' => ['title' => 'مشترکان بله', 'model' => BaleSubscriber::class, 'editable' => true],
        'source-items' => ['title' => 'مدیریت خبرها', 'model' => SourceItem::class, 'editable' => true],
        'monitoring-logs' => ['title' => 'کنسول لاگ‌ها', 'model' => MonitoringLog::class, 'editable' => false],
        'queue-jobs' => ['title' => 'صف پردازش', 'model' => QueueJob::class, 'editable' => false],
    ];

    public function index(Request $request, string $section)
    {
        $config = $this->section($section);
        $query = match ($section) {
            'sources' => Source::query()->with(['parent', 'defaultCategory'])->latest('id'),
            'source-items' => SourceItem::query()->with(['source', 'category'])->latest('published_at'),
            'monitoring-logs' => MonitoringLog::query()->with('source')->latest('id'),
            'bale-subscribers' => BaleSubscriber::query()->latest('created_at'),
            'queue-jobs' => QueueJob::query()->orderByDesc('id'),
            'keywords' => Keyword::query()->orderByDesc('priority')->orderBy('word'),
            default => $config['model']::query()->latest('id'),
        };

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search, $section) {
                match ($section) {
                    'sources' => $builder->where('name', 'like', "%{$search}%")
                        ->orWhere('url', 'like', "%{$search}%"),
                    'keywords', 'blacklist-keywords' => $builder->where('word', 'like', "%{$search}%"),
                    'categories' => $builder->where('name', 'like', "%{$search}%"),
                    'bale-subscribers' => $builder->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%"),
                    'source-items' => $builder->where('title', 'like', "%{$search}%")
                        ->orWhere('matched_keyword', 'like', "%{$search}%"),
                    'monitoring-logs' => $builder->where('message', 'like', "%{$search}%")
                        ->orWhere('error_type', 'like', "%{$search}%"),
                    'queue-jobs' => $builder->where('queue', 'like', "%{$search}%")
                        ->orWhere('payload', 'like', "%{$search}%"),
                };
            });
        }

        if ($section === 'monitoring-logs') {
            if ($request->filled('status')) $query->where('status', $request->query('status'));
            if ($request->filled('source')) $query->where('source_id', $request->integer('source'));
            if ($request->boolean('bale')) $query->where('error_type', 'like', 'BALE_SEND_%');
        }

        $records = $query->paginate(20)->withQueryString();

        return view('panel.management.index', [
            'section' => $section,
            'config' => $config,
            'records' => $records,
            'sources' => $section === 'monitoring-logs' ? Source::orderBy('name')->get(['id', 'name']) : collect(),
        ]);
    }

    public function create(string $section)
    {
        $config = $this->section($section);
        abort_unless($config['editable'], 404);

        return $this->formView($section, $config, null);
    }

    public function edit(string $section, int $record)
    {
        $config = $this->section($section);
        abort_unless($config['editable'], 404);
        $model = $config['model'];
        $item = $model::query()->findOrFail($record);

        return $this->formView($section, $config, $item);
    }

    public function store(Request $request, string $section)
    {
        $config = $this->section($section);
        abort_unless($config['editable'], 404);
        $data = $this->validatedData($request, $section);
        $config['model']::query()->create($data);

        return redirect()->route('panel.manage.index', $section)->with('success', 'رکورد جدید با موفقیت ایجاد شد.');
    }

    public function update(Request $request, string $section, int $record)
    {
        $config = $this->section($section);
        abort_unless($config['editable'], 404);
        $model = $config['model'];
        $item = $model::query()->findOrFail($record);
        $data = $this->validatedData($request, $section, $item->id);
        $item->update($data);

        return redirect()->route('panel.manage.index', $section)->with('success', 'تغییرات ذخیره شد.');
    }

    public function destroy(string $section, int $record)
    {
        $config = $this->section($section);
        abort_unless($config['editable'], 404);
        $model = $config['model'];
        $item = $model::query()->findOrFail($record);
        $item->delete();

        return redirect()->route('panel.manage.index', $section)->with('success', 'رکورد حذف شد.');
    }

    public function checkSource(int $record)
    {
        $source = Source::query()->findOrFail($record);
        CheckSourceJob::dispatch($source->id);

        return back()->with('success', 'بررسی «'.$source->name.'» در صف قرار گرفت.');
    }

    public function testSource(int $record, SourceReaderFactory $readerFactory)
    {
        $source = Source::query()->findOrFail($record);

        try {
            $items = $readerFactory->make($source)->read($source);
            $preview = collect(array_slice($items, 0, 5))->map(fn ($item) => $item->title)->values()->all();

            return back()->with('success', 'تست خواندن «'.$source->name.'» موفق بود؛ '.count($items).' مورد خوانده شد.')
                ->with('source_test_preview', $preview);
        } catch (Throwable $exception) {
            return back()->with('error', 'تست خواندن منبع ناموفق بود: '.$exception->getMessage());
        }
    }

    public function sendForm(int $record)
    {
        $item = SourceItem::query()->findOrFail($record);
        $subscribers = BaleSubscriber::query()->where('is_active', true)->whereNotNull('chat_id')->orderBy('name')->get();

        return view('panel.management.send-news', compact('item', 'subscribers'));
    }

    public function sendToSubscribers(Request $request, int $record)
    {
        $item = SourceItem::query()->findOrFail($record);
        $data = $request->validate(['subscriber_ids' => ['required', 'array', 'min:1'], 'subscriber_ids.*' => ['integer', 'exists:bale_subscribers,id']]);
        $subscribers = BaleSubscriber::query()->whereIn('id', $data['subscriber_ids'])->where('is_active', true)->whereNotNull('chat_id')->pluck('id')->all();
        if (count($subscribers) !== count(array_unique(array_map('intval', $data['subscriber_ids'])))) {
            return back()->withErrors(['subscriber_ids' => 'فقط مشترکان فعال و متصل قابل انتخاب هستند.'])->withInput();
        }

        SendSelectedNewsToBaleJob::dispatch([$item->id], $subscribers);

        return redirect()->route('panel.manage.index', 'source-items')->with('success', 'ارسال خبر به '.count($subscribers).' مشترک در صف قرار گرفت.');
    }

    private function formView(string $section, array $config, mixed $record)
    {
        return view('panel.management.form', [
            'section' => $section,
            'config' => $config,
            'record' => $record,
            'sources' => Source::query()->orderBy('name')->get(['id', 'name']),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    private function section(string $section): array
    {
        abort_unless(isset(self::SECTIONS[$section]), 404);
        return self::SECTIONS[$section];
    }

    private function validatedData(Request $request, string $section, ?int $id = null): array
    {
        return match ($section) {
            'sources' => $this->sourceData($request, $id),
            'keywords' => $request->validate([
                'word' => ['required', 'string', 'max:500', Rule::unique('keywords', 'word')->ignore($id)],
                'priority' => ['nullable', 'integer'], 'is_active' => ['nullable', 'boolean'],
            ]) + ['is_active' => $request->boolean('is_active')],
            'blacklist-keywords' => $request->validate([
                'word' => ['required', 'string', 'max:500', Rule::unique('blacklist_keywords', 'word')->ignore($id)],
                'is_active' => ['nullable', 'boolean'],
            ]) + ['is_active' => $request->boolean('is_active')],
            'categories' => $this->categoryData($request, $id),
            'bale-subscribers' => $request->validate([
                'name' => ['required', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:30'],
                'is_active' => ['nullable', 'boolean'],
            ]) + ['is_active' => $request->boolean('is_active')],
            'source-items' => $request->validate([
                'title' => ['required', 'string', 'max:10000'], 'source_id' => ['required', 'exists:sources,id'],
                'url' => ['nullable', 'url', 'max:4000'], 'featured_image_url' => ['nullable', 'url', 'max:4000'],
                'content' => ['nullable', 'string'], 'matched_content' => ['nullable', 'string'],
                'matched_keyword' => ['nullable', 'string', 'max:500'], 'category_id' => ['nullable', 'exists:categories,id'],
                'published_at' => ['nullable', 'date'], 'is_repost' => ['nullable', 'boolean'],
            ]) + ['is_repost' => $request->boolean('is_repost')],
            default => abort(404),
        };
    }

    private function sourceData(Request $request, ?int $id = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['rss', 'google', 'eitaa', 'html', 'javascript', 'browser', 'farsnews', 'farsnews_sitemap', 'aparat_sitemap'])],
            'url' => ['nullable', 'url', 'required_if:type,html,javascript,browser,farsnews', 'max:4000'],
            'identifier' => ['nullable', 'string', 'required_if:type,eitaa', 'max:255'],
            'parent_id' => ['nullable', 'exists:sources,id', Rule::notIn(array_filter([$id]))], 'default_category_id' => ['nullable', 'exists:categories,id'],
            'profile_image_url' => ['nullable', 'url', 'max:4000'], 'profile_image_path' => ['nullable', 'string', 'max:1000'], 'feed_urls' => ['nullable', 'string'],
            'google_query' => ['nullable', 'string', 'required_if:type,google', 'max:500'], 'google_count' => ['nullable', 'integer', 'min:1', 'max:10'],
            'farsnews_authors' => ['nullable', 'string'], 'max_items' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'auto_categorize' => ['nullable', 'boolean'], 'ignore_link_keyword' => ['nullable', 'boolean'], 'is_active' => ['nullable', 'boolean'],
        ]);

        $settings = array_filter([
            'feed_urls' => array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) ($data['feed_urls'] ?? '')) ?: []))),
            'query' => trim((string) ($data['google_query'] ?? '')),
            'count' => isset($data['google_count']) ? (int) $data['google_count'] : null,
            'farsnews_authors' => trim((string) ($data['farsnews_authors'] ?? '')),
            'max_items' => isset($data['max_items']) ? (int) $data['max_items'] : null,
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);

        unset($data['feed_urls'], $data['google_query'], $data['google_count'], $data['farsnews_authors'], $data['max_items']);
        return $data + [
            'settings' => $settings,
            'auto_categorize' => $request->boolean('auto_categorize'),
            'ignore_link_keyword' => $request->boolean('ignore_link_keyword'),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function categoryData(Request $request, ?int $id = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($id)],
            'keywords_text' => ['nullable', 'string'], 'is_active' => ['nullable', 'boolean'],
        ]);
        return [
            'name' => $data['name'],
            'keywords' => array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) ($data['keywords_text'] ?? '')) ?: []))),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
