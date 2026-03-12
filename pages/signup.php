<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/session.php';

$pdo = db();

// If already logged in, send to appropriate dashboard
if (current_user()) {
    redirect_by_role();
}

$errors = [];

// Load active barangays (San Ildefonso only, enforced at DB level)
$barangays = $pdo->query("SELECT id, name FROM barangays WHERE is_active = 1 ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName   = trim($_POST['full_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';
    $barangayId = (int)($_POST['barangay_id'] ?? 0);
    $houseNo    = trim($_POST['house_number'] ?? '');
    $terms      = isset($_POST['terms']);

    if ($fullName === '') {
        $errors[] = 'Kailangan ang buong pangalan.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Kailangan ng wastong email.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Ang password ay dapat hindi bababa sa 8 karakter.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Hindi magkatugma ang mga password.';
    }
    if (!$barangayId) {
        $errors[] = 'Pakipili ang iyong barangay sa San Ildefonso.';
    }
    if ($houseNo === '') {
        $errors[] = 'Kailangan ang numero ng bahay.';
    }
    if (!$terms) {
        $errors[] = 'Kailangan mong sumang-ayon sa mga tuntunin.';
    }

    if (!$errors) {
        // Ensure barangay exists and is active (extra safety)
        $stmt = $pdo->prepare("SELECT id FROM barangays WHERE id = ? AND is_active = 1");
        $stmt->execute([$barangayId]);
        if (!$stmt->fetch()) {
            $errors[] = 'Ang napiling barangay ay hindi wasto para sa San Ildefonso.';
        }
    }

    if (!$errors) {
        // Check email uniqueness
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'Mayroon nang account na may ganitong email.';
        }
    }

    if (!$errors) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $otp          = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otpHash      = password_hash($otp, PASSWORD_DEFAULT);
        $expiresAt    = date('Y-m-d H:i:s', time() + 15 * 60); // 15 minutes

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, role, barangay_id, house_number, is_email_verified, otp_code_hash, otp_expires_at)
                                   VALUES (?, ?, ?, 'citizen', ?, ?, 0, ?, ?)");
            $stmt->execute([$fullName, $email, $passwordHash, $barangayId, $houseNo, $otpHash, $expiresAt]);

            // Send OTP (currently logged to otp_test.log)
            send_otp_email($email, $fullName, $otp);

            $pdo->commit();

            header('Location: verify_otp.php?email=' . urlencode($email));
            exit;
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = 'Hindi nagawa ang account. Pakisubukang muli.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tl">
<head>
<meta charset="UTF-8">
<title>Sign Up - MDRRMO San Ildefonso</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<!-- All styles are inline below — no external CSS needed -->
<style>

/* ==============================================
   MDRRMO San Ildefonso — Sign Up Page
   Same design as Login · Scrollable Card
   ============================================== */

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

html, body {
  width: 100%;
  height: 100%;
  font-family: 'Poppins', sans-serif;
  -webkit-font-smoothing: antialiased;
  overflow: hidden; /* body itself doesn't scroll — card does */
}

