<?php

namespace App\Http\Controllers;

use App\Support\Edition;
use App\Support\PublicSite;
use App\Support\SiteVisits;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The public site: the home page at the site root (what SchoolHub does,
 * what it costs, and the way in), and the About, Features, Pricing, Help
 * and Contact pages, which reuse its sections. Each visit is counted for
 * the platform owner's Website visitors page (App\Support\SiteVisits).
 */
class LandingController extends Controller
{
    public function __invoke(Request $request, SiteVisits $visits): View|RedirectResponse
    {
        // The Windows app has no public website: straight to the school.
        if (Edition::isDesktop()) {
            return redirect()->to(filament()->getPanel('app')->getUrl());
        }

        $visits->recordLater($request, '/');

        // On the home page "Book a demo" scrolls to its own form.
        return view('landing', [...PublicSite::viewData(), 'demoUrl' => '#demo']);
    }

    public function page(Request $request, SiteVisits $visits, string $page): View
    {
        abort_unless(array_key_exists($page, PublicSite::PAGES), 404);

        $visits->recordLater($request, '/'.$page);

        [, $title, $description] = PublicSite::PAGES[$page];

        return view("site.{$page}", [
            ...PublicSite::viewData(),
            'page' => $page,
            'title' => $title,
            'description' => $description,
        ]);
    }
}
