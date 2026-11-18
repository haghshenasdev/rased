@extends('panel.layout')
@section('title', 'خبرهای پیدا شده')
@section('crumb', 'خبرها')
@section('heading', 'خبرهای پیدا شده')
@section('content')
<div class="space-y-5">
  <section class="glass rounded-2xl p-5 sm:p-6"><div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center"><div><div class="text-xs font-black text-emerald-700">آرشیو رصد</div><h2 class="mt-1 text-xl font-black">خبرهای جمع‌آوری‌شده از منابع</h2><p class="mt-1 text-sm text-slate-500">جست‌وجو و فیلتر خبرها با همان خلاصه، کلیدواژه و جزئیات صفحه‌ی خبر</p></div><span class="rounded-xl bg-slate-100 px-3 py-2 text-xs font-bold text-slate-600">{{ number_format($items->total()) }} خبر</span></div>
    <form method="GET" action="{{ route('panel.news') }}" class="mt-5 grid gap-2 sm:grid-cols-2 lg:grid-cols-[minmax(220px,1.5fr)_repeat(3,minmax(140px,.7fr))_auto]">
      <label class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3"><span class="text-slate-400">⌕</span><input name="search" value="{{ request('search') }}" placeholder="جست‌وجو در عنوان، متن یا کلیدواژه" class="w-full bg-transparent py-3 text-sm outline-none"></label>
      <select name="period" class="rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none"><option value="">همه‌ی زمان‌ها</option><option value="today" @selected(request('period')==='today')>امروز</option><option value="yesterday" @selected(request('period')==='yesterday')>دیروز</option><option value="week" @selected(request('period')==='week')>۷ روز اخیر</option></select>
      <select name="source" class="rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none"><option value="">همه‌ی منابع</option>@foreach($sources as $source)<option value="{{ $source->id }}" @selected((string)request('source')===(string)$source->id)>{{ $source->name }}</option>@endforeach</select>
      <select name="category" class="rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none"><option value="">همه‌ی دسته‌ها</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string)request('category')===(string)$category->id)>{{ $category->name }}</option>@endforeach</select>
      <div class="flex gap-2"><button class="flex-1 rounded-xl gradient-bg px-4 py-3 text-sm font-extrabold text-white">اعمال فیلتر</button><a href="{{ route('panel.news') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-600">پاک‌کردن</a></div>
    </form>
  </section>
  @if($items->count())
  <section class="grid gap-4 sm:grid-cols-2 2xl:grid-cols-3">
    @foreach($items as $item)
    <article class="news-card glass flex min-w-0 flex-col rounded-2xl p-5">
      <div class="mb-3 flex items-center justify-between gap-3"><span class="flex min-w-0 items-center gap-2 text-xs font-bold text-slate-600"><i class="h-2 w-2 shrink-0 rounded-full bg-emerald-500"></i><span class="truncate">{{ $item->source?->name ?? 'منبع خبری' }}</span></span><span class="flex shrink-0 gap-1.5">@if($item->is_repost)<span class="rounded-full bg-amber-100 px-2 py-1 text-[10px] font-extrabold text-amber-800">بازنشر</span>@endif @if($item->category)<span class="rounded-full bg-sky-50 px-2 py-1 text-[10px] font-bold text-sky-700">{{ $item->category->name }}</span>@endif</span></div>
      @if($item->featured_image_url)<a href="{{ route('news.show',$item) }}" class="mb-4 block aspect-[16/9] overflow-hidden rounded-xl bg-slate-100"><img src="{{ $item->featured_image_url }}" alt="" loading="lazy" class="h-full w-full object-cover transition duration-500 hover:scale-105" onerror="this.parentElement.style.display='none'"></a>@endif
      <h3 class="clamp-2 text-base font-extrabold leading-7"><a href="{{ route('news.show',$item) }}" class="hover:text-emerald-700">{{ $item->title }}</a></h3>
      @php($summary = trim(strip_tags($item->matched_content ?: $item->content ?: '')))
      @php($summary = \Illuminate\Support\Str::limit(preg_replace('/\s+/u',' ',$summary), 240, '…'))
      @if($summary)<p class="clamp-3 mt-3 text-sm leading-7 text-slate-600">{{ $summary }}</p>@else<p class="mt-3 text-sm text-slate-400">برای مطالعه‌ی جزئیات، خبر را باز کنید.</p>@endif
      <div class="mt-auto flex items-end justify-between gap-3 pt-5"><div class="text-[11px] text-slate-500">{{ $item->published_at ? \Morilog\Jalali\Jalalian::fromCarbon($item->published_at->copy()->setTimezone('Asia/Tehran'))->format('Y/m/d · H:i') : 'زمان نامشخص' }}@if($item->matched_keyword)<span class="mt-1 block font-bold text-emerald-700">کلیدواژه: {{ \Illuminate\Support\Str::limit($item->matched_keyword,35) }}</span>@endif</div><a href="{{ route('news.show',$item) }}" class="shrink-0 rounded-xl bg-slate-900 px-3 py-2.5 text-xs font-bold text-white hover:gradient-bg">جزئیات خبر ←</a></div>
    </article>
    @endforeach
  </section>
  <div>{{ $items->onEachSide(1)->links() }}</div>
  @else
  <section class="glass rounded-3xl px-6 py-16 text-center"><span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-emerald-50 text-2xl text-emerald-700">⌕</span><h3 class="mt-4 text-xl font-black">خبری با این فیلتر پیدا نشد</h3><p class="mt-2 text-sm text-slate-500">عبارت جست‌وجو یا فیلترهای انتخابی را تغییر دهید.</p><a href="{{ route('panel.news') }}" class="mt-5 inline-flex rounded-xl gradient-bg px-5 py-3 text-sm font-bold text-white">نمایش همه‌ی خبرها</a></section>
  @endif
</div>
@endsection
