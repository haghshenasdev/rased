@extends('panel.layout')
@section('title', 'ارسال خبر به بله')
@section('crumb', 'مدیریت خبرها')
@section('heading', 'ارسال خبر به بله')
@section('content')
<div class="mx-auto max-w-3xl space-y-5">
  <article class="glass rounded-2xl p-5 sm:p-6"><span class="text-xs font-black text-emerald-700">ارسال دستی خبر</span><h2 class="mt-2 text-lg font-black leading-8">{{ $item->title }}</h2><p class="mt-2 text-xs text-slate-500">{{ $item->source?->name ?? 'منبع نامشخص' }} @if($item->matched_keyword) · کلیدواژه: {{ $item->matched_keyword }}@endif</p><p class="mt-4 text-sm leading-7 text-slate-600">خبر پس از تأیید در صف ارسال قرار می‌گیرد. در پیام بله، عنوان و بخش مرتبط خبر به‌همراه لینک منبع نمایش داده می‌شود.</p></article>
  <form method="POST" action="{{ route('panel.manage.source-items.send',$item->id) }}" class="glass space-y-4 rounded-2xl p-5 sm:p-6" onsubmit="return confirm('خبر برای مشترکان انتخاب‌شده در صف ارسال قرار بگیرد؟')">
    @csrf
    <div class="flex items-center justify-between gap-3"><div><h3 class="font-black">انتخاب مشترکان</h3><p class="mt-1 text-xs text-slate-500">فقط مشترکان فعال و متصل نمایش داده می‌شوند.</p></div><label class="flex items-center gap-2 text-xs font-bold"><input id="select-all-subscribers" type="checkbox" class="rounded border-slate-300">انتخاب همه</label></div>
    @error('subscriber_ids')<p class="rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-700">{{ $message }}</p>@enderror
    @if($subscribers->isEmpty())<div class="rounded-xl bg-amber-50 p-4 text-sm text-amber-800">مشترک فعالی به ربات متصل نیست. <a href="{{ route('panel.manage.index','bale-subscribers') }}" class="font-extrabold underline">مدیریت مشترکان</a></div>
    @else<div class="grid gap-2 sm:grid-cols-2">@foreach($subscribers as $subscriber)<label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-100 bg-white p-3 hover:border-emerald-200"><input class="subscriber-checkbox rounded border-slate-300 text-emerald-600" type="checkbox" name="subscriber_ids[]" value="{{ $subscriber->id }}" @checked(in_array($subscriber->id, old('subscriber_ids',[])))><span class="min-w-0"><b class="block truncate text-sm">{{ $subscriber->name }}</b><small class="text-xs text-slate-400">{{ $subscriber->phone ?: 'بدون شماره تماس' }} · chat {{ $subscriber->chat_id }}</small></span></label>@endforeach</div>@endif
    <div class="flex flex-wrap gap-2 border-t border-slate-100 pt-4"><button @disabled($subscribers->isEmpty()) class="rounded-xl gradient-bg px-5 py-3 text-sm font-extrabold text-white disabled:opacity-50">تأیید و افزودن به صف</button><a href="{{ route('panel.manage.index','source-items') }}" class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-600">بازگشت</a></div>
  </form>
</div>
<script>
document.getElementById('select-all-subscribers')?.addEventListener('change', event => document.querySelectorAll('.subscriber-checkbox').forEach(box => box.checked = event.target.checked));
</script>
@endsection
