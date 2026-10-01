<?php

namespace App\Http\Controllers;

use App\Support\PublicSite;
use Illuminate\Contracts\View\View;

/**
 * The public site: the home page at the site root (what SchoolHub does,
 * what it costs, and the way in), and the About, Features, Pricing, Help
 * and Contact pages, which reuse its sections.
 */
class LandingController extends Controller
{
    public function __invoke(): View
    {
        // On the home page "Book a demo" scrolls to its own form.
        return view('landing', [...PublicSite::viewData(), 'demoUrl' => '#demo']);
    }

    public function page(string $page): View
    {
        abort_unless(array_key_exists($page, PublicSite::PAGES), 404);

        [, $title, $description] = PublicSite::PAGES[$page];

        return view("site.{$page}", [
            ...PublicSite::viewData(),
            'page' => $page,
            'title' => $title,
            'description' => $description,
        ]);
    }
}
