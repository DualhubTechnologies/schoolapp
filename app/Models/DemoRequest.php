<?php

namespace App\Models;

use App\Services\SmsSender;
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
        'spam' => 'Spam',
    ];

    /**
     * Sales pitches sent through the form ("Hello schoolhubug.com, I
     * wanted to reach out...") carry a link; a school asking for a demo
     * almost never does. They are kept, but filed as spam.
     */
    public static function looksLikeSpam(?string $message): bool
    {
        return (bool) preg_match('~(https?://|www\.)~i', (string) $message);
    }

    /** wa.me link to reply to this person on WhatsApp. */
    public function whatsappUrl(): string
    {
        // 0772…, 772… and +256 772… all become 256772…; anything else
        // (a foreign number) is used as typed.
        $digits = ltrim((string) SmsSender::normalisePhone($this->phone), '+')
            ?: preg_replace('/\D/', '', $this->phone);

        return 'https://wa.me/'.$digits.'?text='.rawurlencode("Hello {$this->name}, thank you for your interest in SchoolHub for {$this->school_name}.");
    }
}
