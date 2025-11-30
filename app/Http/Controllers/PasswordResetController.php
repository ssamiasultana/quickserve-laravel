<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Mail\ResetPasswordMail;

class PasswordResetController extends Controller
{
    //
    public function forgotPassword(Request $request):JsonResponse{
        $request->validate([
            'email' => 'required|email|exists:users,email'
        ]);
        DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->delete();

        $token = Str::random(64);

        DB::table('password_reset_tokens')->insert([
            'email' => $request->email,
            'token' => $token,
            'created_at' => Carbon::now()
        ]);
        $resetLink = config('app.frontend_url') . '/reset-password/' . $token;

        try {
            Mail::to($request->email)->send(new ResetPasswordMail($resetLink, $token));
            
            return response()->json([
                'success' => true,
                'message' => 'Password reset link sent to your email'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send email',
                'error' => $e->getMessage()
            ], 500);
        }


    }

    public function verifyToken($token): JsonResponse
    {
        $resetRecord = DB::table('password_reset_tokens')
            ->where('token', $token)
            ->first();

        if (!$resetRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token'
            ], 404);
        }

        // Check if token is older than 60 minutes
        $tokenAge = Carbon::parse($resetRecord->created_at)->diffInMinutes(Carbon::now());
        
        if ($tokenAge > 60) {
            DB::table('password_reset_tokens')->where('token', $token)->delete();
            return response()->json([
                'success' => false,
                'message' => 'Token has expired'
            ], 410);
        }
        return response()->json([
            'success' => true,
            'message' => 'Token is valid',
            'email' => $resetRecord->email
        ], 200);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required',
            'password' => 'required|string|min:8|confirmed'
        ]);

        // Find reset record
        $resetRecord = DB::table('password_reset_tokens')
            ->where('token', $request->token)
            ->first();

        if (!$resetRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token'
            ], 404);
        }

        $tokenAge = Carbon::parse($resetRecord->created_at)->diffInMinutes(Carbon::now());
        
        if ($tokenAge > 60) {
            DB::table('password_reset_tokens')->where('token', $request->token)->delete();
            return response()->json([
                'success' => false,
                'message' => 'Token has expired'
            ], 410);
        }

        // Update user password
        $user = User::where('email', $resetRecord->email)->first();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }
        $user->password = Hash::make($request->password);
        $user->save();

        // Delete the used token
        DB::table('password_reset_tokens')->where('token', $request->token)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully'
        ], 200);
    }


}
