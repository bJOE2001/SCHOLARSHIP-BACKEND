<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArchivedGrantBatch extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'original_grant_batch_id',
        'scholarship_program_id',
        'created_by_id',
        'archived_by_id',
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
        'beneficiaries',
        'archived_at',
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
            'beneficiaries' => 'array',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * Get the scholarship program linked to this archived grant batch.
     *
     * @return BelongsTo<ScholarshipProgram, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(ScholarshipProgram::class, 'scholarship_program_id');
    }
}
