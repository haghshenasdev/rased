<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>راصد | پایش هوشمند اخبار</title>

    @vite([
        'resources/css/home.css',
        'resources/js/app.js'
    ])

</head>

<body>

<div class="background"></div>

<header class="site-header">

    <div class="container">

        <nav class="navbar glass">

            <a
                href="{{ route('home') }}"
                class="logo"
            >

                <div class="logo-icon">
                    ر
                </div>

                <div>

                    <div class="logo-title">
                        راصد
                    </div>

                    <div class="logo-subtitle">
                        سامانه پایش اخبار
                    </div>

                </div>

            </a>


            <div class="nav-actions">

                <a
                    href="{{ route('home') }}"
                    class="nav-link"
                >
                    اخبار
                </a>

                <a
                    href="#about"
                    class="nav-link"
                >
                    درباره راصد
                </a>

            </div>

        </nav>

    </div>

</header>


<section class="hero">

    <div class="container">

        <div class="status-pill">

            <span class="status-dot"></span>

            پایش لحظه‌ای منابع خبری

        </div>


        <h1>

            تمام اخبار مهم،

            <br>

            <span class="gradient-text">
                یکجا زیر نظر شما
            </span>

        </h1>


        <p class="hero-description">

            راصد منابع خبری و کانال‌های مختلف را
            پایش می‌کند و مطالب مرتبط با کلمات
            کلیدی را به صورت هوشمند جمع‌آوری می‌کند.

        </p>


        <form
            action="{{ route('home') }}"
            method="GET"
            class="search-box"
        >

            <span class="search-icon">
                🔍
            </span>

            <input
                type="text"
                name="search"
                value="{{ request('search') }}"
                placeholder="جستجو در عنوان، متن و کلمات کلیدی..."
            >

        </form>

    </div>

</section>


<main class="container">

    {{-- Statistics --}}

    <div class="stats">

        <div class="stat glass">

            <div class="stat-number">
                {{ $items->total() }}
            </div>

            <div class="stat-label">
                خبر پیدا شده
            </div>

        </div>


        <div class="stat glass">

            <div class="stat-number">
                {{ \App\Models\Source::where('is_active', true)->count() }}
            </div>

            <div class="stat-label">
                منبع فعال
            </div>

        </div>


        <div class="stat glass">

            <div class="stat-number">
                {{ \App\Models\SourceItem::whereDate('created_at', today())->count() }}
            </div>

            <div class="stat-label">
                خبر امروز
            </div>

        </div>

    </div>


    {{-- Filters --}}

    <div class="filters">

        <a
            href="{{ route('home') }}"
            class="filter {{ !request('period') ? 'active' : '' }}"
        >
            همه
        </a>

        <a
            href="{{ route('home', ['period' => 'today']) }}"
            class="filter {{ request('period') === 'today' ? 'active' : '' }}"
        >
            امروز
        </a>

        <a
            href="{{ route('home', ['period' => 'yesterday']) }}"
            class="filter {{ request('period') === 'yesterday' ? 'active' : '' }}"
        >
            دیروز
        </a>

        <a
            href="{{ route('home', ['period' => 'week']) }}"
            class="filter {{ request('period') === 'week' ? 'active' : '' }}"
        >
            هفته اخیر
        </a>

    </div>


    {{-- News --}}

    <div class="news-grid">

        @forelse($items as $item)

            <article class="news-card glass">

                <div class="news-meta">

                    <div class="source">

                        <div class="source-icon">

                            {{ mb_substr(
                                $item->source->name ?? '؟',
                                0,
                                1
                            ) }}

                        </div>

                        <div>

                            <div class="source-name">
                                {{ $item->source->name ?? 'منبع نامشخص' }}
                            </div>

                            <div class="news-time">

                                {{ $item->published_at?->diffForHumans() }}

                            </div>

                        </div>

                    </div>


                    @if($item->matched_keyword)

                        <span class="keyword">

                            {{ $item->matched_keyword }}

                        </span>

                    @endif

                </div>


                <a
                    href="{{ route('news.show', $item) }}"
                    class="news-title"
                >

                    {{ $item->title }}

                </a>


                @if($item->matched_content)

                    <div class="matched-content">

                        {{ $item->matched_content }}

                    </div>

                @endif


                <div class="news-footer">

                    <span class="news-date">

                        {{ $item->published_at?->format('Y/m/d H:i') }}

                    </span>


                    @if($item->url)

                        <a
                            href="{{ $item->url }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="source-link"
                        >
                            مشاهده منبع ←
                        </a>

                    @endif

                </div>

            </article>

        @empty

            <div class="empty glass">

                <div class="empty-icon">
                    🔎
                </div>

                <div class="empty-title">
                    خبری پیدا نشد
                </div>

                <div class="empty-text">
                    هنوز مطلبی مطابق معیارهای جستجو پیدا نشده است.
                </div>

            </div>

        @endforelse

    </div>


    <div class="pagination">

        {{ $items->links() }}

    </div>

</main>


<footer
    id="about"
    class="site-footer"
>

    <div class="container">

        راصد · سامانه پایش و گردآوری هوشمند اخبار

    </div>

</footer>

</body>

</html>
