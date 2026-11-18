@extends('panel.layout')
@section('title', $config['title'])
@section('crumb', $config['title'])
@section('heading', $config['title'])
@section('content')
@php($sourceTypeLabels = ['rss' => 'خوراک RSS', 'google' => 'جست‌وجوی گوگل', 'eitaa' => 'کانال ایتا', 'html' => 'صفحه HTML', 'javascript' => 'صفحه JavaScript', 'browser' => 'مرورگر', 'farsnews' => 'خبرگزاری فارس', 'farsnews_sitemap' => 'نقشه سایت فارس', 'aparat_sitemap' => 'نقشه سایت آپارات'])
<section class="space-y-5">
  <div class="glass rounded-2xl p-5 sm:p-6"><div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"><div><span class="text-xs font-black text-emerald-700">مدیریت سامانه</span><h2 class="mt-1 text-xl font-black">{{ $config['title'] }}</h2><p class="mt-1 text-sm text-slate-500">{{ number_format($records->total()) }} رکورد</p></div>
    <div class="flex flex-wrap gap-2">@if($config['editable'])<a href="{{ route('panel.manage.create', $section) }}" class="rounded-xl gradient-bg px-4 py-2.5 text-sm font-extrabold text-white">افزودن مورد جدید +</a>@endif
      @if($section==='sources')<form method="POST" action="{{ route('panel.monitoring.run') }}" onsubmit="return confirm('بررسی همه‌ی منابع فعال در صف قرار بگیرد؟')">@csrf<button class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-bold text-emerald-800">شروع پایش همه منابع</button></form>@endif
    </div>
  </div>
  <form method="GET" class="grid gap-2 sm:grid-cols-[minmax(220px,1fr)_auto]">
    <label class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3"><span class="text-slate-400">⌕</span><input name="search" value="{{ request('search') }}" placeholder="جست‌وجو در جدول..." class="w-full bg-transparent py-3 text-sm outline-none"></label>
    @if($section==='monitoring-logs')<div class="flex flex-wrap gap-2"><select name="status" class="rounded-xl border border-slate-200 bg-white px-3 text-sm"><option value="">همه وضعیت‌ها</option><option value="success" @selected(request('status')==='success')>موفق</option><option value="failed" @selected(request('status')==='failed')>ناموفق</option></select><select name="source" class="rounded-xl border border-slate-200 bg-white px-3 text-sm"><option value="">همه‌ی منابع</option>@foreach($sources as $source)<option value="{{ $source->id }}" @selected((string)request('source')===(string)$source->id)>{{ $source->name }}</option>@endforeach</select><label class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold"><input type="checkbox" name="bale" value="1" @checked(request()->boolean('bale'))>ارسال بله</label><button class="rounded-xl gradient-bg px-4 py-2 text-sm font-bold text-white">فیلتر</button></div>@else<button class="rounded-xl gradient-bg px-5 py-3 text-sm font-bold text-white">جست‌وجو</button>@endif
  </form></div>
  @if(session('success'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">{{ session('success') }}@if(session('source_test_preview'))<ul class="mt-2 list-inside list-disc text-xs font-normal">@foreach(session('source_test_preview') as $title)<li>{{ $title }}</li>@endforeach</ul>@endif</div>@endif
  @if(session('error'))<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-800">{{ session('error') }}</div>@endif
  <div class="glass overflow-hidden rounded-2xl"><div class="overflow-x-auto"><table class="w-full min-w-[760px] text-right text-sm"><thead class="bg-slate-50/80 text-[11px] font-black text-slate-500"><tr>
    @switch($section)
      @case('sources')<th class="px-4 py-4">منبع</th><th class="px-4 py-4">نوع</th><th class="px-4 py-4">والد / دسته</th><th class="px-4 py-4">پایش</th><th class="px-4 py-4">آخرین بررسی</th><th class="px-4 py-4">عملیات</th>@break
      @case('keywords')<th class="px-4 py-4">کلیدواژه</th><th class="px-4 py-4">اولویت</th><th class="px-4 py-4">وضعیت</th><th class="px-4 py-4">تاریخ ثبت</th><th class="px-4 py-4">عملیات</th>@break
      @case('blacklist-keywords')<th class="px-4 py-4">واژه‌ی مسدود</th><th class="px-4 py-4">وضعیت</th><th class="px-4 py-4">تاریخ ثبت</th><th class="px-4 py-4">عملیات</th>@break
      @case('categories')<th class="px-4 py-4">دسته</th><th class="px-4 py-4">کلیدواژه‌های دسته</th><th class="px-4 py-4">وضعیت</th><th class="px-4 py-4">عملیات</th>@break
      @case('bale-subscribers')<th class="px-4 py-4">مشترک</th><th class="px-4 py-4">تماس</th><th class="px-4 py-4">توکن اتصال</th><th class="px-4 py-4">شناسه بله</th><th class="px-4 py-4">ارسال</th><th class="px-4 py-4">عملیات</th>@break
      @case('source-items')<th class="px-4 py-4">عنوان خبر</th><th class="px-4 py-4">منبع</th><th class="px-4 py-4">کلیدواژه / دسته</th><th class="px-4 py-4">انتشار</th><th class="px-4 py-4">عملیات</th>@break
      @case('monitoring-logs')<th class="px-4 py-4">زمان</th><th class="px-4 py-4">منبع / عملیات</th><th class="px-4 py-4">وضعیت / نوع</th><th class="px-4 py-4">پیام</th><th class="px-4 py-4">خبر</th>@break
      @case('queue-jobs')<th class="px-4 py-4">شناسه</th><th class="px-4 py-4">شرح پردازش</th><th class="px-4 py-4">صف</th><th class="px-4 py-4">تلاش</th><th class="px-4 py-4">وضعیت / زمان</th>@break
    @endswitch
  </tr></thead><tbody class="divide-y divide-slate-100">
    @forelse($records as $item)
    <tr class="align-top hover:bg-white/70">
      @switch($section)
        @case('sources')
          <td class="px-4 py-4"><b class="block">{{ $item->name }}</b><a href="{{ $item->url }}" target="_blank" rel="noopener" class="mt-1 block max-w-[260px] truncate text-xs text-sky-700">{{ $item->url ?: 'آدرس ثبت نشده' }}</a></td><td class="px-4 py-4"><span class="rounded-lg bg-sky-50 px-2 py-1 text-xs font-bold text-sky-700">{{ $sourceTypeLabels[$item->type] ?? $item->type }}</span></td><td class="px-4 py-4 text-xs text-slate-600">{{ $item->parent?->name ?? '—' }}<span class="mt-1 block text-slate-400">{{ $item->defaultCategory?->name ?? 'بدون دسته پیش‌فرض' }}</span></td><td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $item->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $item->is_active ? 'فعال' : 'متوقف' }}</span></td><td class="px-4 py-4 text-xs text-slate-500">{{ $item->last_read_at ? \Morilog\Jalali\Jalalian::fromCarbon($item->last_read_at->copy()->setTimezone('Asia/Tehran'))->format('Y/m/d H:i') : 'هنوز اجرا نشده' }}</td><td class="px-4 py-4">@include('panel.management.actions',['section'=>$section,'item'=>$item])</td>
          @break
        @case('keywords')
          <td class="px-4 py-4 font-extrabold">{{ $item->word }}</td><td class="px-4 py-4">{{ $item->priority ?? 0 }}</td><td class="px-4 py-4">@include('panel.management.status',['active'=>$item->is_active])</td><td class="px-4 py-4 text-xs text-slate-500">{{ $item->created_at ? \Morilog\Jalali\Jalalian::fromCarbon($item->created_at->copy()->setTimezone('Asia/Tehran'))->format('Y/m/d H:i') : '—' }}</td><td class="px-4 py-4">@include('panel.management.actions',['section'=>$section,'item'=>$item])</td>
          @break
        @case('blacklist-keywords')
          <td class="px-4 py-4 font-extrabold">{{ $item->word }}</td><td class="px-4 py-4">@include('panel.management.status',['active'=>$item->is_active])</td><td class="px-4 py-4 text-xs text-slate-500">{{ $item->created_at ? \Morilog\Jalali\Jalalian::fromCarbon($item->created_at->copy()->setTimezone('Asia/Tehran'))->format('Y/m/d H:i') : '—' }}</td><td class="px-4 py-4">@include('panel.management.actions',['section'=>$section,'item'=>$item])</td>
          @break
        @case('categories')
          <td class="px-4 py-4 font-extrabold">{{ $item->name }}</td><td class="max-w-md px-4 py-4 text-xs leading-6 text-slate-500">{{ is_array($item->keywords) ? implode('، ', $item->keywords) : '—' }}</td><td class="px-4 py-4">@include('panel.management.status',['active'=>$item->is_active])</td><td class="px-4 py-4">@include('panel.management.actions',['section'=>$section,'item'=>$item])</td>
          @break
        @case('bale-subscribers')
          <td class="px-4 py-4"><b>{{ $item->name }}</b><small class="mt-1 block text-xs text-slate-400">{{ $item->connected_at ? \Morilog\Jalali\Jalalian::fromCarbon($item->connected_at->copy()->setTimezone('Asia/Tehran'))->format('Y/m/d H:i') : 'اتصال انجام نشده' }}</small></td><td class="px-4 py-4 text-xs">{{ $item->phone ?: '—' }}</td><td class="px-4 py-4 font-mono text-xs">{{ $item->token }}</td><td class="px-4 py-4 text-xs">{{ $item->chat_id ?: 'متصل نشده' }}</td><td class="px-4 py-4">@include('panel.management.status',['active'=>$item->is_active])</td><td class="px-4 py-4">@include('panel.management.actions',['section'=>$section,'item'=>$item])</td>
          @break
        @case('source-items')
          <td class="px-4 py-4"><a href="{{ route('news.show',$item) }}" class="font-extrabold hover:text-emerald-700">{{ \Illuminate\Support\Str::limit($item->title, 90) }}</a><small class="mt-1 block text-xs text-slate-400">{{ $item->is_repost ? 'بازنشر' : 'مستقل' }}</small></td><td class="px-4 py-4 text-xs">{{ $item->source?->name ?? '—' }}</td><td class="px-4 py-4 text-xs">{{ $item->matched_keyword ?: '—' }}<small class="mt-1 block text-slate-400">{{ $item->category?->name ?? 'بدون دسته' }}</small></td><td class="px-4 py-4 text-xs text-slate-500">{{ $item->published_at ? \Morilog\Jalali\Jalalian::fromCarbon($item->published_at->copy()->setTimezone('Asia/Tehran'))->format('Y/m/d H:i') : '—' }}</td><td class="px-4 py-4">@include('panel.management.actions',['section'=>$section,'item'=>$item])</td>
          @break
        @case('monitoring-logs')
          <td class="whitespace-nowrap px-4 py-4 text-xs text-slate-500">{{ $item->created_at ? \Morilog\Jalali\Jalalian::fromCarbon($item->created_at->copy()->setTimezone('Asia/Tehran'))->format('Y/m/d H:i:s') : '—' }}</td><td class="px-4 py-4"><b>{{ $item->source?->name ?? '—' }}</b><small class="mt-1 block text-xs text-slate-400">{{ data_get($item->context,'operation')==='bale_send' ? 'ارسال بله' : 'خواندن منبع' }}</small></td><td class="px-4 py-4"><span class="rounded-lg px-2 py-1 text-xs font-bold {{ $item->status==='failed' ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700' }}">{{ $item->status==='failed' ? 'ناموفق' : 'موفق' }}</span><small class="mt-2 block text-[10px] text-slate-400">{{ $item->error_type }}</small></td><td class="max-w-md whitespace-normal px-4 py-4 text-xs leading-6 text-slate-600" title="{{ $item->message }}">{{ \Illuminate\Support\Str::limit($item->message, 180) }}</td><td class="max-w-xs px-4 py-4 text-xs">{{ data_get($item->context,'title','—') }}</td>
          @break
        @case('queue-jobs')
          <td class="px-4 py-4 font-mono text-xs">#{{ $item->id }}</td><td class="max-w-lg whitespace-normal px-4 py-4 text-xs leading-6">{{ $item->description() }}</td><td class="px-4 py-4"><span class="rounded-lg bg-sky-50 px-2 py-1 text-xs font-bold text-sky-700">{{ $item->queue }}</span></td><td class="px-4 py-4">{{ $item->attempts }}</td><td class="px-4 py-4 text-xs text-slate-500">{{ $item->reserved_at ? \Morilog\Jalali\Jalalian::fromCarbon(\Carbon\Carbon::createFromTimestamp($item->reserved_at)->timezone('Asia/Tehran'))->format('Y/m/d H:i') : 'در انتظار اجرا' }}<small class="mt-1 block">قابل اجرا: {{ \Morilog\Jalali\Jalalian::fromCarbon(\Carbon\Carbon::createFromTimestamp($item->available_at)->timezone('Asia/Tehran'))->format('Y/m/d H:i') }}</small></td>
          @break
      @endswitch
    </tr>
    @empty<tr><td colspan="7" class="px-6 py-14 text-center text-sm text-slate-400">رکوردی برای نمایش پیدا نشد.</td></tr>@endforelse
  </tbody></table></div><div class="border-t border-slate-100 p-4">{{ $records->onEachSide(1)->links() }}</div></div>
</section>
@endsection
