<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class AuthController
{
    public function login(Request $r)
    {
        $data = $r->validate(['email' => 'required|email|max:254', 'password' => 'required|string|max:256']);
        if (! Auth::attempt($data)) {
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }
        $r->session()->regenerate();

        return response()->json($r->user()->only(['id', 'name', 'email', 'role']));
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return response()->noContent();
    }
}
