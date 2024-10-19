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

            // Encode device_id and role_id in the state parameter
            $state = base64_encode(json_encode([
                'device_id' => $deviceId,

            ]));

            $redirectUrl = Socialite::driver('google')->stateless()->with(['state' => $state])->redirect()->getTargetUrl();
            // Redirection status code causes issue, so beware of using 302
            return $this->jsonResponse(
                200,
                true,
                "Redirection to google sign-in page",
                ['url' => $redirectUrl]
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

    public function handleGoogleCallback(Request $request) {

        // Decode the state parameter
        $state = json_decode(base64_decode($request->query('state')),true);
        $deviceId = $state['device_id'];
        // Get the user's information from the callback
        $googleUser = Socialite::driver('google')->stateless()->user();
        return $this->createOrLoginUser($googleUser, 'google',$deviceId);
        
    }

    private function createOrLoginUser($providerUser, $providerName, $deviceId) {
        $baseRedirectURI = env('BASE_REDIRECT_URL');
        $redirectParams = [];
        try {
            // Check if the user already exists with the provided email
            $user = User::where('email', $providerUser->getEmail())->first();

            if ($user) {

                // Login
                // If user exists and is registered with the same provider, log them in
                if ($user->provider == $providerName && $user->provider_id == $providerUser->getId()) {

                    // Create a new token for the user
                    $token = $user->createToken('auth_token'); 
                    $plainTextToken = $token->plainTextToken; // To issue the user with a token
                    $tokenId = $token->accessToken->id; // To pass the token id to the session table

                    // Start a new session for the user
                    $userSession = $this->startSession($user, $deviceId, $tokenId);

                   // For front-end ease:
                    $roles = $user->roles->pluck('id')->toArray();
                    $roleValue = in_array(1, $roles) && in_array(2, $roles) ? 12 : (in_array(1, $roles) ? 1 : 0);

                    // Profile pic:
                    $profilePicUrl = asset($user->profile_pic);
        

                    // Redirect to the frontend with the bearer token, using consistent params
                    $redirectParams['statusCode'] = 200;
                    $redirectParams['status'] = 'true';
                    $redirectParams['bearerToken'] = $plainTextToken;
                    $redirectParams['userName'] = $user->name;
                    $redirectParams['email'] = $user->email;     
                    $redirectParams['profilePicUrl'] = $profilePicUrl;              
                    $redirectParams['roles'] = $roleValue;

                    return redirect()->away($baseRedirectURI."?".http_build_query($redirectParams));
                } else {

                    $redirectParams['statusCode'] = 409;
                    $redirectParams['status'] = 'false';
                    $redirectParams['bearer_token'] = null;
                    $redirectParams['message'] = 'Registred with a different provider, Please login with that';

                    return redirect()->away($baseRedirectURI."?".http_build_query($redirectParams));
                    
                }
            } else {
                    // Register the user as a Client
                    $user = User::create([
                        'name' => $providerUser->getName(),
                        'email' => $providerUser->getEmail(),
                        'provider' => $providerName,
                        'provider_id' => $providerUser->getId(),
                    ]);

                    // Assign the role to the user
                    $user->roles()->attach(1);
                    $user->email_verified_at = now();
                    $user->save();

                    // Log the user in
                    // Create a new token for the user
                    $token = $user->createToken('auth_token'); 
                    $plainTextToken = $token->plainTextToken; // To issue the user with a token
                    $tokenId = $token->accessToken->id; // To pass the token id to the session table

                    // Start a new session for the user
                    $userSession = $this->startSession($user, $deviceId, $tokenId);

                    // For front-end ease:
                    $roles = $user->roles->pluck('id')->toArray();
                    $roleValue = in_array(1, $roles) && in_array(2, $roles) ? 12 : (in_array(1, $roles) ? 1 : 0);

                    $profilePicUrl = asset($user->profile_pic);
        
                    $redirectParams['statusCode'] = 201;
                    $redirectParams['status'] = 'true';
                    $redirectParams['bearerToken'] = $plainTextToken;
                    $redirectParams['userName'] = $user->name;
                    $redirectParams['email'] = $user->email;     
                    $redirectParams['profilePicUrl'] = $profilePicUrl;              
                    $redirectParams['roles'] = $roleValue;

                    // Redirect to the frontend with the bearer token, using consistent params
                    return redirect()->away($baseRedirectURI."?".http_build_query($redirectParams));
                } 
        } catch (\Exception $e) {
            // Handle any other internal server error with consistent error params
            $redirectParams['statusCode'] = 500;
            $redirectParams['status'] = 'false';
            $redirectParams['bearerToken'] = null;
            $redirectParams['message'] = 'Internal server error';

            return redirect()->away($baseRedirectURI."?".http_build_query($redirectParams));
        }
    }
   
}
