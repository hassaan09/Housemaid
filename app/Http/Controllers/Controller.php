<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use App\Models\Session;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\JsonResponse;  

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
        $user = $request->user();

        // Invalidate the current session token
        Session::where('user_id', $user->id)
            ->where('token', $request->bearerToken())
            ->update(['is_active' => false]);

        // Revoke the current token
        $user->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
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
            'device_id' => $deviceId,    
            'token_id' => $tokenId,  
            'last_activity' => now(),              
            'is_active' => true,                  
        ]);
        return $session;
    }

}