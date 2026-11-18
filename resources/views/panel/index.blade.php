@extends('panel.layout')
@section('title', 'داشبورد')
@section('crumb', 'نمای کلی')
@section('heading', 'داشبورد رصد')
@section('content')
<div class="space-y-6">
  <section class="relative overflow-hidden rounded-[1.7rem] bg-slate-950 px-5 py-6 text-white sm:px-8 sm:py-8"><div class="absolute -left-10 -top-20 h-64 w-64 rounded-full bg-emerald-500/15 blur-3xl"></div><div class="relative flex flex-col justify-between gap-5 sm:flex-row sm:items-center"><div><div class="inline-flex items-center gap-2 rounded-full border border-emerald-300/20 bg-emerald-300/10 px-3 py-1.5 text-xs font-bold text-emerald-200"><span class="h-2 w-2 animate-pulse rounded-full bg-emerald-300"></span>پایش سامانه فعال است</div><h2 class="mt-4 text-2xl font-black sm:text-3xl">سلام، {{ auth()->user()->name ?? 'مدیر' }} 👋</h2><p class="mt-2 text-sm text-slate-300">نمای زنده از خبرها، منابع و وضعیت پایش رسانه‌ها</p></div><div class="flex flex-wrap gap-2"><a href="{{ route('panel.news') }}" class="rounded-xl gradient-bg px-4 py-2.5 text-sm font-bold">مرور خبرها ←</a><a href="{{ url('/admin/sources/create') }}" class="rounded-xl border border-white/15 bg-white/10 px-4 py-2.5 text-sm font-bold hover:bg-white/15">افزودن منبع +</a></div></div></section>

  <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach([
      ['خبرهای بازه', number_format($stats['news']), 'مجموع خبرهای ثبت‌شده در بازه‌ی انتخابی', '▤', 'emerald'],
      ['خبرهای امروز', number_format($stats['today']), 'خبرهای منتشرشده از ابتدای امروز', '◷', 'sky'],
      ['منابع فعال', number_format($stats['sources']), 'منابع آماده‌ی پایش', '◉', 'violet'],
      ['کلیدواژه‌های فعال', number_format($stats['keywords']), 'واژه‌های در حال جست‌وجو', '⌕', 'amber'],
    ] as [$label,$value,$hint,$icon,$color])
    <article class="glass rounded-2xl p-5"><div class="flex items-start justify-between"><span class="text-sm font-bold text-slate-500">{{ $label }}</span><span class="grid h-10 w-10 place-items-center rounded-xl bg-{{ $color }}-50 text-lg font-black text-{{ $color }}-700">{{ $icon }}</span></div><p class="mt-4 text-3xl font-black tracking-tight">{{ $value }}</p><p class="mt-1 text-xs text-slate-400">{{ $hint }}</p></article>
    @endforeach
  </section>

  <section class="grid gap-5 xl:grid-cols-[1.6fr_1fr]">
    <article class="glass rounded-2xl p-5 sm:p-6"><div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"><div><h3 class="text-lg font-black">روند کشف خبرها</h3><p class="mt-1 text-xs text-slate-500">تعداد خبرهای منتشرشده در هر روز</p></div><form method="GET" action="{{ route('panel.index') }}" class="flex items-center gap-2"><label for="range" class="text-xs font-bold text-slate-500">بازه:</label><select id="range" name="range" @change="$event.target.form.submit()" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-bold outline-none focus:border-emerald-400"><option value="7" @selected($days===7)>۷ روز اخیر</option><option value="30" @selected($days===30)>۳۰ روز اخیر</option></select></form></div><div class="mt-5 h-[270px]"><canvas id="newsTrendChart" aria-label="نمودار روند خبرهای روزانه"></canvas></div></article>
    <article class="glass rounded-2xl p-5 sm:p-6"><div class="flex items-start justify-between"><div><h3 class="text-lg font-black">نمای سلامت سامانه</h3><p class="mt-1 text-xs text-slate-500">عملیات پایش و ارسال</p></div><span class="rounded-full bg-emerald-50 px-3 py-1.5 text-[11px] font-extrabold text-emerald-700">فعال</span></div>
      <div class="mt-5 divide-y divide-slate-100">
        <div class="flex items-center justify-between py-3"><span class="flex items-center gap-2 text-sm text-slate-600"><i class="h-2 w-2 rounded-full bg-emerald-500"></i>مشترک فعال بله</span><b>{{ number_format($stats['subscribers']) }}</b></div>
        <div class="flex items-center justify-between py-3"><span class="flex items-center gap-2 text-sm text-slate-600"><i class="h-2 w-2 rounded-full bg-amber-400"></i>بازنشرهای شناسایی‌شده</span><b>{{ number_format($stats['reposts']) }}</b></div>
        <div class="flex items-center justify-between py-3"><span class="flex items-center gap-2 text-sm text-slate-600"><i class="h-2 w-2 rounded-full {{ $stats['errors'] ? 'bg-rose-500' : 'bg-emerald-500' }}"></i>خطاهای ثبت‌شده</span><b class="{{ $stats['errors'] ? 'text-rose-600' : 'text-slate-800' }}">{{ number_format($stats['errors']) }}</b></div>
        <div class="py-3"><span class="text-sm text-slate-600">آخرین اجرای پایش</span><p class="mt-1 text-xs font-bold text-slate-800">{{ $latestRun?->started_at?->timezone('Asia/Tehran')->format('Y/m/d H:i') ?? 'هنوز اجرایی ثبت نشده است' }} @if($latestRun)<span class="mr-1 rounded-md px-2 py-1 text-[10px] {{ $latestRun->status === 'failed' ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700' }}">{{ $latestRun->status === 'failed' ? 'ناموفق' : 'ثبت‌شده' }}</span>@endif</p></div>
      </div><a href="{{ url('/admin/monitoring-logs') }}" class="mt-2 inline-flex text-xs font-extrabold text-emerald-700">مشاهده‌ی گزارش‌های پایش ←</a>
    </article>
  </section>

  <section class="grid gap-5 xl:grid-cols-2">
    <article class="glass rounded-2xl p-5 sm:p-6"><div class="flex items-center justify-between"><div><h3 class="text-lg font-black">منابع پربازده</h3><p class="mt-1 text-xs text-slate-500">بر اساس تعداد خبر در این بازه</p></div><span class="text-xl">◉</span></div>
      <div class="mt-5 space-y-4">@forelse($topSources as $source)<div><div class="mb-1.5 flex items-center justify-between gap-3 text-xs"><span class="truncate font-bold">{{ $source['name'] }}</span><b class="shrink-0 text-slate-500">{{ number_format($source['count']) }}</b></div><div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full gradient-bg" style="width:{{ max(4, (int) round($source['count'] / max(1, $topSources->max('count')) * 100)) }}%"></div></div></div>@empty<p class="py-8 text-center text-sm text-slate-400">هنوز داده‌ای برای این بازه ثبت نشده است.</p>@endforelse</div>
    </article>
    <article class="glass rounded-2xl p-5 sm:p-6"><div class="flex items-center justify-between"><div><h3 class="text-lg font-black">کلیدواژه‌های پرتکرار</h3><p class="mt-1 text-xs text-slate-500">کلیدواژه‌هایی که بیشترین خبر را یافته‌اند</p></div><span class="text-xl">⌕</span></div>
      <div class="mt-5 flex flex-wrap gap-2">@forelse($topKeywords as $keyword)<span class="inline-flex items-center gap-2 rounded-xl border border-emerald-100 bg-emerald-50/70 px-3 py-2 text-xs font-bold text-emerald-900"><span>{{ $keyword['name'] }}</span><b class="rounded-lg bg-white px-2 py-1 text-emerald-700">{{ number_format($keyword['count']) }}</b></span>@empty<p class="py-8 text-center text-sm text-slate-400">هنوز داده‌ای برای این بازه ثبت نشده است.</p>@endforelse</div>
    </article>
  </section>

  <section class="glass overflow-hidden rounded-2xl"><div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 sm:px-6"><div><h3 class="font-black">تازه‌ترین خبرهای پیدا شده</h3><p class="mt-1 text-xs text-slate-500">خبرهایی که به‌تازگی وارد سامانه شده‌اند</p></div><a href="{{ route('panel.news') }}" class="text-xs font-extrabold text-emerald-700">همه‌ی خبرها ←</a></div>
    <div class="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-3">@forelse($recentNews as $item)<a href="{{ route('news.show',$item) }}" class="rounded-xl border border-slate-100 bg-white p-4 transition hover:border-emerald-200 hover:shadow-md"><div class="flex items-center justify-between gap-2"><span class="truncate text-[11px] font-bold text-slate-500">{{ $item->source?->name ?? 'منبع نامشخص' }}</span>@if($item->category)<span class="shrink-0 rounded-lg bg-sky-50 px-2 py-1 text-[10px] font-bold text-sky-700">{{ $item->category->name }}</span>@endif</div><h4 class="mt-3 clamp-2 text-sm font-extrabold leading-6">{{ $item->title }}</h4><div class="mt-3 flex items-center justify-between text-[10px] text-slate-400"><span>{{ $item->matched_keyword ? 'کلیدواژه: '.$item->matched_keyword : 'خبر ثبت‌شده' }}</span><span>{{ $item->published_at?->timezone('Asia/Tehran')->format('m/d H:i') }}</span></div></a>@empty<p class="col-span-full py-10 text-center text-sm text-slate-400">هنوز خبری ثبت نشده است.</p>@endforelse</div>
  </section>
</div>
@endsection
@push('scripts')
<script>
const newsTrendLabels=@json($chart->pluck('label'));
const newsTrendCounts=@json($chart->pluck('count'));
new Chart(document.getElementById('newsTrendChart'),{type:'line',data:{labels:newsTrendLabels,datasets:[{label:'خبرهای پیدا شده',data:newsTrendCounts,borderColor:'#059669',backgroundColor:'rgba(16,185,129,.12)',fill:true,tension:.38,borderWidth:3,pointRadius:3,pointHoverRadius:6,pointBackgroundColor:'#fff',pointBorderWidth:2}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{rtl:true,displayColors:false,backgroundColor:'#0f172a',padding:12,titleFont:{family:'Vazirmatn'},bodyFont:{family:'Vazirmatn'}}},scales:{x:{grid:{display:false},ticks:{font:{family:'Vazirmatn'},maxTicksLimit:10}},y:{beginAtZero:true,ticks:{precision:0,font:{family:'Vazirmatn'}},grid:{color:'rgba(148,163,184,.15)'}}}}});
</script>
@endpush
