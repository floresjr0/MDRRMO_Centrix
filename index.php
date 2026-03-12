<?php
require_once __DIR__ . '/pages/db.php';
require_once __DIR__ . '/pages/session.php';

$pdo = db();

if (current_user()) {
    redirect_by_role();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    // $lat      = $_POST['lat'] ?? null;
    // $lng      = $_POST['lng'] ?? null;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Kailangan ng wastong email.';
    }

    if ($password === '') {
        $errors[] = 'Kailangan ng password.';
    }

    // if (!$lat || !$lng) {
    //     $errors[] = 'Kailangan ang access sa lokasyon para mag-login.';
    // }

    if (!$errors) {

        $stmt = $pdo->prepare("SELECT u.*, b.municipality, b.province
                               FROM users u
                               JOIN barangays b ON b.id = u.barangay_id
                               WHERE u.email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {

            $errors[] = 'Mali ang email o password.';

        } elseif ((int)$user['is_active'] !== 1) {

            $errors[] = 'Ang account ay hindi aktibo.';

        } elseif ((int)$user['is_email_verified'] !== 1) {

            $errors[] = 'Paki-verify muna ang iyong email bago mag-login.';

        } elseif ($user['municipality'] !== 'San Ildefonso' || $user['province'] !== 'Bulacan') {

            $errors[] = 'Para lamang sa mga residente ng San Ildefonso, Bulacan ang access na ito.';

        } else {

            $_SESSION['user_id'] = $user['id'];
            redirect_by_role();

        }
    }
}
?>
<!DOCTYPE html>
<html lang="tl">
<head>
<meta charset="UTF-8">
<title>Login - MDRRMO San Ildefonso</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Poppins:wght@300;400;500;600;700;800&family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/index.css">
<style>

/* =============================================
   DESKTOP LAYOUT — only activates at 900px+
   Mobile styles remain untouched below
   ============================================= */

@media (min-width: 900px) {

  /* Hide mobile splash & login */
  #splash,
  #login-page {
    display: none !important;
  }

  /* Show desktop wrapper */
  #desktop-page {
    display: flex !important;
  }
}

/* Desktop page — hidden on mobile */
#desktop-page {
  display: none;
  position: fixed;
  inset: 0;
  width: 100%;
  height: 100%;
  font-family: 'Poppins', sans-serif;

  /* Same dark-red gradient vibe as mobile splash */
  background:
    radial-gradient(ellipse at 30% 60%, rgba(140,25,10,0.55) 0%, transparent 55%),
    radial-gradient(ellipse at 75% 30%, rgba(80,10,5,0.6) 0%, transparent 55%),
    #0d0806;

  align-items: center;
  justify-content: center;
  overflow: hidden;
  z-index: 100;
}

/* Honeycomb texture overlay on full background */
#desktop-page::before {
  content: '';
  position: absolute;
  inset: 0;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='56' height='100'%3E%3Cpath d='M28 66L0 50V18L28 2l28 16v32z' fill='none' stroke='rgba(255,255,255,0.03)' stroke-width='1'/%3E%3Cpath d='M28 100L0 84V52l28-16 28 16v32z' fill='none' stroke='rgba(255,255,255,0.03)' stroke-width='1'/%3E%3C/svg%3E");
  pointer-events: none;
  z-index: 0;
}

/* Floating glow blobs */
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

/* ---- CARD LEFT: Branding ---- */
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

/* Inner glow on left */
.dt-card-left::before {
  content: '';
  position: absolute;
  inset: 0;
  background:
    radial-gradient(circle at 50% 40%, rgba(255,80,20,0.15) 0%, transparent 60%),
    url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='56' height='100'%3E%3Cpath d='M28 66L0 50V18L28 2l28 16v32z' fill='none' stroke='rgba(255,255,255,0.05)' stroke-width='1'/%3E%3Cpath d='M28 100L0 84V52l28-16 28 16v32z' fill='none' stroke='rgba(255,255,255,0.05)' stroke-width='1'/%3E%3C/svg%3E");
  pointer-events: none;
}

/* Seal image — no orbit, just the logo */
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

/* Agency name */
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

/* Info pills */
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

