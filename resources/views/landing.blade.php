<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="رصد؛ پایش هوشمند خبرها و محتوای رسانه‌ای از منابع مختلف، با دسته‌بندی و ارسال خبرهای مرتبط به بله.">
    <title>رصد | پایش هوشمند رسانه‌ها</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
      *{font-family:Vazirmatn,Tahoma,sans-serif}body{background:#f6f8fc;background-image:radial-gradient(ellipse at 4% 0%,rgba(16,185,129,.12),transparent 30%),radial-gradient(ellipse at 95% 8%,rgba(14,165,233,.13),transparent 32%)}
      .glass{background:rgba(255,255,255,.76);backdrop-filter:blur(18px);border:1px solid rgba(255,255,255,.9);box-shadow:0 18px 55px rgba(15,23,42,.07)}.gradient-bg{background:linear-gradient(125deg,#059669,#0284c7)}.gradient-text{background:linear-gradient(110deg,#059669,#0284c7);-webkit-background-clip:text;color:transparent}
    </style>
</head>
<body class="min-h-screen text-slate-800">
<div id="landing-app">
<header class="sticky top-0 z-20 border-b border-white/70 bg-white/75 backdrop-blur-xl">
  <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6">
    <a href="{{ route('home') }}" class="flex items-center gap-3"><span class="grid h-11 w-11 place-items-center rounded-2xl gradient-bg text-xl font-black text-white shadow-lg">ر</span><span><b class="text-xl font-black">رصد</b><small class="block text-[11px] text-slate-500">پایش هوشمند رسانه‌ها</small></span></a>
    <nav class="hidden items-center gap-7 text-sm text-slate-600 md:flex"><a href="#features" class="hover:text-emerald-700">قابلیت‌ها</a><a href="#workflow" class="hover:text-emerald-700">نحوه کار</a><a href="{{ route('news.index') }}" class="hover:text-emerald-700">اخبار عمومی</a></nav>
    <a href="{{ url('/admin/login') }}" class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-700">ورود به پنل</a>
  </div>
</header>
<main class="mx-auto max-w-7xl px-4 pb-20 sm:px-6">
  <section class="relative mt-7 overflow-hidden rounded-[2rem] px-6 py-12 text-white shadow-2xl shadow-emerald-950/10 sm:px-12 sm:py-20" style="background:linear-gradient(115deg,#047857,#0284c7)">
    <div class="absolute -left-20 -top-24 h-80 w-80 rounded-full border border-white/15"></div><div class="absolute -bottom-40 right-1/3 h-96 w-96 rounded-full bg-cyan-200/10 blur-3xl"></div>
    <div class="relative grid items-center gap-12 lg:grid-cols-[1.2fr_.8fr]">
      <div><span class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-xs"><i class="h-2 w-2 animate-pulse rounded-full bg-emerald-200"></i>سامانه‌ی رصد و مدیریت محتوای رسانه‌ای</span>
        <h1 class="mt-6 text-4xl font-black leading-[1.5] sm:text-6xl">خبر مهم را<br><span class="text-cyan-100">از میان انبوه محتوا پیدا کنید</span></h1>
        <p class="mt-5 max-w-2xl text-base leading-8 text-white/85 sm:text-lg">رصد، خبرها را از منابع خبری و رسانه‌ای جمع‌آوری می‌کند، بر اساس کلیدواژه‌ها و دسته‌ها پالایش می‌کند و دسترسی به خبرهای مرتبط را در یک محیط یکپارچه فراهم می‌سازد.</p>
        <div class="mt-8 flex flex-wrap gap-3"><a href="{{ url('/admin/login') }}" class="rounded-2xl bg-white px-6 py-3.5 font-extrabold text-emerald-800 shadow-lg transition hover:-translate-y-0.5">ورود به پنل مدیریت <span aria-hidden="true">←</span></a><a href="{{ route('news.index') }}" class="rounded-2xl border border-white/30 bg-white/10 px-6 py-3.5 font-bold text-white transition hover:bg-white/20">مشاهده‌ی خبرهای پیدا شده</a></div>
      </div>
      <div class="hidden lg:block"><div class="relative mx-auto max-w-sm rotate-2 rounded-[2rem] border border-white/20 bg-white/10 p-5 shadow-2xl backdrop-blur-xl">
        <div class="flex items-center justify-between"><div><span class="text-xs text-white/70">نمای کلی سامانه</span><h3 class="mt-1 text-lg font-black">پایش زنده</h3></div><span class="rounded-full bg-emerald-300/20 px-3 py-1 text-xs font-bold text-emerald-100">فعال</span></div>
        <div class="mt-5 space-y-3"><div class="rounded-2xl bg-white/10 p-4"><div class="flex justify-between text-sm"><span>منابع رسانه‌ای</span><span>در یک نگاه</span></div><div class="mt-3 h-2 rounded-full bg-white/15"><div class="h-2 w-4/5 rounded-full bg-cyan-200"></div></div></div><div class="rounded-2xl bg-white/10 p-4"><div class="flex items-center gap-3"><span class="grid h-10 w-10 place-items-center rounded-xl bg-white/15">⌕</span><div><b class="text-sm">پالایش هوشمند خبر</b><p class="mt-1 text-xs text-white/70">کلیدواژه · دسته‌بندی · تشابه</p></div></div></div><div class="rounded-2xl bg-white p-4 text-slate-800"><div class="flex items-center justify-between"><b class="text-sm">خبرهای مرتبط</b><span class="text-xs font-bold text-emerald-700">آماده‌ی بررسی</span></div><div class="mt-3 h-2 w-4/5 rounded-full bg-slate-100"></div><div class="mt-2 h-2 w-3/5 rounded-full bg-slate-100"></div></div></div>
      </div></div>
    </div>
  </section>
  <section id="features" class="py-16 sm:py-20"><div class="mx-auto max-w-2xl text-center"><span class="text-xs font-black tracking-wide text-emerald-700">امکانات سامانه</span><h2 class="mt-3 text-3xl font-black sm:text-4xl">از رصد منبع تا دریافت خبر</h2><p class="mt-4 leading-8 text-slate-500">ابزارهای اصلی پایش در کنار هم قرار گرفته‌اند تا پیگیری خبرها سریع‌تر و منظم‌تر شود.</p></div>
    <div class="mt-9 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      @foreach([
        ['⌕','جمع‌آوری از منابع','رصد RSS، وب‌سایت‌ها و کانال‌های رسانه‌ای در یک سامانه.','emerald'],
        ['✳','پالایش با کلیدواژه','پیدا کردن خبرهای مرتبط با واژه‌های تعریف‌شده و حذف موارد ناخواسته.','sky'],
        ['▦','دسته‌بندی خبرها','گروه‌بندی خبرها برای مرور سریع‌تر و تمرکز روی موضوع‌های مشخص.','violet'],
        ['⟲','تشخیص خبر مشابه','شناسایی بازنشرها و خبرهای مشابه برای کاهش موارد تکراری.','amber'],
        ['◷','پایش و گزارش وضعیت','مشاهده‌ی آخرین وضعیت منابع، اجراها، خطاها و صف پردازش.','cyan'],
        ['➤','ارسال به بله','دریافت خبرهای پیدا شده از طریق اشتراک‌های بله.','rose'],
      ] as [$icon,$title,$desc,$color])
      <article class="glass rounded-3xl p-6 transition hover:-translate-y-1"><span class="grid h-12 w-12 place-items-center rounded-2xl bg-{{ $color }}-50 text-xl font-black text-{{ $color }}-700">{{ $icon }}</span><h3 class="mt-5 text-lg font-black">{{ $title }}</h3><p class="mt-2 text-sm leading-7 text-slate-500">{{ $desc }}</p></article>
      @endforeach
    </div>
  </section>
  <section id="workflow" class="glass grid items-center gap-8 rounded-[2rem] p-6 sm:p-10 lg:grid-cols-2"><div><span class="text-xs font-black text-sky-700">مسیر کار</span><h2 class="mt-3 text-3xl font-black">یک جریان روشن برای پیگیری خبر</h2><p class="mt-4 leading-8 text-slate-500">منابع را ثبت کنید، کلیدواژه‌ها و دسته‌ها را تنظیم کنید و خبرهای پیدا شده را از پنل یا بله دنبال کنید.</p></div><div class="grid gap-3 sm:grid-cols-3">@foreach([['۰۱','تعریف منابع'],['۰۲','تنظیم کلیدواژه'],['۰۳','مرور و دریافت خبر']] as [$n,$label])<div class="rounded-2xl bg-white p-5"><span class="text-xs font-black text-emerald-700">{{ $n }}</span><p class="mt-3 font-extrabold">{{ $label }}</p></div>@endforeach</div></section>
  <section class="mt-12 rounded-[2rem] bg-slate-950 px-6 py-10 text-center text-white sm:px-10"><h2 class="text-2xl font-black sm:text-3xl">خبرهای مرتبط را یک‌جا دنبال کنید</h2><p class="mt-3 text-sm leading-7 text-slate-300">وارد پنل شوید تا وضعیت پایش، آمار و تازه‌ترین خبرهای جمع‌آوری‌شده را ببینید.</p><a href="{{ url('/admin/login') }}" class="mt-6 inline-flex rounded-xl gradient-bg px-5 py-3 font-bold text-white">رفتن به پنل مدیریت</a></section>
</main>
<footer class="border-t border-white/80 bg-white/60 py-6 text-center text-xs text-slate-500">رصد · پایش و دسته‌بندی هوشمند خبرها · محتوای خبر متعلق به منابع منتشرکننده است.</footer>
</div>
<script>Vue.createApp({}).mount('#landing-app')</script>
</body>
</html>
