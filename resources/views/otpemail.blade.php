<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Verification</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f7f7f7; font-family: Arial, sans-serif;">
    <div style="max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        <div style="padding: 20px; text-align: center; background-color: #007bff; border-radius: 8px 8px 0 0;">
            <h2 style="color: #ffffff; margin: 0;">OTP Verification</h2>
        </div>
        <div style="padding: 20px; text-align: center;">
            <h3 style="color: #333;">Your OTP Code</h3>
            <p style="font-size: 24px; font-weight: bold; color: #007bff;">{{$msg}}</p>
            <p style="color: #555;">This code will expire in 10 minutes. Please do not share it with anyone.</p>
        </div>
        <div style="padding: 20px; text-align: center; background-color: #f1f1f1; border-radius: 0 0 8px 8px;">
            <!-- <p style="margin: 0; color: #777;">From Team HouseMaide</p> -->
            <p style="margin: 0; color: #777; display: inline-block; vertical-align: middle;">
                <img src="{{ asset('images/Webp.net-resizeimage.jpg') }}" alt="HouseMaide Logo" style="width: 40px; height: auto; vertical-align: middle; margin-right: 8px;">
                From Team HouseMaide
            </p>
        </div>
    </div>
</body>
</html>