/* Bottom badge */
.dt-bottom-badge {
  position: relative;
  z-index: 1;
  margin-top: 32px;
  font-size: 10px;
  color: rgba(255,255,255,0.25);
  letter-spacing: 1.5px;
  text-transform: uppercase;
}

/* ---- CARD RIGHT: Login Form — White ---- */
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
  margin-bottom: 36px;
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

/* Form fields */
.dt-field {
  margin-bottom: 20px;
}

.dt-field label {
  display: block;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 2px;
  text-transform: uppercase;
  color: #999;
  margin-bottom: 8px;
}

.dt-field input {
  width: 100%;
  background: #f7f7f7;
  border: 1.5px solid #e8e8e8;
  border-radius: 10px;
  padding: 13px 16px;
  font-size: 14px;
  color: #1a0a06;
  font-family: 'Poppins', sans-serif;
  outline: none;
  transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
  box-sizing: border-box;
  -webkit-text-fill-color: #1a0a06;
  caret-color: #c0391e;
}

/* Override browser autofill */
.dt-field input:-webkit-autofill,
.dt-field input:-webkit-autofill:hover,
.dt-field input:-webkit-autofill:focus {
  -webkit-box-shadow: 0 0 0 1000px #f7f7f7 inset !important;
  -webkit-text-fill-color: #1a0a06 !important;
  border: 1.5px solid #e8e8e8;
  transition: background-color 5000s ease-in-out 0s;
}

.dt-field input::placeholder {
  color: #bbb;
}

.dt-field input:focus {
  border-color: #c0391e;
  background: #fff;
  box-shadow: 0 0 0 3px rgba(192,57,30,0.10);
}

/* Forgot row */
.dt-forgot {
  text-align: right;
  margin-top: -10px;
  margin-bottom: 26px;
}

.dt-forgot a {
  font-size: 12px;
  color: #c0391e;
  text-decoration: none;
  font-weight: 500;
  transition: color 0.2s;
}

