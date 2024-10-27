<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    /**
     * Login user and create token
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        // Validate request inputs
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'android_id' => 'required|string', // Require android_id in the request
        ]);

        // Find user by email
        $user = User::where('email', $request->email)->first();

        // Check if user exists
        if (!$user) {
            return response()->json(['message' => __('messages.unAuthorisedUser')], 401);
        }

        // Check user status
        if ($user->status === 'deactive') {
            return response()->json(['message' => __('auth.failedBlocked')], 403);
        }

        if ($user->login === 'disable') {
            return response()->json(['message' => __('auth.failedLoginDisabled')], 403);
        }

        // Verify password
        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['message' => __('auth.failed')], 401);
        }

        // Check android_id
        if ($user->android_id) {
            // If android_id is set, verify it matches the provided one
            if ($user->android_id !== $request->android_id) {
                return response()->json(['message' => 'Device ID does not match.'], 403);
            }
        } else {
            // If android_id is null, update it with the provided android_id
            $user->android_id = $request->android_id;
            $user->save();
        }

        // User authenticated successfully, generate Sanctum token
        $token = $user->createToken('API Token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user,
        ], 200);
    }

    public function getUser(Request $request)
    {
        // Get the currently authenticated user
        $user = Auth::user();

        // Check if the user is authenticated
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Return the authenticated user's data
        return response()->json([
            'message' => 'User retrieved successfully.',
            'data' => $user,
        ]);
    }
    
    public function logout(Request $request)
    {
        try {
            // Revoke the token that was used to authenticate the current request
            $request->user()->currentAccessToken()->delete();

            return response()->json(['message' => 'Logout successful', 'status' => 'success'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Logout failed', 'status' => 'error'], 500);
        }
    }

}
