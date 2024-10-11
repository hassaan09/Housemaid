<?php

namespace App\Http\Controllers;

use App\Mail\OtpEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class MailController extends Controller
{
    public function sendEmail($email, $otp) {
        $to = $email;
        $msg = $otp;
        $subject = "OTP for registeration";
        Mail::to($to)->send(new OtpEmail($msg,$subject));


    }
}