/* ── OUTER WRAPPER ── */
body {
  display: flex;
  align-items: center;
  justify-content: center;
  background:
    radial-gradient(ellipse 70% 60% at 65% 25%, rgba(160,70,5,0.80) 0%, transparent 65%),
    linear-gradient(160deg, #5c1800 0%, #3a0e02 40%, #1c0600 100%);
  min-height: 100vh;
}

/* ── PHONE SHELL ── */
.signup-shell {
  width: 100%;
  max-width: 420px;
  height: 100vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  position: relative;
}

/* ── HERO — same as login ── */
.hero {
  position: relative;
  flex-shrink: 0;
  padding: 2.2rem 1.8rem 5.5rem 1.8rem;
  overflow: hidden;
  background:
    radial-gradient(ellipse 75% 65% at 90% 10%, rgba(180,80,10,0.75) 0%, transparent 60%),
    url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='56' height='64' viewBox='0 0 56 64'%3E%3Cpolygon points='28,2 54,16 54,48 28,62 2,48 2,16' fill='none' stroke='rgba(255,255,255,0.07)' stroke-width='1.2'/%3E%3C/svg%3E") repeat top left / 56px 64px,
    linear-gradient(155deg, #5c1800 0%, #3a0e02 50%, #1c0600 100%);
}

/* Logo row */
.logo-row {
  display: flex;
  align-items: center;
  gap: 0.7rem;
  margin-bottom: 1.8rem;
  opacity: 0;
  transform: translateY(-14px);
  transition: opacity 0.55s ease, transform 0.55s ease;
}
.logo-row.visible { opacity: 1; transform: translateY(0); }

.logo-circle {
  width: 48px; height: 48px;
  border-radius: 50%;
  overflow: hidden;
  border: 2px solid rgba(255,255,255,0.25);
  flex-shrink: 0;
  background: rgba(255,255,255,0.08);
  display: flex; align-items: center; justify-content: center;
}
.logo-circle img { width: 100%; height: 100%; object-fit: cover; }
.logo-circle svg { width: 24px; height: 24px; fill: rgba(255,255,255,0.80); }

.logo-text strong {
  display: block;
  font-size: 0.58rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: rgba(255,255,255,0.90);
  line-height: 1.35;
}
.logo-text span {
  display: block;
  font-size: 0.54rem;
  font-weight: 400;
  color: rgba(255,255,255,0.55);
  margin-top: 2px;
}

/* Big headline */
.hero-headline {
  font-size: clamp(1.85rem, 6.5vw, 2.6rem);
  font-weight: 800;
  line-height: 1.10;
  color: #fff;
  text-shadow: 0 3px 18px rgba(0,0,0,0.35);
  opacity: 0;
  transform: translateY(10px);
  transition: opacity 0.6s ease 0.15s, transform 0.6s ease 0.15s;
}
.hero-headline.visible { opacity: 1; transform: translateY(0); }

/* ── WHITE CARD — rises from bottom, SCROLLABLE ── */
.card {
  position: relative;
  z-index: 5;
  background: #ffffff;
  border-radius: 28px 28px 0 0;
  margin-top: -2.8rem;
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden; /* clip children, scroll handled by inner */
  box-shadow: 0 -6px 36px rgba(0,0,0,0.13);
  opacity: 0;
  transform: translateY(100%);
  transition: opacity 0.85s cubic-bezier(0.16, 1, 0.3, 1),
              transform 0.85s cubic-bezier(0.16, 1, 0.3, 1);
  will-change: transform, opacity;
}
.card.visible { opacity: 1; transform: translateY(0); }

/* Scrollable form area — everything ABOVE the fixed button */
.card-scroll {
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
  padding: 2rem 1.8rem 1rem;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: none; /* Firefox */
}
.card-scroll::-webkit-scrollbar { display: none; } /* Chrome/Safari */

/* ── SECTION DIVIDER ── */
.section-divider {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  margin-bottom: 1.3rem;
  margin-top: 0.2rem;
}
.section-divider::before,
.section-divider::after {
  content: '';
  flex: 1;
  height: 1px;
  background: #e0e0e0;
}
.section-divider span {
  font-size: 0.70rem;
  font-weight: 700;
  color: #c0391e;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  white-space: nowrap;
}

/* ── ERROR BOX ── */
.auth-errors {
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 10px;
  padding: 0.7rem 1rem;
  margin-bottom: 1.3rem;
}
.auth-errors ul { list-style: none; }
.auth-errors li {
  font-size: 0.78rem;
  color: #b91c1c;
  font-weight: 500;
  line-height: 1.5;
}

/* ── FORM FIELDS ── */
.auth-form { display: flex; flex-direction: column; }

.field {
  position: relative;
  margin-bottom: 1.3rem;
}

.field-label {
  display: block;
  font-size: 0.73rem;
  font-weight: 600;
  color: #c0391e;
  margin-bottom: 0.35rem;
  letter-spacing: 0.01em;
}
.field-label .req { color: #c0391e; margin-left: 1px; }

/* Underline-only inputs */
.field input,
.field select {
  width: 100%;
  border: none !important;
  border-bottom: 1.5px solid #c8c8c8 !important;
  border-radius: 0 !important;
  box-shadow: none !important;
  padding: 0.45rem 0;
  font-size: 0.90rem;
  font-family: 'Poppins', sans-serif;
  font-weight: 400;
  color: #1a1a1a;
  background: transparent !important;
  outline: none !important;
  transition: border-color 0.22s;
  -webkit-appearance: none;
  appearance: none;
}
.field input:focus,
.field select:focus {
  border-bottom: 1.5px solid #c0391e !important;
  box-shadow: none !important;
  outline: none !important;
}
.field input::placeholder {
  color: #c8c8c8;
  font-size: 0.85rem;
  font-weight: 300;
}
.field input[readonly] {
  color: #888;
}

/* Dropdown chevron */
.select-wrap {
  position: relative;
}
.select-wrap::after {
  content: '';
  position: absolute;
  right: 4px;
  top: 50%;
  transform: translateY(-50%);
  width: 0; height: 0;
  border-left: 5px solid transparent;
  border-right: 5px solid transparent;
  border-top: 6px solid #c0391e;
  pointer-events: none;
}
.select-wrap select {
  padding-right: 1.4rem;
  cursor: pointer;
}

/* Checkbox row */
.checkbox-field {
  display: flex;
  align-items: flex-start;
  gap: 0.65rem;
  margin-bottom: 1.3rem;
}
.checkbox-field input[type="checkbox"] {
  width: 16px !important;
  height: 16px !important;
  min-width: 16px;
  border: 1.5px solid #c8c8c8 !important;
  border-radius: 3px !important;
  margin-top: 2px;
  accent-color: #c0391e;
  cursor: pointer;
  flex-shrink: 0;
}
.checkbox-field label {
  font-size: 0.73rem;
  color: #555;
  line-height: 1.5;
  cursor: pointer;
}

/* ── FIXED BOTTOM AREA ── */
.card-footer {
  flex-shrink: 0;
  padding: 0.9rem 1.8rem 1.1rem;
  background: #fff;
  border-top: 1px solid #f0f0f0;
}

.btn-signup {
  width: 100%;
  padding: 0.95rem;
  border: none;
  border-radius: 50px;
  background: linear-gradient(135deg, #c0391e 0%, #a83010 55%, #8f2608 100%);
  color: #fff;
  font-family: 'Poppins', sans-serif;
  font-size: 0.97rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  cursor: pointer;
  box-shadow: 0 6px 22px rgba(140,40,10,0.42);
  position: relative;
  overflow: hidden;
  transition: transform 0.16s ease, box-shadow 0.16s ease, filter 0.16s ease;
  margin-bottom: 0.6rem;
}
/* Shine sweep */
.btn-signup::before {
  content: '';
  position: absolute;
  top: 0; left: -80%;
  width: 55%; height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.18), transparent);
  transform: skewX(-18deg);
  transition: left 0.55s ease;
}
.btn-signup:hover::before { left: 160%; }
.btn-signup:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 28px rgba(140,40,10,0.52);
  filter: brightness(1.08);
}
.btn-signup:active {
  transform: translateY(0);
  box-shadow: 0 4px 12px rgba(140,40,10,0.30);
}

.login-link {
  text-align: center;
  font-size: 0.75rem;
  color: #aaa;
}
.login-link a {
  color: #c0391e;
  font-weight: 600;
  text-decoration: none;
}
.login-link a:hover { text-decoration: underline; }

/* ===============================================
   RESPONSIVE — Tablet / Desktop (≥ 600px)
   =============================================== */
@media (min-width: 600px) {
  body {
    padding: 2rem;
    align-items: center;
    overflow: auto;
  }
  .signup-shell {
    height: auto;
    max-height: 92vh;
    border-radius: 26px;
    box-shadow: 0 32px 100px rgba(0,0,0,0.60);
    overflow: hidden;
  }
  .hero { padding: 2.2rem 2.2rem 5.5rem 2.2rem; }
  .card {
    transform: translateY(60px);
    border-radius: 28px 28px 26px 26px;
    flex: 1;
    min-height: 0;
  }
  .card.visible { transform: translateY(0); }
  .card-footer {
    border-radius: 0 0 26px 26px;
  }
}

@media (min-width: 900px) {
  .signup-shell { max-width: 420px; }
  .hero { padding: 2.5rem 2.6rem 5.8rem 2.6rem; }
  .card-scroll { padding: 2rem 2.4rem 1rem; }
  .card-footer { padding: 0.9rem 2.4rem 1.2rem; }
}

/* =============================================
   DESKTOP LAYOUT — only activates at 900px+
   Mobile styles remain untouched above
   ============================================= */

@media (min-width: 900px) {

  /* Hide mobile shell */
  .signup-shell {
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
  max-height: 820px;
}

/* ---- CARD LEFT: Branding ---- */
.dt-card-left {
  width: 40%;
  flex-shrink: 0;
  background: linear-gradient(160deg, #1f0b06 0%, #3a1008 40%, #8b1a0a 80%, #c0391e 100%);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 52px 36px;
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
  width: 110px;
  height: 110px;
  margin-bottom: 20px;
}

.dt-seal-wrap img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  filter: drop-shadow(0 6px 28px rgba(0,0,0,0.55));
  border-radius: 50%;
}

.dt-agency {
  position: relative;
  z-index: 1;
  font-family: 'Bebas Neue', sans-serif;
  font-size: 46px;
  letter-spacing: 7px;
  color: #fff;
  line-height: 1;
  margin-bottom: 4px;
  text-shadow: 0 2px 20px rgba(0,0,0,0.5);
}

.dt-tagline {
  position: relative;
  z-index: 1;
  font-size: 10.5px;
  font-weight: 600;
  letter-spacing: 3px;
  color: rgba(255,255,255,0.5);
  text-transform: uppercase;
  margin-bottom: 30px;
}

.dt-info-pills {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  gap: 10px;
  width: 100%;
}

.dt-pill {
  display: flex;
  align-items: center;
  gap: 12px;
  background: rgba(0,0,0,0.25);
  border: 1px solid rgba(255,255,255,0.09);
  border-radius: 12px;
  padding: 11px 14px;
  text-align: left;
  transition: background 0.2s;
}

.dt-pill:hover {
  background: rgba(0,0,0,0.38);
}

.dt-pill-icon {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: rgba(192,57,30,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-size: 15px;
}

.dt-pill-text strong {
  display: block;
  font-size: 12px;
  font-weight: 700;
  color: #fff;
}

.dt-pill-text span {
  font-size: 10.5px;
  color: rgba(255,255,255,0.5);
  font-weight: 400;
}

.dt-bottom-badge {
  position: relative;
  z-index: 1;
  margin-top: 28px;
  font-size: 10px;
  color: rgba(255,255,255,0.25);
  letter-spacing: 1.5px;
  text-transform: uppercase;
}

/* ---- CARD RIGHT: Signup Form — White ---- */
.dt-card-right {
  flex: 1;
  display: flex;
  flex-direction: column;
  background: #ffffff;
  overflow: hidden;
}

.dt-form-scroll {
  flex: 1;
  overflow-y: auto;
  padding: 48px 52px 20px;
  scrollbar-width: thin;
  scrollbar-color: #e0e0e0 transparent;
}

.dt-form-scroll::-webkit-scrollbar {
  width: 4px;
}
.dt-form-scroll::-webkit-scrollbar-track {
  background: transparent;
}
.dt-form-scroll::-webkit-scrollbar-thumb {
  background: #e0e0e0;
  border-radius: 4px;
}

.dt-form-header {
  margin-bottom: 28px;
}

.dt-welcome {
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 3px;
  text-transform: uppercase;
  color: #c0391e;
  margin-bottom: 6px;
}

.dt-form-title {
  font-family: 'Bebas Neue', sans-serif;
  font-size: 44px;
  letter-spacing: 3px;
  color: #1a0a06;
  line-height: 1.05;
  margin-bottom: 6px;
}

.dt-form-subtitle {
  font-size: 12.5px;
  color: #888;
  font-weight: 400;
  line-height: 1.6;
}

.dt-errors {
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 10px;
  padding: 12px 16px;
  margin-bottom: 20px;
}

.dt-errors ul { list-style: none; }
.dt-errors li {
  font-size: 12.5px;
  color: #b91c1c;
  font-weight: 500;
  line-height: 1.5;
}

.dt-section-divider {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  margin-bottom: 18px;
  margin-top: 4px;
}
.dt-section-divider::before,
.dt-section-divider::after {
  content: '';
  flex: 1;
  height: 1px;
  background: #ececec;
}
.dt-section-divider span {
  font-size: 10.5px;
  font-weight: 700;
  color: #c0391e;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  white-space: nowrap;
}

.dt-fields-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0 28px;
}

.dt-fields-grid .dt-field-full {
  grid-column: 1 / -1;
}

.dt-field {
  margin-bottom: 18px;
}

.dt-field label {
  display: block;
  font-size: 10.5px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #c0391e;
  margin-bottom: 5px;
}

.dt-field input,
.dt-field select {
  width: 100%;
  border: none;
  border-bottom: 1.5px solid #d0d0d0;
  border-radius: 0;
  padding: 7px 0;
  font-size: 13.5px;
  font-family: 'Poppins', sans-serif;
  font-weight: 400;
  color: #1a1a1a;
  background: transparent;
  outline: none;
  transition: border-color 0.2s;
  -webkit-appearance: none;
  appearance: none;
  box-shadow: none;
}

.dt-field input:focus,
.dt-field select:focus {
  border-bottom-color: #c0391e;
}

.dt-field input::placeholder {
  color: #c8c8c8;
  font-size: 13px;
  font-weight: 300;
}

.dt-field input[readonly] {
  color: #999;
}

.dt-select-wrap {
  position: relative;
}
.dt-select-wrap::after {
  content: '';
  position: absolute;
  right: 4px;
  top: 50%;
  transform: translateY(-50%);
  width: 0; height: 0;
  border-left: 5px solid transparent;
  border-right: 5px solid transparent;
  border-top: 6px solid #c0391e;
  pointer-events: none;
}
.dt-select-wrap select {
  padding-right: 1.4rem;
  cursor: pointer;
}

.dt-checkbox-field {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  margin-top: 4px;
  margin-bottom: 4px;
}
.dt-checkbox-field input[type="checkbox"] {
  width: 15px;
  height: 15px;
  min-width: 15px;
  margin-top: 2px;
  accent-color: #c0391e;
  cursor: pointer;
  flex-shrink: 0;
}
.dt-checkbox-field label {
  font-size: 12px;
  color: #666;
  line-height: 1.55;
  cursor: pointer;
}

.dt-card-footer {
  flex-shrink: 0;
  padding: 16px 48px 20px;
  background: #fff;
  border-top: 1px solid #f0f0f0;
  display: flex;
  align-items: center;
  gap: 20px;
}

.dt-btn-signup {
  flex: 1;
  padding: 13px;
  border: none;
  border-radius: 50px;
  background: linear-gradient(135deg, #c0391e 0%, #a83010 55%, #8f2608 100%);
  color: #fff;
  font-family: 'Poppins', sans-serif;
  font-size: 13px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  cursor: pointer;
  box-shadow: 0 4px 18px rgba(140,40,10,0.38);
  position: relative;
  overflow: hidden;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.dt-btn-signup::before {
  content: '';
  position: absolute;
  top: 0; left: -80%;
  width: 55%; height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.18), transparent);
  transform: skewX(-18deg);
  transition: left 0.5s ease;
}
.dt-btn-signup:hover::before { left: 160%; }
.dt-btn-signup:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(140,40,10,0.48);
}
.dt-btn-signup:active {
  transform: translateY(0);
}

.dt-login-link {
  font-size: 12.5px;
  color: #aaa;
  white-space: nowrap;
}
.dt-login-link a {
  color: #c0391e;
  font-weight: 600;
  text-decoration: none;
}
.dt-login-link a:hover { text-decoration: underline; }

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
     MOBILE: Signup Shell
     ================================================ -->
<div class="signup-shell">

  <!-- ── HERO ── -->
  <div class="hero">
    <div class="logo-row" id="logoRow">
      <div class="logo-circle">
        <img src="../img/mdrrmo.png" alt="MDRRMO"
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
      Gumawa ng<br>Account
    </div>
  </div>

  <!-- ── WHITE CARD ── -->
  <div class="card" id="card">

    <div class="card-scroll">

      <?php if ($errors): ?>
      <div class="auth-errors">
        <ul>
          <?php foreach ($errors as $err): ?>
            <li><?php echo htmlspecialchars($err); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <form method="post" class="auth-form" id="signupForm">

        <div class="section-divider"><span>Personal na Impormasyon</span></div>

        <div class="field">
          <label class="field-label" for="full_name">Buong Pangalan <span class="req">*</span></label>
          <input type="text" id="full_name" name="full_name" required
                 placeholder="Juan Dela Cruz"
                 value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
        </div>

        <div class="field">
          <label class="field-label" for="barangay_id">Barangay <span class="req">*</span></label>
          <div class="select-wrap">
            <select id="barangay_id" name="barangay_id" required>
              <option value="">Piliin ang Barangay</option>
              <?php foreach ($barangays as $b): ?>
                <option value="<?php echo (int)$b['id']; ?>"
                  <?php echo isset($_POST['barangay_id']) && (int)$_POST['barangay_id'] === (int)$b['id'] ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($b['name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="field">
          <label class="field-label" for="house_number">Numero ng Bahay <span class="req">*</span></label>
          <input type="text" id="house_number" name="house_number" required
                 placeholder="hal. 123"
                 value="<?php echo htmlspecialchars($_POST['house_number'] ?? ''); ?>">
        </div>

        <div class="field">
          <label class="field-label" for="address">Nakitang Tirahan</label>
          <input type="text" id="address" name="detected_address" readonly
                 placeholder="Kinukuha ang lokasyon...">
        </div>

        <input type="hidden" id="lat">
        <input type="hidden" id="lng">

        <div class="section-divider" style="margin-top:0.5rem;"><span>Impormasyon ng Account</span></div>

        <div class="field">
          <label class="field-label" for="email">Email <span class="req">*</span></label>
          <input type="email" id="email" name="email" required
                 placeholder="juandelacruz@gmail.com"
                 value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>

        <div class="field">
          <label class="field-label" for="password">Password <span class="req">*</span></label>
          <input type="password" id="password" name="password" required minlength="8"
                 placeholder="Hindi bababa sa 8 karakter">
        </div>

        <div class="field">
          <label class="field-label" for="confirm_password">Kumpirmahin ang Password <span class="req">*</span></label>
          <input type="password" id="confirm_password" name="confirm_password" required minlength="8"
                 placeholder="Ulitin ang password">
        </div>

        <!-- Terms checkbox -->
        <div class="checkbox-field">
          <input type="checkbox" name="terms" id="terms" value="1"
                 <?php echo isset($_POST['terms']) ? 'checked' : ''; ?>>
          <label for="terms">
            Kinukumpirma ko na ako ay residente ng San Ildefonso, Bulacan at sumasang-ayon sa patakaran ng MDRRMO ukol sa datos.
          </label>
        </div>

      </form>
    </div>

    <div class="card-footer">
      <button type="submit" form="signupForm" class="btn-signup">Mag-sign Up</button>
      <p class="login-link">May account na? <a href="../index.php">Mag-login</a></p>
    </div>

  </div><!-- /card -->

</div><!-- /.signup-shell -->


<!-- ================================================
     DESKTOP: Centered Card Layout
     ================================================ -->
<div id="desktop-page">

  <!-- CENTERED CARD -->
  <div class="dt-card">

    <!-- LEFT: Branding -->
    <div class="dt-card-left">

      <div class="dt-seal-wrap">
        <img src="../img/mdrrmo.png" alt="MDRRMO Seal"
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

    </div><!-- /.dt-card-left -->

    <!-- RIGHT: Signup Form -->
    <div class="dt-card-right">

      <div class="dt-form-scroll">

        <div class="dt-form-header">
          <div class="dt-welcome">Sumali sa Komunidad</div>
          <div class="dt-form-title">Gumawa ng<br>Account</div>
          <div class="dt-form-subtitle">Mag-rehistro bilang residente ng San Ildefonso, Bulacan<br>para manatiling handa at may kaalaman.</div>
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

        <form method="post" id="dtSignupForm">

          <div class="dt-section-divider"><span>Personal na Impormasyon</span></div>

          <div class="dt-fields-grid">

            <div class="dt-field dt-field-full">
              <label for="dt-full_name">Buong Pangalan *</label>
              <input type="text" id="dt-full_name" name="full_name" required
                     placeholder="Juan Dela Cruz"
                     value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
            </div>

            <div class="dt-field">
              <label for="dt-barangay_id">Barangay *</label>
              <div class="dt-select-wrap">
                <select id="dt-barangay_id" name="barangay_id" required>
                  <option value="">Piliin ang Barangay</option>
                  <?php foreach ($barangays as $b): ?>
                    <option value="<?php echo (int)$b['id']; ?>"
                      <?php echo isset($_POST['barangay_id']) && (int)$_POST['barangay_id'] === (int)$b['id'] ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($b['name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="dt-field">
              <label for="dt-house_number">Numero ng Bahay *</label>
              <input type="text" id="dt-house_number" name="house_number" required
                     placeholder="hal. 123"
                     value="<?php echo htmlspecialchars($_POST['house_number'] ?? ''); ?>">
            </div>

            <div class="dt-field dt-field-full">
              <label for="dt-address">Nakitang Tirahan</label>
              <input type="text" id="dt-address" name="detected_address" readonly
                     placeholder="Kinukuha ang lokasyon...">
            </div>

          </div><!-- /.dt-fields-grid -->

          <input type="hidden" id="dt-lat">
          <input type="hidden" id="dt-lng">

          <div class="dt-section-divider" style="margin-top:8px;"><span>Impormasyon ng Account</span></div>

          <div class="dt-fields-grid">

            <div class="dt-field dt-field-full">
              <label for="dt-email">Email *</label>
              <input type="email" id="dt-email" name="email" required
                     placeholder="juandelacruz@gmail.com"
                     value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>

            <div class="dt-field">
              <label for="dt-password">Password *</label>
              <input type="password" id="dt-password" name="password" required minlength="8"
                     placeholder="Hindi bababa sa 8 karakter">
            </div>

            <div class="dt-field">
              <label for="dt-confirm_password">Kumpirmahin ang Password *</label>
              <input type="password" id="dt-confirm_password" name="confirm_password" required minlength="8"
                     placeholder="Ulitin ang password">
            </div>

          </div><!-- /.dt-fields-grid -->

          <!-- Terms checkbox -->
          <div class="dt-checkbox-field">
            <input type="checkbox" name="terms" id="dt-terms" value="1"
                   <?php echo isset($_POST['terms']) ? 'checked' : ''; ?>>
            <label for="dt-terms">
              Kinukumpirma ko na ako ay residente ng San Ildefonso, Bulacan at sumasang-ayon sa patakaran ng MDRRMO ukol sa datos.
            </label>
          </div>

        </form>
      </div><!-- /.dt-form-scroll -->

      <!-- Fixed footer -->
      <div class="dt-card-footer">
        <button type="submit" form="dtSignupForm" class="dt-btn-signup">Gumawa ng Account</button>
        <p class="dt-login-link">May account na? <a href="../index.php">Mag-login</a></p>
      </div>

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
     MOBILE: Entrance animations
     ================================================ */
  window.addEventListener('DOMContentLoaded', function () {
    requestAnimationFrame(function () {
      setTimeout(function () {
        document.getElementById('logoRow').classList.add('visible');
        document.getElementById('heroHeadline').classList.add('visible');
      }, 60);
      setTimeout(function () {
        document.getElementById('card').classList.add('visible');
      }, 180);
    });
  });

  /* Mobile: underline focus highlight */
  document.querySelectorAll('.field input, .field select').forEach(function (inp) {
    inp.addEventListener('focus', function () {
      this.style.setProperty('border-bottom', '1.5px solid #c0391e', 'important');
    });
    inp.addEventListener('blur', function () {
      this.style.setProperty('border-bottom', '1.5px solid #c8c8c8', 'important');
    });
  });

  /* Mobile: button ripple */
  var btn = document.querySelector('.btn-signup');
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

  /* Desktop: button ripple */
  var dtBtn = document.querySelector('.dt-btn-signup');
  if (dtBtn) {
    dtBtn.addEventListener('click', function (e) {
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
      requestAnimationFrame(function () {
        rpl.style.transform = 'scale(2.8)';
        rpl.style.opacity   = '0';
      });
      setTimeout(function () { rpl.remove(); }, 600);
    });
  }

  /* ================================================
     GEOLOCATION — shared for both mobile and desktop
     ================================================ */
  const allowedMunicipality = "San Ildefonso";
  const allowedProvince = "Bulacan";

  function detectLocation() {

    if (!navigator.geolocation) {
      alert("Hindi sinusuportahan ng iyong browser ang geolocation.");
      return;
    }

    navigator.geolocation.getCurrentPosition(async function(position){

      const lat = position.coords.latitude;
      const lon = position.coords.longitude;

      // Populate hidden lat/lng for both mobile and desktop forms
      document.getElementById("lat").value = lat;
      document.getElementById("lng").value = lon;
      document.getElementById("dt-lat").value = lat;
      document.getElementById("dt-lng").value = lon;

      // Reverse geocode using OpenStreetMap
      const url = `https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lon}&format=json`;

      const res = await fetch(url);
      const data = await res.json();

      const addr = data.address;

      let municipality = addr.town || addr.city || addr.municipality || "";
      let province = addr.state || "";

      const fullAddress = data.display_name;

      // Populate detected address for both forms
      document.getElementById("address").value = fullAddress;
      document.getElementById("dt-address").value = fullAddress;

      if (
        !municipality.toLowerCase().includes("san ildefonso") ||
        !province.toLowerCase().includes("bulacan")
      ) {

        alert("Ang pagpaparehistro ay para lamang sa mga residente ng San Ildefonso, Bulacan.");

        document.querySelector("button[type=submit]").disabled = true;
      }

    }, function(){
      alert("Kailangan ang access sa lokasyon para sa pagpaparehistro.");
    });
  }

  detectLocation();

</script>
</body>
</html>