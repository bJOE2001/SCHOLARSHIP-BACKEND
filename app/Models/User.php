<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

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
        'assigned_program_ids',
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
            'assigned_program_ids' => 'array',
            'password' => 'hashed',
        ];
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
     * Determine whether the user is an administrator.
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'officer'], true);
    }

    /**
     * Determine whether the user is a student.
     */
    public function isStudent(): bool
    {
        return $this->role === 'student';
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
}
