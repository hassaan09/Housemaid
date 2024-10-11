<?php

namespace App\Http\Controllers;

use Illuminate\Validation\ValidationException; // Corrected import
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Role;
use App\Models\Session;
use Illuminate\Support\Facades\Hash;
use App\Models\Otp;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;


class BasicAuthController extends Controller {

    public function register(Request $request) 
    {
        try {

            // Clear any residue cache:
            Cache::forget('registration_' . $request->email);

            $req = $request->validate([
                'name' => 'required|string',
                'email' => 'required|email',
                'password' => 'required|string'

            ]);

            // Validation handled in catch block

            $tempRoleId = $request->query('role_id') ;
            // Register new user
            $user = User::where('email',$req['email'])->first();
            if($user && $user->roles->contains($tempRoleId)) {

                return response()->json([
                    'statusCode' => 409,
                    'status' => false,
                    'message' => 'User Already exists, Please Login',
                    'data' => null,
                ], 409);
            }

            // Temporarily store the user's data in cache
            Cache::put('registration_' . $req['email'], [
                'name' => $req['name'],
                'email' => $req['email'],
                'password' => Hash::make($req['password']),
                'role_id' => $request->query('role_id', '1'),
            ], now()->addMinutes(10));

            // Create an OTP:
            $otpCode = rand(1000,9999); 
            $otpExpiration = now()->addMinutes(30);

            Otp::create([
                'email' => $request->email,
                'otp' => $otpCode,
                'expires_at' => $otpExpiration,
            ]);

            $mailController = new MailController();
            $mailController->sendEmail($request->email, $otpCode);
            return $this->jsonResponse(
                200,
                true,
                'OTP sent to your email. Please verify to complete registration. Check spam folder as well.',
                ['email' => $request->email]
            );
            

        } catch(ValidationException $e) {
            return $this->jsonResponse(
                400,
                false,
                'Bad request, missing field',
                null,
            );
        } catch(\Exception $e) {
            return $this->jsonResponse(
                500,
                false,
                'Internal Server Error',
                null,
            );  
        }
    }

    public function verifyOtp(Request $request)
    {
        try {
            $req = $request->validate( [
                'otp' => 'required|string',
                'email' => 'required|email',
            ]);

            // validation handled by first catch()

            // Check the OTP in the database
            $otpRecord = Otp::where('otp', $request->otp)
            ->where('email', $request->email)
            ->first();

            if ($otpRecord->expires_at->isPast()) {
                return $this->jsonResponse(
                    400, false, "OTP timed-out, proceed to register again", null);
            }

            // Retrieve user from cache with the corrected key
            $userData = Cache::get('registration_' . $request->email);

            if ($userData['role_id']==="1") {
                $user = User::create([
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'password' => $userData['password'], // Already hashed
                ]);

                 // Assign the role
                $user->roles()->attach($userData['role_id']);
                $user->email_verified_at = now();
                $user->save();

                return $this->jsonResponse(
                    201,
                    true,
                    "Client account created successfully, proceed to login page",
                    $user->toArray()
                    
                );
            } else{ 

                
                return $this->jsonResponse(
                    200,
                    true,
                    "Housemaide account creation in-progress, proceed to complete further questions",
                    null
                );

            }

            // Clear cache after successful registration
        
        } catch(ValidationException $e) {
            return $this->jsonResponse(
                400,
                false,
                'OTP or email missing, proceed to register again',
                null
            );
        } catch(\Exception $e) {
            return $this->jsonResponse(
                500,
                false,
                'Internal Server Error',
                null,
            );
            
        }
       
    }

    public function verifyQuestions(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'questions.*' => 'string',
                'answers.*' => 'string',
                'schedule.*.day' => 'required|string',
                'schedule.*.start_time' => 'required|string',
                'schedule.*.end_time' => 'required|string',
                'hourly_rate' => 'required|integer',
                'currency' => 'required|string',
                'country' => 'required|string',
                'identity_type' => 'required|string',
                'documents.*' => 'file|mimes:jpg,jpeg,png,pdf'
            ]);
    
            if ($validator->fails()) {
                return $this->jsonResponse(422,false,"Missing data or invalid",null);
            }

            foreach($request->file('documents') as $doc) {
                $path = $doc->store('uploads');
            }

            // Actual Saving to DB write logic here later
             
            $userData = Cache::get('registration_' . $request['email']);

            if (!$userData) {
                return $this->jsonResponse(
                    404,
                    false,
                    'No user data found for this email. Please register again',
                    null
                );
            }

            $user = User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => $userData['password'],
            ]);


            // Assign the role
            $user->roles()->attach($userData['role_id']);
            $user->email_verified_at = now();
            $user->save();

            return $this->jsonResponse(
                200,
                true,
                "Housemaide account created successfully,proceed to login page",
                ['username' => $userData['name'],
                'email' => $userData['email'],]
            );

            

        } catch(\Exception $e) {
            return $this->jsonResponse(
                500,
                false,
                'Internal Server Error',
                null,
            );
        }
            
    }


   
    public function login(Request $request) 
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);

             // If user doesn't specify role_id or device_id, it will be assumed on own
             $roleId = $request->query('role_id','1');
             $deviceId = $request->query('device_id','web');

            $user = User::where('email', $validated['email'])->first();

            $role = Role::find($roleId)->first();

            if(!$user->roles->contains($roleId)) {
                return $this->jsonResponse(
                    403,
                    false,
                    'You dont have any account against this role',
                    null);
            }
            
            if(!$user->password) {
                return $this->jsonResponse(
                    401,
                    false,
                    "Login failed. already account exists against this email",
                    null);
            }

            if (!$user || !Hash::check($validated['password'],$user->password)) {
                return $this->jsonResponse(
                    401,
                    false,
                    "Login failed",
                    null
                );
            }

            // Create a new token for the user
            $token = $user->createToken('auth_token');
            $plainTextToken = $token->plainTextToken;
            $tokenId = $token->accessToken->id;

             // Start a new session for the user
             $this->startSession($user, $deviceId, $tokenId);

            // Return the access token
            return $this->jsonResponse(
                    200,
                    true,
                    "User logged in ",
                    [
                        'bearerToken' => $plainTextToken,
                        'userName' => $user->name,
                        'roleName'=> $role->role_name
                        ]
                );
        } catch(ValidationException $e) {
            return $this->jsonResponse(
                400,
                false,
                'Login credentials missing, login again',
                null
            );
        } catch(\Exception $e) {
            return $this->jsonResponse(
                500,
                false,
                'Internal Server Error',
                null,
            );
            
        }
       
    }

}