.dt-forgot a:hover {
  color: #a02d15;
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
  position: relative;
  overflow: hidden;
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

/* Signup link */
.dt-signup-row {
  text-align: center;
  font-size: 13px;
  color: #888;
}

.dt-signup-row a {
  color: #c0391e;
  text-decoration: none;
  font-weight: 600;
  transition: color 0.2s;
}

.dt-signup-row a:hover {
  color: #a02d15;
  text-decoration: underline;
}

/* Status bar at bottom of page */
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

</style>
</head>
<body>

<!-- ================================================
     MOBILE: Splash Screen
     ================================================ -->
<div id="splash" onclick="goToLogin()">

  <div class="bg-base"></div>
  <div class="bg-pulse"></div>
  <div class="bg-drift"></div>
  <div class="honeycomb"></div>

  <div class="particle" style="width:6px;height:6px;left:12%;animation-duration:10s;animation-delay:0s;"></div>
  <div class="particle" style="width:4px;height:4px;left:28%;animation-duration:13s;animation-delay:2.5s;"></div>
  <div class="particle" style="width:7px;height:7px;left:52%;animation-duration:8s;animation-delay:1s;"></div>
  <div class="particle" style="width:5px;height:5px;left:70%;animation-duration:11s;animation-delay:3.5s;"></div>
  <div class="particle" style="width:3px;height:3px;left:85%;animation-duration:14s;animation-delay:0.5s;"></div>
  <div class="particle" style="width:5px;height:5px;left:40%;animation-duration:9s;animation-delay:5s;"></div>

  <div class="splash-content">
    <div class="seal-shine-wrap">
      <canvas id="orbitCanvas" width="280" height="280"></canvas>
      <div class="seal-wrap">
        <img class="seal-img"
             src="./img/mdrrmo.png"
             alt="MDRRMO Seal"
             onerror="this.style.display='none'; document.querySelector('.seal-fallback').style.display='grid';">
        <div class="seal-fallback">
          <div class="sq tl">🌳</div>
          <div class="sq tr">🌊</div>
          <div class="sq bl">🌍</div>
          <div class="sq br">🔥</div>
        </div>
      </div>
    </div>

    <div class="splash-title">MDRRMO</div>
    <div class="splash-hashtag">#BidaAngLagingHanda</div>
    <div class="tap-hint">Pindutin kahit saan para magpatuloy</div>
  </div>

</div>


<!-- ================================================
     MOBILE: Login Page
     ================================================ -->
<div id="login-page">

  <div class="login-shell">

    <div class="hero">
      <div class="logo-row" id="logoRow">
        <div class="logo-circle">
          <img src="./img/mdrrmo.png" alt="MDRRMO"
               onerror="this.style.display='none'">
          <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 2L3 7v5c0 5.25 3.75 10.15 9 11.35C17.25 22.15 21 17.25 21 12V7L12 2z"/>
          </svg>
        </div>
        <div class="logo-text">
          <strong>Office of the Municipal Disaster Risk Reduction<br>and Management Office</strong>
          <span>San Ildefonso, Bulacan</span>
        </div>
      </div>

      <div class="hero-headline" id="heroHeadline">
        Mag-login sa<br>iyong account
      </div>
    </div>

    <div class="card" id="card">

      <?php if ($errors): ?>
      <div class="auth-errors">
        <ul>
          <?php foreach ($errors as $err): ?>
            <li><?php echo htmlspecialchars($err); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <form method="post" class="auth-form">

        <div class="field">
          <label class="field-label" for="email">Email / Username</label>
          <input type="email" id="email" name="email"
                 placeholder="Ilagay ang iyong email" required
                 value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>

        <div class="field">
          <label class="field-label" for="password">Password</label>
          <input type="password" id="password" name="password"
                 placeholder="Ilagay ang iyong password" required>
        </div>

        <div class="forgot-row">
          <a href="#">Nakalimutan ang Password?</a>
        </div>

        <input type="hidden" name="lat" id="lat">
        <input type="hidden" name="lng" id="lng">

        <button type="submit" class="btn-signin">Mag-login</button>

      </form>

      <p class="signup-row">Wala pang account? <a href="pages/signup.php">Mag-sign up</a></p>

    </div>

  </div>

</div>


<!-- ================================================
     DESKTOP: Centered Card Layout
     ================================================ -->
<div id="desktop-page">

  <!-- CENTERED CARD -->
  <div class="dt-card">

    <!-- LEFT: Branding -->
    <div class="dt-card-left">

      <!-- Seal — no orbit animation on desktop -->
      <div class="dt-seal-wrap">
        <img src="./img/mdrrmo.png" alt="MDRRMO Seal"
             onerror="this.style.display='none'">
      </div>

      <div class="dt-agency">MDRRMO</div>
      <div class="dt-tagline">#BidaAngLagingHanda</div>

      <div class="dt-info-pills">
        <div class="dt-pill">
          <div class="dt-pill-icon">🛡️</div>
          <div class="dt-pill-text">
            <strong>Disaster Risk Reduction</strong>
            <span>Proactive community preparedness & mitigation</span>
          </div>
        </div>
        <div class="dt-pill">
          <div class="dt-pill-icon">🚨</div>
          <div class="dt-pill-text">
            <strong>Emergency Response</strong>
            <span>Rapid coordination during crisis events</span>
          </div>
        </div>
        <div class="dt-pill">
          <div class="dt-pill-icon">📍</div>
          <div class="dt-pill-text">
            <strong>San Ildefonso, Bulacan</strong>
            <span>Serving all barangays of the municipality</span>
          </div>
        </div>
      </div>

      <div class="dt-bottom-badge">Municipal Government of San Ildefonso</div>

    </div>

    <!-- RIGHT: Login Form -->
    <div class="dt-card-right">

      <div class="dt-form-header">
        <div class="dt-welcome">Maligayang Pagbabalik</div>
        <div class="dt-form-title">Mag-login sa<br>Iyong Account</div>
        <div class="dt-form-subtitle">Manatiling handa at may kaalaman.<br>I-access ang iyong MDRRMO account.</div>
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

      <form method="post">

        <div class="dt-field">
          <label for="dt-email">Email Address</label>
          <input type="email" id="dt-email" name="email"
                 placeholder="yourname@example.com" required
                 value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>

        <div class="dt-field">
          <label for="dt-password">Password</label>
          <input type="password" id="dt-password" name="password"
                 placeholder="Ilagay ang iyong password" required>
        </div>

        <div class="dt-forgot">
          <a href="#">Nakalimutan ang Password?</a>
        </div>

        <input type="hidden" name="lat">
        <input type="hidden" name="lng">

        <button type="submit" class="dt-btn-signin">Mag-login</button>

      </form>

      <div class="dt-divider"><span>Bago pa lang?</span></div>

      <p class="dt-signup-row">Wala pang account? <a href="pages/signup.php">Mag-sign up</a></p>

    </div><!-- /.dt-card-right -->

  </div><!-- /.dt-card -->

  <!-- Status bar -->
  <div class="dt-status-bar">
    <span><span class="dt-status-dot"></span>System Online</span>
    <span>·</span>
    <span>MDRRMO · San Ildefonso, Bulacan</span>
  </div>

</div><!-- /#desktop-page -->



<script>

  /* ================================================
     MOBILE: Original orbit canvas animation
     ================================================ */

  const canvas = document.getElementById('orbitCanvas');
  const ctx    = canvas.getContext('2d');

  const CX       = 140;
  const CY       = 140;
  const R        = 114;
  const TAIL_RAD = (220 * Math.PI) / 180;
  const TOTAL    = Math.PI * 2 * 1.25;
  const DURATION = 7000;

  let startTime = null;
  let rafId     = null;

  setTimeout(() => {
    startTime = performance.now();
    rafId = requestAnimationFrame(draw);
  }, 1100);

  function draw(now) {
    const elapsed  = now - startTime;
    const progress = Math.min(elapsed / DURATION, 1);

    ctx.clearRect(0, 0, canvas.width, canvas.height);

    const eased     = easeInOutCubic(progress);
    const headAngle = -Math.PI / 2 + eased * TOTAL;
    const tailAngle = headAngle - TAIL_RAD;

    let alpha = 1;
    if (progress < 0.10) alpha = progress / 0.10;
    else if (progress > 0.72) alpha = 1 - (progress - 0.72) / 0.28;

    const STEPS = 100;
    for (let i = 0; i < STEPS; i++) {
      const frac = i / STEPS;
      const a0   = tailAngle + frac * TAIL_RAD;
      const a1   = tailAngle + (frac + 1 / STEPS) * TAIL_RAD;
      const segOpacity = Math.pow(frac, 1.8) * alpha;
      const w    = 1 + frac * 5.5;
      const rr   = 255;
      const gg   = Math.round(160 + frac * 90);
      const bb   = Math.round(20  + frac * 200);

      ctx.beginPath();
      ctx.arc(CX, CY, R, a0, a1);
      ctx.strokeStyle = `rgba(${rr},${gg},${bb},${segOpacity})`;
      ctx.lineWidth   = w;
      ctx.lineCap     = 'round';
      ctx.stroke();
    }

    const hx = CX + R * Math.cos(headAngle);
    const hy = CY + R * Math.sin(headAngle);

    let g = ctx.createRadialGradient(hx, hy, 0, hx, hy, 28);
    g.addColorStop(0,    `rgba(255,250,200,${0.85 * alpha})`);
    g.addColorStop(0.25, `rgba(255,210,100,${0.55 * alpha})`);
    g.addColorStop(0.6,  `rgba(255,140, 30,${0.18 * alpha})`);
    g.addColorStop(1,    `rgba(255, 80,  0,0)`);
    ctx.beginPath();
    ctx.arc(hx, hy, 28, 0, Math.PI * 2);
    ctx.fillStyle = g;
    ctx.fill();

    let core = ctx.createRadialGradient(hx, hy, 0, hx, hy, 6);
    core.addColorStop(0,   `rgba(255,255,255,${alpha})`);
    core.addColorStop(0.6, `rgba(255,240,160,${0.7 * alpha})`);
    core.addColorStop(1,   `rgba(255,200,80,0)`);
    ctx.beginPath();
    ctx.arc(hx, hy, 6, 0, Math.PI * 2);
    ctx.fillStyle = core;
    ctx.fill();

    if (progress < 1) {
      rafId = requestAnimationFrame(draw);
    } else {
      ctx.clearRect(0, 0, canvas.width, canvas.height);
    }
  }

  function easeInOutCubic(t) {
    return t < 0.5 ? 4*t*t*t : 1 - Math.pow(-2*t+2, 3)/2;
  }

  function goToLogin() {
    if (rafId) cancelAnimationFrame(rafId);

    document.getElementById('splash').classList.add('exit');

    setTimeout(() => {
      const splash = document.getElementById('splash');
      splash.style.display = 'none';

      const loginPage = document.getElementById('login-page');
      loginPage.classList.add('visible');

      requestAnimationFrame(() => {
        setTimeout(() => {
          document.getElementById('logoRow').classList.add('visible');
          document.getElementById('heroHeadline').classList.add('visible');
        }, 60);

        setTimeout(() => {
          document.getElementById('card').classList.add('visible');
        }, 180);
      });

    }, 420);
  }

  setTimeout(goToLogin, 8500);

  document.querySelectorAll('.field input').forEach(function (inp) {
    inp.addEventListener('focus', function () {
      this.style.setProperty('border-bottom', '1.5px solid #c0391e', 'important');
    });
    inp.addEventListener('blur', function () {
      this.style.setProperty('border-bottom', '1.5px solid #c8c8c8', 'important');
    });
  });

  var btn = document.querySelector('.btn-signin');
  if (btn) {
    btn.addEventListener('click', function (e) {
      var r   = btn.getBoundingClientRect();
      var sz  = Math.max(r.width, r.height);
      var rpl = document.createElement('span');
      rpl.style.cssText =
        'position:absolute;border-radius:50%;pointer-events:none;' +
        'width:'  + sz + 'px;height:' + sz + 'px;' +
        'left:'   + (e.clientX - r.left - sz / 2) + 'px;' +
        'top:'    + (e.clientY - r.top  - sz / 2) + 'px;' +
        'background:rgba(255,255,255,0.20);' +
        'transform:scale(0);opacity:1;' +
        'transition:transform 0.55s ease,opacity 0.55s ease;';
      btn.appendChild(rpl);
      requestAnimationFrame(function () {
        rpl.style.transform = 'scale(2.6)';
        rpl.style.opacity   = '0';
      });
      setTimeout(function () { rpl.remove(); }, 600);
    });
  }


  /* Desktop button ripple */
  var dtBtn = document.querySelector('.dt-btn-signin');
  if (dtBtn) {
    dtBtn.addEventListener('click', function(e) {
      var r   = dtBtn.getBoundingClientRect();
      var sz  = Math.max(r.width, r.height);
      var rpl = document.createElement('span');
      rpl.style.cssText =
        'position:absolute;border-radius:50%;pointer-events:none;' +
        'width:'  + sz + 'px;height:' + sz + 'px;' +
        'left:'   + (e.clientX - r.left - sz / 2) + 'px;' +
        'top:'    + (e.clientY - r.top  - sz / 2) + 'px;' +
        'background:rgba(255,255,255,0.18);' +
        'transform:scale(0);opacity:1;' +
        'transition:transform 0.55s ease,opacity 0.55s ease;';
      dtBtn.appendChild(rpl);
      requestAnimationFrame(function() {
        rpl.style.transform = 'scale(2.8)';
        rpl.style.opacity   = '0';
      });
      setTimeout(function() { rpl.remove(); }, 600);
    });
  }

  /* ================================================
     GEOLOCATION (kept inactive — uncomment to enable)
     ================================================

  const allowedMunicipality = "san ildefonso";
  const allowedProvince     = "bulacan";

  function detectLocation() {

    if (!navigator.geolocation) {
      alert("Geolocation is not supported by your browser.");
      return;
    }

    navigator.geolocation.getCurrentPosition(async function(position){

      const lat = position.coords.latitude;
      const lon = position.coords.longitude;

      document.getElementById("lat").value = lat;
      document.getElementById("lng").value = lon;

      const url = `https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lon}&format=json`;

      try{

        const res = await fetch(url);
        const data = await res.json();

        const addr = data.address;

        let municipality = addr.town || addr.city || addr.municipality || "";
        let province = addr.state || "";

        municipality = municipality.toLowerCase();
        province = province.toLowerCase();

        if(
          !municipality.includes(allowedMunicipality) ||
          !province.includes(allowedProvince)
        ){

          alert("Login is only allowed inside San Ildefonso, Bulacan.");

          document.querySelector("button[type=submit]").disabled = true;

        }

      }catch(e){
        console.log("Location verification failed.");
      }

    }, function(){

      alert("Location permission is required to login.");

    });

  }

  detectLocation();

  */

</script>

</body>
</html>