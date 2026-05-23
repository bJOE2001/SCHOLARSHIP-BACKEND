<?php

namespace App\Models;

use Database\Factories\SemesterRequirementDraftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SemesterRequirementDraft extends Model
{
    /** @use HasFactory<SemesterRequirementDraftFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'scholar_id',
        'scholarship_application_id',
        'status',
        'grades',
        'computed_average',
        'submitted_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'grades' => 'array',
            'computed_average' => 'float',
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * Get the user who owns this draft.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the scholar record linked to this draft.
     *
     * @return BelongsTo<Scholar, $this>
     */
    public function scholar(): BelongsTo
    {
        return $this->belongsTo(Scholar::class);
    }

    /**
     * Get the application linked to this draft.
     *
     * @return BelongsTo<ScholarshipApplication, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(ScholarshipApplication::class, 'scholarship_application_id');
    }
}
