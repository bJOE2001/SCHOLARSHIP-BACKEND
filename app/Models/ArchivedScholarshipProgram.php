<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArchivedScholarshipProgram extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'original_scholarship_program_id',
        'archived_by_id',
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
        'maintaining_grade',
        'schedule',
        'eligibility',
        'requirements',
        'requirement_rules',
        'scoring_criteria',
        'renewal_rules',
        'assigned_officer_ids',
        'published_at',
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
            'slots' => 'integer',
            'used_slots' => 'integer',
            'budget' => 'integer',
            'maintaining_grade' => 'float',
            'schedule' => 'array',
            'eligibility' => 'array',
            'requirements' => 'array',
            'requirement_rules' => 'array',
            'scoring_criteria' => 'array',
            'renewal_rules' => 'array',
            'assigned_officer_ids' => 'array',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }
}
