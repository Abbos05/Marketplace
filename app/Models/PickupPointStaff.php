<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PickupPointStaff extends Model
{
    public const TYPE_JOIN = 'join';

    public const TYPE_OPEN = 'open';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $table = 'pickup_point_staff';

    protected $fillable = [
        'user_id',
        'pickup_point_id',
        'type',
        'status',
        'contact_name',
        'contact_phone',
        'inn',
        'org_type',
        'legal_name',
        'proposed_title',
        'proposed_address',
        'proposed_region_id',
        'working_hours',
        'premises_info',
        'application_comment',
        'consent_accepted_at',
        'reviewed_by',
        'reviewed_at',
        'reject_reason',
    ];

    protected function casts(): array
    {
        return [
            'consent_accepted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'working_hours' => 'array',
        ];
    }

    public static function mapForAdmin(self $s): array
    {
        return [
            'id' => $s->id,
            'type' => $s->type,
            'created_at' => $s->created_at,
            'contact_name' => $s->contact_name,
            'contact_phone' => $s->contact_phone,
            'inn' => $s->inn,
            'org_type' => $s->org_type,
            'legal_name' => $s->legal_name,
            'proposed_title' => $s->proposed_title,
            'proposed_address' => $s->proposed_address,
            'proposed_region_name' => $s->proposedRegion?->name,
            'premises_info' => $s->premises_info,
            'working_hours' => $s->working_hours,
            'application_comment' => $s->application_comment,
            'user' => $s->user ? [
                'id' => $s->user->id,
                'name' => $s->user->name,
                'last_name' => $s->user->last_name,
                'email' => $s->user->email,
                'phone' => $s->user->phone,
                'avatar' => $s->user->avatar,
            ] : null,
            'pickup_point' => $s->pickupPoint ? [
                'id' => $s->pickupPoint->id,
                'title' => $s->pickupPoint->title,
                'address' => $s->pickupPoint->address,
            ] : null,
        ];
    }

    public static function mapForUserDetail(self $s): array
    {
        return array_merge(self::mapForAdmin($s), [
            'status' => $s->status,
            'reject_reason' => $s->reject_reason,
            'reviewed_at' => $s->reviewed_at,
            'consent_accepted_at' => $s->consent_accepted_at,
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function pickupPoint(): BelongsTo
    {
        return $this->belongsTo(PickupPoint::class, 'pickup_point_id');
    }

    public function proposedRegion(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'proposed_region_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public static function pickupPointHasApprovedStaff(int $pickupPointId): bool
    {
        return static::query()
            ->where('pickup_point_id', $pickupPointId)
            ->where('status', self::STATUS_APPROVED)
            ->exists();
    }
}
