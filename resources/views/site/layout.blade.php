{{--
    The public pages other than the home page (About, Features, Pricing,
    Help, Contact): the home page's look, header and footer around each
    page's own content. Served by LandingController::page() with
    App\Support\PublicSite::viewData(), $page, $title and $description.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} | SchoolHub</title>
    <meta name="description" content="{{ $description }}">
    <meta name="theme-color" content="#ffffff">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ rtrim((string) config('app.url'), '/') }}/{{ $page }}">
    @include('partials.site-head', ['path' => '/'.$page])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
@include('site.partials.styles')
    </style>
</head>
<body>

@include('site.partials.header')

<main id="main">
    <section class="page-hero">
        <div class="container">
            <div class="eyebrow">@yield('eyebrow')</div>
            <h1>@yield('heading')</h1>
            <p>@yield('lead')</p>
            @hasSection('hero-actions')
                <div class="hero-actions">@yield('hero-actions')</div>
            @endif
        </div>
    </section>

    @yield('content')

    @unless (($page ?? null) === 'contact')
        @include('site.sections.cta')
    @endunless
</main>

@include('site.partials.footer')
</body>
</html>
