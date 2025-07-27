<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Notifications\TestNotification;

class TestNotificationController extends Controller
{
    public function send(Request $request)
    {
        $user = $request->user();
        $user->notify(new TestNotification());
        return response()->json(['message'=>'Notifikasi dihantar']);
    }
}
