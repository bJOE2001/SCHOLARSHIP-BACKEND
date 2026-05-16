<?php

namespace App\Models;

use Database\Factories\ScholarshipProgramFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScholarshipProgram extends Model
{
    /** @use HasFactory<ScholarshipProgramFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'provider',
        'category',
        'type',
        'description',
        'eligibility_summary',
        'status',
        'slots',
        'used_slots',
        'budget',
        'schedule',
        'eligibility',
        'requirements',
        'scoring_criteria',
        'renewal_rules',
        'assigned_admin_ids',
        'published_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'slots' => 'integer',
            'used_slots' => 'integer',
            'budget' => 'integer',
            'schedule' => 'array',
            'eligibility' => 'array',
            'requirements' => 'array',
            'scoring_criteria' => 'array',
            'renewal_rules' => 'array',
            'assigned_admin_ids' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Get the applications linked to the scholarship program.
     *
     * @return HasMany<ScholarshipApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(ScholarshipApplication::class);
    }

    /**
     * Get the scholars linked to the scholarship program.
     *
     * @return HasMany<Scholar, $this>
     */
    public function scholars(): HasMany
    {
        return $this->hasMany(Scholar::class);
    }

    /**
     * Determine the number of unused slots.
     */
    public function availableSlots(): int
    {
        return max(($this->slots ?? 0) - ($this->used_slots ?? 0), 0);
    }
}
