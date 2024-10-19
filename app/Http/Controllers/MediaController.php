<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function showProfilePicture($filename)
    {
        $filePath = 'uploads/' . $filename;
        if(!Storage::exists($filePath)) {
            return response()->json(['error' => 'File not found'], 404);
        }
        return Storage::download($filePath);
    }
}
