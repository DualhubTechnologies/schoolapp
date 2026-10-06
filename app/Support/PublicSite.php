<?php

namespace App\Support;

use App\Models\DemoRequest;
use App\Models\Plan;

/**
 * What every public page (home, About, Features, Pricing, Contact, Help)
 * needs: the sign-in and register links, plans and prices, and how to
 * reach the SchoolHub team. One place, so the pages always agree.
 */
class PublicSite
{
    /** The public pages besides the home page: path => [menu label, page title, description]. */
    public const PAGES = [
        'features' => ['Features', 'Features — School management for Ugandan schools', 'Students, fees, exams and report cards, ID cards, attendance, payroll and finance in one system, with the calculations done for you.'],
        'pricing' => ['Pricing', 'Pricing — Simple per-term plans', 'SchoolHub plans by number of learners, paid per term or per year in Uganda shillings. Every plan includes every module and starts with a free trial.'],
        'about' => ['About', 'About SchoolHub — Made in Uganda for Ugandan schools', 'Who builds SchoolHub, why, and how we look after your school\'s data.'],
        'team' => ['Team', 'SchoolHub Team — The people behind SchoolHub', 'Meet the team at FERO TECH SMC LIMITED who build SchoolHub and support Ugandan schools.'],
        'help' => ['Help', 'Help — Getting started and common questions', 'How to set up SchoolHub, import learners, and answers to common questions from schools.'],
        'contact' => ['Contact', 'Contact SchoolHub — Call, WhatsApp or book a demo', 'Call or WhatsApp the SchoolHub team, visit us in Wakiso, or book a free demo for your school.'],
    ];

    /**
     * @return array<string, mixed>
     */
    public static function viewData(): array
    {
        $contact = config('contact');
        $markup = 1 + (float) config('subscriptions.landing_price_markup', 0) / 100;
        $plans = Plan::where('is_active', true)->where('is_trial', false)->orderBy('sort_order')->get();
        $signedIn = filament()->auth()->check();
        $registerUrl = filament()->getRegistrationUrl();
        $dashboardUrl = filament()->getUrl();

        return [
            'plans' => $plans,
            'trialDays' => (int) config('subscriptions.trial_days', 30),
            'graceDays' => (int) config('subscriptions.grace_days', 14),
            'contact' => $contact,
            'learnerRanges' => DemoRequest::LEARNERS,
            'contactMethods' => DemoRequest::CONTACT_METHODS,
            'signedIn' => $signedIn,
            'loginUrl' => filament()->getLoginUrl(),
            'registerUrl' => $registerUrl,
            'dashboardUrl' => $dashboardUrl,
            'startUrl' => $signedIn ? $dashboardUrl : $registerUrl,
            'telUrl' => 'tel:+256'.ltrim((string) preg_replace('/\D/', '', $contact['phone']), '0'),
            'waUrl' => 'https://wa.me/'.$contact['whatsapp'].'?text='.rawurlencode("Hello, I'd like to book a demo of SchoolHub for my school."),
            'mailUrl' => 'mailto:'.$contact['email'].'?subject='.rawurlencode('SchoolHub demo request'),
            // Where "Book a demo" goes: the form on the Contact page (the
            // home page points it at its own form instead).
            'demoUrl' => route('filament.app.site.page', 'contact').'#demo',
            // Prices as advertised: the plan price plus the public-site
            // markup (config/subscriptions.php), to the nearest UGX 1,000.
            'shown' => fn ($amount): float => (float) $amount > 0 ? round((float) $amount * $markup / 1000) * 1000 : (float) $amount,
            'hasYearly' => $plans->contains(fn ($p) => $p->price_per_year > 0),
            'popular' => $plans->count() >= 3 ? $plans->values()[1]->id : null,
            'check' => '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/></svg>',
            'arrow' => '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd"/></svg>',
        ];
    }
}
