<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterStudentRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
        $token = $user->createToken('scholarship-web')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ], 201);
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
    public function logout(Request $request): JsonResponse
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
            'password' => Str::random(32),
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
            'assigned_program_ids' => [],
        ];
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
}
