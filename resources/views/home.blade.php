<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ config('app.name', 'راصد') }}</title>
<style>
body{font-family:Tahoma,Arial,sans-serif;background:#f4f6f8;margin:0;color:#17202a}
.container{max-width:1280px;margin:auto;padding:20px}
h1{margin:0 0 16px}.sources{display:flex;gap:10px;overflow:auto;padding:6px 0 18px}
.source{background:#fff;border-radius:14px;padding:9px 12px;display:flex;align-items:center;gap:8px;white-space:nowrap;border:1px solid #e5e7eb}
.source img{width:42px;height:42px;border-radius:50%;object-fit:cover}.source .fallback{width:42px;height:42px;border-radius:50%;display:grid;place-items:center;background:#e8eef5}
.filters{background:#fff;padding:14px;border-radius:14px;margin-bottom:16px;display:flex;gap:8px}.filters input{flex:1;padding:10px;border:1px solid #ddd;border-radius:8px}.filters button,.filters a{padding:10px 14px;border:0;border-radius:8px;text-decoration:none;background:#17202a;color:white}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(310px,1fr));gap:16px}.card{background:#fff;border-radius:16px;overflow:hidden;border:1px solid #e5e7eb}.card img{width:100%;height:180px;object-fit:cover}.body{padding:15px}.meta{font-size:12px;color:#68737d;margin-bottom:8px}.badge{display:inline-block;padding:3px 7px;border-radius:999px;background:#eef2ff;margin-left:5px}.repost{background:#fff3cd;color:#805b00}.title{font-size:18px;font-weight:700;line-height:1.7}.desc{font-size:14px;line-height:1.9;color:#48515a;margin-top:8px}.more{display:block;margin-top:12px;text-decoration:none;color:#1769aa}.pagination{margin-top:20px}
</style>
</head>
<body>
<div class="container">
<h1>📰 {{ config('app.name','راصد') }}</h1>

<div class="sources">
@foreach($sources as $source)
<a class="source" href="{{ route('home', ['search'=>$source->name]) }}">
    @if($source->profileImage())
        <img src="{{ $source->profileImage() }}" alt="">
    @else
        <span class="fallback">📡</span>
    @endif
    <span>{{ $source->parent?->name ? $source->parent->name.' / ' : '' }}{{ $source->name }}</span>
</a>
@endforeach
</div>

<form class="filters" method="get">
<input name="search" value="{{ request('search') }}" placeholder="جستجو...">
<button>جستجو</button>
<a href="{{ route('home') }}">همه</a>
</form>

<div class="grid">
@forelse($items as $item)
<article class="card">
@if($item->featured_image_url)
<img src="{{ $item->featured_image_url }}" alt="" loading="lazy">
@endif
<div class="body">
<div class="meta">
{{ $item->source?->name }}
@if($item->category) <span class="badge">{{ $item->category->name }}</span> @endif
@if($item->is_repost) <span class="badge repost">🔁 بازنشر</span> @endif
@if($item->published_at) · {{ \Morilog\Jalali\Jalalian::fromCarbon($item->published_at->setTimezone('Asia/Tehran'))->format('Y/m/d H:i') }} @endif
</div>
<div class="title">{{ $item->title }}</div>
<div class="desc">{{ \Illuminate\Support\Str::limit(strip_tags($item->content ?? $item->matched_content ?? ''), 260) }}</div>
<a class="more" href="{{ route('news.show',$item) }}">مشاهده خبر ←</a>
</div>
</article>
@empty
<p>خبری پیدا نشد.</p>
@endforelse
</div>
<div class="pagination">{{ $items->links() }}</div>
</div>
</body>
</html>
