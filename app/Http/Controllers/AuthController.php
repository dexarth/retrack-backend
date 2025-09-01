<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function logout(Request $request)
    {
        // If using Personal Access Tokens (Sanctum token guard)
        if ($request->user() && method_exists($request->user(), 'currentAccessToken')) {
            optional($request->user()->currentAccessToken())->delete();
        }

        // If you also support cookie-based sessions (optional):
        auth()->guard('web')->logout();

        return response()->json(['message' => 'Logged out'], 200);
    }
}
