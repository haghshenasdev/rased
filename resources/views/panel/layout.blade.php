<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'داشبورد') | رصد</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
      *{font-family:Vazirmatn,Tahoma,sans-serif}body{background:#f4f7fb;background-image:radial-gradient(ellipse at 78% 0%,rgba(14,165,233,.08),transparent 28%),radial-gradient(ellipse at 0% 10%,rgba(16,185,129,.07),transparent 25%)}
      .glass{background:rgba(255,255,255,.8);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,.95);box-shadow:0 12px 38px rgba(15,23,42,.045)}.gradient-bg{background:linear-gradient(125deg,#059669,#0284c7)}.nav-active{background:linear-gradient(100deg,rgba(5,150,105,.12),rgba(2,132,199,.08));color:#047857}
      .clamp-2{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}.clamp-3{display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}.news-card{transition:transform .2s,box-shadow .2s}.news-card:hover{transform:translateY(-3px);box-shadow:0 20px 50px rgba(15,23,42,.10)}
    </style>
</head>
<body class="min-h-screen text-slate-800">
<div id="panel-app" class="min-h-screen">
  <div v-if="mobileOpen" class="fixed inset-0 z-40 bg-slate-950/35 lg:hidden" @click="mobileOpen=false"></div>
  <aside class="fixed inset-y-0 right-0 z-50 flex w-[276px] -translate-x-full flex-col border-l border-slate-200/70 bg-white/95 p-4 shadow-2xl transition-transform lg:translate-x-0" :class="{'translate-x-0':mobileOpen}">
    <a href="{{ route('panel.index') }}" class="flex items-center gap-3 px-2 py-3"><span class="grid h-11 w-11 place-items-center rounded-2xl gradient-bg text-xl font-black text-white shadow-lg">ر</span><span><b class="text-lg font-black">رصد</b><small class="block text-[11px] text-slate-500">مرکز مدیریت رسانه</small></span></a>
    <div class="mt-5 rounded-2xl bg-slate-50 px-3 py-3"><div class="flex items-center gap-3"><span class="grid h-10 w-10 place-items-center rounded-xl bg-emerald-100 font-black text-emerald-800">{{ mb_substr(auth()->user()->name ?? 'م', 0, 1) }}</span><span class="min-w-0"><b class="block truncate text-sm">{{ auth()->user()->name ?? 'مدیر سامانه' }}</b><small class="text-xs text-slate-500">مدیر سامانه</small></span><span class="mr-auto h-2 w-2 rounded-full bg-emerald-500"></span></div></div>
    <nav class="mt-6 flex-1 space-y-1 overflow-y-auto text-sm">
      <p class="px-3 pb-2 text-[10px] font-black tracking-wider text-slate-400">نمای کلی</p>
      <a href="{{ route('panel.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-3 font-bold {{ request()->routeIs('panel.index') ? 'nav-active' : 'text-slate-600 hover:bg-slate-50' }}"><span>▦</span> داشبورد</a>
      <a href="{{ route('panel.news') }}" class="flex items-center gap-3 rounded-xl px-3 py-3 font-bold {{ request()->routeIs('panel.news') ? 'nav-active' : 'text-slate-600 hover:bg-slate-50' }}"><span>▤</span> خبرهای پیدا شده</a>
      <p class="px-3 pb-2 pt-6 text-[10px] font-black tracking-wider text-slate-400">مدیریت پایش</p>
      <a href="{{ url('/admin/sources') }}" class="flex items-center gap-3 rounded-xl px-3 py-3 text-slate-600 hover:bg-slate-50"><span>◉</span> منابع خبری</a>
      <a href="{{ url('/admin/keywords') }}" class="flex items-center gap-3 rounded-xl px-3 py-3 text-slate-600 hover:bg-slate-50"><span>⌕</span> کلیدواژه‌ها</a>
      <a href="{{ url('/admin/blacklist-keywords') }}" class="flex items-center gap-3 rounded-xl px-3 py-3 text-slate-600 hover:bg-slate-50"><span>⊘</span> واژه‌های مسدود</a>
      <a href="{{ url('/admin/categories') }}" class="flex items-center gap-3 rounded-xl px-3 py-3 text-slate-600 hover:bg-slate-50"><span>◫</span> دسته‌بندی‌ها</a>
      <p class="px-3 pb-2 pt-6 text-[10px] font-black tracking-wider text-slate-400">عملیات و ارسال</p>
      <a href="{{ url('/admin/monitoring-logs') }}" class="flex items-center gap-3 rounded-xl px-3 py-3 text-slate-600 hover:bg-slate-50"><span>◷</span> گزارش پایش</a>
      <a href="{{ url('/admin/queue-jobs') }}" class="flex items-center gap-3 rounded-xl px-3 py-3 text-slate-600 hover:bg-slate-50"><span>↻</span> صف پردازش</a>
      <a href="{{ url('/admin/bale-subscribers') }}" class="flex items-center gap-3 rounded-xl px-3 py-3 text-slate-600 hover:bg-slate-50"><span>➤</span> مشترکان بله</a>
      <a href="{{ url('/admin/bale-bot-settings') }}" class="flex items-center gap-3 rounded-xl px-3 py-3 text-slate-600 hover:bg-slate-50"><span>⚙</span> تنظیمات ربات</a>
      <a href="{{ url('/admin/source-items') }}" class="flex items-center gap-3 rounded-xl px-3 py-3 text-slate-600 hover:bg-slate-50"><span>☷</span> مدیریت همه خبرها</a>
    </nav>
    <div class="border-t border-slate-100 pt-3"><a href="{{ route('home') }}" class="flex items-center justify-between rounded-xl px-3 py-3 text-xs font-bold text-slate-500 hover:bg-slate-50"><span>مشاهده‌ی سایت عمومی</span><span>↗</span></a><a href="{{ url('/admin') }}" class="mt-1 flex items-center justify-between rounded-xl px-3 py-3 text-xs font-bold text-emerald-700 hover:bg-emerald-50"><span>ورود به پنل Filament</span><span>↗</span></a></div>
  </aside>
  <div class="min-h-screen lg:pr-[276px]">
    <header class="sticky top-0 z-30 border-b border-white/80 bg-white/75 backdrop-blur-xl"><div class="mx-auto flex max-w-[1600px] items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8">
      <div class="flex items-center gap-3"><button class="grid h-10 w-10 place-items-center rounded-xl border border-slate-200 bg-white text-lg lg:hidden" @click="mobileOpen=true" aria-label="باز کردن منو">☰</button><div><p class="text-[11px] font-bold text-slate-400">پنل مدیریت / @yield('crumb','داشبورد')</p><h1 class="mt-0.5 text-lg font-black sm:text-xl">@yield('heading','داشبورد')</h1></div></div>
      <div class="flex items-center gap-2"><a href="{{ route('news.index') }}" class="hidden rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-600 hover:border-emerald-300 sm:inline-flex">اخبار عمومی</a><span class="hidden h-9 w-px bg-slate-200 sm:block"></span><div class="flex items-center gap-2"><span class="hidden text-left text-xs sm:block"><b class="block">{{ auth()->user()->name ?? 'مدیر' }}</b><small class="text-slate-400">وضعیت سامانه: فعال</small></span><span class="grid h-9 w-9 place-items-center rounded-xl bg-gradient-to-br from-emerald-100 to-sky-100 font-black text-emerald-800">{{ mb_substr(auth()->user()->name ?? 'م', 0, 1) }}</span></div></div>
    </div></header>
    <main class="mx-auto max-w-[1600px] px-4 py-6 sm:px-6 lg:px-8">@yield('content')</main>
  </div>
</div>
<script>const panelVue=Vue.createApp({data(){return{mobileOpen:false}}});panelVue.mount('#panel-app');</script>
@stack('scripts')
</body>
</html>
