<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileTokenController extends Controller
{
    public function generate(Request $request)
    {
        $token = $request->user()->createToken('web-ui')->plainTextToken;

        return redirect()->back()->with('generated_token', $token);
    }
}
