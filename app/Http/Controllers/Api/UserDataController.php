<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Carbon\Carbon;

class UserDataController extends Controller
{
    /**
     * Export user data as JSON.
     */
    public function exportData(): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                ], 401);
            }

            // Gather all user data
            $userData = [
                'export_info' => [
                    'generated_at' => now()->toISOString(),
                    'user_id' => $user->id,
                    'format' => 'JSON',
                ],
                'profile' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'company' => $user->company,
                    'email_verified_at' => $user->email_verified_at,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ],
                'account_activity' => [
                    'last_login' => $user->updated_at, // Placeholder - you'd track this separately
                    'login_count' => 0, // Placeholder - you'd track this separately
                ],
                // Add other data sections as needed
                'settings' => [
                    'theme' => 'system',
                    'language' => 'en',
                    'notifications' => [
                        'email' => true,
                        'push' => false,
                    ],
                ],
            ];

            // Create a temporary file
            $filename = 'user_data_export_' . $user->id . '_' . now()->format('Y-m-d_H-i-s') . '.json';
            $filePath = 'exports/' . $filename;
            
            Storage::disk('local')->put($filePath, json_encode($userData, JSON_PRETTY_PRINT));

            // Generate download URL (you might want to make this a signed URL for security)
            $downloadUrl = route('user.download-export', ['filename' => $filename]);

            return response()->json([
                'success' => true,
                'message' => 'Data export generated successfully',
                'data' => [
                    'download_url' => $downloadUrl,
                    'filename' => $filename,
                    'expires_at' => now()->addHours(24)->toISOString(),
                    'size' => Storage::disk('local')->size($filePath),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download exported data file.
     */
    public function downloadExport(Request $request, string $filename): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                abort(401, 'Unauthenticated');
            }

            $filePath = 'exports/' . $filename;
            
            // Verify the file belongs to the authenticated user
            if (!str_contains($filename, 'user_data_export_' . $user->id . '_')) {
                abort(403, 'Unauthorized access to file');
            }

            if (!Storage::disk('local')->exists($filePath)) {
                abort(404, 'File not found');
            }

            return Storage::disk('local')->download($filePath, $filename, [
                'Content-Type' => 'application/json',
            ]);

        } catch (\Exception $e) {
            abort(500, 'Failed to download file');
        }
    }

    /**
     * Delete user account and all associated data.
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|string',
            'confirmation' => 'required|string|in:DELETE',
        ]);

        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                ], 401);
            }

            // Verify password
            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password is incorrect',
                ], 400);
            }

            // TODO: Add cleanup logic for related data
            // - Remove user files
            // - Clean up sessions
            // - Remove from related tables
            
            // Delete the user
            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'Account deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete account',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
