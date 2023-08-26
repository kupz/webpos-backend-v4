<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class AuthController extends Controller
{
    // ROUTE: POST - /api/register
    public function register(Request $request){
        $validator = Validator::make($request->all(),[
            'name' => 'required|unique:users|max:64|string|alpha_num',
            'email' => 'required|unique:users|max:200|email',
            'password' => 'required|confirmed|min:8|max:64',
        ]);
        
        if($validator->fails()){
            return response()->json(['ok' => false, 'message' => 'Request didn\'t pass the validation.', 'errors' => $validator->errors()], 400);
        }
        else{
            $validated = $validator->validated();
            $validated['password'] = bcrypt($validated['password']);
            $user = User::create($validated);
            unset($validated['password']); // PREVENTS PASSWORD FROM BEING LOGGED


            // LOGS THE REGISTRATION INFO
            $user->logs()->create([
                'table_name' => 'users',
                'object_id' => $user->id,
                'description' => "User ($user->id) has been registered!",
                'label' => 'api-register',
                'ip' => $request->ip(),
                'properties' => json_encode(array_merge($validated, ['user-agent' => $request->userAgent()]))
            ]);

            $token = $user->createToken("kupz-webpos")->accessToken;
            unset($user->tokens);
            // LOGS THE CREATION OF TOKEN OR LOGIN
            $user->logs()->create([
                'table_name' => 'oauth_access_tokens',
                'object_id' => $user->tokens->sortBy('created_at')->first()->id,
                'description' => "User ($user->id) has logged in!",
                'label' => 'api-login',
                'ip' => $request->ip(),
                'properties' => json_encode(['user-agent' => $request->userAgent()])
            ]);
            $user->accessToken = $token;
            return response()->json(['ok' => true, 'data' => $user, 'message' => 'Account has been registered!'], 200);
        }
    }

    // MIDDLEWARE: auth:api
    // ROUTE: GET - /api/userInfo
    public function userInfo(Request $request){
        return response()->json(['ok' => true, 'data' => $request->user(), 'message' => "User info has been retrieved!"]);
    }

    // ROUTE: POST - /api/login
    public function login(Request $request){
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'password' => 'required'
        ]);

        
        if($validator->fails()){
            return response()->json(['ok' => false, 'message' => 'Request didn\'t pass the validation.', 'errors' => $validator->errors()], 400);
        }
        else{
            $query = ActivityLog::whereRaw("label = 'api-login-failed' AND ip = ? AND created_at > ?", [$request->ip(), now()->subSeconds(env("KUPZ_AUTH_LOCK_DURATION", 300))]);
            if($query->count() < env("KUPZ_AUTH_LOCK_MAX_ATTEMPT", 5)){
                $validated = $validator->validated();
                $type = filter_var($validated['name'], FILTER_VALIDATE_EMAIL) ? 'email' : 'name';
                $data = [
                    $type => $validated['name'],
                    'password' => $validated['password']
                ];
                if(auth()->attempt($data)){
                    // LOGIN SUCCESS
                    $token = auth()->user()->createToken("kupz-webpos")->accessToken;
                    $user = auth()->user();
                    $user->logs()->create([
                        'table_name' => 'oauth_access_tokens',
                        'object_id' => $user->tokens->sortBy("created_at")->last()->id,
                        'description' => "User ($user->id) has logged in!",
                        'label' => 'api-login',
                        'ip' => $request->ip(),
                        'properties' => json_encode(['user-agent' => $request->userAgent()])
                    ]);
                    $user->accessToken = $token;
                    unset($user->tokens);
                    return response()->json(['ok' => true, 'data' => $user, 'message' => 'Account has been registered!'], 200);
                }
                else{
                    ActivityLog::create([
                        'table_name' => 'oauth_access_tokens',
                        'description' => "Failed login attempt!",
                        'label' => 'api-login-failed',
                        'ip' => $request->ip(),
                        'properties' => json_encode([$type => $validated['name'], 'user-agent' => $request->userAgent()])
                    ]);
                    return response()->json(['ok' => false, 'message' => 'Invalid credentials!'], 401);
                }
            }
            $attempt_date = new Carbon(date_create($query->orderBy('created_at')->first()->created_at));
            $attempt_date = $attempt_date->addSeconds(env("KUPZ_AUTH_LOCK_DURATION", 300));
            $remaining = $attempt_date->timestamp - now()->timestamp;
            return response()->json(['ok' => false, 'message' => "Locked for $remaining " . ($remaining > 1 ? "seconds" : "second"), 'remaining' => $remaining], 403);
        }
    }


    // MIDDLEWARE: auth:api
    // ROUTE: DELETE - /api/logout
    public function logout(Request $request){
        $user = $request->user();
        $request->user()->token()->revoke();
        $user->logs()->create([
            'table_name' => 'oauth_access_tokens',
            'object_id' => $request->user()->token()->id,
            'description' => "User ($user->id) has logged out!",
            'label' => 'api-logout',
            'ip' => $request->ip(),
            'properties' => json_encode(['user-agent' => $request->userAgent()])
        ]);
        return response()->json(['ok' => true, 'message' => 'Logged out!'], 200);
    }
}
