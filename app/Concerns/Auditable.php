<?php

namespace App\Concerns;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Wires a model into the Audit Trail.
 *
 * Use this instead of repeating the same getActivitylogOptions() body in
 * every model. By default it logs every fillable attribute except the
 * noise listed in $auditIgnore.
 *
 *     use App\Concerns\Auditable;
 *
 *     class House extends Model
 *     {
 *         use Auditable;
 *     }
 *
 * To log a narrower set on a particular model, declare $auditOnly:
 *
 *     protected array $auditOnly = ['name', 'status'];
 *
 * NOTE ON PRIVACY: do not audit-log columns holding sensitive personal
 * detail (medical notes, ID numbers, bank details). The audit trail is
 * visible to admins, and old values are kept indefinitely. Add anything
 * like that to $auditIgnore on the model.
 */
trait Auditable
{
    use LogsActivity;

    /**
     * Columns never worth logging — they change on every save and tell
     * an auditor nothing.
     *
     * @var list<string>
     */
    protected array $auditIgnoreDefaults = [
        'created_at',
        'updated_at',
        'remember_token',
        'password',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        $only = property_exists($this, 'auditOnly') && ! empty($this->auditOnly)
            ? $this->auditOnly
            : array_values(array_diff(
                $this->getFillable(),
                $this->auditIgnoreDefaults,
                property_exists($this, 'auditIgnore') ? $this->auditIgnore : [],
            ));

        return LogOptions::defaults()
            ->logOnly($only)
            // Without this every save writes a full row even when nothing
            // changed, and the table grows fast.
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly([]);
    }
}
