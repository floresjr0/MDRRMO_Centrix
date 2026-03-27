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

// ── Weather cache — fetch from OWM max once every 10 minutes ──
$cacheFile = sys_get_temp_dir() . '/mdrrmo_weather.json';
$cacheTTL  = 600; // 10 minutes

if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL) {
    $response = file_get_contents($cacheFile);
} else {
    $response = @file_get_contents($url);
    if ($response !== false) {
        file_put_contents($cacheFile, $response);
    }
}


$weather = null;

if ($response !== false) {

    $data = json_decode($response, true);

    if (!empty($data['main'])) {

        $temp = $data['main']['temp'];
        $humidity = $data['main']['humidity'];
        $condition  = $data['weather'][0]['description'] ?? 'N/A';
        $owm_icon   = $data['weather'][0]['icon'] ?? '01d'; // OWM icon code e.g. 01d, 10n

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
            'temp_c'         => $temp,
            'humidity'       => $humidity,
            'heat_index'     => $heatIndex,
            'condition_text' => $condition,
            'owm_icon'       => $owm_icon,
            'level'          => $level
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

/* ── TIME OF DAY DETECTION FOR WEATHER CONTAINER ONLY ── */
$currentHour = (int)date('H');
$isNightTime = ($currentHour >= 18 || $currentHour < 6); // 6PM to 6AM shows moon, 6AM to 6PM shows sun

/* ── PHP weather category + mascot SVG ──
   Determined server-side so the SVG is rendered directly in HTML —
   no JS innerHTML injection, no gradient ID conflicts between instances.
── */
function wx_category(string $desc): string {
    $d = strtolower($desc);
    if (preg_match('/thunder|storm|lightning/', $d)) return 'storm';
    if (preg_match('/rain|drizzle|shower/',     $d)) return 'rain';
    if (preg_match('/snow|sleet|hail/',         $d)) return 'rain';
    if (preg_match('/fog|mist|haze|smoke/',     $d)) return 'fog';
    if (preg_match('/cloud|overcast/',          $d)) return 'cloudy';
    if (preg_match('/wind|breezy/',             $d)) return 'windy';
    return 'sunny';
}

// Colors based on time of day (night mode only for weather container)
function wx_colors(string $cat, bool $isNight): array {
    if ($isNight) {
        // Night mode colors - dark blue/purple tones
        $nightMap = [
            'sunny'  => ['#0f1729','#1a2a52','#2d4080','rgba(15,23,41,.6)'],
            'cloudy' => ['#0d1520','#1e2d3d','#2e4158','rgba(13,21,32,.55)'],
            'rain'   => ['#080e1e','#0f1f3d','#162d5a','rgba(8,14,30,.6)'],
            'storm'  => ['#060810','#0d1020','#161c30','rgba(6,8,16,.7)'],
            'fog'    => ['#111820','#1c2530','#283545','rgba(17,24,32,.5)'],
            'windy'  => ['#091218','#112233','#1a3040','rgba(9,18,24,.5)'],
        ];
        return $nightMap[$cat] ?? $nightMap['sunny'];
    } else {
        // Day mode colors - original sunny/warm tones
        $dayMap = [
            'sunny'  => ['#F97316','#FB923C','#FBBF24','rgba(249,115,22,.5)'],
            'cloudy' => ['#475569','#64748B','#94A3B8','rgba(71,85,105,.4)'],
            'rain'   => ['#1D4ED8','#2563EB','#3B82F6','rgba(29,78,216,.5)'],
            'storm'  => ['#0F172A','#1E293B','#334155','rgba(15,23,42,.6)'],
            'fog'    => ['#475569','#64748B','#94A3B8','rgba(71,85,105,.35)'],
            'windy'  => ['#047857','#059669','#34D399','rgba(4,120,87,.4)'],
        ];
        return $dayMap[$cat] ?? $dayMap['sunny'];
    }
}

/* ── ANIMATED WEATHER MASCOT (SVG-based, no external deps) ──
   Each category renders a fully self-contained animated SVG character.
   prefix = unique ID prefix to avoid SVG gradient/clip conflicts between
   the mobile and desktop instances on the same page.
   Time-based: moon at night (6PM-6AM), sun during day (6AM-6PM)
── */
function wx_mascot_html(string $cat, bool $isNight, string $p = 'm'): string {
    if ($isNight) {
        // NIGHT MODE - MOON MASCOT
        return <<<SVG
<svg viewBox="0 0 160 160" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:100%;overflow:visible">
<defs>
  <radialGradient id="{$p}moon" cx="42%" cy="36%" r="62%">
    <stop offset="0%" stop-color="#FDFBF0"/>
    <stop offset="18%" stop-color="#F5F0D8"/>
    <stop offset="42%" stop-color="#E8DEBB"/>
    <stop offset="68%" stop-color="#C8BFA0"/>
    <stop offset="85%" stop-color="#9E9580"/>
    <stop offset="100%" stop-color="#6B6555"/>
  </radialGradient>
  <radialGradient id="{$p}limb" cx="68%" cy="58%" r="55%">
    <stop offset="0%" stop-color="rgba(15,12,30,.0)"/>
    <stop offset="60%" stop-color="rgba(15,12,30,.08)"/>
    <stop offset="100%" stop-color="rgba(15,12,30,.58)"/>
  </radialGradient>
  <radialGradient id="{$p}glow" cx="50%" cy="50%" r="50%">
    <stop offset="55%" stop-color="rgba(220,210,160,.0)"/>
    <stop offset="75%" stop-color="rgba(220,210,160,.12)"/>
    <stop offset="88%" stop-color="rgba(200,190,130,.22)"/>
    <stop offset="100%" stop-color="rgba(180,170,100,.0)"/>
  </radialGradient>
  <radialGradient id="{$p}spec" cx="34%" cy="28%" r="38%">
    <stop offset="0%" stop-color="rgba(255,255,245,.55)"/>
    <stop offset="100%" stop-color="rgba(255,255,245,.0)"/>
  </radialGradient>
  <radialGradient id="{$p}cr" cx="35%" cy="30%" r="65%">
    <stop offset="0%" stop-color="rgba(255,250,230,.18)"/>
    <stop offset="50%" stop-color="rgba(140,130,110,.25)"/>
    <stop offset="100%" stop-color="rgba(80,75,60,.45)"/>
  </radialGradient>
  <radialGradient id="{$p}iris" cx="38%" cy="32%" r="62%">
    <stop offset="0%" stop-color="#8fa8c8"/>
    <stop offset="55%" stop-color="#4a6890"/>
    <stop offset="100%" stop-color="#1e3050"/>
  </radialGradient>
  <radialGradient id="{$p}pupil" cx="36%" cy="30%" r="65%">
    <stop offset="0%" stop-color="#1a2a3a"/>
    <stop offset="100%" stop-color="#050a10"/>
  </radialGradient>
  <radialGradient id="{$p}mare" cx="50%" cy="50%" r="50%">
    <stop offset="0%" stop-color="rgba(90,82,65,.52)"/>
    <stop offset="100%" stop-color="rgba(90,82,65,.0)"/>
  </radialGradient>
</defs>
<style>
  .{$p}bob { animation:{$p}bob 4.2s ease-in-out infinite; transform-origin:80px 78px }
  .{$p}blink { animation:{$p}blink 5.5s ease-in-out infinite }
  .{$p}glow { animation:{$p}pulse 4.2s ease-in-out infinite }
  @keyframes {$p}bob { 0%,100%{transform:translateY(0) rotate(-1.5deg)} 50%{transform:translateY(-9px) rotate(1.5deg)} }
  @keyframes {$p}blink { 0%,80%,100%{transform:scaleY(1)} 86%,89%{transform:scaleY(.06)} }
  @keyframes {$p}pulse { 0%,100%{opacity:.7} 50%{opacity:1} }
</style>
<circle class="{$p}glow" cx="80" cy="78" r="55" fill="url(#{$p}glow)"/>
<circle class="{$p}glow" cx="80" cy="78" r="62" fill="none" stroke="rgba(220,205,140,.09)" stroke-width="8"/>
<g class="{$p}bob">
  <circle cx="80" cy="78" r="44" fill="url(#{$p}moon)"/>
  <ellipse cx="72" cy="66" rx="13" ry="10" fill="url(#{$p}mare)" transform="rotate(-15 72 66)"/>
  <ellipse cx="92" cy="82" rx="10" ry="7" fill="url(#{$p}mare)" transform="rotate(20 92 82)"/>
  <ellipse cx="65" cy="88" rx="8" ry="5" fill="url(#{$p}mare)" transform="rotate(-8 65 88)"/>
  <circle cx="60" cy="96" r="7.5" fill="url(#{$p}cr)"/>
  <circle cx="101" cy="68" r="5.5" fill="url(#{$p}cr)"/>
  <circle cx="88" cy="101" r="3.8" fill="url(#{$p}cr)"/>
  <circle cx="68" cy="58" r="2.8" fill="url(#{$p}cr)"/>
  <circle cx="110" cy="90" r="2.4" fill="url(#{$p}cr)"/>
  <circle cx="80" cy="78" r="44" fill="url(#{$p}limb)"/>
  <circle cx="80" cy="78" r="44" fill="url(#{$p}spec)"/>
  <g class="{$p}blink" style="transform-origin:80px 74px">
    <ellipse cx="68" cy="74" rx="6.5" ry="7" fill="url(#{$p}iris)"/>
    <ellipse cx="68" cy="74" rx="4.2" ry="4.8" fill="url(#{$p}pupil)"/>
    <circle cx="65.8" cy="71.5" r="2" fill="rgba(255,255,255,.88)"/>
    <ellipse cx="92" cy="74" rx="6.5" ry="7" fill="url(#{$p}iris)"/>
    <ellipse cx="92" cy="74" rx="4.2" ry="4.8" fill="url(#{$p}pupil)"/>
    <circle cx="89.8" cy="71.5" r="2" fill="rgba(255,255,255,.88)"/>
  </g>
  <ellipse cx="80" cy="83" rx="3.5" ry="2.2" fill="rgba(120,110,90,.2)"/>
  <path d="M70 90 Q80 97 90 90" stroke="#7a6a50" stroke-width="2.8" fill="none" stroke-linecap="round"/>
  <ellipse cx="60" cy="86" rx="8.5" ry="5" fill="rgba(180,160,210,.22)"/>
  <ellipse cx="100" cy="86" rx="8.5" ry="5" fill="rgba(180,160,210,.22)"/>
  <path d="M46 62 Q38 78 44 96" stroke="rgba(255,252,230,.28)" stroke-width="4" fill="none" stroke-linecap="round" style="filter:blur(1px)"/>
</g>
</svg>
SVG;
    } else {
        // DAY MODE - SUN MASCOT (original)
        return <<<SVG
<svg viewBox="0 0 160 160" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:100%;overflow:visible">
<defs>
  <radialGradient id="{$p}sph" cx="38%" cy="32%" r="62%">
    <stop offset="0%" stop-color="#FFFDE7"/>
    <stop offset="25%" stop-color="#FFE57F"/>
    <stop offset="60%" stop-color="#FFD600"/>
    <stop offset="100%" stop-color="#F57C00"/>
  </radialGradient>
  <radialGradient id="{$p}cor" cx="50%" cy="50%" r="50%">
    <stop offset="55%" stop-color="#FFAB40" stop-opacity=".55"/>
    <stop offset="100%" stop-color="#FFAB40" stop-opacity="0"/>
  </radialGradient>
  <linearGradient id="{$p}ray" x1="0" y1="0" x2="0" y2="1">
    <stop offset="0%" stop-color="#FFD600" stop-opacity="1"/>
    <stop offset="100%" stop-color="#FF6F00" stop-opacity="0"/>
  </linearGradient>
  <radialGradient id="{$p}spec" cx="35%" cy="28%" r="40%">
    <stop offset="0%" stop-color="#FFFFFF" stop-opacity=".9"/>
    <stop offset="100%" stop-color="#FFFFFF" stop-opacity="0"/>
  </radialGradient>
</defs>
<style>
  .{$p}rays{animation:{$p}spin 12s linear infinite;transform-origin:80px 80px}
  .{$p}bob{animation:{$p}bob 2.8s ease-in-out infinite;transform-origin:80px 80px}
  .{$p}blnk{animation:{$p}blnk 4s ease-in-out infinite}
  .{$p}chk{animation:{$p}chk 2.8s ease-in-out infinite}
  @keyframes {$p}spin{to{transform:rotate(360deg)}}
  @keyframes {$p}bob{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}
  @keyframes {$p}blnk{0%,88%,100%{transform:scaleY(1)}93%,96%{transform:scaleY(.08)}}
  @keyframes {$p}chk{0%,100%{opacity:.65}50%{opacity:1}}
</style>
<circle cx="80" cy="80" r="68" fill="url(#{$p}cor)"/>
<g class="{$p}rays">
  <ellipse cx="80" cy="22" rx="4.5" ry="14" fill="url(#{$p}ray)" transform="rotate(0 80 80)"/>
  <ellipse cx="80" cy="22" rx="4.5" ry="14" fill="url(#{$p}ray)" transform="rotate(45 80 80)"/>
  <ellipse cx="80" cy="22" rx="4.5" ry="14" fill="url(#{$p}ray)" transform="rotate(90 80 80)"/>
  <ellipse cx="80" cy="22" rx="4.5" ry="14" fill="url(#{$p}ray)" transform="rotate(135 80 80)"/>
  <ellipse cx="80" cy="22" rx="4.5" ry="14" fill="url(#{$p}ray)" transform="rotate(180 80 80)"/>
  <ellipse cx="80" cy="22" rx="4.5" ry="14" fill="url(#{$p}ray)" transform="rotate(225 80 80)"/>
  <ellipse cx="80" cy="22" rx="4.5" ry="14" fill="url(#{$p}ray)" transform="rotate(270 80 80)"/>
  <ellipse cx="80" cy="22" rx="4.5" ry="14" fill="url(#{$p}ray)" transform="rotate(315 80 80)"/>
</g>
<g class="{$p}bob">
  <circle cx="80" cy="80" r="36" fill="url(#{$p}sph)"/>
  <circle cx="80" cy="80" r="36" fill="none" stroke="#E65100" stroke-width="2.5" opacity=".25"/>
  <ellipse cx="68" cy="68" rx="14" ry="9" fill="url(#{$p}spec)"/>
  <g class="{$p}blnk" style="transform-origin:80px 80px">
    <ellipse cx="70" cy="77" rx="4.5" ry="5.5" fill="#7B3700"/>
    <ellipse cx="90" cy="77" rx="4.5" ry="5.5" fill="#7B3700"/>
    <circle cx="72" cy="75" r="1.8" fill="#fff" opacity=".9"/>
    <circle cx="92" cy="75" r="1.8" fill="#fff" opacity=".9"/>
    <circle cx="71" cy="77" r="2.2" fill="#3E1A00"/>
    <circle cx="91" cy="77" r="2.2" fill="#3E1A00"/>
  </g>
  <path d="M68 88 Q80 98 92 88" stroke="#7B3700" stroke-width="3" fill="none" stroke-linecap="round"/>
  <ellipse class="{$p}chk" cx="60" cy="87" rx="7" ry="4.5" fill="#FF7043" opacity=".45"/>
  <ellipse class="{$p}chk" cx="100" cy="87" rx="7" ry="4.5" fill="#FF7043" opacity=".45"/>
</g>
</svg>
SVG;
    }
}

$wx_cat    = isset($weather) ? wx_category($weather['condition_text'] ?? '') : 'sunny';
$wx_colors = wx_colors($wx_cat, $isNightTime);

// Particles based on time of day
if ($isNightTime) {
    $wx_particles = [
        'sunny'  => ['✨','⭐','🌙'],
        'cloudy' => ['☁','🌙','✦'],
        'rain'   => ['💧','🌧','💦'],
        'storm'  => ['⚡','🌩','💥'],
        'fog'    => ['🌫','✦','👻'],
        'windy'  => ['🌬','🍃','💨'],
    ];
} else {
    $wx_particles = [
        'sunny'  => ['✨','☀','⭐'],
        'cloudy' => ['☁','💨','🌤'],
        'rain'   => ['💧','🌧','💦'],
        'storm'  => ['⚡','🌩','💥'],
        'fog'    => ['👻','🌫','✦'],
        'windy'  => ['🍃','🌬','💨'],
    ];
}
$wx_ptcls = $wx_particles[$wx_cat] ?? $wx_particles['sunny'];
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
/* =============================================================
   CITIZEN DASHBOARD — MDRRMO San Ildefonso, Bulacan
   Main Stylesheet
   ============================================================= */


/* ─── RESET & TOKENS ──────────────────────────────────────── */

*,
*::before,
*::after {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

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

html,
body {
  height: 100%;
  font-family: var(--font);
  background: var(--bg);
  color: var(--text);
  -webkit-font-smoothing: antialiased;
}


/* ─── TOPBAR ──────────────────────────────────────────────── */

.topbar {
  height: var(--topbar-h);
  background: var(--white);
  display: flex;
  align-items: center;
  padding: 0 1rem;
  gap: .7rem;
  border-bottom: 1px solid var(--border);
  position: sticky;
  top: 0;
  z-index: 50;
}

.topbar-logo {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  overflow: hidden;
  flex-shrink: 0;
  background: #eee;
  display: flex;
  align-items: center;
  justify-content: center;
}

.topbar-logo img { width: 100%; height: 100%; object-fit: cover; }
.topbar-logo svg { width: 20px; height: 20px; fill: var(--red); }

.topbar-info  { flex: 1; min-width: 0; }

.topbar-title {
  font-size: .70rem;
  font-weight: 700;
  color: var(--text);
  text-transform: uppercase;
  letter-spacing: .04em;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.topbar-sub {
  font-size: .60rem;
  color: var(--muted);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}


/* ─── HAMBURGER ───────────────────────────────────────────── */

.hamburger-btn {
  background: none;
  border: none;
  cursor: pointer;
  padding: .3rem;
  display: flex;
  flex-direction: column;
  gap: 5px;
  flex-shrink: 0;
  border-radius: 6px;
  transition: background .15s;
}

.hamburger-btn:hover { background: var(--bg); }

.hamburger-btn span {
  display: block;
  width: 22px;
  height: 2px;
  background: var(--text);
  border-radius: 2px;
  transition: all .3s ease;
}

.hamburger-btn.open span:nth-child(1) { transform: translateY(7px)  rotate(45deg);  }
.hamburger-btn.open span:nth-child(2) { opacity: 0;                                 }
.hamburger-btn.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }


/* ─── SIDEBAR OVERLAY & DRAWER ────────────────────────────── */

.sidebar-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.45);
  z-index: 200;
  opacity: 0;
  pointer-events: none;
  transition: opacity .3s ease;
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
  transition: transform .32s cubic-bezier(.16,1,.3,1);
  box-shadow: 6px 0 32px rgba(0,0,0,.18);
  overflow-y: auto;
}

.sidebar-drawer.open { transform: translateX(0); }

/* Drawer brand header */
.drawer-brand {
  display: flex;
  align-items: center;
  gap: .75rem;
  padding: 1.25rem 1.2rem;
  border-bottom: 1px solid var(--border);
  background: linear-gradient(135deg, var(--red), var(--orange));
}

.drawer-logo {
  width: 42px;
  height: 42px;
  border-radius: 50%;
  overflow: hidden;
  flex-shrink: 0;
  background: rgba(255,255,255,.2);
  display: flex;
  align-items: center;
  justify-content: center;
}

.drawer-logo img { width: 100%; height: 100%; object-fit: cover; }
.drawer-logo svg { width: 22px; height: 22px; fill: #fff; }

.drawer-brand-title {
  font-size: .68rem;
  font-weight: 800;
  color: #fff;
  text-transform: uppercase;
  letter-spacing: .05em;
  line-height: 1.3;
}

.drawer-brand-sub {
  font-size: .58rem;
  color: rgba(255,255,255,.75);
  margin-top: 1px;
}

/* Drawer nav links */
.drawer-nav {
  flex: 1;
  padding: .75rem .7rem;
  display: flex;
  flex-direction: column;
  gap: .2rem;
}

.drawer-nav-label {
  font-size: .55rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: var(--muted);
  padding: .6rem .8rem .2rem;
}

.drawer-nav-item {
  display: flex;
  align-items: center;
  gap: .75rem;
  padding: .65rem .8rem;
  border-radius: 10px;
  text-decoration: none;
  color: var(--muted);
  font-size: .78rem;
  font-weight: 500;
  transition: background .15s, color .15s;
  cursor: pointer;
  border: none;
  background: none;
  width: 100%;
  text-align: left;
  font-family: var(--font);
}

.drawer-nav-item:hover  { background: #f5f5f5; color: var(--text); }
.drawer-nav-item.active { background: #fdecea; color: var(--red);  font-weight: 700; }

.drawer-nav-item svg {
  width: 18px;
  height: 18px;
  fill: currentColor;
  flex-shrink: 0;
}

/* Drawer footer / logout */
.drawer-footer {
  padding: .8rem .7rem;
  border-top: 1px solid var(--border);
}

.drawer-logout {
  display: flex;
  align-items: center;
  gap: .7rem;
  padding: .6rem .8rem;
  border-radius: 10px;
  background: #fff0f0;
  color: var(--red);
  text-decoration: none;
  font-size: .75rem;
  font-weight: 700;
  border: 1px solid #ffd0d0;
  transition: background .15s;
}

.drawer-logout:hover { background: #ffe0e0; }

.drawer-logout svg {
  width: 16px;
  height: 16px;
  fill: var(--red);
  flex-shrink: 0;
}


/* ─── MOBILE SHELL & PAGE SCROLL ──────────────────────────── */

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
  padding-bottom: calc(var(--navbar-h) + .5rem);
}


/* ─── ALERT BANNERS ───────────────────────────────────────── */

.alert-banner {
  margin: .7rem .9rem;
  border-radius: 10px;
  padding: .65rem .9rem;
  display: flex;
  align-items: center;
  gap: .6rem;
  cursor: pointer;
}

.alert-icon    { font-size: 1.1rem; flex-shrink: 0; }
.alert-text    { flex: 1; min-width: 0; }
.alert-chevron { font-size: 1rem; flex-shrink: 0; opacity: .7; }

.alert-title {
  font-size: .78rem;
  font-weight: 700;
  line-height: 1.3;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.alert-sub {
  font-size: .65rem;
  opacity: .85;
  margin-top: 1px;
}

/* Alert severity levels */
.alert-level-1 { background: #FFF9C4; color: #7a6000; }
.alert-level-2 { background: #FFE0B2; color: #7a3500; }
.alert-level-3 { background: #FFCDD2; color: #7a0000; }
.alert-level-4 { background: #B71C1C; color: #fff;    }
.alert-typhoon { background: #e07020; color: #fff;    }
.alert-none    { background: #E8F5E9; color: #1b5e20; }


/* ─── READY BAG CARD ──────────────────────────────────────── */

.readybag-card {
  margin: 0 .9rem .5rem;
  background: #fff8e1;
  border-radius: 10px;
  padding: .75rem .9rem;
  display: flex;
  gap: .7rem;
  align-items: flex-start;
  border: 1px solid #ffe082;
}

.readybag-icon  { font-size: 1.4rem; flex-shrink: 0; }

.readybag-title {
  font-size: .78rem;
  font-weight: 700;
  color: #5d4037;
  margin-bottom: .25rem;
}

.readybag-text {
  font-size: .68rem;
  color: #795548;
  line-height: 1.5;
}


/* ─── SECTION HEADER ──────────────────────────────────────── */

.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: .9rem .9rem .4rem;
}

.section-header h2 {
  font-size: .90rem;
  font-weight: 700;
  color: var(--text);
}

.section-header a {
  font-size: .68rem;
  color: var(--red);
  font-weight: 600;
  text-decoration: none;
}


/* ─── WEATHER CARD ────────────────────────────────────────── */

.weather-card {
  margin: 0 .9rem .5rem;
  border-radius: 26px;
  overflow: hidden;
  box-shadow: 0 10px 36px rgba(0,0,0,.17);
  position: relative;
}

.weather-banner {
  padding: 1.1rem 1rem 1.5rem;
  position: relative;
  overflow: hidden;
  min-height: 164px;
}

/* Decorative background orbs */
.weather-banner::before {
  content: '';
  position: absolute;
  top: -28px;
  right: -28px;
  width: 130px;
  height: 130px;
  border-radius: 50%;
  background: rgba(255,255,255,.11);
}

.weather-banner::after {
  content: '';
  position: absolute;
  bottom: -18px;
  left: 18px;
  width: 90px;
  height: 90px;
  border-radius: 50%;
  background: rgba(255,255,255,.07);
}

.weather-top-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  position: relative;
  z-index: 2;
}

.weather-left { flex: 1; }

.weather-temp-big {
  font-size: 3.8rem;
  font-weight: 800;
  color: #fff;
  line-height: 1;
  letter-spacing: -2px;
  text-shadow: 0 3px 14px rgba(0,0,0,.18);
}

.weather-temp-big sup {
  font-size: 1.2rem;
  font-weight: 600;
  letter-spacing: 0;
  vertical-align: super;
}

.weather-place-name {
  font-size: .82rem;
  font-weight: 700;
  color: rgba(255,255,255,.95);
  margin-top: .2rem;
}

.weather-condition-label {
  font-size: .70rem;
  color: rgba(255,255,255,.78);
  text-transform: capitalize;
  margin-top: 2px;
}

/* Risk pill */
.weather-risk-pill {
  padding: .28rem .80rem;
  border-radius: 20px;
  font-size: .60rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: .07em;
  background: rgba(255,255,255,.22);
  color: #fff;
  border: 1px solid rgba(255,255,255,.38);
  backdrop-filter: blur(4px);
  flex-shrink: 0;
  margin-top: 2px;
}

.weather-risk-pill.low     { background: rgba(34,197,94,.25);  border-color: rgba(34,197,94,.5);   }
.weather-risk-pill.medium  { background: rgba(255,255,255,.22); border-color: rgba(255,255,255,.5); }
.weather-risk-pill.high    { background: rgba(239,68,68,.25);  border-color: rgba(239,68,68,.5);   }
.weather-risk-pill.extreme { background: rgba(180,0,0,.35);    border-color: rgba(255,100,100,.6); }


/* ─── WEATHER MASCOT ──────────────────────────────────────── */

.weather-mascot-wrap {
  position: absolute;
  bottom: -8px;
  right: 6px;
  width: 128px;
  height: 128px;
  z-index: 5;
  animation: mascot-entrance .7s cubic-bezier(.34,1.56,.64,1) both;
}

@keyframes mascot-entrance {
  from { transform: translateY(22px) scale(.82); opacity: 0; }
  to   { transform: translateY(0)    scale(1);   opacity: 1; }
}

/* Floating particle emojis */
.mascot-note {
  position: absolute;
  font-size: .88rem;
  animation: float-note 2.6s ease-in-out infinite;
  opacity: 0;
  pointer-events: none;
  z-index: 6;
}

.mascot-note:nth-child(1) { top: 0;   right: 34px; animation-delay: 0s;    }
.mascot-note:nth-child(2) { top: 12px; right: 4px;  animation-delay: .85s;  }
.mascot-note:nth-child(3) { top: -7px; right: 58px; animation-delay: 1.5s; font-size: .64rem; }

@keyframes float-note {
  0%   { opacity: 0; transform: translateY(0)    scale(.8);  }
  30%  { opacity: 1;                                          }
  100% { opacity: 0; transform: translateY(-26px) scale(1.1); }
}


/* ─── WEATHER STATS STRIP ─────────────────────────────────── */

.weather-stats-strip {
  padding: .85rem 1rem .9rem;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: .7rem;
}

.w-stat-pill {
  background: rgba(255,255,255,.20);
  border: 1px solid rgba(255,255,255,.30);
  border-radius: 15px;
  padding: .68rem .85rem;
  display: flex;
  align-items: center;
  gap: .55rem;
  backdrop-filter: blur(4px);
}

.w-stat-pill .stat-emoji { font-size: 1.35rem; flex-shrink: 0; }
.w-stat-pill .stat-val   { font-size: .88rem; font-weight: 800; color: #fff; line-height: 1.1; }
.w-stat-pill .stat-label { font-size: .60rem; color: rgba(255,255,255,.75); font-weight: 500; }


/* ─── EVACUATION CARD ─────────────────────────────────────── */

.evac-card {
  margin: 0 .9rem .5rem;
  background: var(--white);
  border-radius: 14px;
  overflow: hidden;
  box-shadow: 0 2px 10px rgba(0,0,0,.06);
}

.btn-nav {
  display: inline-block;
  padding: .5rem 1.2rem;
  background: linear-gradient(135deg, var(--red), var(--orange));
  color: #fff;
  border-radius: 50px;
  font-size: .78rem;
  font-weight: 600;
  text-decoration: none;
  font-family: var(--font);
  transition: filter .2s;
}

.btn-nav:hover { filter: brightness(1.08); }


/* ─── ANNOUNCEMENTS ───────────────────────────────────────── */

.ann-list {
  margin: 0 .9rem .5rem;
  background: var(--white);
  border-radius: 14px;
  overflow: hidden;
  box-shadow: 0 2px 10px rgba(0,0,0,.06);
}

.ann-item {
  padding: .75rem .9rem;
  border-bottom: 1px solid var(--border);
  display: flex;
  gap: .6rem;
  align-items: flex-start;
}

.ann-item:last-child { border-bottom: none; }

.ann-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--red);
  flex-shrink: 0;
  margin-top: 5px;
}

.ann-dot.pinned { background: var(--yellow); }

.ann-body { flex: 1; min-width: 0; }

.ann-title {
  font-size: .78rem;
  font-weight: 600;
  color: var(--text);
  line-height: 1.3;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.ann-preview {
  font-size: .65rem;
  color: var(--muted);
  margin-top: 2px;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* Badges */
.badge {
  display: inline-block;
  font-size: .55rem;
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

.ann-empty {
  padding: 1.5rem;
  text-align: center;
  font-size: .78rem;
  color: var(--muted);
}


/* ─── BOTTOM NAVIGATION ───────────────────────────────────── */

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
  padding: 0 .5rem;
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
  padding: .3rem 0;
  transition: color .15s;
}

.nav-item.active { color: var(--red); }

.nav-item svg {
  width: 22px;
  height: 22px;
  fill: currentColor;
}

.nav-item span {
  font-size: .58rem;
  font-weight: 600;
  font-family: var(--font);
}

/* Center FAB nav item */
.nav-item.nav-center { position: relative; top: -16px; }

.nav-center-circle {
  width: 54px;
  height: 54px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--red), var(--orange));
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 16px rgba(192,57,30,.45);
}

.nav-center-circle svg { width: 26px; height: 26px; fill: #fff; }

.nav-item.nav-center span { color: var(--red); font-weight: 700; }


/* ─── DESKTOP LAYOUT  (≥ 1024px) ─────────────────────────── */

@media (min-width: 1024px) {

  html,
  body {
    overflow: auto;
    background: #f0ede8;
    height: auto;
  }

  .mobile-shell    { display: none; }

  .desktop-wrapper {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
  }

  /* Top bar */
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
    margin-left: .5rem;
  }

  .desktop-topbar-title { font-size: 1rem;  font-weight: 700; color: var(--text); }
  .desktop-topbar-sub   { font-size: .68rem; color: var(--muted); }

  .desktop-topbar-right {
    display: flex;
    align-items: center;
    gap: .8rem;
  }

  .desktop-date-chip {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: .3rem .9rem;
    font-size: .68rem;
    color: var(--muted);
    font-weight: 500;
  }

  /* Content area */
  .desktop-content { flex: 1; padding: 1.5rem 2rem; }

  .desktop-grid {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 1.25rem;
    align-items: start;
  }

  .desktop-col-left,
  .desktop-col-right {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
  }

  /* Cards */
  .desktop-card {
    background: var(--white);
    border-radius: 16px;
    box-shadow: 0 2px 12px rgba(0,0,0,.06);
    overflow: hidden;
  }

  .desktop-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.2rem .6rem;
    border-bottom: 1px solid var(--border);
  }

  .desktop-card-header h2 { font-size: .85rem; font-weight: 700; color: var(--text); }
  .desktop-card-header a  { font-size: .68rem; color: var(--red); font-weight: 600; text-decoration: none; }

  .desktop-card-body { padding: 1rem 1.2rem; }

  /* Alert section inside card */
  .desktop-alert-wrap { display: flex; flex-direction: column; gap: .5rem; }
  .desktop-alert-wrap .alert-banner { margin: 0; border-radius: 10px; }

  /* Ready bag inside card */
  .desktop-readybag {
    display: flex;
    gap: .7rem;
    align-items: flex-start;
    background: #fff8e1;
    border-radius: 10px;
    padding: .75rem .9rem;
    border: 1px solid #ffe082;
  }

  .desktop-readybag-icon  { font-size: 1.4rem; flex-shrink: 0; }
  .desktop-readybag-title { font-size: .78rem; font-weight: 700; color: #5d4037; margin-bottom: .2rem; }
  .desktop-readybag-text  { font-size: .68rem; color: #795548; line-height: 1.5; }

  /* Weather card overrides */
  .desktop-card .weather-card         { margin: 0; border-radius: 0; box-shadow: none; }
  .desktop-card .weather-mascot-wrap  { width: 140px; height: 140px; bottom: -10px; right: 16px; }

  /* Misc */
  .desktop-evac-body { padding: 1rem 1.2rem; }
  .desktop-ann-list  { margin: 0; box-shadow: none; border-radius: 0; }

}


/* ─── MOBILE-ONLY OVERRIDES  (≤ 1023px) ──────────────────── */

@media (max-width: 1023px) {
  .desktop-wrapper { display: none; }
  .bottom-nav      { display: flex; }
}

</style>
</head>
<body>

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
      <svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>Dashboard
    </a>
    <a href="#current-alerts" class="drawer-nav-item" onclick="closeSidebar()">
      <svg viewBox="0 0 24 24"><path d="M12 22c1.1 0 2-.9 2-2h-4a2 2 0 0 0 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4a1.5 1.5 0 0 0-3 0v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>Alerts
    </a>
    <a href="navigation.php" class="drawer-nav-item">
      <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5z"/></svg>Evacuation
    </a>
    <a href="#announcements" class="drawer-nav-item" onclick="closeSidebar()">
      <svg viewBox="0 0 24 24"><path d="M20 2H4a2 2 0 0 0-2 2v18l4-4h14a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z"/></svg>Announcements
    </a>
  </nav>
  <div class="drawer-footer">
    <a href="logout.php" class="drawer-logout">
      <svg viewBox="0 0 24 24"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5-5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>Log Out
    </a>
  </div>
</div>

<!-- MOBILE -->
<div class="mobile-shell">
  <header class="topbar">
    <div class="topbar-logo">
      <img src="../img/mdrrmo.png" alt="MDRRMO" onerror="this.style.display='none'">
      <svg viewBox="0 0 24 24"><path d="M12 2L3 7v5c0 5.25 3.75 10.15 9 11.35C17.25 22.15 21 17.25 21 12V7L12 2z"/></svg>
    </div>
    <div class="topbar-info">
      <div class="topbar-title">MDRRMO-San Ildefonso Bulacan</div>
      <div class="topbar-sub">Brgy. <?php echo htmlspecialchars($user['barangay_name'] ?? ''); ?> &nbsp;·&nbsp; <?php echo date('D, M j, Y'); ?></div>
    </div>
  </header>
  <div class="page-scroll">

    <?php if ($activeDisaster): ?>
    <div class="alert-banner alert-typhoon">
      <div class="alert-icon">⚠️</div>
      <div class="alert-text">
        <div class="alert-title"><?php echo htmlspecialchars(ucfirst($activeDisaster['type'])); ?> Signal#<?php echo (int)$activeDisaster['level']; ?> Active</div>
        <div class="alert-sub"><?php $lvlLabel=['1'=>'Low','2'=>'Moderate','3'=>'High','4'=>'Extreme']; echo ($lvlLabel[(string)(int)$activeDisaster['level']]??'Moderate').' risk level · Tap for full details'; ?></div>
      </div>
      <div class="alert-chevron">›</div>
    </div>
    <?php if ($advice): ?><div class="readybag-card"><div class="readybag-icon">🎒</div><div><div class="readybag-title">Ready Bag Advice</div><div class="readybag-text"><?php echo htmlspecialchars($advice['message']); ?></div></div></div><?php endif; ?>
    <?php elseif ($weather && ($weather['level']==='high'||$weather['level']==='extreme')): ?>
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

    <div class="section-header"><h2>Weather Forecast</h2><a href="#">Live Data</a></div>

    <?php if ($weather): ?>
    <div class="weather-card" style="box-shadow:0 10px 38px <?php echo $wx_colors[3]; ?>;">
      <div class="weather-banner" style="background:linear-gradient(140deg,<?php echo $wx_colors[0]; ?> 0%,<?php echo $wx_colors[1]; ?> 45%,<?php echo $wx_colors[2]; ?> 100%);">
        <div class="weather-top-row">
          <div class="weather-left">
            <div class="weather-temp-big"><?php echo round($weather['temp_c']); ?><sup>°C</sup></div>
            <div class="weather-place-name">San Ildefonso, Bulacan</div>
            <div class="weather-condition-label"><?php echo htmlspecialchars($weather['condition_text']); ?></div>
          </div>
          <div class="weather-risk-pill <?php echo $weather['level']; ?>"><?php echo strtoupper($weather['level']); ?> RISK</div>
        </div>
        <div class="weather-mascot-wrap">
          <span class="mascot-note"><?php echo $wx_ptcls[0]; ?></span>
          <span class="mascot-note" style="animation-delay:.9s"><?php echo $wx_ptcls[1]; ?></span>
          <span class="mascot-note" style="animation-delay:1.6s"><?php echo $wx_ptcls[2]; ?></span>
          <?php echo wx_mascot_html($wx_cat, $isNightTime, 'm'); ?>
        </div>
      </div>
      <div class="weather-stats-strip" style="background:linear-gradient(180deg,<?php echo $wx_colors[1]; ?> 0%,<?php echo $wx_colors[2]; ?> 100%);">
        <div class="w-stat-pill"><div class="stat-emoji">💧</div><div class="stat-info"><div class="stat-val"><?php echo $weather['humidity']; ?>%</div><div class="stat-label">Humidity</div></div></div>
        <div class="w-stat-pill"><div class="stat-emoji">🌡️</div><div class="stat-info"><div class="stat-val"><?php echo round($weather['heat_index'],1); ?>°C</div><div class="stat-label">Heat Index</div></div></div>
      </div>
    </div>
    <?php else: ?>
    <div class="weather-card"><div class="weather-banner" style="padding-bottom:1rem;background:linear-gradient(135deg,#F97316,#FBBF24);"><p style="font-size:.82rem;color:rgba(255,255,255,.8);text-align:center;padding:.5rem 0;">No weather data available.</p></div></div>
    <?php endif; ?>

    <div class="section-header"><h2>Evacuation assistance</h2></div>
    <div class="evac-card">
      <p style="font-size:.80rem;color:#555;padding:.9rem 1rem .4rem;">When available, you will be able to find the nearest evacuation center and open navigation from here.</p>
      <div style="padding:0 1rem 1rem;"><a href="navigation.php" class="btn-nav">Open navigation prototype</a></div>
    </div>

    <div class="section-header" id="announcements"><h2>Announcements</h2></div>
    <?php if (!$announcements): ?>
    <div class="ann-list"><div class="ann-empty">No announcements yet.</div></div>
    <?php else: ?>
    <div class="ann-list">
      <?php foreach ($announcements as $a): ?>
      <div class="ann-item">
        <div class="ann-dot <?php echo $a['is_pinned']?'pinned':''; ?>"></div>
        <div class="ann-body">
          <div class="ann-title">
            <?php if($a['is_pinned']): ?><span class="badge">PINNED</span><?php endif; ?>
            <?php if($a['disaster_title']): ?><span class="badge badge-disaster"><?php echo htmlspecialchars($a['disaster_title']); ?></span><?php endif; ?>
            <?php echo htmlspecialchars($a['title']); ?>
          </div>
          <div class="ann-preview"><?php echo htmlspecialchars(mb_substr($a['body'],0,200)); ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div>
  <nav class="bottom-nav">
    <a href="citizen_dashboard.php" class="nav-item active"><svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg><span>Home</span></a>
    <a href="#current-alerts" class="nav-item"><svg viewBox="0 0 24 24"><path d="M12 22c1.1 0 2-.9 2-2h-4a2 2 0 0 0 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4a1.5 1.5 0 0 0-3 0v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg><span>Alerts</span></a>
    <a href="navigation.php" class="nav-item nav-center"><div class="nav-center-circle"><svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5z"/></svg></div><span>Evacuate</span></a>
    <a href="#announcements" class="nav-item"><svg viewBox="0 0 24 24"><path d="M20 2H4a2 2 0 0 0-2 2v18l4-4h14a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z"/></svg><span>Updates</span></a>
    <button class="nav-item" onclick="openSidebar()"><svg viewBox="0 0 24 24"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg><span>Menu</span></button>
  </nav>
</div>

<!-- DESKTOP -->
<div class="desktop-wrapper">
  <header class="desktop-topbar">
    <div class="drawer-logo" style="width:38px;height:38px;background:#eee;border-radius:50%;overflow:hidden;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <img src="../img/mdrrmo.png" alt="MDRRMO" onerror="this.style.display='none'" style="width:100%;height:100%;object-fit:cover;">
      <svg viewBox="0 0 24 24" style="width:20px;height:20px;fill:var(--red);"><path d="M12 2L3 7v5c0 5.25 3.75 10.15 9 11.35C17.25 22.15 21 17.25 21 12V7L12 2z"/></svg>
    </div>
    <div class="desktop-topbar-center">
      <div class="desktop-topbar-title">Citizen Dashboard</div>
      <div class="desktop-topbar-sub">Welcome back, <?php echo htmlspecialchars($user['full_name']??'Citizen'); ?></div>
    </div>
    <div class="desktop-topbar-right">
      <div class="desktop-date-chip"><?php echo date('l, F j, Y'); ?></div>
      <button class="hamburger-btn" id="desktopHamburger" onclick="openSidebar()" aria-label="Open menu"><span></span><span></span><span></span></button>
    </div>
  </header>
  <div class="desktop-content">
    <div class="desktop-grid">
      <div class="desktop-col-left">
        <div class="desktop-card">
          <div class="desktop-card-header"><h2>Active Status</h2></div>
          <div class="desktop-card-body desktop-alert-wrap">
            <?php if ($activeDisaster): ?>
            <div class="alert-banner alert-typhoon">
              <div class="alert-icon">⚠️</div>
              <div class="alert-text">
                <div class="alert-title"><?php echo htmlspecialchars(ucfirst($activeDisaster['type'])); ?> Signal#<?php echo (int)$activeDisaster['level']; ?> Active</div>
                <div class="alert-sub"><?php $lvlLabel=['1'=>'Low','2'=>'Moderate','3'=>'High','4'=>'Extreme']; echo ($lvlLabel[(string)(int)$activeDisaster['level']]??'Moderate').' risk level · Click for full details'; ?></div>
              </div>
              <div class="alert-chevron">›</div>
            </div>
            <?php if ($advice): ?><div class="desktop-readybag"><div class="desktop-readybag-icon">🎒</div><div><div class="desktop-readybag-title">Ready Bag Advice</div><div class="desktop-readybag-text"><?php echo htmlspecialchars($advice['message']); ?></div></div></div><?php endif; ?>
            <?php elseif ($weather && ($weather['level']==='high'||$weather['level']==='extreme')): ?>
            <div class="alert-banner alert-level-3">
              <div class="alert-icon">🌡️</div>
              <div class="alert-text"><div class="alert-title">HEAT ALERT — Heat Index: <?php echo round($weather['heat_index']); ?>°C</div><div class="alert-sub">Stay hydrated and avoid outdoor activities</div></div>
              <div class="alert-chevron">›</div>
            </div>
            <?php else: ?>
            <div class="alert-banner alert-none">
              <div class="alert-icon">✅</div>
              <div class="alert-text"><div class="alert-title">No active disaster at this time</div><div class="alert-sub">Stay prepared and monitor updates</div></div>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <div class="desktop-card">
          <div class="desktop-card-header"><h2>Weather Forecast</h2><a href="#">Live Data</a></div>
          <?php if ($weather): ?>
          <div class="weather-card" style="margin:0;border-radius:0;box-shadow:none;">
            <div class="weather-banner" style="background:linear-gradient(140deg,<?php echo $wx_colors[0]; ?> 0%,<?php echo $wx_colors[1]; ?> 45%,<?php echo $wx_colors[2]; ?> 100%);">
              <div class="weather-top-row">
                <div class="weather-left">
                  <div class="weather-temp-big"><?php echo round($weather['temp_c']); ?><sup>°C</sup></div>
                  <div class="weather-place-name">San Ildefonso, Bulacan</div>
                  <div class="weather-condition-label"><?php echo htmlspecialchars($weather['condition_text']); ?></div>
                </div>
                <div class="weather-risk-pill <?php echo $weather['level']; ?>"><?php echo strtoupper($weather['level']); ?> RISK</div>
              </div>
              <div class="weather-mascot-wrap" style="width:140px;height:140px;bottom:-10px;right:16px;">
                <span class="mascot-note"><?php echo $wx_ptcls[0]; ?></span>
                <span class="mascot-note" style="animation-delay:.9s"><?php echo $wx_ptcls[1]; ?></span>
                <span class="mascot-note" style="animation-delay:1.6s"><?php echo $wx_ptcls[2]; ?></span>
                <?php echo wx_mascot_html($wx_cat, $isNightTime, 'd'); ?>
              </div>
            </div>
            <div class="weather-stats-strip" style="background:linear-gradient(180deg,<?php echo $wx_colors[1]; ?> 0%,<?php echo $wx_colors[2]; ?> 100%);">
              <div class="w-stat-pill"><div class="stat-emoji">💧</div><div class="stat-info"><div class="stat-val"><?php echo $weather['humidity']; ?>%</div><div class="stat-label">Humidity</div></div></div>
              <div class="w-stat-pill"><div class="stat-emoji">🌡️</div><div class="stat-info"><div class="stat-val"><?php echo round($weather['heat_index'],1); ?>°C</div><div class="stat-label">Heat Index</div></div></div>
            </div>
          </div>
          <?php else: ?><p style="font-size:.82rem;color:#888;text-align:center;padding:1rem 0;">No weather data available.</p><?php endif; ?>
        </div>
        <div class="desktop-card">
          <div class="desktop-card-header"><h2>Evacuation Assistance</h2></div>
          <div class="desktop-evac-body">
            <p style="font-size:.82rem;color:#555;margin-bottom:.9rem;">When available, you will be able to find the nearest evacuation center and open navigation from here.</p>
            <a href="navigation.php" class="btn-nav">Open Navigation Prototype</a>
          </div>
        </div>
      </div>
      <div class="desktop-col-right">
        <div class="desktop-card">
          <div class="desktop-card-header" id="announcements"><h2>Announcements</h2></div>
          <?php if (!$announcements): ?>
          <div class="ann-empty">No announcements yet.</div>
          <?php else: ?>
          <div class="desktop-ann-list ann-list">
            <?php foreach ($announcements as $a): ?>
            <div class="ann-item">
              <div class="ann-dot <?php echo $a['is_pinned']?'pinned':''; ?>"></div>
              <div class="ann-body">
                <div class="ann-title">
                  <?php if($a['is_pinned']): ?><span class="badge">PINNED</span><?php endif; ?>
                  <?php if($a['disaster_title']): ?><span class="badge badge-disaster"><?php echo htmlspecialchars($a['disaster_title']); ?></span><?php endif; ?>
                  <?php echo htmlspecialchars($a['title']); ?>
                </div>
                <div class="ann-preview"><?php echo htmlspecialchars(mb_substr($a['body'],0,200)); ?></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function openSidebar(){document.getElementById('sidebarDrawer').classList.add('open');document.getElementById('sidebarOverlay').classList.add('open');document.querySelectorAll('.hamburger-btn').forEach(b=>b.classList.add('open'));document.body.style.overflow='hidden'}
function closeSidebar(){document.getElementById('sidebarDrawer').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('open');document.querySelectorAll('.hamburger-btn').forEach(b=>b.classList.remove('open'));document.body.style.overflow=''}
</script>
</body>
</html>