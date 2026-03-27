<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';

$pdo = db();

// If already verified and logged in, send to dashboard
if (current_user()) {
    redirect_by_role();
}

$email = trim($_GET['email'] ?? $_POST['email'] ?? '');
$errors = [];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = trim($_POST['otp'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email.';
    }
    if ($otp === '') {
        $errors[] = 'Please enter the code sent to your email.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $errors[] = 'Account not found.';
        } elseif ((int)$user['is_email_verified'] === 1) {
            $message = 'Email already verified. You can log in.';
        } elseif (empty($user['otp_code_hash']) || empty($user['otp_expires_at'])) {
            $errors[] = 'No active verification code. Please sign up again.';
        } elseif (strtotime($user['otp_expires_at']) < time()) {
            $errors[] = 'Verification code has expired. Please sign up again.';
        } elseif (!password_verify($otp, $user['otp_code_hash'])) {
            $errors[] = 'Incorrect verification code.';
        } else {
            $upd = $pdo->prepare("UPDATE users SET is_email_verified = 1, otp_code_hash = NULL, otp_expires_at = NULL WHERE id = ?");
            $upd->execute([$user['id']]);
            $message = 'Email verified successfully. You can now log in.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Email — MDRRMO San Ildefonso</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/index.css">
    <style>
        /* =============================================
           DESKTOP LAYOUT — only activates at 900px+
           ============================================= */
        @media (min-width: 900px) {
            #splash,
            #login-page {
                display: none !important;
            }
            #desktop-page {
                display: flex !important;
            }
        }

        #desktop-page {
            display: none;
            position: fixed;
            inset: 0;
            width: 100%;
            height: 100%;
            font-family: 'Poppins', sans-serif;
            background:
                radial-gradient(ellipse at 30% 60%, rgba(140,25,10,0.55) 0%, transparent 55%),
                radial-gradient(ellipse at 75% 30%, rgba(80,10,5,0.6) 0%, transparent 55%),
                #0d0806;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            z-index: 100;
        }

        #desktop-page::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='56' height='100'%3E%3Cpath d='M28 66L0 50V18L28 2l28 16v32z' fill='none' stroke='rgba(255,255,255,0.03)' stroke-width='1'/%3E%3Cpath d='M28 100L0 84V52l28-16 28 16v32z' fill='none' stroke='rgba(255,255,255,0.03)' stroke-width='1'/%3E%3C/svg%3E");
            pointer-events: none;
            z-index: 0;
        }

        #desktop-page::after {
            content: '';
            position: absolute;
            width: 700px;
            height: 700px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(160,40,15,0.18) 0%, transparent 65%);
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            pointer-events: none;
            z-index: 0;
            animation: dtBgPulse 6s ease-in-out infinite;
        }

        @keyframes dtBgPulse {
            0%, 100% { opacity: 0.7; transform: translate(-50%,-50%) scale(1); }
            50%       { opacity: 1;   transform: translate(-50%,-50%) scale(1.08); }
        }

        /* ---- CENTERED CARD ---- */
        .dt-card {
            position: relative;
            z-index: 1;
            width: calc(100% - 80px);
            max-width: 900px;
            margin: 0 auto;
            background: rgba(18, 12, 10, 0.75);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 24px;
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            box-shadow:
                0 32px 80px rgba(0,0,0,0.6),
                0 0 0 1px rgba(192,57,30,0.08) inset;
            display: flex;
            overflow: hidden;
            height: calc(100vh - 80px);
            max-height: 640px;
        }

        /* ---- CARD LEFT ---- */
        .dt-card-left {
            width: 44%;
            flex-shrink: 0;
            background: linear-gradient(160deg, #1f0b06 0%, #3a1008 40%, #8b1a0a 80%, #c0391e 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 52px 40px;
            position: relative;
            overflow: hidden;
            text-align: center;
        }

        .dt-card-left::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at 50% 40%, rgba(255,80,20,0.15) 0%, transparent 60%),
                url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='56' height='100'%3E%3Cpath d='M28 66L0 50V18L28 2l28 16v32z' fill='none' stroke='rgba(255,255,255,0.05)' stroke-width='1'/%3E%3Cpath d='M28 100L0 84V52l28-16 28 16v32z' fill='none' stroke='rgba(255,255,255,0.05)' stroke-width='1'/%3E%3C/svg%3E");
            pointer-events: none;
        }

        .dt-seal-wrap {
            position: relative;
            z-index: 1;
            width: 120px;
            height: 120px;
            margin-bottom: 22px;
        }

        .dt-seal-wrap img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            filter: drop-shadow(0 6px 28px rgba(0,0,0,0.55));
            border-radius: 50%;
        }

        /* Envelope icon (replaces seal on OTP page) */
        .dt-otp-icon {
            position: relative;
            z-index: 1;
            width: 96px;
            height: 96px;
            margin-bottom: 22px;
            background: rgba(0,0,0,0.25);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .dt-otp-icon svg {
            width: 42px;
            height: 42px;
            opacity: 0.9;
        }

        .dt-agency {
            position: relative;
            z-index: 1;
            font-family: 'Bebas Neue', sans-serif;
            font-size: 48px;
            letter-spacing: 7px;
            color: #fff;
            line-height: 1;
            margin-bottom: 6px;
            text-shadow: 0 2px 20px rgba(0,0,0,0.5);
        }

        .dt-tagline {
            position: relative;
            z-index: 1;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 3px;
            color: rgba(255,255,255,0.5);
            text-transform: uppercase;
            margin-bottom: 36px;
        }

        .dt-info-pills {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            gap: 12px;
            width: 100%;
        }

        .dt-pill {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(0,0,0,0.25);
            border: 1px solid rgba(255,255,255,0.09);
            border-radius: 12px;
            padding: 12px 16px;
            text-align: left;
            transition: background 0.2s;
        }

        .dt-pill:hover {
            background: rgba(0,0,0,0.38);
        }

        .dt-pill-icon {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            background: rgba(192,57,30,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 16px;
        }

        .dt-pill-text strong {
            display: block;
            font-size: 12.5px;
            font-weight: 700;
            color: #fff;
        }

        .dt-pill-text span {
            font-size: 11px;
            color: rgba(255,255,255,0.5);
            font-weight: 400;
        }

        .dt-bottom-badge {
            position: relative;
            z-index: 1;
            margin-top: 32px;
            font-size: 10px;
            color: rgba(255,255,255,0.25);
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        /* ---- CARD RIGHT ---- */
        .dt-card-right {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 52px 48px;
            position: relative;
            background: #ffffff;
        }

        .dt-form-header {
            margin-bottom: 32px;
        }

        .dt-welcome {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: #c0391e;
            margin-bottom: 8px;
        }

        .dt-form-title {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 42px;
            letter-spacing: 3px;
            color: #1a0a06;
            line-height: 1.05;
            margin-bottom: 6px;
        }

        .dt-form-subtitle {
            font-size: 13px;
            color: #888;
            font-weight: 400;
            line-height: 1.6;
        }

        .dt-form-subtitle strong {
            color: #444;
            font-weight: 600;
        }

        /* Error box */
        .dt-errors {
            background: rgba(192,57,30,0.08);
            border: 1px solid rgba(192,57,30,0.35);
            border-radius: 10px;
            padding: 14px 18px;
            margin-bottom: 24px;
        }

        .dt-errors ul {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .dt-errors li {
            font-size: 13px;
            color: #c0391e;
            font-weight: 500;
        }

        .dt-errors li::before {
            content: '⚠ ';
        }

        /* Success box */
        .dt-success {
            background: rgba(21, 128, 61, 0.08);
            border: 1px solid rgba(21, 128, 61, 0.35);
            border-radius: 10px;
            padding: 14px 18px;
            margin-bottom: 24px;
            font-size: 13px;
            color: #15803d;
            font-weight: 500;
        }

        .dt-success::before {
            content: '✓  ';
            font-weight: 700;
        }

        /* OTP input boxes */
        .dt-otp-group {
            display: flex;
            gap: 10px;
            margin-bottom: 28px;
            justify-content: center;
        }

        .dt-otp-group input[type="text"] {
            width: 52px;
            height: 58px;
            text-align: center;
            font-size: 22px;
            font-weight: 700;
            font-family: 'Poppins', sans-serif;
            color: #1a0a06;
            background: #f7f7f7;
            border: 1.5px solid #e8e8e8;
            border-radius: 12px;
            outline: none;
            caret-color: #c0391e;
            transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
            -webkit-text-fill-color: #1a0a06;
        }

        .dt-otp-group input[type="text"]:focus {
            border-color: #c0391e;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(192,57,30,0.10);
        }

        /* Hidden real input */
        #otp-hidden {
            display: none;
        }

        /* Timer */
        .dt-timer {
            text-align: center;
            font-size: 12.5px;
            color: #aaa;
            margin-bottom: 24px;
        }

        .dt-timer span {
            font-weight: 600;
            color: #c0391e;
        }

        /* Sign in button */
        .dt-btn-signin {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #c0391e 0%, #a02d15 100%);
            color: #fff;
            font-family: 'Poppins', sans-serif;
            font-size: 13.5px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.2s;
            box-shadow: 0 4px 20px rgba(192,57,30,0.35);
        }

        .dt-btn-signin:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(192,57,30,0.5);
        }

        .dt-btn-signin:active {
            transform: translateY(0);
        }

        .dt-btn-signin:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Divider */
        .dt-divider {
            display: flex;
            align-items: center;
            gap: 14px;
            margin: 22px 0;
        }

        .dt-divider::before,
        .dt-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e8e8e8;
        }

        .dt-divider span {
            font-size: 11px;
            color: #bbb;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        /* Resend + back links */
        .dt-links-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12.5px;
            color: #888;
            margin-top: 4px;
        }

        .dt-links-row a,
        .dt-links-row button {
            color: #c0391e;
            text-decoration: none;
            font-weight: 600;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 12.5px;
            font-family: 'Poppins', sans-serif;
            padding: 0;
            transition: color 0.2s;
        }

        .dt-links-row a:hover,
        .dt-links-row button:hover {
            color: #a02d15;
        }

        /* Status bar */
        .dt-status-bar {
            position: absolute;
            bottom: 16px;
            left: 0;
            right: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            font-size: 11px;
            color: rgba(255,255,255,0.18);
            z-index: 2;
            pointer-events: none;
        }

        .dt-status-dot {
            display: inline-block;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #22c55e;
            margin-right: 6px;
            box-shadow: 0 0 6px #22c55e;
            animation: dtBlink 2.5s ease-in-out infinite;
        }

        @keyframes dtBlink {
            0%, 100% { opacity: 1; }
            50%       { opacity: 0.3; }
        }

        /* =============================================
           MOBILE FALLBACK (below 900px)
           ============================================= */
        @media (max-width: 899px) {
            #desktop-page { display: none !important; }

            body {
                font-family: 'Poppins', sans-serif;
                margin: 0;
                min-height: 100vh;
                background:
                    radial-gradient(ellipse at 30% 60%, rgba(140,25,10,0.55) 0%, transparent 55%),
                    radial-gradient(ellipse at 75% 30%, rgba(80,10,5,0.6) 0%, transparent 55%),
                    #0d0806;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 24px;
                box-sizing: border-box;
            }

            .mobile-otp-card {
                width: 100%;
                max-width: 420px;
                background: #fff;
                border-radius: 20px;
                padding: 36px 28px 32px;
                box-shadow: 0 24px 60px rgba(0,0,0,0.45);
            }

            .mob-icon {
                width: 64px;
                height: 64px;
                background: rgba(192,57,30,0.08);
                border: 1.5px solid rgba(192,57,30,0.2);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 20px;
            }

            .mob-icon svg {
                width: 28px;
                height: 28px;
            }

            .mob-title {
                font-family: 'Bebas Neue', sans-serif;
                font-size: 32px;
                letter-spacing: 3px;
                color: #1a0a06;
                text-align: center;
                margin-bottom: 6px;
            }

            .mob-sub {
                font-size: 13px;
                color: #888;
                text-align: center;
                margin-bottom: 28px;
                line-height: 1.55;
            }

            .mob-sub strong { color: #444; font-weight: 600; }

            .mob-otp-group {
                display: flex;
                gap: 8px;
                justify-content: center;
                margin-bottom: 20px;
            }

            .mob-otp-group input[type="text"] {
                width: 46px;
                height: 54px;
                text-align: center;
                font-size: 20px;
                font-weight: 700;
                font-family: 'Poppins', sans-serif;
                color: #1a0a06;
                background: #f7f7f7;
                border: 1.5px solid #e8e8e8;
                border-radius: 10px;
                outline: none;
                caret-color: #c0391e;
                -webkit-text-fill-color: #1a0a06;
                transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
            }

            .mob-otp-group input[type="text"]:focus {
                border-color: #c0391e;
                background: #fff;
                box-shadow: 0 0 0 3px rgba(192,57,30,0.10);
            }

            .mob-errors {
                background: rgba(192,57,30,0.08);
                border: 1px solid rgba(192,57,30,0.35);
                border-radius: 10px;
                padding: 12px 16px;
                margin-bottom: 18px;
                font-size: 13px;
                color: #c0391e;
                font-weight: 500;
            }

            .mob-success {
                background: rgba(21,128,61,0.08);
                border: 1px solid rgba(21,128,61,0.35);
                border-radius: 10px;
                padding: 12px 16px;
                margin-bottom: 18px;
                font-size: 13px;
                color: #15803d;
                font-weight: 500;
            }

            .mob-btn {
                width: 100%;
                padding: 14px;
                background: linear-gradient(135deg, #c0391e 0%, #a02d15 100%);
                color: #fff;
                font-family: 'Poppins', sans-serif;
                font-size: 13px;
                font-weight: 700;
                letter-spacing: 2px;
                text-transform: uppercase;
                border: none;
                border-radius: 10px;
                cursor: pointer;
                box-shadow: 0 4px 20px rgba(192,57,30,0.35);
                margin-bottom: 20px;
            }

            .mob-links {
                display: flex;
                justify-content: space-between;
                font-size: 12.5px;
                color: #888;
            }

            .mob-links a,
            .mob-links button {
                color: #c0391e;
                text-decoration: none;
                font-weight: 600;
                background: none;
                border: none;
                cursor: pointer;
                font-size: 12.5px;
                font-family: 'Poppins', sans-serif;
                padding: 0;
            }

            .mob-timer {
                text-align: center;
                font-size: 12px;
                color: #aaa;
                margin-bottom: 16px;
            }

            .mob-timer span { color: #c0391e; font-weight: 600; }
        }
    </style>
</head>
<body>

<!-- ===================== DESKTOP VERSION ===================== -->
<div id="desktop-page">
    <div class="dt-card">

        <!-- LEFT: Branding -->
        <div class="dt-card-left">
            <div class="dt-otp-icon">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="2" y="4" width="20" height="16" rx="2" stroke="rgba(255,255,255,0.85)" stroke-width="1.5"/>
                    <path d="M2 8l10 6 10-6" stroke="rgba(255,255,255,0.85)" stroke-width="1.5" stroke-linejoin="round"/>
                </svg>
            </div>
            <div class="dt-agency">MDRRMO</div>
            <div class="dt-tagline">San Ildefonso</div>

            <div class="dt-info-pills">
                <div class="dt-pill">
                    <div class="dt-pill-icon">📧</div>
                    <div class="dt-pill-text">
                        <strong>Check your inbox</strong>
                        <span>A 6-digit code was sent to your email</span>
                    </div>
                </div>
                <div class="dt-pill">
                    <div class="dt-pill-icon">⏱</div>
                    <div class="dt-pill-text">
                        <strong>Code expires in 10 mins</strong>
                        <span>Request a new code if it expires</span>
                    </div>
                </div>
                <div class="dt-pill">
                    <div class="dt-pill-icon">🔒</div>
                    <div class="dt-pill-text">
                        <strong>One-time use only</strong>
                        <span>Code becomes invalid after use</span>
                    </div>
                </div>
            </div>
            <div class="dt-bottom-badge">MDRRMO · Emergency Management System</div>
        </div>

        <!-- RIGHT: Form -->
        <div class="dt-card-right">
            <div class="dt-form-header">
                <div class="dt-welcome">Step 2 of 2</div>
                <div class="dt-form-title">Verify Email</div>
                <div class="dt-form-subtitle">
                    Enter the 6-digit code we sent to<br>
                    <?php if ($email): ?>
                        <strong><?php echo htmlspecialchars($email); ?></strong>
                    <?php else: ?>
                        your email address.
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($errors): ?>
                <div class="dt-errors">
                    <ul>
                        <?php foreach ($errors as $err): ?>
                            <li><?php echo htmlspecialchars($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($message): ?>
                <div class="dt-success">
                    <?php echo htmlspecialchars($message); ?>
                    <?php if (strpos($message, 'verified successfully') !== false): ?>
                        <a href="../index.php" style="color:#15803d;font-weight:700;margin-left:4px;">Log in now →</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (!$message): ?>
            <form method="post" id="otp-form">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                <input type="hidden" name="otp" id="otp-hidden">

                <div class="dt-otp-group" id="dt-otp-boxes">
                    <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="one-time-code">
                    <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
                    <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
                    <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
                    <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
                    <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
                </div>

                <div class="dt-timer" id="dt-timer">
                    Code expires in <span id="dt-countdown">10:00</span>
                </div>

                <button type="submit" class="dt-btn-signin" id="dt-submit-btn" disabled>Verify Code</button>
            </form>

            <div class="dt-divider"><span>or</span></div>

            <div class="dt-links-row">
                <span>Didn't receive it?
                    <button type="button" onclick="alert('Resend functionality — wire to your resend OTP endpoint.')">Resend code</button>
                </span>
                <a href="../index.php">← Back to login</a>
            </div>
            <?php else: ?>
            <div class="dt-links-row" style="justify-content:center;margin-top:8px;">
                <a href="../index.php">← Back to login</a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="dt-status-bar">
        <span><span class="dt-status-dot"></span>System Online</span>
        <span>·</span>
        <span>MDRRMO San Ildefonso © <?php echo date('Y'); ?></span>
    </div>
</div>

<!-- ===================== MOBILE VERSION ===================== -->
<div class="mobile-otp-card" id="mobile-card">
    <div class="mob-icon">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="2" y="4" width="20" height="16" rx="2" stroke="#c0391e" stroke-width="1.5"/>
            <path d="M2 8l10 6 10-6" stroke="#c0391e" stroke-width="1.5" stroke-linejoin="round"/>
        </svg>
    </div>
    <div class="mob-title">Verify Email</div>
    <div class="mob-sub">
        Enter the 6-digit code sent to<br>
        <?php if ($email): ?>
            <strong><?php echo htmlspecialchars($email); ?></strong>
        <?php else: ?>
            your email address.
        <?php endif; ?>
    </div>

    <?php if ($errors): ?>
        <div class="mob-errors">
            <?php foreach ($errors as $err): ?>
                ⚠ <?php echo htmlspecialchars($err); ?><br>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($message): ?>
        <div class="mob-success">
            ✓ <?php echo htmlspecialchars($message); ?>
            <?php if (strpos($message, 'verified successfully') !== false): ?>
                <a href="../index.php" style="color:#15803d;font-weight:700;display:block;margin-top:6px;">Log in now →</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!$message): ?>
    <form method="post" id="mob-otp-form">
        <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
        <input type="hidden" name="otp" id="mob-otp-hidden">

        <div class="mob-otp-group" id="mob-otp-boxes">
            <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="one-time-code">
            <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
            <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
            <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
            <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
            <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
        </div>

        <div class="mob-timer">Code expires in <span id="mob-countdown">10:00</span></div>

        <button type="submit" class="mob-btn" id="mob-submit-btn" disabled>Verify Code</button>
    </form>

    <div class="mob-links">
        <button type="button" onclick="alert('Resend functionality — wire to your resend OTP endpoint.')">Resend code</button>
        <a href="../index.php">← Back to login</a>
    </div>
    <?php endif; ?>
</div>

<script>
(function () {
    /* ---- OTP box auto-advance for BOTH desktop and mobile ---- */
    function initOtpBoxes(groupId, hiddenId, submitBtnId, formId) {
        var group = document.getElementById(groupId);
        if (!group) return;
        var boxes   = Array.from(group.querySelectorAll('input[type="text"]'));
        var hidden  = document.getElementById(hiddenId);
        var btn     = document.getElementById(submitBtnId);
        var form    = document.getElementById(formId);
        if (!boxes.length || !hidden || !btn) return;

        function collectValue() {
            return boxes.map(function (b) { return b.value; }).join('');
        }

        function updateState() {
            var val = collectValue();
            hidden.value = val;
            btn.disabled = val.length < 6;
        }

        boxes.forEach(function (box, i) {
            box.addEventListener('input', function (e) {
                var v = e.target.value.replace(/\D/g, '');
                if (v.length > 1) {
                    // Handle paste across multiple boxes
                    for (var j = 0; j < v.length && (i + j) < boxes.length; j++) {
                        boxes[i + j].value = v[j];
                    }
                    var next = Math.min(i + v.length, boxes.length - 1);
                    boxes[next].focus();
                } else {
                    e.target.value = v;
                    if (v && i < boxes.length - 1) {
                        boxes[i + 1].focus();
                    }
                }
                updateState();
            });

            box.addEventListener('keydown', function (e) {
                if (e.key === 'Backspace' && !e.target.value && i > 0) {
                    boxes[i - 1].focus();
                    boxes[i - 1].value = '';
                    updateState();
                }
            });

            box.addEventListener('paste', function (e) {
                e.preventDefault();
                var text = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
                for (var j = 0; j < text.length && j < boxes.length; j++) {
                    boxes[j].value = text[j];
                }
                var next = Math.min(text.length, boxes.length - 1);
                boxes[next].focus();
                updateState();
            });
        });

        boxes[0].focus();

        if (form) {
            form.addEventListener('submit', function () {
                hidden.value = collectValue();
            });
        }
    }

    initOtpBoxes('dt-otp-boxes',  'otp-hidden',     'dt-submit-btn', 'otp-form');
    initOtpBoxes('mob-otp-boxes', 'mob-otp-hidden',  'mob-submit-btn', 'mob-otp-form');

    /* ---- Countdown timer (shared 10 min) ---- */
    function startCountdown(elementId) {
        var el = document.getElementById(elementId);
        if (!el) return;
        var seconds = 10 * 60;
        var interval = setInterval(function () {
            seconds--;
            if (seconds <= 0) {
                clearInterval(interval);
                el.textContent = '0:00';
                el.style.color = '#c0391e';
                return;
            }
            var m = Math.floor(seconds / 60);
            var s = seconds % 60;
            el.textContent = m + ':' + (s < 10 ? '0' : '') + s;
            if (seconds <= 60) {
                el.style.color = '#c0391e';
            }
        }, 1000);
    }

    startCountdown('dt-countdown');
    startCountdown('mob-countdown');

    /* ---- Hide mobile card on desktop, hide desktop on mobile ---- */
    function adjustVisibility() {
        var mobileCard = document.getElementById('mobile-card');
        var desktopPage = document.getElementById('desktop-page');
        if (window.innerWidth >= 900) {
            if (mobileCard) mobileCard.style.display = 'none';
            if (desktopPage) desktopPage.style.display = 'flex';
        } else {
            if (mobileCard) mobileCard.style.display = 'block';
            if (desktopPage) desktopPage.style.display = 'none';
        }
    }
    adjustVisibility();
    window.addEventListener('resize', adjustVisibility);
})();
</script>

</body>
</html>