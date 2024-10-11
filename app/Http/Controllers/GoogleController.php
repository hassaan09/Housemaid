<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use App\Models\Role;

class GoogleController extends Controller
{

    public function redirectToGoogle(Request $request) {
        try {
            // As device id unique for each device in the world, use that

            $deviceId = $request->query('device_id', 'web'); 
            $roleId = $request->query('role_id', '1');

            // Encode device_id and role_id in the state parameter
            $state = base64_encode(json_encode([
                'device_id' => $deviceId,
                'role_id' => $roleId,
            ]));

            $redirectUrl = Socialite::driver('google')->stateless()->with(['state' => $state])->redirect()->getTargetUrl();
            // Redirection status code causes issue, so beware of using 302
            return $this->jsonResponse(
                200,
                true,
                "Redirection to google sign-in page",
                ['url' => $redirectUrl]
            );
            // $redirectUrl = Socialite::driver('google')->stateless()->redirect()->getTargetUrl();
        } catch(\Exception $e) {
            return $this->jsonResponse(

                500,
                false,
                'Internal Server Error',
                null,
            );  
        }
        
    }

    public function handleGoogleCallback(Request $request) {

        // Decode the state parameter
        $state = json_decode(base64_decode($request->query('state')),true);
        $deviceId = $state['device_id'];
        $roleId = $state['role_id'];
        // Get the user's information from the callback
        $googleUser = Socialite::driver('google')->stateless()->user();
        return $this->createOrLoginUser($googleUser, 'google',$deviceId,$roleId);
        
    }

    private function createOrLoginUser($providerUser, $providerName, $deviceId, $roleId) {
        $baseRedirectURI = env('BASE_REDIRECT_URL');
        $redirectParams = [];
        try {
            // Check if the user already exists with the provided email
            $user = User::where('email', $providerUser->getEmail())->first();
            $role = Role::find($roleId)->first();

            if ($user) {

                // If user exists and is registered with the same provider, log them in
                if ($user->provider == $providerName && $user->provider_id == $providerUser->getId()) {

                    if(!$user->roles->contains($roleId)) {
                        $redirectParams['statusCode'] = 403;
                        $redirectParams['status'] = 'false';
                        $redirectParams['bearer_token'] = null;
                        $redirectParams['message'] = "You dont have any account against this role, login with other role";
                        $redirectParams['role_id'] = $roleId;
    
                        return redirect()->away($baseRedirectURI."?".http_build_query($redirectParams));
                    }
                    // Create a new token for the user
                    $token = $user->createToken('auth_token');
                    $plainTextToken = $token->plainTextToken;
                    $tokenId = $token->accessToken->id;

                    // Start a new session for the user
                    $this->startSession($user, $deviceId, $tokenId);

                    // Redirect to the frontend with the bearer token, using consistent params
                    $redirectParams['statusCode'] = 200;
                    $redirectParams['status'] = 'true';
                    $redirectParams['bearer_token'] = $plainTextToken;
                    $redirectParams['message'] = "User logged in successfully";
                    $redirectParams['role_id'] = $roleId;

                    return redirect()->away($baseRedirectURI."?".http_build_query($redirectParams));
                } else {
                    $redirectParams['statusCode'] = 404;
                    $redirectParams['status'] = 'false';
                    $redirectParams['bearer_token'] = null;
                    $redirectParams['message'] = 'Registred with a different account, Please login with that';
                    $redirectParams['role_id'] = $roleId;

                    return redirect()->away($baseRedirectURI."?".http_build_query($redirectParams));
                }
            } else {
                // Handle registration based on roleId
                if ($roleId === "1") {
                    // Register the user as a Client
                    $user = User::create([
                        'name' => $providerUser->getName(),
                        'email' => $providerUser->getEmail(),
                        'provider' => $providerName,
                        'provider_id' => $providerUser->getId(),
                    ]);

                    // Assign the role to the user
                    $user->roles()->attach($roleId);
                    $user->email_verified_at = now();
                    $user->save();

                    // Log the user in
                    $token = $user->createToken('auth_token');
                    $plainTextToken = $token->plainTextToken;
                    $tokenId = $token->accessToken->id;

                    // Start a session for the user
                    $this->startSession($user, $deviceId, $tokenId);

                    $redirectParams['statusCode'] = 201;
                    $redirectParams['status'] = 'true';
                    $redirectParams['bearer_token'] = $plainTextToken;
                    $redirectParams['message'] = 'Client account created successfully, you are now logged in into the app';
                    $redirectParams['role_id'] = $roleId;

                    // Redirect to the frontend with the bearer token, using consistent params
                    return redirect()->away($baseRedirectURI."?".http_build_query($redirectParams));
                } else {
                    // For Housemaid role, cache user data and redirect to complete additional steps
                    Cache::forget('registration_' . $providerUser->getEmail());

                    Cache::put('registration_' . $providerUser->getEmail(), [
                        'name' => $providerUser->getName(),
                        'email' => $providerUser->getEmail(),
                        'provider' => $providerUser->getName(),
                        'provider_id' => $providerUser->getId(),
                        'role_id' => $roleId,
                    ], now()->addMinutes(10));


                    $redirectParams['statusCode'] = 202;
                    $redirectParams['status'] = 'true';
                    $redirectParams['bearer_token'] = null;
                    $redirectParams['message'] = 'Housemaide account creation in-progress, proceed to complete further questions';
                    $redirectParams['role_id'] = $roleId;
                    // Redirect to the frontend to complete further registration steps with consistent params
                    return redirect()->away($baseRedirectURI."?".http_build_query($redirectParams));
                }
            }
        } catch (\Exception $e) {
            // Handle any other internal server error with consistent error params
            $redirectParams['statusCode'] = 500;
            $redirectParams['status'] = 'false';
            $redirectParams['bearer_token'] = null;
            $redirectParams['message'] = 'Internal server error';

            return redirect()->away($baseRedirectURI."?".http_build_query($redirectParams));
        }
    }
   
}
