<?php

namespace App\Actions;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class CustomLoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        $user = $request->user();
        $token = $user->createToken('web')->plainTextToken;
        $role = $user->role;

        $redirectUrl = config('app.frontend_url') . "?token={$token}&role={$role}";
        return redirect()->to($redirectUrl);
    }
}
