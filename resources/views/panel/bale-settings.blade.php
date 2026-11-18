@extends('panel.layout')
@section('title', 'تنظیمات ربات بله')
@section('crumb', 'تنظیمات ربات')
@section('heading', 'تنظیمات ربات بله')
@section('content')
<div class="mx-auto max-w-4xl space-y-5">
  @if(session('success'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">{{ session('success') }}</div>@endif
  @if(session('error') || $error)<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-800">{{ session('error') ?? $error }}</div>@endif
  <section class="glass rounded-2xl p-5 sm:p-7"><div class="flex items-start justify-between gap-4"><div><span class="text-xs font-black text-emerald-700">اتصال و دریافت پیام</span><h2 class="mt-1 text-xl font-black">وضعیت وب‌هوک</h2><p class="mt-2 text-sm leading-7 text-slate-500">وب‌هوک پیام‌های اتصال مشترکان را به سامانه می‌رساند.</p></div><span class="rounded-full px-3 py-1.5 text-xs font-extrabold {{ $currentUrl ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $currentUrl ? 'فعال' : 'تنظیم نشده' }}</span></div>
    @if($currentUrl)<div class="mt-5 rounded-xl bg-slate-50 p-4"><small class="text-xs font-bold text-slate-400">نشانی ثبت‌شده</small><p class="mt-1 break-all text-sm font-semibold" dir="ltr">{{ $currentUrl }}</p></div>@endif
    <form method="POST" action="{{ route('panel.bale-settings.webhook') }}" class="mt-5 space-y-3" onsubmit="return confirm('نشانی وب‌هوک ربات با مقدار جدید جایگزین شود؟')">@csrf<label class="block text-sm font-bold">نشانی وب‌هوک<input type="url" name="webhook_url" required value="{{ old('webhook_url',$webhookUrl) }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-emerald-400" dir="ltr"></label>@error('webhook_url')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror<button class="rounded-xl gradient-bg px-5 py-3 text-sm font-bold text-white">تنظیم وب‌هوک</button></form>
    @if($currentUrl)<form method="POST" action="{{ route('panel.bale-settings.webhook.delete') }}" class="mt-3" onsubmit="return confirm('وب‌هوک ربات حذف شود؟')">@csrf @method('DELETE')<button class="rounded-xl border border-rose-200 bg-white px-5 py-3 text-sm font-bold text-rose-700">حذف وب‌هوک</button></form>@endif
  </section>
  <section class="glass flex flex-col justify-between gap-4 rounded-2xl p-5 sm:flex-row sm:items-center sm:p-7"><div><h2 class="font-black">آزمایش ارسال پیام</h2><p class="mt-1 text-sm leading-7 text-slate-500">پیام کوتاهی به مقصد تعیین‌شده در تنظیمات محیط ارسال می‌کند.</p></div><form method="POST" action="{{ route('panel.bale-settings.test-message') }}" onsubmit="return confirm('یک پیام آزمایشی به مقصد تنظیم‌شده در بله ارسال شود؟')">@csrf<button class="rounded-xl bg-slate-950 px-5 py-3 text-sm font-bold text-white hover:bg-emerald-700">ارسال پیام آزمایشی</button></form></section>
  <p class="px-1 text-xs leading-6 text-slate-400">توکن ربات در پنل نمایش داده نمی‌شود و از تنظیمات محیط برنامه خوانده می‌شود.</p>
</div>
@endsection
