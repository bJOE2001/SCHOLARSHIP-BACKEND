<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterStudentRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Mail\StudentRegistrationMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class AuthController extends Controller
{
    private const DEFAULT_OFFICER_PASSWORDS = ['admin', 'password'];

    /**
     * Log a user into the API with a Sanctum token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = User::query()->where('email', $validated['email'])->first();

        if ($user === null || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->status === 'Inactive') {
            throw ValidationException::withMessages([
                'email' => ['This account is inactive.'],
            ]);
        }

        $portal = $validated['portal'] ?? null;

        if ($portal === 'student' && ! $user->isStudent()) {
            throw ValidationException::withMessages([
                'portal' => ['Please use the officer portal for this account.'],
            ]);
        }

        if ($portal === 'officer' && ! $user->isOfficer()) {
            throw ValidationException::withMessages([
                'portal' => ['Please use the student portal for this account.'],
            ]);
        }

        $this->markDefaultOfficerPasswordForChange($user, $validated['password']);

        $deviceName = $validated['deviceName'] ?? 'scholarship-web';
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Register a student account and email a welcome message.
     */
    public function register(RegisterStudentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = User::create($this->buildStudentAttributes($validated));
        $this->sendRegistrationEmail($user);

        return response()->json([
            'message' => 'Registration successful. You can now sign in with your password.',
            'email' => $user->email,
        ], 201);
    }

    /**
     * Reset a user's password back to their birthdate in MMDDYY format.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'string', 'max:255'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();
        $message = 'If that email exists and has a birthdate, the password has been reset to MMDDYY.';

        if ($user === null) {
            return response()->json(['message' => $message]);
        }

        $birthdatePassword = $this->birthdatePassword($user);

        if ($birthdatePassword === '') {
            throw ValidationException::withMessages([
                'email' => ['This account does not have a birthdate saved.'],
            ]);
        }

        $user->forceFill([
            'password' => $birthdatePassword,
        ])->save();
        $user->tokens()->delete();

        return response()->json(['message' => $message]);
    }

    /**
     * Return the currently authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()),
        ]);
    }

    /**
     * Update the currently authenticated user's profile.
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $user->fill($this->mapProfileAttributes($validated));
        $user->save();
        $this->syncScholarProfile($user);

        return response()->json([
            'user' => new UserResource($user->refresh()),
        ]);
    }

    /**
     * Change the authenticated user's password.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        if (! Hash::check($validated['currentPassword'], $user->password)) {
            throw ValidationException::withMessages([
                'currentPassword' => ['The current password is incorrect.'],
            ]);
        }

        if (Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The new password must be different from the current password.'],
            ]);
        }

        $user->forceFill([
            'password' => $validated['password'],
            'force_password_change' => false,
        ])->save();

        $user->tokens()->where('id', '!=', $request->user()?->currentAccessToken()?->id)->delete();

        return response()->json([
            'message' => 'Password changed successfully.',
            'user' => new UserResource($user->refresh()),
        ]);
    }

    /**
     * Revoke the current access token.
     */
    public function logout(Request $request): Response
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->noContent();
    }

    /**
     * Convert the student registration payload into user attributes.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function buildStudentAttributes(array $validated): array
    {
        return [
            'name' => $validated['fullName'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'force_password_change' => false,
            'role' => 'student',
            'status' => 'Active',
            'gender' => $validated['gender'] ?? null,
            'birth_date' => $validated['birthDate'] ?? null,
            'civil_status' => $validated['civilStatus'] ?? null,
            'citizenship' => $validated['citizenship'] ?? null,
            'address' => $validated['address'] ?? null,
            'barangay' => $validated['barangay'] ?? null,
            'city' => $validated['city'] ?? null,
            'province' => $validated['province'] ?? null,
            'contact_number' => $validated['contactNumber'] ?? null,
            'school_name' => $validated['schoolName'] ?? null,
            'student_id' => $validated['studentId'] ?? null,
            'course' => $validated['course'] ?? null,
            'year_level' => $validated['yearLevel'] ?? null,
            'semester' => $validated['semester'] ?? null,
            'academic_year' => $validated['academicYear'] ?? null,
            'gpa' => $validated['gpa'] ?? null,
            'family_income' => $this->resolveFamilyIncome($validated),
            'enrollment_status' => $validated['enrollmentStatus'] ?? null,
            'academic_awards' => $validated['academicAwards'] ?? null,
            'father_name' => $validated['fatherName'] ?? null,
            'mother_name' => $validated['motherName'] ?? null,
            'guardian_name' => $validated['guardianName'] ?? null,
            'parent_occupation' => $validated['parentOccupation'] ?? null,
            'monthly_income' => $validated['monthlyIncome'] ?? null,
            'siblings' => $validated['siblings'] ?? null,
            'studying_siblings' => $validated['studyingSiblings'] ?? null,
            'income_bracket' => $validated['incomeBracket'] ?? null,
        ];
    }

    /**
     * Convert profile payload keys to user database columns.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function mapProfileAttributes(array $validated): array
    {
        $attributeMap = [
            'name' => 'name',
            'email' => 'email',
            'avatar' => 'avatar',
            'department' => 'department',
            'studentId' => 'student_id',
            'birthDate' => 'birth_date',
            'gender' => 'gender',
            'civilStatus' => 'civil_status',
            'citizenship' => 'citizenship',
            'address' => 'address',
            'barangay' => 'barangay',
            'city' => 'city',
            'province' => 'province',
            'contactNumber' => 'contact_number',
            'campus' => 'campus',
            'schoolName' => 'school_name',
            'course' => 'course',
            'yearLevel' => 'year_level',
            'semester' => 'semester',
            'academicYear' => 'academic_year',
            'gpa' => 'gpa',
            'familyIncome' => 'family_income',
            'enrollmentStatus' => 'enrollment_status',
            'academicAwards' => 'academic_awards',
            'fatherName' => 'father_name',
            'motherName' => 'mother_name',
            'guardianName' => 'guardian_name',
            'parentOccupation' => 'parent_occupation',
            'monthlyIncome' => 'monthly_income',
            'siblings' => 'siblings',
            'studyingSiblings' => 'studying_siblings',
            'incomeBracket' => 'income_bracket',
        ];
        $attributes = [];

        foreach ($attributeMap as $payloadKey => $databaseColumn) {
            if (array_key_exists($payloadKey, $validated)) {
                $attributes[$databaseColumn] = $validated[$payloadKey];
            }
        }

        return $attributes;
    }

    /**
     * Keep a student's active scholar profile aligned after profile edits.
     */
    private function syncScholarProfile(User $user): void
    {
        $scholar = $user->scholarRecord;

        if ($scholar === null) {
            return;
        }

        $scholar->update([
            'name' => $user->name,
            'avatar' => $user->avatar,
            'course' => $user->course,
            'year_level' => $user->year_level,
            'school' => $user->school_name ?: $user->campus,
            'gender' => $user->gender,
            'birthdate' => $user->birth_date,
            'address' => $user->address,
            'contact_number' => $user->contact_number,
            'email' => $user->email,
            'gpa' => $user->gpa,
            'enrollment_status' => $user->enrollment_status,
            'academic_year' => $user->academic_year,
            'semester' => $user->semester,
        ]);
    }

    /**
     * Convert a user's saved birthdate to MMDDYY.
     */
    private function birthdatePassword(User $user): string
    {
        if ($user->birth_date === null) {
            return '';
        }

        return $user->birth_date->format('mdy');
    }

    /**
     * Existing officer accounts that still use the default password must update it.
     */
    private function markDefaultOfficerPasswordForChange(User $user, string $plainPassword): void
    {
        if (
            ! $user->isOfficer() ||
            $user->force_password_change ||
            ! in_array($plainPassword, self::DEFAULT_OFFICER_PASSWORDS, true)
        ) {
            return;
        }

        $user->forceFill([
            'force_password_change' => true,
        ])->save();
    }

    /**
     * Derive a family income amount from the current payload.
     *
     * @param  array<string, mixed>  $validated
     */
    private function resolveFamilyIncome(array $validated): ?float
    {
        if (isset($validated['familyIncome'])) {
            return (float) $validated['familyIncome'];
        }

        $incomeBracket = $validated['monthlyIncome'] ?? $validated['incomeBracket'] ?? null;

        return match ($incomeBracket) {
            'Below PHP 20,000' => 15000.0,
            'PHP 20,000 - PHP 39,999' => 30000.0,
            'PHP 40,000 - PHP 59,999' => 50000.0,
            'PHP 60,000 and above' => 65000.0,
            default => null,
        };
    }

    /**
     * Send a welcome message after a student registers.
     */
    private function sendRegistrationEmail(User $user): void
    {
        try {
            Mail::to($user->email)->queue(new StudentRegistrationMail($user));
        } catch (Throwable $throwable) {
            report($throwable);
        }
    }
}
