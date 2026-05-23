<?php

namespace App\Models;

use Database\Factories\GrantBatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GrantBatch extends Model
{
    /** @use HasFactory<GrantBatchFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'scholarship_program_id',
        'created_by_id',
        'title',
        'semester',
        'school_year',
        'amount',
        'claiming_start_date',
        'claiming_end_date',
        'venue',
        'daily_limit',
        'remarks',
        'status',
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
            'claiming_start_date' => 'date',
            'claiming_end_date' => 'date',
            'daily_limit' => 'integer',
        ];
    }

    /**
     * Get the scholarship program linked to this grant batch.
     *
     * @return BelongsTo<ScholarshipProgram, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(ScholarshipProgram::class, 'scholarship_program_id');
    }

    /**
     * Get the user who created this grant batch.
     *
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * Get the beneficiaries linked to this grant batch.
     *
     * @return HasMany<GrantBeneficiary, $this>
     */
    public function beneficiaries(): HasMany
    {
        return $this->hasMany(GrantBeneficiary::class);
    }

    /**
     * Get the published grant announcement for this batch.
     *
     * @return HasOne<GrantAnnouncement, $this>
     */
    public function announcement(): HasOne
    {
        return $this->hasOne(GrantAnnouncement::class);
    }
}
