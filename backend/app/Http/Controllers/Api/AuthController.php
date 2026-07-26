<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;

class AuthController extends Controller
{
    /**
     * Register a new user.
     *
     * @param  \App\Http\Requests\Auth\RegisterRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        Auth::login($user);

        return response()->json([
            'data' => [
                'user' => new UserResource($user),
            ],
            'message' => 'User registered successfully',
        ], 201);
    }

    /**
     * Login user.
     *
     * @param  \App\Http\Requests\Auth\LoginRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(LoginRequest $request)
    {
        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        // Start a new session for API login
        Session::regenerate();

        return response()->json([
            'data' => [
                'user' => new UserResource(Auth::user()),
            ],
            'message' => 'User logged in successfully',
        ]);
    }

    /**
     * Logout user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        // Start a session if not already started
        if (!$request->hasSession()) {
            $request->setLaravelSession(app('session.store'));
        }

        // Get the authenticated user before logging out
        $user = Auth::guard('web')->user();

        Auth::guard('web')->logout();

        // Invalidate the session
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Clear the session cookie
        $cookie = cookie('XSRF-TOKEN', '', -1);
        $sessionCookie = cookie('laravel_session', '', -1);

        // Manually clear the user from the session
        $request->session()->forget('user');
        
        // Forget the user from the auth guard
        Auth::shouldUse('web');
        Auth::guard('web')->logout();

        return response()->json([
            'message' => 'User logged out successfully',
        ])->withCookie($cookie)->withCookie($sessionCookie);
    }

    /**
     * Get the authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function user(Request $request)
    {
        return response()->json([
            'data' => [
                'user' => new UserResource($request->user()),
            ],
        ]);
    }
}
