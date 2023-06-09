<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * 註冊
     */
    public function register(Request $request): JsonResponse
    {
        Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|max:32',
            'comfirm_password' => 'required|same:password',
        ])->validate();

        $input = $request->all();

        $input['password'] = Hash::make($input['password']);

        $user = User::create($input);

        if (! $user->hasVerifiedEmail()) {
            event(new Registered($user));
        }

        return response()->json($user->toArray(), 200);
    }

    /**
     * 寄驗證信
     */
    public function sendVerifyEmail(Request $request): JsonResponse
    {
        Validator::make($request->all(), [
            'email' => 'required|email',
        ])->validate();

        $email = $request->email;

        $user = User::where('email', $email)->firstOrFail();

        if ($user->hasVerifiedEmail()) {
            abort(400, 'Email already verified.');
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'success'], 200);
    }

    /**
     * 驗證碼
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        Validator::make($request->all(), [
            'email' => 'required|email',
            'code' => 'required',
        ])->validate();

        $user = User::where('email', $request->email)->first();

        is_null($user) && abort(400);

        if ($user->hasVerifiedEmail()) {
            abort(403, 'Your email address is verified.');
        }

        $verify = $user->verifies()
            ->where('code', $request->code)
            ->where('expires', '>=', now())
            ->first();

        is_null($verify) && abort(400);

        // 標記驗證
        $user->markEmailAsVerified();

        // 刪除用過的驗證碼
        $verify->delete();

        // 呼叫事件
        event(new Verified($user));

        return response()->json(['message' => 'success'], 200);
    }

    /**
     * 登入
     */
    public function login(Request $request): JsonResponse
    {
        Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|min:8|max:32',
        ])->validate();

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('normal');

        return response()->json(['token' => $token->plainTextToken], 200);
    }

    /**
     * 登出
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'success'], 200);
    }

    /**
     * 忘記密碼
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        Validator::make($request->all(), [
            'email' => 'required|email',
        ])->validate();

        $user = User::where('email', $request->email)->first();

        is_null($user) && abort(400);

        $user->sendPasswordResetNotify();

        return response()->json(['message' => 'success'], 200);

    }

    /**
     * 變更密碼
     */
    public function resetPassword(Request $request): JsonResponse
    {
        Validator::make($request->all(), [
            'code' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|max:32',
            'comfirm_password' => 'required|same:password',
        ])->validate();

        $user = User::where('email', $request->email)->first();

        is_null($user) && abort(400);

        $verify = $user->verifies()
            ->where('code', $request->code)
            ->where('expires', '>=', now())
            ->first();

        is_null($verify) && abort(400);

        $user->password = Hash::make($request->password);

        $user->save();

        $verify->delete();

        return response()->json(['message' => 'success'], 200);
    }
}
