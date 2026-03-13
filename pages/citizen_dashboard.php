<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/db.php';

require_login(); // any logged-in user can see; citizens will arrive here by default
$user = current_user();
$pdo  = db();

// Latest weather snapshot
// LIVE WEATHER DATA
require_once __DIR__ . '/config.php';

$lat = 15.0828;
$lon = 120.9417;

$url = "https://api.openweathermap.org/data/2.5/weather?lat=$lat&lon=$lon&appid=" . WEATHER_API_KEY . "&units=metric";

$response = @file_get_contents($url);
$weather = null;

if ($response !== false) {

    $data = json_decode($response, true);

    if (!empty($data['main'])) {

        $temp = $data['main']['temp'];
        $humidity = $data['main']['humidity'];
        $condition = $data['weather'][0]['description'] ?? 'N/A';

        // Heat index calculation
        $t = $temp;
        $rh = $humidity;

        $heatIndex = $t;

        if ($t >= 27 && $rh >= 40) {
            $heatIndex = -8.784695 + 1.61139411*$t + 2.338549*$rh
                - 0.14611605*$t*$rh - 0.012308094*($t*$t)
                - 0.016424828*($rh*$rh) + 0.002211732*($t*$t*$rh)
                + 0.00072546*($t*$rh*$rh) - 0.000003582*($t*$t*$rh*$rh);
        }

        // Determine risk level
        $level = 'low';

        if ($heatIndex >= 41) {
            $level = 'extreme';
        } elseif ($heatIndex >= 38) {
            $level = 'high';
        } elseif ($heatIndex >= 32) {
            $level = 'medium';
        }

        $weather = [
            'temp_c' => $temp,
            'humidity' => $humidity,
            'heat_index' => $heatIndex,
            'condition_text' => $condition,
            'level' => $level
        ];
    }
}

// Highest-level ongoing disaster
$disasterStmt = $pdo->query("SELECT * FROM disasters WHERE status = 'ongoing' ORDER BY level DESC, started_at DESC LIMIT 1");
$activeDisaster = $disasterStmt->fetch();

