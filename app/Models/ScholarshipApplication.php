<?php

namespace App\Models;

use Database\Factories\ScholarshipApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ScholarshipApplication extends Model
{
    /** @use HasFactory<ScholarshipApplicationFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'scholarship_program_id',
        'applicant_id',
        'application_no',
        'status',
        'risk_label',
        'score',
        'progress',
        'remarks',
        'next_action',
        'missing_requirements',
        'timeline',
        'submitted_at',
        'reviewed_at',
        'reviewed_by_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'progress' => 'integer',
            'missing_requirements' => 'array',
            'timeline' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * Get the scholarship program for the application.
     *
     * @return BelongsTo<ScholarshipProgram, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(ScholarshipProgram::class, 'scholarship_program_id');
    }

    /**
     * Get the applicant for the application.
     *
     * @return BelongsTo<User, $this>
     */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_id');
    }

    /**
     * Get the reviewer for the application.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    /**
     * Get the uploaded documents for the application.
     *
     * @return HasMany<ApplicationDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    /**
     * Get the scholar record linked to the application.
     *
     * @return HasOne<Scholar, $this>
     */
    public function scholarRecord(): HasOne
    {
        return $this->hasOne(Scholar::class);
    }

    /**
     * Append a timeline event to the application.
     */
    public function appendTimelineEvent(string $status, string $remarks): void
    {
        $timeline = $this->timeline ?? [];
        $timeline[] = [
            'status' => $status,
            'label' => $status,
            'remarks' => $remarks,
            'date' => now()->format('M d, Y'),
        ];

        $this->timeline = $timeline;
    }

    /**
     * Replace the current missing requirements list.
     *
     * @param  array<int, string>  $requirements
     */
    public function syncMissingRequirements(array $requirements): void
    {
        $this->missing_requirements = array_values(array_unique(array_filter($requirements)));
    }
}
