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
        $errors[] = 'Valid email is required.';
    }

    if ($password === '') {
        $errors[] = 'Password is required.';
    }

    // if (!$lat || !$lng) {
    //     $errors[] = 'Location access is required to log in.';
    // }

    if (!$errors) {

        $stmt = $pdo->prepare("SELECT u.*, b.municipality, b.province
                               FROM users u
                               JOIN barangays b ON b.id = u.barangay_id
                               WHERE u.email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {

            $errors[] = 'Invalid email or password.';

        } elseif ((int)$user['is_active'] !== 1) {

            $errors[] = 'Account is disabled.';

        } elseif ((int)$user['is_email_verified'] !== 1) {

            $errors[] = 'Please verify your email before logging in.';

        } elseif ($user['municipality'] !== 'San Ildefonso' || $user['province'] !== 'Bulacan') {

            $errors[] = 'Access is restricted to residents of San Ildefonso, Bulacan.';

        } else {

            $_SESSION['user_id'] = $user['id'];
            redirect_by_role();

        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login - MDRRMO San Ildefonso</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Poppins:wght@300;400;500;600;700;800&family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/index.css">
</head>
<body>

<div id="splash" onclick="goToLogin()">

  <div class="bg-base"></div>
  <div class="bg-pulse"></div>
  <div class="bg-drift"></div>
  <div class="honeycomb"></div>

  <!-- Floating particles -->
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
    <div class="tap-hint">Tap anywhere to continue</div>
  </div>

</div><!-- /#splash -->



<div id="login-page">

  <div class="login-shell">

    <!-- Hero / top section -->
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
        Sign in your<br>account
      </div>
    </div>

    <!-- White card -->
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
          <label class="field-label" for="email">Email/ Username</label>
          <input type="email" id="email" name="email"
                 placeholder="Enter your email" required
                 value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>

        <div class="field">
          <label class="field-label" for="password">Password</label>
          <input type="password" id="password" name="password"
                 placeholder="Enter your password" required>
        </div>

        <div class="forgot-row">
          <a href="#">Forgot Password?</a>
        </div>

        <input type="hidden" name="lat" id="lat">
        <input type="hidden" name="lng" id="lng">

        <button type="submit" class="btn-signin">Sign In</button>

      </form>

      <p class="signup-row">Don't have an account? <a href="pages/signup.php">Sign up</a></p>

    </div><!-- /.card -->

  </div><!-- /.login-shell -->

</div><!-- /#login-page -->



<script>

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

    /* Splash exit animation */
    document.getElementById('splash').classList.add('exit');

    setTimeout(() => {
      const splash = document.getElementById('splash');
      splash.style.display = 'none';

      const loginPage = document.getElementById('login-page');
      loginPage.classList.add('visible');

      /* Trigger login entrance animations */
      requestAnimationFrame(() => {
      
        setTimeout(() => {
          document.getElementById('logoRow').classList.add('visible');
          document.getElementById('heroHeadline').classList.add('visible');
        }, 60);

        /* Card slides up smoothly after a beat — feels like it rises from below */
        setTimeout(() => {
          document.getElementById('card').classList.add('visible');
        }, 180);
      });

    }, 420);
  }

  /* Auto-advance after 8.5 s if user doesn't tap */
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