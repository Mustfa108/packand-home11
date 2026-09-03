<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() instanceof \App\Models\Admin) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بالوصول إلى هذه الصفحة.',
            ], 403);
        }

        return $next($request);
    }
}
