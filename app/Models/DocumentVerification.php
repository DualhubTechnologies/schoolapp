<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A printed document anyone can check by scanning its QR code (or typing
 * its code) at /verify: a report card, later receipts and ID cards. The
 * summary is what the document said when it was printed. Printing the
 * same document again with the same content keeps its code; printing it
 * with changed content gives it a new code and marks the old one as
 * replaced, so an outdated or altered copy shows up as such.
 *
 * @property int $id
 * @property int $school_id
 * @property string $code
 * @property string $type
 * @property string $subject_key
 * @property array<string, mixed> $summary
 * @property string $fingerprint
 * @property Carbon|null $replaced_at
 * @property Carbon|null $created_at
 */
class DocumentVerification extends Model
{
    public const TYPES = [
        'report_card' => 'Report card',
        'receipt' => 'Fee receipt',
    ];

    /** No 0/O, 1/I/L: easy to read off paper and type. */
    protected const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    protected $fillable = [
        'school_id',
        'code',
        'type',
        'subject_key',
        'summary',
        'fingerprint',
        'replaced_at',
    ];

    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'replaced_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<School, $this> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * The code for this document as it is now: the same one as last time
     * if nothing on it changed, otherwise a new one (and the old one is
     * marked as replaced).
     *
     * @param  array<string, mixed>  $summary
     */
    public static function issue(int $schoolId, string $type, string $subjectKey, array $summary): self
    {
        $fingerprint = hash('sha256', (string) json_encode($summary));

        $current = static::where('school_id', $schoolId)
            ->where('subject_key', $subjectKey)
            ->whereNull('replaced_at')
            ->latest('id')
            ->first();

        if ($current && hash_equals($current->fingerprint, $fingerprint)) {
            return $current;
        }

        $current?->update(['replaced_at' => now()]);

        // The result lines as [label, value] pairs: the database's JSON
        // column re-sorts an object's keys, which would jumble their order.
        $lines = [];
        foreach ((array) ($summary['result'] ?? []) as $label => $value) {
            $lines[] = [(string) $label, (string) $value];
        }
        $summary['result'] = $lines;

        return static::create([
            'school_id' => $schoolId,
            'code' => static::newCode(self::prefixFor($type)),
            'type' => $type,
            'subject_key' => $subjectKey,
            'summary' => $summary,
            'fingerprint' => $fingerprint,
        ]);
    }

    /** The code for a fee receipt, as printed: who paid what, when and how. */
    public static function forReceipt(StudentPayment $payment): self
    {
        $student = $payment->student;

        return static::issue($payment->school_id, 'receipt', 'receipt:'.$payment->getKey(), [
            'school' => (string) $payment->school?->name,
            'learner' => (string) $student?->name,
            'admission_no' => (string) $student?->admission_no,
            'class' => trim(($student->schoolClass->name ?? '').($student?->section ? ' · '.$student->section->name : '')),
            'term' => $payment->term?->label(),
            'result' => [
                'Receipt no.' => (string) $payment->receipt_no,
                'Amount' => 'UGX '.number_format((float) $payment->amount, 0),
                'Paid on' => $payment->paid_on ? Carbon::parse((string) $payment->paid_on)->format('j M Y') : '',
                'Method' => $payment->methodLabel(),
            ],
        ]);
    }

    /**
     * The receipt's payment as it is now, to tell whether the school has
     * since cancelled it. Null for other documents.
     */
    public function payment(): ?StudentPayment
    {
        if ($this->type !== 'receipt' || ! preg_match('/^receipt:(\d+)$/', $this->subject_key, $m)) {
            return null;
        }

        $payment = StudentPayment::withVoided()->where('school_id', $this->school_id)->find((int) $m[1]);

        return $payment instanceof StudentPayment ? $payment : null;
    }

    /** "RC-7KQ4-M2XP": a prefix for the kind of document and 8 random characters. */
    protected static function newCode(string $prefix): string
    {
        do {
            $chars = '';
            for ($i = 0; $i < 8; $i++) {
                $chars .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            $code = $prefix.'-'.substr($chars, 0, 4).'-'.substr($chars, 4);
        } while (static::where('code', $code)->exists());

        return $code;
    }

    protected static function prefixFor(string $type): string
    {
        return match ($type) {
            'report_card' => 'RC',
            'receipt' => 'RT',
            default => 'DOC',
        };
    }

    /** A typed code tidied up: "rc 7kq4m2xp" → "RC-7KQ4-M2XP". */
    public static function normalise(string $typed): string
    {
        $clean = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $typed));

        return preg_match('/^([A-Z]{2,3})([A-Z0-9]{4})([A-Z0-9]{4})$/', $clean, $m) ? "{$m[1]}-{$m[2]}-{$m[3]}" : $clean;
    }

    /**
     * The result lines in printed order, as label => value. Codes issued
     * before the lines were kept as pairs hold them as an object instead.
     *
     * @return array<string, string>
     */
    public function resultLines(): array
    {
        $lines = [];

        foreach ((array) ($this->summary['result'] ?? []) as $label => $value) {
            if (is_array($value) && count($value) === 2) {
                $lines[(string) $value[0]] = (string) $value[1];
            } else {
                $lines[(string) $label] = (string) $value;
            }
        }

        return $lines;
    }

    public function url(): string
    {
        return route('verify.show', $this->code);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? 'Document';
    }
}
