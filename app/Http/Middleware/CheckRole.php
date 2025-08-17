<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        // Vérifier que l'utilisateur est authentifié
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non authentifié'
            ], 401);
        }

        $user = $request->user();

        // Vérifier que l'utilisateur est actif
        if ($user->statut !== 'actif') {
            return response()->json([
                'success' => false,
                'message' => 'Votre compte est désactivé. Contactez l\'administration.'
            ], 403);
        }

        // Vérifier le rôle
        if ($user->role !== $role) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé. Rôle requis: ' . ucfirst($role)
            ], 403);
        }

        return $next($request);
    }
}