// Ready-bag advice based on disaster or weather
$advice = null;
if ($activeDisaster) {
    $type = $activeDisaster['type'];
    $level = (int)$activeDisaster['level'];
    $stmt = $pdo->prepare("SELECT * FROM ready_bag_templates
                           WHERE disaster_type = ?
                             AND level_min <= ?
                             AND level_max >= ?
                           ORDER BY level_min DESC
                           LIMIT 1");
    $stmt->execute([$type, $level, $level]);
    $advice = $stmt->fetch();
} elseif ($weather) {
    // Map weather level to a heat advice template
    $type = 'heat';
    $level = $weather['level'] === 'extreme' ? 4 :
             ($weather['level'] === 'high' ? 3 :
             ($weather['level'] === 'medium' ? 2 : 1));
    $stmt = $pdo->prepare("SELECT * FROM ready_bag_templates
                           WHERE disaster_type = ?
                             AND level_min <= ?
                             AND level_max >= ?
                           ORDER BY level_min DESC
                           LIMIT 1");
    $stmt->execute([$type, $level, $level]);
    $advice = $stmt->fetch();
}

// Announcements (pinned first, then latest)
$annStmt = $pdo->query("SELECT a.*, d.title AS disaster_title
                        FROM announcements a
                        LEFT JOIN disasters d ON d.id = a.disaster_id
                        ORDER BY a.is_pinned DESC, a.published_at DESC
                        LIMIT 6");
$announcements = $annStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Citizen Dashboard - MDRRMO San Ildefonso</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --red:      #c0391e;
  --orange:   #e07020;
  --yellow:   #f5a623;
  --bg:       #f2f2f7;
  --white:    #ffffff;
  --text:     #1a1a1a;
  --muted:    #888888;
  --border:   #e8e8e8;
  --topbar-h: 58px;
  --navbar-h: 68px;
  --font:     'Poppins', sans-serif;
}

html, body {
  height: 100%;
  font-family: var(--font);
  background: var(--bg);
  color: var(--text);
  -webkit-font-smoothing: antialiased;
}

/* ============================================================
   SHARED TOP BAR (used on both mobile and desktop)
   ============================================================ */
.topbar {
  height: var(--topbar-h);
  background: var(--white);
  display: flex;
  align-items: center;
  padding: 0 1rem;
  gap: 0.7rem;
  border-bottom: 1px solid var(--border);
  z-index: 50;
  position: sticky;
  top: 0;
}

.topbar-logo {
  width: 36px; height: 36px;
  border-radius: 50%;
  overflow: hidden;
  flex-shrink: 0;
  background: #eee;
  display: flex; align-items: center; justify-content: center;
}
.topbar-logo img { width: 100%; height: 100%; object-fit: cover; }
.topbar-logo svg { width: 20px; height: 20px; fill: var(--red); }

.topbar-info { flex: 1; min-width: 0; }
.topbar-title {
  font-size: 0.70rem;
  font-weight: 700;
  color: var(--text);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.topbar-sub {
  font-size: 0.60rem;
  color: var(--muted);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Hamburger button */
.hamburger-btn {
  background: none;
  border: none;
  cursor: pointer;
  padding: 0.3rem;
  display: flex;
  flex-direction: column;
  gap: 5px;
  flex-shrink: 0;
  border-radius: 6px;
  transition: background 0.15s;
}
.hamburger-btn:hover { background: var(--bg); }
.hamburger-btn span {
  display: block;
  width: 22px;
  height: 2px;
  background: var(--text);
  border-radius: 2px;
  transition: all 0.3s ease;
}
.hamburger-btn.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
.hamburger-btn.open span:nth-child(2) { opacity: 0; }
.hamburger-btn.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

.topbar-date {
  font-size: 0.60rem;
  color: var(--muted);
  white-space: nowrap;
  flex-shrink: 0;
}

/* ============================================================
   DRAWER SIDEBAR (shared mobile + desktop)
   ============================================================ */
.sidebar-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.45);
  z-index: 200;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.3s ease;
}
.sidebar-overlay.open {
  opacity: 1;
  pointer-events: all;
}

.sidebar-drawer {
  position: fixed;
  top: 0;
  left: 0;
  width: 270px;
  height: 100%;
  background: var(--white);
  z-index: 300;
  display: flex;
  flex-direction: column;
  transform: translateX(-100%);
  transition: transform 0.32s cubic-bezier(0.16, 1, 0.3, 1);
  box-shadow: 6px 0 32px rgba(0,0,0,0.18);
  overflow-y: auto;
}
.sidebar-drawer.open { transform: translateX(0); }

.drawer-brand {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 1.25rem 1.2rem;
  border-bottom: 1px solid var(--border);
  background: linear-gradient(135deg, var(--red), var(--orange));
}
.drawer-logo {
  width: 42px; height: 42px;
  border-radius: 50%;
  overflow: hidden;
  flex-shrink: 0;
  background: rgba(255,255,255,0.2);
  display: flex; align-items: center; justify-content: center;
}
.drawer-logo img { width: 100%; height: 100%; object-fit: cover; }
.drawer-logo svg { width: 22px; height: 22px; fill: #fff; }
.drawer-brand-text { min-width: 0; }
.drawer-brand-title {
  font-size: 0.68rem;
  font-weight: 800;
  color: #fff;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  line-height: 1.3;
}
.drawer-brand-sub {
  font-size: 0.58rem;
  color: rgba(255,255,255,0.75);
  margin-top: 1px;
}

.drawer-user {
  display: flex;
  align-items: center;
  gap: 0.7rem;
  padding: 1rem 1.2rem;
  border-bottom: 1px solid var(--border);
}
.drawer-avatar {
  width: 36px; height: 36px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--red), var(--orange));
  display: flex; align-items: center; justify-content: center;
  color: #fff;
  font-size: 0.80rem;
  font-weight: 700;
  flex-shrink: 0;
}
.drawer-user-info { min-width: 0; }
.drawer-username {
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.drawer-barangay {
  font-size: 0.60rem;
  color: var(--muted);
}

.drawer-nav {
  flex: 1;
  padding: 0.75rem 0.7rem;
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}
.drawer-nav-label {
  font-size: 0.55rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--muted);
  padding: 0.6rem 0.8rem 0.2rem;
}
.drawer-nav-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.65rem 0.8rem;
  border-radius: 10px;
  text-decoration: none;
  color: var(--muted);
  font-size: 0.78rem;
  font-weight: 500;
  transition: background 0.15s, color 0.15s;
  cursor: pointer;
  border: none;
  background: none;
  width: 100%;
  text-align: left;
  font-family: var(--font);
}
.drawer-nav-item:hover { background: #f5f5f5; color: var(--text); }
.drawer-nav-item.active {
  background: #fdecea;
  color: var(--red);
  font-weight: 700;
}
.drawer-nav-item svg { width: 18px; height: 18px; fill: currentColor; flex-shrink: 0; }

.drawer-footer {
  padding: 0.8rem 0.7rem;
  border-top: 1px solid var(--border);
}
.drawer-logout {
  display: flex;
  align-items: center;
  gap: 0.7rem;
  padding: 0.6rem 0.8rem;
  border-radius: 10px;
  background: #fff0f0;
  color: var(--red);
  text-decoration: none;
  font-size: 0.75rem;
  font-weight: 700;
  border: 1px solid #ffd0d0;
  transition: background 0.15s;
}
.drawer-logout:hover { background: #ffe0e0; }
.drawer-logout svg { width: 16px; height: 16px; fill: var(--red); flex-shrink: 0; }

/* ============================================================
   MOBILE LAYOUT
   ============================================================ */
.mobile-shell {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  background: var(--bg);
}

.page-scroll {
  flex: 1;
  overflow-x: hidden;
  -webkit-overflow-scrolling: touch;
  padding-bottom: calc(var(--navbar-h) + 0.5rem);
}

/* ── ALERT BANNER ── */
.alert-banner {
  margin: 0.7rem 0.9rem;
  border-radius: 10px;
  padding: 0.65rem 0.9rem;
  display: flex;
  align-items: center;
  gap: 0.6rem;
  cursor: pointer;
}
.alert-icon { font-size: 1.1rem; flex-shrink: 0; }
.alert-text { flex: 1; min-width: 0; }
.alert-title {
  font-size: 0.78rem;
  font-weight: 700;
  line-height: 1.3;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.alert-sub { font-size: 0.65rem; opacity: 0.85; margin-top: 1px; }
.alert-chevron { font-size: 1rem; flex-shrink: 0; opacity: 0.7; }

.alert-level-1  { background: #FFF9C4; color: #7a6000; }
.alert-level-2  { background: #FFE0B2; color: #7a3500; }
.alert-level-3  { background: #FFCDD2; color: #7a0000; }
.alert-level-4  { background: #B71C1C; color: #fff; }
.alert-typhoon  { background: #e07020; color: #fff; }
.alert-none     { background: #E8F5E9; color: #1b5e20; }

/* ── READY BAG CARD (mobile) ── */
.readybag-card {
  margin: 0 0.9rem 0.5rem;
  background: #fff8e1;
  border-radius: 10px;
  padding: 0.75rem 0.9rem;
  display: flex;
  gap: 0.7rem;
  align-items: flex-start;
  border: 1px solid #ffe082;
}
.readybag-icon { font-size: 1.4rem; flex-shrink: 0; }
.readybag-content { flex: 1; min-width: 0; }
.readybag-title {
  font-size: 0.78rem;
  font-weight: 700;
  color: #5d4037;
  margin-bottom: 0.25rem;
}
.readybag-text {
  font-size: 0.68rem;
  color: #795548;
  line-height: 1.5;
}

/* ── SECTION HEADER ── */
.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.9rem 0.9rem 0.4rem;
}
.section-header h2 { font-size: 0.90rem; font-weight: 700; color: var(--text); }
.section-header a {
  font-size: 0.68rem;
  color: var(--red);
  font-weight: 600;
  text-decoration: none;
}

/* ── WEATHER CARD ── */
.weather-card {
  margin: 0 0.9rem 0.5rem;
  background: var(--white);
  border-radius: 14px;
  padding: 1rem;
  box-shadow: 0 2px 10px rgba(0,0,0,0.06);
}
.weather-main {
  display: flex;
  align-items: center;
  gap: 0.8rem;
  margin-bottom: 0.8rem;
}
.weather-icon { font-size: 3rem; line-height: 1; flex-shrink: 0; }
.weather-temp-block { flex: 1; }
.weather-temp {
  font-size: 2.8rem;
  font-weight: 800;
  color: var(--text);
  line-height: 1;
}
.weather-temp sup { font-size: 1rem; font-weight: 500; vertical-align: super; }
.weather-desc { font-size: 0.72rem; color: var(--muted); margin-top: 2px; text-transform: capitalize; }
.weather-location { font-size: 0.65rem; color: var(--muted); }

.weather-risk {
  flex-shrink: 0;
  padding: 0.25rem 0.7rem;
  border-radius: 20px;
  font-size: 0.62rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.status-low     { background: #E8F5E9; color: #2e7d32; }
.status-medium  { background: #FFF9C4; color: #f57f17; }
.status-high    { background: #FFE0B2; color: #e65100; }
.status-extreme { background: #FFCDD2; color: #b71c1c; }

.weather-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.5rem;
}
.w-stat {
  background: var(--bg);
  border-radius: 10px;
  padding: 0.55rem 0.4rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.2rem;
}
.w-stat .stat-icon { font-size: 1.2rem; }
.w-stat .stat-val  { font-size: 0.80rem; font-weight: 700; color: var(--text); }
.w-stat .stat-label { font-size: 0.57rem; color: var(--muted); text-align: center; }

/* ── EVACUATION CARD ── */
.evac-card {
  margin: 0 0.9rem 0.5rem;
  background: var(--white);
  border-radius: 14px;
  overflow: hidden;
  box-shadow: 0 2px 10px rgba(0,0,0,0.06);
}
.btn-nav {
  display: inline-block;
  padding: 0.5rem 1.2rem;
  background: linear-gradient(135deg, var(--red), var(--orange));
  color: #fff;
  border-radius: 50px;
  font-size: 0.78rem;
  font-weight: 600;
  text-decoration: none;
  font-family: var(--font);
  transition: filter 0.2s;
}
.btn-nav:hover { filter: brightness(1.08); }

/* ── ANNOUNCEMENTS ── */
.ann-list {
  margin: 0 0.9rem 0.5rem;
  background: var(--white);
  border-radius: 14px;
  overflow: hidden;
  box-shadow: 0 2px 10px rgba(0,0,0,0.06);
}
.ann-item {
  padding: 0.75rem 0.9rem;
  border-bottom: 1px solid var(--border);
  display: flex;
  gap: 0.6rem;
  align-items: flex-start;
}
.ann-item:last-child { border-bottom: none; }
.ann-dot {
  width: 8px; height: 8px;
  border-radius: 50%;
  background: var(--red);
  flex-shrink: 0;
  margin-top: 5px;
}
.ann-dot.pinned { background: var(--yellow); }
.ann-body { flex: 1; min-width: 0; }
.ann-title {
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--text);
  line-height: 1.3;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.ann-preview {
  font-size: 0.65rem;
  color: var(--muted);
  margin-top: 2px;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.badge {
  display: inline-block;
  font-size: 0.55rem;
  font-weight: 700;
  padding: 1px 5px;
  border-radius: 4px;
  margin-right: 4px;
  vertical-align: middle;
  background: var(--yellow);
  color: #7a5000;
  text-transform: uppercase;
}
.badge-disaster { background: #FFCDD2; color: #7a0000; }
.ann-empty { padding: 1.5rem; text-align: center; font-size: 0.78rem; color: var(--muted); }

/* ── BOTTOM NAV ── */
.bottom-nav {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  height: var(--navbar-h);
  background: var(--white);
  border-top: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: space-around;
  z-index: 100;
  padding: 0 0.5rem;
}
.nav-item {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 3px;
  text-decoration: none;
  color: var(--muted);
  cursor: pointer;
  border: none;
  background: none;
  padding: 0.3rem 0;
  transition: color 0.15s;
}
.nav-item.active { color: var(--red); }
.nav-item svg { width: 22px; height: 22px; fill: currentColor; }
.nav-item span { font-size: 0.58rem; font-weight: 600; font-family: var(--font); }

.nav-item.nav-center { position: relative; top: -16px; }
.nav-center-circle {
  width: 54px; height: 54px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--red), var(--orange));
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 4px 16px rgba(192,57,30,0.45);
}
.nav-center-circle svg { width: 26px; height: 26px; fill: #fff; }
.nav-item.nav-center span { color: var(--red); font-weight: 700; }

/* ============================================================
   DESKTOP LAYOUT — 1024px+
   ============================================================ */
@media (min-width: 1024px) {
  html, body {
    overflow: auto;
    background: #f0ede8;
    height: auto;
  }

  .mobile-shell { display: none; }

  .desktop-wrapper {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
  }

  /* Desktop topbar replaces mobile topbar */
  .desktop-topbar {
    display: flex;
    align-items: center;
    padding: 0 2rem;
    height: 64px;
    background: var(--white);
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
    position: sticky;
    top: 0;
    z-index: 50;
    gap: 1rem;
  }
  .desktop-topbar-center {
    flex: 1;
    display: flex;
    flex-direction: column;
    margin-left: 0.5rem;
  }
  .desktop-topbar-title {
    font-size: 1rem;
    font-weight: 700;
    color: var(--text);
  }
  .desktop-topbar-sub {
    font-size: 0.68rem;
    color: var(--muted);
  }
  .desktop-topbar-right {
    display: flex;
    align-items: center;
    gap: 0.8rem;
  }
  .desktop-date-chip {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 0.3rem 0.9rem;
    font-size: 0.68rem;
    color: var(--muted);
    font-weight: 500;
  }

  /* Desktop content */
  .desktop-content {
    flex: 1;
    padding: 1.5rem 2rem;
  }
  .desktop-grid {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 1.25rem;
    align-items: start;
  }
  .desktop-col-left { display: flex; flex-direction: column; gap: 1.25rem; }
  .desktop-col-right { display: flex; flex-direction: column; gap: 1.25rem; }

  .desktop-card {
    background: var(--white);
    border-radius: 16px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    overflow: hidden;
  }
  .desktop-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.2rem 0.6rem;
    border-bottom: 1px solid var(--border);
  }
  .desktop-card-header h2 {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--text);
  }
  .desktop-card-header a {
    font-size: 0.68rem;
    color: var(--red);
    font-weight: 600;
    text-decoration: none;
  }
  .desktop-card-body { padding: 1rem 1.2rem; }

  .desktop-alert-wrap { display: flex; flex-direction: column; gap: 0.5rem; }
  .desktop-alert-wrap .alert-banner { margin: 0; border-radius: 10px; }

  /* Ready bag inside desktop card */
  .desktop-readybag {
    display: flex;
    gap: 0.7rem;
    align-items: flex-start;
    background: #fff8e1;
    border-radius: 10px;
    padding: 0.75rem 0.9rem;
    border: 1px solid #ffe082;
  }
  .desktop-readybag-icon { font-size: 1.4rem; flex-shrink: 0; }
  .desktop-readybag-title {
    font-size: 0.78rem;
    font-weight: 700;
    color: #5d4037;
    margin-bottom: 0.2rem;
  }
  .desktop-readybag-text {
    font-size: 0.68rem;
    color: #795548;
    line-height: 1.5;
  }

  .desktop-weather-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
    align-items: start;
  }
  .desktop-evac-body { padding: 1rem 1.2rem; }
  .desktop-ann-list { margin: 0; box-shadow: none; border-radius: 0; }
}

/* Hide desktop wrapper on mobile */
@media (max-width: 1023px) {
  .desktop-wrapper { display: none; }
  .bottom-nav { display: flex; }
}

</style>
</head>
<body>

<!-- ============================================================
     SHARED DRAWER SIDEBAR (works for both mobile & desktop)
     ============================================================ -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<div class="sidebar-drawer" id="sidebarDrawer">
  <div class="drawer-brand">
    <div class="drawer-logo">
      <img src="../img/mdrrmo.png" alt="MDRRMO" onerror="this.style.display='none'">
      <svg viewBox="0 0 24 24"><path d="M12 2L3 7v5c0 5.25 3.75 10.15 9 11.35C17.25 22.15 21 17.25 21 12V7L12 2z"/></svg>
    </div>
    <div class="drawer-brand-text">
      <div class="drawer-brand-title">MDRRMO</div>
      <div class="drawer-brand-sub">San Ildefonso, Bulacan</div>
    </div>
  </div>

  <nav class="drawer-nav">
    <div class="drawer-nav-label">Menu</div>
    <a href="citizen_dashboard.php" class="drawer-nav-item active">
      <svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
      Dashboard
    </a>
    <a href="#current-alerts" class="drawer-nav-item" onclick="closeSidebar()">
      <svg viewBox="0 0 24 24"><path d="M12 22c1.1 0 2-.9 2-2h-4a2 2 0 0 0 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4a1.5 1.5 0 0 0-3 0v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>
      Alerts
    </a>
    <a href="navigation.php" class="drawer-nav-item">
      <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5z"/></svg>
      Evacuation
    </a>
    <a href="#announcements" class="drawer-nav-item" onclick="closeSidebar()">
      <svg viewBox="0 0 24 24"><path d="M20 2H4a2 2 0 0 0-2 2v18l4-4h14a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z"/></svg>
      Announcements
    </a>
  </nav>

  <div class="drawer-footer">
    <a href="logout.php" class="drawer-logout">
      <svg viewBox="0 0 24 24"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5-5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>
      Log Out
    </a>
  </div>
</div>


<!-- ============================================================
     MOBILE LAYOUT
     ============================================================ -->
<div class="mobile-shell">

  <header class="topbar">
    <div class="topbar-logo">
      <img src="../img/mdrrmo.png" alt="MDRRMO" onerror="this.style.display='none'">
      <svg viewBox="0 0 24 24"><path d="M12 2L3 7v5c0 5.25 3.75 10.15 9 11.35C17.25 22.15 21 17.25 21 12V7L12 2z"/></svg>
    </div>
    <div class="topbar-info">
      <div class="topbar-title">MDRRMO-San Ildefonso Bulacan</div>
      <div class="topbar-sub">
        Brgy. <?php echo htmlspecialchars($user['barangay_name'] ?? ''); ?> &nbsp;·&nbsp; <?php echo date('D, M j, Y'); ?>
      </div>
    </div>
  </header>

  <div class="page-scroll">

    <!-- ALERT BANNER -->
    <?php if ($activeDisaster): ?>
      <div class="alert-banner alert-typhoon">
        <div class="alert-icon">⚠️</div>
        <div class="alert-text">
          <div class="alert-title">
            <?php echo htmlspecialchars(ucfirst($activeDisaster['type'])); ?>
            Signal#<?php echo (int)$activeDisaster['level']; ?> Active
          </div>
          <div class="alert-sub">
            <?php
              $lvlLabel = ['1'=>'Low','2'=>'Moderate','3'=>'High','4'=>'Extreme'];
              echo ($lvlLabel[(string)(int)$activeDisaster['level']] ?? 'Moderate') . ' risk level · Tap for full details';
            ?>
          </div>
        </div>
        <div class="alert-chevron">›</div>
      </div>

      <?php if ($advice): ?>
        <div class="readybag-card">
          <div class="readybag-icon">🎒</div>
          <div class="readybag-content">
            <div class="readybag-title">Ready Bag Advice</div>
            <div class="readybag-text"><?php echo htmlspecialchars($advice['message']); ?></div>
          </div>
        </div>
      <?php endif; ?>

    <?php elseif ($weather && ($weather['level'] === 'high' || $weather['level'] === 'extreme')): ?>
      <div class="alert-banner alert-level-3">
        <div class="alert-icon">🌡️</div>
        <div class="alert-text">
          <div class="alert-title">HEAT ALERT — Heat Index: <?php echo round($weather['heat_index']); ?>°C</div>
          <div class="alert-sub">Stay hydrated and avoid outdoor activities · Tap for full details</div>
        </div>
        <div class="alert-chevron">›</div>
      </div>
    <?php else: ?>
      <div class="alert-banner alert-none">
        <div class="alert-icon">✅</div>
        <div class="alert-text">
          <div class="alert-title">No active disaster at this time</div>
          <div class="alert-sub">Stay prepared and monitor updates</div>
        </div>
      </div>
    <?php endif; ?>

    <!-- WEATHER -->
    <div class="section-header">
      <h2>Weather Forecast</h2>
      <a href="#">Live Data</a>
    </div>
    <div class="weather-card">
      <?php if ($weather): ?>
        <div class="weather-main">
          <div class="weather-icon">🌤️</div>
          <div class="weather-temp-block">
            <div class="weather-temp"><?php echo round($weather['temp_c']); ?><sup>°C</sup></div>
            <div class="weather-desc"><?php echo htmlspecialchars($weather['condition_text']); ?></div>
            <div class="weather-location">San Ildefonso, Bulacan</div>
          </div>
          <div class="weather-risk status-<?php echo $weather['level']; ?>">
            <?php echo strtoupper($weather['level']); ?>
          </div>
        </div>
        <div class="weather-stats">
          <div class="w-stat">
            <div class="stat-icon">💧</div>
            <div class="stat-val"><?php echo $weather['humidity']; ?> %</div>
            <div class="stat-label">Humidity</div>
          </div>
          <div class="w-stat">
            <div class="stat-icon">🌡️</div>
            <div class="stat-val"><?php echo round($weather['heat_index'], 1); ?>°</div>
            <div class="stat-label">Heat Index</div>
          </div>
        </div>
      <?php else: ?>
        <p style="font-size:0.82rem;color:#888;text-align:center;padding:1rem 0;">No weather data available.</p>
      <?php endif; ?>
    </div>

    <!-- EVACUATION -->
    <div class="section-header"><h2>Evacuation assistance</h2></div>
    <div class="evac-card">
      <p style="font-size:0.80rem;color:#555;padding:0.9rem 1rem 0.4rem;">
        When available, you will be able to find the nearest evacuation center and open navigation from here.
      </p>
      <div style="padding:0 1rem 1rem;">
        <a href="navigation.php" class="btn-nav">Open navigation prototype</a>
      </div>
    </div>

    <!-- ANNOUNCEMENTS -->
    <div class="section-header" id="announcements">
      <h2>Announcements</h2>
    </div>
    <?php if (!$announcements): ?>
      <div class="ann-list"><div class="ann-empty">No announcements yet.</div></div>
    <?php else: ?>
      <div class="ann-list">
        <?php foreach ($announcements as $a): ?>
          <div class="ann-item">
            <div class="ann-dot <?php echo $a['is_pinned'] ? 'pinned' : ''; ?>"></div>
            <div class="ann-body">
              <div class="ann-title">
                <?php if ($a['is_pinned']): ?><span class="badge">PINNED</span><?php endif; ?>
                <?php if ($a['disaster_title']): ?><span class="badge badge-disaster"><?php echo htmlspecialchars($a['disaster_title']); ?></span><?php endif; ?>
                <?php echo htmlspecialchars($a['title']); ?>
              </div>
              <div class="ann-preview"><?php echo htmlspecialchars(mb_substr($a['body'], 0, 200)); ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div><!-- /page-scroll -->

  <!-- BOTTOM NAV -->
  <nav class="bottom-nav">
    <a href="citizen_dashboard.php" class="nav-item active">
      <svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
      <span>Home</span>
    </a>
    <a href="#current-alerts" class="nav-item">
      <svg viewBox="0 0 24 24"><path d="M12 22c1.1 0 2-.9 2-2h-4a2 2 0 0 0 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4a1.5 1.5 0 0 0-3 0v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>
      <span>Alerts</span>
    </a>
    <a href="navigation.php" class="nav-item nav-center">
      <div class="nav-center-circle">
        <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5z"/></svg>
      </div>
      <span>Evacuate</span>
    </a>
    <a href="#announcements" class="nav-item">
      <svg viewBox="0 0 24 24"><path d="M20 2H4a2 2 0 0 0-2 2v18l4-4h14a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z"/></svg>
      <span>Updates</span>
    </a>
    <button class="nav-item" onclick="openSidebar()">
      <svg viewBox="0 0 24 24"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg>
      <span>Menu</span>
    </button>
  </nav>

</div><!-- /mobile-shell -->


<!-- ============================================================
     DESKTOP LAYOUT — 1024px+
     ============================================================ -->
<div class="desktop-wrapper">

  <!-- Desktop Top Bar with Hamburger on right -->
  <header class="desktop-topbar">
    <div class="drawer-logo" style="width:38px;height:38px;background:#eee;border-radius:50%;overflow:hidden;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <img src="../img/mdrrmo.png" alt="MDRRMO" onerror="this.style.display='none'" style="width:100%;height:100%;object-fit:cover;">
      <svg viewBox="0 0 24 24" style="width:20px;height:20px;fill:var(--red);"><path d="M12 2L3 7v5c0 5.25 3.75 10.15 9 11.35C17.25 22.15 21 17.25 21 12V7L12 2z"/></svg>
    </div>
    <div class="desktop-topbar-center">
      <div class="desktop-topbar-title">Citizen Dashboard</div>
      <div class="desktop-topbar-sub">Welcome back, <?php echo htmlspecialchars($user['full_name'] ?? 'Citizen'); ?></div>
    </div>
    <div class="desktop-topbar-right">
      <div class="desktop-date-chip"><?php echo date('l, F j, Y'); ?></div>
      <button class="hamburger-btn" id="desktopHamburger" onclick="openSidebar()" aria-label="Open menu">
        <span></span><span></span><span></span>
      </button>
    </div>
  </header>

  <div class="desktop-content">
    <div class="desktop-grid">

      <!-- LEFT COLUMN -->
      <div class="desktop-col-left">

        <!-- ACTIVE STATUS -->
        <div class="desktop-card">
          <div class="desktop-card-header"><h2>Active Status</h2></div>
          <div class="desktop-card-body desktop-alert-wrap">
            <?php if ($activeDisaster): ?>
              <div class="alert-banner alert-typhoon">
                <div class="alert-icon">⚠️</div>
                <div class="alert-text">
                  <div class="alert-title">
                    <?php echo htmlspecialchars(ucfirst($activeDisaster['type'])); ?>
                    Signal#<?php echo (int)$activeDisaster['level']; ?> Active
                  </div>
                  <div class="alert-sub">
                    <?php
                      $lvlLabel = ['1'=>'Low','2'=>'Moderate','3'=>'High','4'=>'Extreme'];
                      echo ($lvlLabel[(string)(int)$activeDisaster['level']] ?? 'Moderate') . ' risk level · Click for full details';
                    ?>
                  </div>
                </div>
                <div class="alert-chevron">›</div>
              </div>
              <?php if ($advice): ?>
                <div class="desktop-readybag">
                  <div class="desktop-readybag-icon">🎒</div>
                  <div>
                    <div class="desktop-readybag-title">Ready Bag Advice</div>
                    <div class="desktop-readybag-text"><?php echo htmlspecialchars($advice['message']); ?></div>
                  </div>
                </div>
              <?php endif; ?>
            <?php elseif ($weather && ($weather['level'] === 'high' || $weather['level'] === 'extreme')): ?>
              <div class="alert-banner alert-level-3">
                <div class="alert-icon">🌡️</div>
                <div class="alert-text">
                  <div class="alert-title">HEAT ALERT — Heat Index: <?php echo round($weather['heat_index']); ?>°C</div>
                  <div class="alert-sub">Stay hydrated and avoid outdoor activities</div>
                </div>
                <div class="alert-chevron">›</div>
              </div>
            <?php else: ?>
              <div class="alert-banner alert-none">
                <div class="alert-icon">✅</div>
                <div class="alert-text">
                  <div class="alert-title">No active disaster at this time</div>
                  <div class="alert-sub">Stay prepared and monitor updates</div>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- WEATHER -->
        <div class="desktop-card">
          <div class="desktop-card-header">
            <h2>Weather Forecast</h2>
            <a href="#">Live Data</a>
          </div>
          <div class="desktop-card-body">
            <?php if ($weather): ?>
              <div class="desktop-weather-grid">
                <div>
                  <div class="weather-main" style="margin-bottom:0;">
                    <div class="weather-icon">🌤️</div>
                    <div class="weather-temp-block">
                      <div class="weather-temp"><?php echo round($weather['temp_c']); ?><sup>°C</sup></div>
                      <div class="weather-desc"><?php echo htmlspecialchars($weather['condition_text']); ?></div>
                      <div class="weather-location">San Ildefonso, Bulacan</div>
                    </div>
                    <div class="weather-risk status-<?php echo $weather['level']; ?>">
                      <?php echo strtoupper($weather['level']); ?>
                    </div>
                  </div>
                </div>
                <div class="weather-stats" style="align-content:start;">
                  <div class="w-stat">
                    <div class="stat-icon">💧</div>
                    <div class="stat-val"><?php echo $weather['humidity']; ?> %</div>
                    <div class="stat-label">Humidity</div>
                  </div>
                  <div class="w-stat">
                    <div class="stat-icon">🌡️</div>
                    <div class="stat-val"><?php echo round($weather['heat_index'], 1); ?>°</div>
                    <div class="stat-label">Heat Index</div>
                  </div>
                </div>
              </div>
            <?php else: ?>
              <p style="font-size:0.82rem;color:#888;text-align:center;padding:1rem 0;">No weather data available.</p>
            <?php endif; ?>
          </div>
        </div>

        <!-- EVACUATION -->
        <div class="desktop-card">
          <div class="desktop-card-header"><h2>Evacuation Assistance</h2></div>
          <div class="desktop-evac-body">
            <p style="font-size:0.82rem;color:#555;margin-bottom:0.9rem;">
              When available, you will be able to find the nearest evacuation center and open navigation from here.
            </p>
            <a href="navigation.php" class="btn-nav">Open Navigation Prototype</a>
          </div>
        </div>

      </div><!-- /desktop-col-left -->

      <!-- RIGHT COLUMN — ANNOUNCEMENTS -->
      <div class="desktop-col-right">
        <div class="desktop-card">
          <div class="desktop-card-header" id="announcements">
            <h2>Announcements</h2>
          </div>
          <?php if (!$announcements): ?>
            <div class="ann-empty">No announcements yet.</div>
          <?php else: ?>
            <div class="desktop-ann-list ann-list">
              <?php foreach ($announcements as $a): ?>
                <div class="ann-item">
                  <div class="ann-dot <?php echo $a['is_pinned'] ? 'pinned' : ''; ?>"></div>
                  <div class="ann-body">
                    <div class="ann-title">
                      <?php if ($a['is_pinned']): ?><span class="badge">PINNED</span><?php endif; ?>
                      <?php if ($a['disaster_title']): ?><span class="badge badge-disaster"><?php echo htmlspecialchars($a['disaster_title']); ?></span><?php endif; ?>
                      <?php echo htmlspecialchars($a['title']); ?>
                    </div>
                    <div class="ann-preview"><?php echo htmlspecialchars(mb_substr($a['body'], 0, 200)); ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- /desktop-grid -->
  </div><!-- /desktop-content -->

</div><!-- /desktop-wrapper -->


<script>
  function openSidebar() {
    document.getElementById('sidebarDrawer').classList.add('open');
    document.getElementById('sidebarOverlay').classList.add('open');
    // Animate hamburger buttons
    document.querySelectorAll('.hamburger-btn').forEach(btn => btn.classList.add('open'));
    document.body.style.overflow = 'hidden';
  }
  function closeSidebar() {
    document.getElementById('sidebarDrawer').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('open');
    document.querySelectorAll('.hamburger-btn').forEach(btn => btn.classList.remove('open'));
    document.body.style.overflow = '';
  }
</script>
</body>
</html>