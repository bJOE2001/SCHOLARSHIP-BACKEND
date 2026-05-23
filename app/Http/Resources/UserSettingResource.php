<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserSettingResource extends JsonResource
{
    /**
     * Default workspace settings.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'emailAlerts' => true,
            'riskAlerts' => true,
            'defaultRange' => 'Last 6 months',
            'tableDensity' => 'Comfortable',
        ];
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $settings = array_merge(self::defaults(), $this->settings ?? []);

        return [
            'id' => $this->id,
            'userId' => $this->user_id,
            'settings' => $settings,
            ...$settings,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
