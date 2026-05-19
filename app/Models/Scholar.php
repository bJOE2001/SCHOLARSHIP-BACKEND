<?php

namespace App\Models;

use Database\Factories\ScholarFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Scholar extends Model
{
    /** @use HasFactory<ScholarFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'scholarship_program_id',
        'scholarship_application_id',
        'scholar_id',
        'name',
        'avatar',
        'program',
        'course',
        'year_level',
        'school',
        'gender',
        'birthdate',
        'address',
        'contact_number',
        'email',
        'gpa',
        'enrollment_status',
        'academic_year',
        'semester',
        'scholarship_status',
        'renewal_status',
        'date_approved',
        'duration',
        'compliance_status',
        'compliance_rate',
        'risk_label',
        'risk_reason',
        'recommended_action',
        'submissions',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'date_approved' => 'date',
            'gpa' => 'float',
            'compliance_rate' => 'integer',
            'submissions' => 'array',
        ];
    }

    /**
     * Get the student profile linked to the scholar.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the scholarship program linked to the scholar.
     *
     * @return BelongsTo<ScholarshipProgram, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(ScholarshipProgram::class, 'scholarship_program_id');
    }

    /**
     * Get the application linked to the scholar.
     *
     * @return BelongsTo<ScholarshipApplication, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(ScholarshipApplication::class, 'scholarship_application_id');
    }

    /**
     * Get semester compliance submissions for this scholar.
     *
     * @return HasMany<ScholarComplianceSubmission, $this>
     */
    public function complianceSubmissions(): HasMany
    {
        return $this->hasMany(ScholarComplianceSubmission::class);
    }
}

