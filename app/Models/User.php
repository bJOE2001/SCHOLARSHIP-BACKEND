<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Program ids supplied through legacy mass-assignment payloads.
     *
     * @var array<int, int>|null
     */
    protected ?array $pendingAssignedProgramIds = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        'avatar',
        'department',
        'student_id',
        'birth_date',
        'gender',
        'civil_status',
        'citizenship',
        'address',
        'barangay',
        'city',
        'province',
        'contact_number',
        'campus',
        'school_name',
        'course',
        'year_level',
        'semester',
        'academic_year',
        'gpa',
        'family_income',
        'enrollment_status',
        'academic_awards',
        'father_name',
        'mother_name',
        'guardian_name',
        'parent_occupation',
        'monthly_income',
        'siblings',
        'studying_siblings',
        'income_bracket',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'birth_date' => 'date',
            'gpa' => 'float',
            'family_income' => 'float',
            'siblings' => 'integer',
            'studying_siblings' => 'integer',
            'password' => 'hashed',
        ];
    }

    /**
     * Sync legacy assigned_program_ids payloads to the normalized pivot table.
     */
    protected static function booted(): void
    {
        static::saved(function (User $user): void {
            if ($user->pendingAssignedProgramIds === null || ! Schema::hasTable('scholarship_program_user')) {
                return;
            }

            $user->assignedPrograms()->sync($user->pendingAssignedProgramIds);
            $user->unsetRelation('assignedPrograms');
            $user->pendingAssignedProgramIds = null;
        });
    }

    /**
     * Get the scholarship applications for the user.
     *
     * @return HasMany<ScholarshipApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(ScholarshipApplication::class, 'applicant_id');
    }

    /**
     * Get the applications reviewed by the user.
     *
     * @return HasMany<ScholarshipApplication, $this>
     */
    public function reviewedApplications(): HasMany
    {
        return $this->hasMany(ScholarshipApplication::class, 'reviewed_by_id');
    }

    /**
     * Get the scholar profile linked to the user.
     *
     * @return HasOne<Scholar, $this>
     */
    public function scholarRecord(): HasOne
    {
        return $this->hasOne(Scholar::class);
    }

    /**
     * Get the notifications for the user.
     *
     * @return HasMany<ScholarshipNotification, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(ScholarshipNotification::class);
    }

    /**
     * Get scholarship programs assigned to this officer.
     *
     * @return BelongsToMany<ScholarshipProgram, $this>
     */
    public function assignedPrograms(): BelongsToMany
    {
        return $this->belongsToMany(ScholarshipProgram::class, 'scholarship_program_user')
            ->withTimestamps();
    }

    /**
     * Determine whether the user can access the officer workspace.
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, ['head_officer', 'officer'], true);
    }

    /**
     * Determine whether the user can access every scholarship program and manage system users.
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'head_officer';
    }

    /**
     * Determine whether the user is a student.
     */
    public function isStudent(): bool
    {
        return $this->role === 'student';
    }

    /**
     * Determine whether the user is a scholarship officer.
     */
    public function isOfficer(): bool
    {
        return in_array($this->role, ['head_officer', 'officer'], true);
    }

    /**
     * Ensure avatars always have a usable fallback.
     */
    protected function avatar(): Attribute
    {
        return Attribute::get(function (?string $value): string {
            if ($value !== null && $value !== '') {
                return $value;
            }

            return $this->initialsFromName($this->name ?: $this->email ?: 'U');
        });
    }

    /**
     * Keep the old assigned_program_ids attribute API backed by the pivot table.
     */
    protected function assignedProgramIds(): Attribute
    {
        return Attribute::make(
            get: fn (): array => $this->relationLoaded('assignedPrograms')
                ? $this->assignedPrograms->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all()
                : $this->assignedPrograms()->pluck('scholarship_programs.id')->map(static fn (mixed $id): int => (int) $id)->all(),
            set: function (mixed $value): array {
                $this->pendingAssignedProgramIds = $this->normalizeProgramIds($value);

                return [];
            },
        );
    }

    /**
     * Build initials from a display name.
     */
    protected function initialsFromName(string $name): string
    {
        $initials = collect(explode(' ', $name))
            ->filter()
            ->map(fn (string $part): string => mb_substr($part, 0, 1))
            ->take(2)
            ->implode('');

        return strtoupper($initials ?: 'U');
    }

    /**
     * @return array<int, int>
     */
    private function normalizeProgramIds(mixed $programIds): array
    {
        if (! is_array($programIds)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $programId): int => (int) $programId, $programIds),
            static fn (int $programId): bool => $programId > 0,
        )));
    }
}
