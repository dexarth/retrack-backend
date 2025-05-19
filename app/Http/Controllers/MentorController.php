<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MentorController extends Controller
{
     public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_penuh' => 'required|string|max:255',
            'pangkat' => 'required|string|max:255',
            'parol_daerah' => 'required|string|in:SANDAKAN,TAWAU,BEAUFORT,KUDAT,PANTAI BARAT,PEDALAMAN',
            'email' => 'required|email|unique:users,email',
            'username' => 'required|string|unique:users,username',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        // Create the user
        $user = User::create([
            'name' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'type' => 'mentor', // You can adjust this based on your user roles
        ]);

        // Create mentor profile
        Mentor::create([
            'user_id' => $user->id,
            'nama_penuh' => $request->nama_penuh,
            'pangkat' => $request->pangkat,
            'parol_daerah' => $request->parol_daerah,
        ]);

        return response()->json([
            'message' => 'Mentor created successfully',
            'user_id' => $user->id
        ], 201);
    }
}
