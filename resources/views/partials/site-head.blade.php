{{--
    What search engines and link previews read on SchoolHub's public pages:
    the icon in every size Google and phones ask for (Google shows a plain
    globe in results without a favicon it can use, at least 48px), the one
    address to list the page under (the site answers on both
    schoolhubug.com and www.schoolhubug.com), a share image, and who
    publishes the site with its logo.

    $path: this page's path, e.g. '/' or '/terms-and-conditions'.
--}}
@php
    $site = rtrim((string) config('app.url'), '/');
    $canonical = $site.($path === '/' ? '/' : '/'.ltrim($path, '/'));
    $logo = $site.'/images/schoolhub-icon-512.png';
@endphp
<link rel="canonical" href="{{ $canonical }}">
<link rel="icon" href="{{ $site }}/favicon.ico" sizes="16x16 32x32 48x48">
<link rel="icon" type="image/png" sizes="48x48" href="{{ $site }}/images/schoolhub-icon-48.png">
<link rel="icon" type="image/png" sizes="192x192" href="{{ $site }}/images/schoolhub-icon-192.png">
<link rel="icon" type="image/svg+xml" href="{{ $site }}/images/schoolhub-icon.svg">
<link rel="apple-touch-icon" href="{{ $site }}/apple-touch-icon.png">
<meta property="og:site_name" content="SchoolHub">
<meta property="og:image" content="{{ $logo }}">
<meta name="twitter:card" content="summary">
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Organization',
    'name' => 'SchoolHub',
    'url' => $site.'/',
    'logo' => $logo,
    'email' => config('contact.email'),
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
</script>
