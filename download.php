<?php
// Google Drive file ID extracted from your share link
$fileId = '1XvXQ-33FVyudKCr6tWiPeyRjDqdsaP8f';

// Direct download link
$downloadUrl = "https://drive.google.com/uc?export=download&id=" . $fileId;

// Redirect after a short delay
header("refresh:1;url=$downloadUrl");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Downloading CENTRIX...</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background: #0f1117;
            color: white;
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            text-align: center;
        }

        .box {
            background: #1a1d26;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 0 30px rgba(0,0,0,0.3);
            max-width: 400px;
            width: 90%;
        }

        .loader {
            width: 50px;
            height: 50px;
            border: 5px solid rgba(255,255,255,0.2);
            border-top: 5px solid #ff5b3d;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        h2 {
            margin-bottom: 10px;
        }

        p {
            color: #b0b0b0;
            margin-bottom: 20px;
        }

        a {
            color: #ff5b3d;
            text-decoration: none;
            font-weight: bold;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="box">
        <div class="loader"></div>
        <h2>Your download is starting...</h2>
        <p>Please wait while we prepare the CENTRIX app download.</p>
        <a href="<?php echo $downloadUrl; ?>">Click here if download does not start</a>
    </div>
</body>
</html>