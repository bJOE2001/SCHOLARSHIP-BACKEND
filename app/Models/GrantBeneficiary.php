<?php

namespace App\Models;

use Database\Factories\GrantBeneficiaryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrantBeneficiary extends Model
{
    /** @use HasFactory<GrantBeneficiaryFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'grant_batch_id',
        'scholar_id',
        'user_id',
        'released_by_id',
        'scholar_identifier',
        'scholar_name',
        'barangay',
        'course',
        'amount',
        'assigned_claim_date',
        'time_slot',
        'claim_status',
        'notified_at',
        'claimed_at',
        'released_by_name',
        'reference_number',
        'claim_method',
        'release_remarks',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'assigned_claim_date' => 'date',
            'notified_at' => 'datetime',
            'claimed_at' => 'datetime',
        ];
    }

    /**
     * Get the grant batch linked to this beneficiary.
     *
     * @return BelongsTo<GrantBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(GrantBatch::class, 'grant_batch_id');
    }

    /**
     * Get the scholar record linked to this beneficiary.
     *
     * @return BelongsTo<Scholar, $this>
     */
    public function scholar(): BelongsTo
    {
        return $this->belongsTo(Scholar::class);
    }

    /**
     * Get the student user linked to this beneficiary.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the officer who released this grant.
     *
     * @return BelongsTo<User, $this>
     */
    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by_id');
    }
}
