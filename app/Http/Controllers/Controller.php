<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use App\Models\Session;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\JsonResponse;
use Laravel\Sanctum\PersonalAccessToken;  

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    // Error Handling Method
    protected function handleError(\Exception $e): JsonResponse
    {
        $this->logErrorToCsv($e);
        return response()->json([
            'satusCode' => 500, 
            'status' => false,
            'message' => 'Internal Server Error',
            'data' => null,
        ], 500);
    }

    // Log Errors to CSV for Debugging
    private function logErrorToCsv(\Exception $e)
    {
        $filePath = storage_path('logs/errors.csv');
        $errorData = [
            // Error data (timestamps, message, etc.)
        ];

        $file = fopen($filePath, 'a');
        if (filesize($filePath) === 0) {
            fputcsv($file, array_keys($errorData));
        }
        fputcsv($file, $errorData);
        fclose($file);
    }

    // Logout Method
    public function logout(Request $request)
    {
        try {
            $user = $request->user();
            $bearerToken = $request->bearerToken();
            $token = PersonalAccessToken::findToken($bearerToken);
            $tokenId = $token->id;
            $userId = $token->tokenable_id;
    
            // Invalidate the current session token
            Session::where('user_id', $userId)
                ->where('token_id', $tokenId )
                ->update(['is_active' => false]);
    
            // Revoke the current token
            $user->currentAccessToken()->delete();
            
            return $this->jsonResponse(200, true, 'Logged out successfully', null);
        } catch (\Exception $e) {
            // If an error occurs, handle it with the protected handleError method
            return $this->handleError($e);
        }
    }

    // Helper json method:
    protected function jsonResponse(int $statusCode, bool $status, string $message, $data)
    {
        return response()->json([
            'statusCode' => $statusCode,
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    // Helper Session Start:
    protected function startSession($user, $deviceId, $tokenId) 
    {
        $session = Session::create([
            'user_id' => $user->id,
            'token_id' => $tokenId,
            'device_id' => $deviceId,                  
            'is_active' => true,                  
        ]);
        return $session;
    }

}