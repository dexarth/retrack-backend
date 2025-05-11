<?php

namespace App\Actions;

use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;

class CustomRegisterResponse implements RegisterResponseContract
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
