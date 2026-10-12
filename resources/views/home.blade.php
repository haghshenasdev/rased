<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="رصد هوشمند اخبار و مطالب رسانه‌ها">
    <title>راصد | پایش هوشمند اخبار</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Vazirmatn','Tahoma','Arial']},boxShadow:{glass:'0 20px 70px rgba(15,23,42,.10)'}}}}</script>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
      *{font-family:Vazirmatn,Tahoma,sans-serif}body{background:#f4f8fb;background-image:radial-gradient(ellipse at 10% 0%,rgba(16,185,129,.13),transparent 34%),radial-gradient(ellipse at 95% 8%,rgba(14,165,233,.15),transparent 32%)}
      .glass{background:rgba(255,255,255,.72);backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px);border:1px solid rgba(255,255,255,.8);box-shadow:0 16px 50px rgba(15,23,42,.07)}
      .clamp-2{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}.clamp-3{display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}.line-clamp-4{display:-webkit-box;-webkit-line-clamp:4;-webkit-box-orient:vertical;overflow:hidden}
      .gradient-text{background:linear-gradient(110deg,#059669,#0284c7);-webkit-background-clip:text;background-clip:text;color:transparent}.gradient-bg{background:linear-gradient(125deg,#059669,#0284c7)}
      .news-card{transition:transform .2s ease,box-shadow .2s ease}.news-card:hover{transform:translateY(-3px);box-shadow:0 22px 60px rgba(15,23,42,.12)}
    </style>
</head>
<body class="text-slate-800 min-h-screen">
<header class="sticky top-0 z-30 border-b border-white/70 bg-white/75 backdrop-blur-xl">
 <div class="max-w-7xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between gap-4">
  <a href="{{ route('home') }}" class="flex items-center gap-3 shrink-0"><span class="w-11 h-11 rounded-2xl gradient-bg text-white grid place-items-center shadow-lg shadow-emerald-900/15"><svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m16 16 4 4M8 11h6M11 8v6"/></svg></span><span><b class="text-xl font-black tracking-tight">راصد</b><span class="block text-[11px] text-slate-500">پایش هوشمند رسانه‌ها</span></span></a>
  <nav class="hidden md:flex items-center gap-6 text-sm text-slate-600"><a class="hover:text-emerald-700" href="#news">آخرین اخبار</a><a class="hover:text-emerald-700" href="#sources">منابع خبری</a><span class="rounded-full bg-emerald-50 text-emerald-700 px-3 py-1.5 text-xs font-bold">رصد زنده رسانه‌ها</span></nav>
  <a href="#news" class="gradient-bg text-white rounded-xl px-4 py-2.5 text-sm font-bold shadow-md shadow-sky-900/10">مشاهده خبرها</a>
 </div>
</header>
<main class="max-w-7xl mx-auto px-4 sm:px-6 pb-16">
 <section class="relative overflow-hidden rounded-[2rem] mt-7 mb-9 px-6 sm:px-10 py-10 sm:py-14 text-white shadow-glass" style="background:linear-gradient(115deg,rgba(5,150,105,.97),rgba(2,132,199,.96))">
  <div class="absolute -left-16 -top-24 w-72 h-72 rounded-full border border-white/15"></div><div class="absolute left-24 -bottom-36 w-80 h-80 rounded-full bg-white/5"></div><div class="absolute right-1/2 top-0 w-64 h-64 rounded-full bg-cyan-300/10 blur-3xl"></div>
  <div class="relative grid lg:grid-cols-[1.2fr_.8fr] items-center gap-10">
   <div><div class="inline-flex items-center gap-2 rounded-full bg-white/15 border border-white/20 px-3 py-1.5 text-xs mb-5"><span class="w-2 h-2 rounded-full bg-emerald-200 animate-pulse"></span> پایش و گردآوری اخبار از منابع مختلف</div><h1 class="text-3xl sm:text-5xl font-black leading-tight">خبرهای مهم را<br><span class="text-cyan-100">هوشمندانه رصد کنید</span></h1><p class="text-white/85 leading-8 mt-5 max-w-2xl">آخرین مطالب رسانه‌ها را یک‌جا ببینید، جست‌وجو کنید و خبرهای مرتبط را سریع‌تر پیدا کنید.</p>
    <form method="GET" action="{{ route('home') }}" class="mt-7 flex flex-col sm:flex-row gap-2 max-w-2xl"><div class="flex-1 flex items-center gap-2 rounded-2xl bg-white text-slate-700 px-4 py-1"><svg class="w-5 h-5 text-slate-400 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m16 16 4 4"/></svg><input name="search" value="{{ request('search') }}" placeholder="جست‌وجو در عنوان، متن و کلیدواژه..." class="w-full py-3 outline-none bg-transparent text-sm" /></div><button class="rounded-2xl bg-slate-950 hover:bg-slate-800 px-6 py-3 font-bold transition">جست‌وجو</button></form>
   </div>
   <div class="hidden lg:flex justify-center"><div class="relative w-64 h-64 rounded-[2.5rem] bg-white/10 border border-white/20 backdrop-blur-md rotate-3 grid place-items-center"><div class="absolute inset-5 rounded-[2rem] border border-white/20 -rotate-6"></div><svg class="w-32 h-32 text-white drop-shadow-lg" viewBox="0 0 120 120" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="52" cy="50" r="30"/><path d="m74 73 22 22M37 42h30M37 52h22M37 62h14"/><circle cx="52" cy="50" r="39" stroke-opacity=".25"/></svg><span class="absolute bottom-5 right-5 rounded-xl bg-white text-emerald-700 text-xs font-black px-3 py-2 shadow-lg">رصد • تحلیل • خبر</span></div></div>
  </div>
 </section>
 <section id="sources" class="mb-10"><div class="flex items-end justify-between gap-4 mb-4"><div><p class="text-emerald-700 text-xs font-extrabold mb-1">منابع منتخب</p><h2 class="text-xl sm:text-2xl font-black">رسانه‌های تحت رصد</h2></div><span class="text-xs text-slate-500">{{ $sources->count() }} منبع فعال</span></div>
  @if($sources->isNotEmpty())<div class="flex gap-3 overflow-x-auto pb-3">@foreach($sources as $source)<a href="{{ route('home', ['search' => $source->name]) }}" class="glass rounded-2xl px-4 py-3 min-w-40 flex items-center gap-3 hover:border-emerald-200 transition">@if($source->profileImage())<img src="{{ $source->profileImage() }}" alt="" loading="lazy" class="w-11 h-11 rounded-xl object-cover bg-slate-100" onerror="this.style.display='none';this.nextElementSibling.style.display='grid'"><span style="display:none" class="w-11 h-11 rounded-xl gradient-bg text-white place-items-center font-black">{{ mb_substr($source->name,0,1) }}</span>@else<span class="w-11 h-11 rounded-xl gradient-bg text-white grid place-items-center font-black">{{ mb_substr($source->name,0,1) }}</span>@endif<span class="font-bold text-sm whitespace-nowrap">{{ $source->name }}</span></a>@endforeach</div>@else<div class="glass rounded-2xl p-5 text-slate-500 text-sm">هنوز منبع فعالی ثبت نشده است.</div>@endif
 </section>
 <section id="news"><div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-5"><div><p class="text-sky-700 text-xs font-extrabold mb-1">مرور سریع رسانه‌ها</p><h2 class="text-2xl sm:text-3xl font-black">آخرین خبرهای پیدا شده</h2><p class="text-sm text-slate-500 mt-2">عنوان و خلاصه خبرها را مرور کنید و برای جزئیات بیشتر وارد صفحه خبر شوید.</p></div>
  <div class="flex gap-2 flex-wrap text-sm">@foreach([''=>'همه','today'=>'امروز','yesterday'=>'دیروز','week'=>'۷ روز اخیر'] as $value=>$label)<a href="{{ route('home', array_merge(request()->except('page'), ['period'=>$value ?: null])) }}" class="rounded-xl px-3 py-2 border {{ request('period','')===$value ? 'gradient-bg text-white border-transparent' : 'bg-white/80 border-white text-slate-600 hover:border-emerald-200' }}">{{ $label }}</a>@endforeach</div>
 </div>
 @if($items->count())<div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-5">@foreach($items as $item)<article class="news-card glass rounded-[1.5rem] p-5 flex flex-col min-w-0">
  <div class="flex items-center justify-between gap-3 mb-4"><span class="inline-flex items-center gap-2 text-xs font-bold text-slate-600 min-w-0"><span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span><span class="truncate">{{ $item->source?->name ?? 'منبع خبری' }}</span></span><span class="flex items-center gap-1.5 shrink-0">@if($item->is_repost)<span class="rounded-full bg-amber-100 text-amber-800 px-2.5 py-1 text-[10px] font-extrabold">🔁 بازنشر</span>@endif @if($item->category)<span class="rounded-full bg-sky-50 text-sky-700 px-2.5 py-1 text-[11px] font-bold">{{ $item->category->name }}</span>@endif</span></div>
  @if($item->featured_image_url)<a href="{{ route('news.show',$item) }}" class="block mb-4 overflow-hidden rounded-2xl bg-slate-100 aspect-[16/9]"><img src="{{ $item->featured_image_url }}" alt="" loading="lazy" class="w-full h-full object-cover hover:scale-105 transition duration-500" onerror="this.parentElement.style.display='none'"></a>@endif
  <h3 class="font-extrabold text-lg leading-8 clamp-2"><a class="hover:text-emerald-700 transition" href="{{ route('news.show',$item) }}">{{ $item->title }}</a></h3>
  @php($summary = trim(strip_tags($item->matched_content ?: $item->content ?: '')))
  @php($summary = \Illuminate\Support\Str::limit(preg_replace('/\s+/u',' ',$summary), 260, '…'))
  @php($keywordForHighlight = trim((string) ($item->matched_keyword ?? '')))
  @php($safeSummary = e($summary))
  @php($summaryPattern = $keywordForHighlight !== '' ? '/' . preg_quote(e($keywordForHighlight), '/') . '/iu' : null)
  @php($highlightedSummary = $summaryPattern ? preg_replace_callback($summaryPattern, fn($m) => '<mark class="rounded bg-yellow-200 px-0.5 text-amber-950 font-bold">'.$m[0].'</mark>', $safeSummary) : $safeSummary)
  @if($summary)<p class="text-sm leading-7 text-slate-600 mt-3 clamp-3">{!! $highlightedSummary !!}</p>@else<p class="text-sm text-slate-400 mt-3">برای مطالعه جزئیات، خبر را باز کنید.</p>@endif
  <div class="mt-auto pt-5 flex items-center justify-between gap-3"><div class="text-[11px] text-slate-500">{{ $item->published_at ? \Morilog\Jalali\Jalalian::fromCarbon($item->published_at->copy()->setTimezone('Asia/Tehran'))->format('Y/m/d · H:i') : 'زمان نامشخص' }}@if($item->matched_keyword)<span class="block mt-1 text-emerald-700">کلیدواژه: {{ \Illuminate\Support\Str::limit($item->matched_keyword,35) }}</span>@endif</div><a href="{{ route('news.show',$item) }}" class="rounded-xl bg-slate-900 text-white text-xs font-bold px-3.5 py-2.5 hover:gradient-bg transition whitespace-nowrap">ادامه خبر ←</a></div>
 </article>@endforeach</div><div class="mt-8">{{ $items->onEachSide(1)->links() }}</div>@else<div class="glass rounded-3xl text-center py-16 px-6"><div class="w-16 h-16 rounded-2xl bg-emerald-50 text-emerald-600 grid place-items-center mx-auto mb-4"><svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="11" cy="11" r="7"/><path d="m16 16 4 4"/></svg></div><h3 class="font-black text-xl">خبری پیدا نشد</h3><p class="text-slate-500 mt-2 text-sm">عبارت جست‌وجو یا بازه زمانی را تغییر دهید.</p><a href="{{ route('home') }}" class="inline-block mt-5 gradient-bg text-white rounded-xl px-5 py-3 text-sm font-bold">نمایش همه خبرها</a></div>@endif
 </section>
</main>
<footer class="border-t border-white/80 bg-white/60 backdrop-blur-lg"><div class="max-w-7xl mx-auto px-4 sm:px-6 py-6 flex flex-col sm:flex-row justify-between gap-2 text-xs text-slate-500"><span>راصد؛ پایش و دسته‌بندی هوشمند اخبار</span><span>مطالب متعلق به منابع منتشرکننده است.</span></div></footer>
</body></html>
