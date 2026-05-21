<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterStudentRequest;
use App\Http\Resources\UserResource;
use App\Mail\StudentRegistrationMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class AuthController extends Controller
{
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

        $deviceName = $validated['deviceName'] ?? 'scholarship-web';
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Register a student account and return an API token.
     */
    public function register(RegisterStudentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = User::create($this->buildStudentAttributes($validated));
        $this->sendRegistrationEmail($user);
        $token = $user->createToken('scholarship-web')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
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
            'password' => $validated['password'] ?? $this->birthdatePasswordFromValue($validated['birthDate'] ?? null) ?: Str::random(32),
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
     * Convert a registration birthdate value to MMDDYY.
     */
    private function birthdatePasswordFromValue(?string $birthDate): string
    {
        if ($birthDate === null || $birthDate === '') {
            return '';
        }

        $parts = explode('-', $birthDate);

        if (count($parts) !== 3) {
            return '';
        }

        [$year, $month, $day] = $parts;

        return str_pad($month, 2, '0', STR_PAD_LEFT).str_pad($day, 2, '0', STR_PAD_LEFT).substr($year, -2);
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
     * Send a welcome email after a student registers.
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
