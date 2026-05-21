<?php

namespace App\Models;

use Database\Factories\ScholarshipProgramFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class ScholarshipProgram extends Model
{
    /** @use HasFactory<ScholarshipProgramFactory> */
    use HasFactory;

    /**
     * Officer ids supplied through legacy mass-assignment payloads.
     *
     * @var array<int, int>|null
     */
    protected ?array $pendingAssignedAdminIds = null;

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
            'published_at' => 'datetime',
        ];
    }

    /**
     * Sync legacy assigned_admin_ids payloads to the normalized pivot table.
     */
    protected static function booted(): void
    {
        static::saved(function (ScholarshipProgram $program): void {
            if ($program->pendingAssignedAdminIds === null || ! Schema::hasTable('scholarship_program_user')) {
                return;
            }

            $program->assignedOfficers()->sync($program->pendingAssignedAdminIds);
            $program->unsetRelation('assignedOfficers');
            $program->pendingAssignedAdminIds = null;
        });
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
     * Get officers assigned to this scholarship program.
     *
     * @return BelongsToMany<User, $this>
     */
    public function assignedOfficers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'scholarship_program_user')
            ->withTimestamps();
    }

    /**
     * Determine the number of unused slots.
     */
    public function availableSlots(): int
    {
        return max(($this->slots ?? 0) - ($this->used_slots ?? 0), 0);
    }

    /**
     * Keep the old assigned_admin_ids attribute API backed by the pivot table.
     */
    protected function assignedAdminIds(): Attribute
    {
        return Attribute::make(
            get: fn (): array => $this->relationLoaded('assignedOfficers')
                ? $this->assignedOfficers->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all()
                : $this->assignedOfficers()->pluck('users.id')->map(static fn (mixed $id): int => (int) $id)->all(),
            set: function (mixed $value): array {
                $this->pendingAssignedAdminIds = $this->normalizeUserIds($value);

                return [];
            },
        );
    }

    /**
     * @return array<int, int>
     */
    private function normalizeUserIds(mixed $userIds): array
    {
        if (! is_array($userIds)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $userId): int => (int) $userId, $userIds),
            static fn (int $userId): bool => $userId > 0,
        )));
    }
}
