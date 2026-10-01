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
@php
    // Phone in international form, as Google prefers: 0782 863209 -> +256782863209.
    $phone = '+256'.ltrim((string) preg_replace('/\D/', '', (string) config('contact.phone')), '0');

    // Built here, inside @php, because Blade would read "@context" written
    // in the template as one of its own directives and mangle the JSON.
    $organization = json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => 'SchoolHub',
        'url' => $site.'/',
        'logo' => $logo,
        'email' => config('contact.email'),
        'telephone' => $phone,
        'address' => [
            '@type' => 'PostalAddress',
            'addressLocality' => config('contact.locality'),
            'addressCountry' => 'UG',
        ],
        'contactPoint' => [
            '@type' => 'ContactPoint',
            'contactType' => 'customer support',
            'telephone' => $phone,
            'email' => config('contact.email'),
            'areaServed' => 'UG',
            'availableLanguage' => 'English',
            'hoursAvailable' => [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => config('contact.days'),
                'opens' => config('contact.opens'),
                'closes' => config('contact.closes'),
            ],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_HEX_TAG);
@endphp
<script type="application/ld+json">
{!! $organization !!}
</script>
