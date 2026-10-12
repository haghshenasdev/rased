<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags($sourceItem->title.' '.($sourceItem->matched_content ?: $sourceItem->content ?: '')), 155) }}">
    <title>{{ $sourceItem->title }} | راصد</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
      *{font-family:Vazirmatn,Tahoma,sans-serif}body{background:#f3f8fb;background-image:radial-gradient(ellipse at 0% 0%,rgba(16,185,129,.12),transparent 35%),radial-gradient(ellipse at 100% 5%,rgba(14,165,233,.13),transparent 32%)}
      .glass{background:rgba(255,255,255,.78);backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px);border:1px solid rgba(255,255,255,.85);box-shadow:0 18px 60px rgba(15,23,42,.07)}
      .article-content{white-space:pre-wrap;overflow-wrap:anywhere;line-height:2.15}.keyword-mark{background:#fef08a;color:#713f12;padding:0 .18rem;border-radius:.25rem;font-weight:800;box-decoration-break:clone;-webkit-box-decoration-break:clone}
      @media print{body{background:#fff!important}.no-print{display:none!important}.print-card{box-shadow:none!important;border:0!important;background:#fff!important;backdrop-filter:none!important}.print-content{font-size:12pt;color:#111!important}.print-header{display:block!important}main{max-width:100%!important;margin:0!important;padding:0!important}}
    </style>
</head>
<body class="min-h-screen text-slate-800">
@php
    $keyword = trim((string) ($sourceItem->matched_keyword ?? ''));
    $highlight = static function (?string $text) use ($keyword): string {
        $escaped = e((string) $text);
        if ($keyword === '') return nl2br($escaped);
        $needle = e($keyword);
        $pattern = '/' . preg_quote($needle, '/') . '/iu';
        $marked = preg_replace_callback($pattern, static fn ($m) => '<mark class="keyword-mark">'.$m[0].'</mark>', $escaped);
        return nl2br($marked ?? $escaped);
    };
    $jalaliDate = $sourceItem->published_at
        ? \Morilog\Jalali\Jalalian::fromCarbon($sourceItem->published_at->copy()->setTimezone('Asia/Tehran'))->format('Y/m/d H:i')
        : null;
    $matchText = trim((string) ($sourceItem->matched_content ?? ''));
    $fullContent = trim((string) ($sourceItem->content ?? ''));
@endphp
<header class="no-print sticky top-0 z-20 border-b border-white/80 bg-white/75 backdrop-blur-xl">
  <div class="max-w-5xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between gap-3">
    <a href="{{ route('home') }}" class="flex items-center gap-3"><span class="w-10 h-10 rounded-2xl bg-gradient-to-br from-emerald-600 to-sky-600 text-white grid place-items-center font-black text-lg shadow-lg">ر</span><span><b class="text-lg font-black">راصد</b><span class="block text-[11px] text-slate-500">پایش هوشمند رسانه‌ها</span></span></a>
    <div class="flex items-center gap-2"><button onclick="window.print()" class="rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm font-bold hover:border-emerald-300 transition">🖨️ چاپ خبر</button><a href="{{ route('home') }}" class="rounded-xl bg-slate-900 text-white px-3.5 py-2.5 text-sm font-bold hover:bg-slate-700 transition">← بازگشت</a></div>
  </div>
</header>
<main class="max-w-5xl mx-auto px-4 sm:px-6 py-7 sm:py-10">
  <article class="glass print-card overflow-hidden rounded-[2rem]">
    @if($sourceItem->featured_image_url)
      <div class="bg-slate-100 max-h-[460px] overflow-hidden"><img src="{{ $sourceItem->featured_image_url }}" alt="تصویر خبر" class="w-full max-h-[460px] object-cover" onerror="this.parentElement.style.display='none'"></div>
    @endif
    <div class="p-5 sm:p-9 lg:p-12">
      <div class="flex flex-wrap items-center gap-2 mb-5 text-xs font-bold">
        <span class="rounded-full bg-emerald-50 text-emerald-800 px-3 py-1.5">{{ $sourceItem->source?->name ?? 'منبع خبری' }}</span>
        @if($sourceItem->category)<span class="rounded-full bg-sky-50 text-sky-800 px-3 py-1.5">{{ $sourceItem->category->name }}</span>@endif
        @if($sourceItem->is_repost)<span class="rounded-full bg-amber-100 text-amber-900 px-3 py-1.5">🔁 بازنشر @if($sourceItem->similarity_percent) · {{ number_format($sourceItem->similarity_percent, 1) }}٪ شباهت @endif</span>@endif
        @if($jalaliDate)<span class="text-slate-500 px-1">📅 {{ $jalaliDate }} (شمسی)</span>@endif
      </div>
      <h1 class="text-2xl sm:text-4xl font-black leading-[1.8] tracking-tight">{{ $sourceItem->title }}</h1>
      @if($keyword)
        <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50/80 p-4 sm:p-5">
          <div class="flex items-center gap-2 text-sm font-black text-amber-900 mb-2"><span>🔎</span> کلیدواژه شناسایی‌شده</div>
          <span class="inline-flex rounded-lg bg-yellow-200 px-3 py-1.5 font-extrabold text-amber-950">{{ $keyword }}</span>
          @if($matchText)<p class="mt-3 text-sm leading-8 text-slate-700">{!! $highlight($matchText) !!}</p>@endif
        </div>
      @endif
      @if($sourceItem->is_repost && $sourceItem->original)
        <div class="mt-5 rounded-2xl border border-sky-100 bg-sky-50/80 p-4 text-sm leading-7"><b class="text-sky-900">مطلب مشابه قبلی:</b> <a class="font-bold text-sky-800 underline underline-offset-4" href="{{ route('news.show', $sourceItem->original) }}">{{ $sourceItem->original->title }}</a></div>
      @endif
      @if($fullContent !== '')
        <section class="mt-8"><h2 class="text-lg font-black mb-3">متن خبر</h2><div class="article-content print-content text-[15px] sm:text-base text-slate-700">{!! $highlight($fullContent) !!}</div></section>
      @elseif(!$matchText)
        <p class="mt-8 text-slate-500 leading-8">متن کامل این خبر از منبع دریافت نشده است.</p>
      @endif
      @if($sourceItem->url)
        <div class="no-print mt-9 border-t border-slate-200/80 pt-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4"><div><p class="font-black">مطالعه در منبع اصلی</p><p class="text-sm text-slate-500 mt-1">برای مشاهده نسخه منتشرشده توسط رسانه، لینک زیر را باز کنید.</p></div><a href="{{ $sourceItem->url }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-l from-emerald-600 to-sky-600 text-white px-5 py-3 font-extrabold text-sm shadow-lg shadow-emerald-900/10">باز کردن منبع اصلی ↗</a></div>
      @endif
      <div class="print-header hidden mt-8 border-t pt-4 text-xs text-slate-500">منتشرشده در راصد · {{ $jalaliDate ?? 'زمان نامشخص' }} · منبع: {{ $sourceItem->source?->name ?? 'نامشخص' }}</div>
    </div>
  </article>
  <div class="no-print flex justify-center mt-6"><a href="{{ route('home') }}" class="rounded-xl border border-white bg-white/80 px-5 py-3 text-sm font-bold text-slate-700 hover:text-emerald-700">بازگشت به فهرست خبرها</a></div>
</main>
<footer class="no-print text-center text-xs text-slate-400 pb-8">راصد؛ پایش و دسته‌بندی هوشمند اخبار</footer>
</body></html>
