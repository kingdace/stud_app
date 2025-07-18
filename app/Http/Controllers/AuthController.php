<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Member;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    private $secretKey = "qQKPjndxljuYQi/POiXJa8O19nVO/vTf/DpXO541g=";

    public function register(Request $request)
    {
        $fields = $request->all();

        // Validate registration fields
        $errors = Validator::make($fields, [
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6|max:8',
        ]);

        if ($errors->fails()) {
            return response($errors->errors()->all(), 422);
        }

        // Create the new user
        $user = User::create([
            'email' => $fields['email'],
            'password' => bcrypt($fields['password']),
            'role' => 'user',
            'remember_token' => $this->generateRandomCode(),
        ]);

        return response(['user' => $user, 'message' => 'User created'], 200);
    }

    private function generateRandomCode()
    {
        return Str::random(40); // Generates a 40-character random string
    }

    public function login(Request $request)
    {
        $fields = $request->all();

        $errors = Validator::make($fields, [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($errors->fails()) {
            return response($errors->errors()->all(), 422);
        }

        // First try to find a user
        $user = User::where('email', $fields['email'])->first();
        $isUser = true;

        // If no user found, try to find a member
        if (!$user) {
            $user = Member::where('email', $fields['email'])->first();
            $isUser = false;
        }

        if (!$user || !Hash::check($fields['password'], $user->password)) {
            return response([
                'message' => 'Email or password invalid',
                'isLoggedIn' => false
            ], 422);
        }

        $token = $user->createToken($isUser ? $this->secretKey : 'member-token')->plainTextToken;

        $responseData = [
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'type' => $isUser ? 'user' : 'member',
                'role' => $isUser ? 'user' : 'member',
                'first_name' => $isUser ? null : $user->first_name,
                'last_name' => $isUser ? null : $user->last_name,
            ],
            'message' => 'Logged in successfully',
            'token' => $token,
            'isLoggedIn' => true,
        ];

        if (!$isUser) {
            $responseData['user']['creator_id'] = $user->user_id;
        }

        return response($responseData, 200);
    }

    public function logoutUser(Request $request)
    {
        // Revoke user's access token
        DB::table('personal_access_tokens')
            ->where('tokenable_id', $request->userId)
            ->delete();

        return response(['message' => 'User logged out'], 200);
    }

    public function memberLogin(Request $request)
    {
        try {
            $fields = $request->all();

            // Validate input
            $errors = Validator::make($fields, [
                'email' => 'required|email',
                'password' => 'required',
            ]);

            if ($errors->fails()) {
                return response([
                    'message' => 'Validation failed',
                    'errors' => $errors->errors()->all(),
                    'isLoggedIn' => false
                ], 422);
            }

            // Find member by email
            $member = Member::where('email', $fields['email'])
                ->whereNull('deleted_at')
                ->where('is_deleted', false)
                ->first();

            if (!$member) {
                return response([
                    'message' => 'Email not found',
                    'isLoggedIn' => false
                ], 422);
            }

            // Verify password
            if (!Hash::check($fields['password'], $member->password)) {
                return response([
                    'message' => 'Invalid password',
                    'isLoggedIn' => false
                ], 422);
            }

            // Revoke existing tokens
            $member->tokens()->delete();

            // Create new token
            $token = $member->createToken('member-token')->plainTextToken;

            $responseData = [
                'user' => [
                    'id' => $member->id,
                    'email' => $member->email,
                    'type' => 'member',
                    'role' => 'member',
                    'first_name' => $member->first_name,
                    'last_name' => $member->last_name,
                    'creator_id' => $member->user_id,
                ],
                'message' => 'Logged in successfully',
                'token' => $token,
                'isLoggedIn' => true,
            ];

            return response($responseData, 200);
        } catch (\Exception $e) {
            Log::error('Member login error: ' . $e->getMessage());
            return response([
                'message' => 'An error occurred during login',
                'error' => $e->getMessage(),
                'isLoggedIn' => false
            ], 500);
        }
    }
}
