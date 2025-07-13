<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyContext
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (!auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $user = auth()->user();

        // Check if user has company context
        if (!$user->company_uuid) {
            return response()->json([
                'success' => false,
                'message' => 'No company context. User must be associated with a company.',
            ], 403);
        }

        // Check if company is active
        if ($user->company && !$user->company->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Company account is inactive.',
            ], 403);
        }

        // Check if company has active subscription (skip during testing)
        if (!app()->environment('testing') && $user->company && !$user->company->hasActiveSubscription()) {
            return response()->json([
                'success' => false,
                'message' => 'Company subscription has expired.',
            ], 403);
        }

        // Set company context in the service container for easy access
        app()->instance('current_company_uuid', $user->company_uuid);
        app()->instance('current_company', $user->company);
        app()->instance('current_user_role', $user->role);

        return $next($request);
    }
}
