<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScholarComplianceSubmission extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'scholar_id',
        'scholarship_application_id',
        'semester',
        'academic_year',
        'status',
        'coe_status',
        'cor_status',
        'grades_status',
        'gpa',
        'submissions',
        'grades',
        'officer_notes',
        'submitted_at',
        'reviewed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gpa' => 'float',
            'submissions' => 'array',
            'grades' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * Get the scholar that owns this submission.
     *
     * @return BelongsTo<Scholar, $this>
     */
    public function scholar(): BelongsTo
    {
        return $this->belongsTo(Scholar::class);
    }

    /**
     * Get the application linked to this submission.
     *
     * @return BelongsTo<ScholarshipApplication, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(ScholarshipApplication::class, 'scholarship_application_id');
    }
}