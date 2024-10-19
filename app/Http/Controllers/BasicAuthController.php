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

            $req = $request->validate([
                'name' => 'required|string',
                'email' => 'required|email',
                'password' => 'required|string'

            ]);

            // Validation handled in catch block

            $user = User::where('email',$req['email'])->first();
            if($user) {

                if($user->provider !== 'local') {

                    return response()->json([
                        'statusCode' => 409,
                        'status' => false,
                        'message' => 'User Already exists with a social account, Please Login',
                        'data' => null,
                    ], 409);
                }
                else {
                    return response()->json([
                        'statusCode' => 409,
                        'status' => false,
                        'message' => 'User Already exists, Please Login',
                        'data' => null,
                    ], 409);

                }

            }

            // Hold the registeration data after issuing OTP:
            Cache::put('registeration_' . $req['email'], [
                'name' => $req['name'],
                'email' => $req['email'],
                'password' => Hash::make($req['password']),
            ], now()->addMinutes(5));

            // Create an OTP:
            $otpCode = rand(1000,9999); 
            $otpExpiration = now()->addMinutes(5);

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

            if(!$otpRecord) {
                return $this->jsonResponse(
                    400, false, "OTP match failed, proceed to register again", null);
            }

            if ($otpRecord->expires_at->isPast()) {
                $otpRecord->delete();
                return $this->jsonResponse(
                    400, false, "OTP timed-out, proceed to register again", null);
            }

            // Retrieve user from cache with the corrected key
            $userData = Cache::get('registeration_' . $request->email);

            $user = User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => $userData['password'], // Already hashed, default value local will be assigned for provider
            ]);
               
            // Assign the role
            $user->roles()->attach(1);
            $user->email_verified_at = now();
            $user->save();

            $otpRecord->delete();

            return $this->jsonResponse(
                201,
                true,
                "Account created successfully, proceed to login page",
                $user->name
                
            );
            } catch(ValidationException $e) {
            return $this->jsonResponse(
                400,
                false,
                'OTP or email missing',
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

            // validation handled by first catch()

            $user = User::where('email', $validated['email'])->first();

            if(!$user) {
                return $this->jsonResponse(
                    404,
                    false,
                    'Account does not exist',
                    null,
                );
            }

            if($user->provider !== 'local') {
                return $this->jsonResponse(
                    409,
                    false,
                    'Account exists with a social account',
                    null,
                );
            }


            if (!Hash::check($validated['password'],$user->password)) {
                return $this->jsonResponse(
                    401,
                    false,
                    "Password doesn't match, login failed",
                    null
                );
            }

            $deviceId = $request->query('device_id','web');

            // Create a new token for the user
            $token = $user->createToken('auth_token'); // Initiates a token in personal_access_token table
            $plainTextToken = $token->plainTextToken; // To issue the user with a token
            $tokenId = $token->accessToken->id; // To pass the token id to the session table

            // Start a new session for the user
            $userSession = $this->startSession($user, $deviceId, $tokenId);

            // For front-end ease:
            $roles = $user->roles->pluck('id')->toArray();
            $roleValue = in_array(1, $roles) && in_array(2, $roles) ? 12 : (in_array(1, $roles) ? 1 : 0);


            // Profile pic:
            $profilePicUrl = asset($user->profile_pic);

            // Return the access token alongwith useful data
            return $this->jsonResponse(
                    200,
                    true,
                    "User logged in ",
                    ['bearerToken' => $plainTextToken,
                    'userName' => $user->name,
                    'email' => $user->email ,
                    'profilePicUrl' => $profilePicUrl,
                    'roles' => $roleValue
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

    public function forgetPassword(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email',
            ]);

            $user = User::where('email', $validated['email'])->first();
            if(!$user) {
                    return $this->jsonResponse(
                        404,
                        false,
                        'Account does not exist',
                        null,
                    );
                }

            if($user->provider !== 'local') {
                    return $this->jsonResponse(
                        409,
                        false,
                        'Account exists with a social provider, plz contact your provider',
                        null,
                    );
                }

            // Delete all the tokens, so all the sessions become inactive:
            $user->tokens()->delete();

            // Create an OTP:
                $otpCode = rand(1000,9999); 
                $otpExpiration = now()->addMinutes(5);

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
                    'OTP sent to your email for password change. Check spam folder as well',
                    ['email' => $request->email]
                );        

        } catch(ValidationException $e) {
            return $this->jsonResponse(
                400,
                false,
                'Email missing',
                null
            );
        } catch(\Exception $e) {
            return $this->jsonResponse(
                500,
                false,
                'Internal Server Error',
                null
            );
        }
    }

    public function changePassword(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
                'otp' => 'required|string'
                ]);

            // Check the OTP in the database
            $otpRecord = Otp::where('otp', $request->otp)
            ->where('email', $request->email)
            ->first();

            if(!$otpRecord) {
                return $this->jsonResponse(
                    400, false, "OTP match failed", null);
                }

            if ($otpRecord->expires_at->isPast()) {
                $otpRecord->delete();
                return $this->jsonResponse(
                    400, false, "OTP timed-out", null);
                }
            

            // Retrieve the user from the database to ensure up-to-date data
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return $this->jsonResponse(404, false, "User not found", null);
            }

            // Ensure the new password is not the same as the old one
            if (Hash::check($request->password, $user->password)) {
                return $this->jsonResponse(400, false, "New password cannot be the same as the old password", null);
            }

            // Update the user's password
            $user->update([
                'password' => Hash::make($request->password),
            ]);

            // Delete the OTP after the password change is successful
            $otpRecord->delete();

            return $this->jsonResponse(200, true, 'Password updated successfully', null);
            

        } catch(ValidationException $e) {
            return $this->jsonResponse(
                400,
                false,
                'Password or OTP missing',
                null
            );
        } catch(\Exception $e) {
            return $this->jsonResponse(
                500,
                false,
                'Internal Server Error',
                null
            );
        }

    }
  

}