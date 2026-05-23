<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserSettingsRequest;
use App\Http\Resources\UserSettingResource;
use App\Models\UserSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserSettingsController extends Controller
{
    /**
     * Return current user's saved settings.
     */
    public function show(Request $request): JsonResponse
    {
        $setting = UserSetting::firstOrCreate(
            ['user_id' => $request->user()?->id],
            ['settings' => UserSettingResource::defaults()],
        );

        return response()->json([
            'settings' => new UserSettingResource($setting),
        ]);
    }

    /**
     * Update current user's saved settings.
     */
    public function update(UpdateUserSettingsRequest $request): JsonResponse
    {
        $setting = UserSetting::firstOrCreate(
            ['user_id' => $request->user()?->id],
            ['settings' => UserSettingResource::defaults()],
        );
        $settings = array_merge(
            UserSettingResource::defaults(),
            $setting->settings ?? [],
            $this->settingsPayload($request->validated()),
        );

        $setting->update([
            'settings' => $settings,
        ]);

        return response()->json([
            'settings' => new UserSettingResource($setting->refresh()),
        ]);
    }

    /**
     * Normalize both supported payload shapes into a settings array.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function settingsPayload(array $validated): array
    {
        $settings = is_array($validated['settings'] ?? null) ? $validated['settings'] : [];

        foreach (array_keys(UserSettingResource::defaults()) as $key) {
            if (array_key_exists($key, $validated)) {
                $settings[$key] = $validated[$key];
            }
        }

        return $settings;
    }
}
