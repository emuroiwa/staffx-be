<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserSettings;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Tymon\JWTAuth\Facades\JWTAuth;

class UserSettingsController extends Controller
{
    /**
     * Get all user settings.
     */
    public function index(): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                ], 401);
            }

            $settings = $user->getAllSettings();

            return response()->json([
                'success' => true,
                'data' => $settings,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch settings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update user settings.
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'settings' => 'required|array',
        ]);

        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                ], 401);
            }

            foreach ($request->settings as $key => $value) {
                // Validate setting keys to prevent arbitrary data storage
                if ($this->isValidSettingKey($key)) {
                    $user->setSetting($key, $value);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Settings updated successfully',
                'data' => $user->getAllSettings(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update settings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a specific setting.
     */
    public function show(string $key): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                ], 401);
            }

            if (!$this->isValidSettingKey($key)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid setting key',
                ], 400);
            }

            $value = $user->getSetting($key);

            return response()->json([
                'success' => true,
                'data' => [
                    'key' => $key,
                    'value' => $value,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch setting',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a specific setting.
     */
    public function destroy(string $key): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                ], 401);
            }

            if (!$this->isValidSettingKey($key)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid setting key',
                ], 400);
            }

            $user->settings()->where('key', $key)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Setting deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete setting',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reset all settings to defaults.
     */
    public function reset(): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                ], 401);
            }

            // Delete all user settings
            $user->settings()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Settings reset to defaults successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset settings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate setting keys to prevent arbitrary data storage.
     */
    private function isValidSettingKey(string $key): bool
    {
        $allowedKeys = [
            // Appearance
            'theme',
            'language',
            'sidebar_collapsed',
            'sidebar_auto_hide',
            
            // Notifications
            'notifications.email.new_employee',
            'notifications.email.payroll_reminder',
            'notifications.email.system_updates',
            'notifications.email.security_alerts',
            'notifications.push.new_employee',
            'notifications.push.payroll_reminder',
            'notifications.push.system_updates',
            'notifications.push.security_alerts',
            
            // Privacy
            'privacy.allow_tracking',
            'privacy.session_timeout',
            
            // Advanced
            'advanced.enable_animations',
            'advanced.preload_data',
            'advanced.debug_mode',
        ];

        return in_array($key, $allowedKeys) || 
               str_starts_with($key, 'notifications.') || 
               str_starts_with($key, 'privacy.') || 
               str_starts_with($key, 'advanced.');
    }
}
