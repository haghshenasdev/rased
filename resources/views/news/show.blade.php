<!doctype html>
<html lang="fa" dir="rtl">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $sourceItem->title }}</title>
<style>body{font-family:Tahoma,Arial;background:#f5f6f8;margin:0}.box{max-width:900px;margin:30px auto;background:white;border-radius:16px;padding:24px}.hero{width:100%;max-height:480px;object-fit:cover;border-radius:12px}.meta{color:#68737d;font-size:13px;margin:10px 0}.content{line-height:2.2;white-space:pre-wrap}.back{display:inline-block;margin-bottom:18px}</style>
</head>
<body><main class="box">
<a class="back" href="{{ route('home') }}">← بازگشت</a>
@if($sourceItem->featured_image_url)<img class="hero" src="{{ $sourceItem->featured_image_url }}" alt="">@endif
<h1>{{ $sourceItem->title }}</h1>
<div class="meta">
{{ $sourceItem->source?->name }}
@if($sourceItem->category) · {{ $sourceItem->category->name }} @endif
@if($sourceItem->published_at) · {{ \Morilog\Jalali\Jalalian::fromCarbon($sourceItem->published_at->setTimezone('Asia/Tehran'))->format('Y/m/d H:i') }} @endif
@if($sourceItem->is_repost) · 🔁 بازنشر ({{ $sourceItem->similarity_percent }}%) @endif
</div>
<div class="content">{{ $sourceItem->content }}</div>
@if($sourceItem->url)<p><a href="{{ $sourceItem->url }}" target="_blank" rel="noopener">مشاهده مطلب اصلی</a></p>@endif
</main></body></html>
