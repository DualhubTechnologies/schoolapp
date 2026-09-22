<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A demo booking or enquiry sent from the landing page.
 */
class DemoRequest extends Model
{
    protected $fillable = [
        'name',
        'school_name',
        'phone',
        'email',
        'learners',
        'preferred_date',
        'preferred_contact',
        'message',
        'status',
        'notes',
        'ip_address',
    ];

    protected function casts(): array
    {
        return ['preferred_date' => 'date'];
    }

    public const LEARNERS = [
        'under-300' => 'Under 300',
        '300-800' => '300 – 800',
        '800-1500' => '800 – 1,500',
        'over-1500' => 'Over 1,500',
    ];

    public const CONTACT_METHODS = [
        'whatsapp' => 'WhatsApp',
        'call' => 'Phone call',
        'email' => 'Email',
    ];

    public const STATUSES = [
        'new' => 'New',
        'contacted' => 'Contacted',
        'booked' => 'Demo booked',
        'closed' => 'Closed',
    ];

    /** wa.me link to reply to this person on WhatsApp (Ugandan numbers). */
    public function whatsappUrl(): string
    {
        $digits = preg_replace('/\D/', '', $this->phone);

        if (str_starts_with($digits, '0')) {
            $digits = '256' . substr($digits, 1);
        }

        return 'https://wa.me/' . $digits . '?text=' . rawurlencode("Hello {$this->name}, thank you for your interest in SchoolHub for {$this->school_name}.");
    }
}
