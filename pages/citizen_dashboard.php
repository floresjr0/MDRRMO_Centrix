<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/db.php';

require_login(); // Kahit sinong naka-login ay makakakita; citizens ang default dito
$user = current_user();
$pdo  = db();

// ── LIVE WEATHER DATA ──────────────────────────────────────────────────────────
require_once __DIR__ . '/config.php';

$lat = 15.0828;
$lon = 120.9417;

$url = "https://api.openweathermap.org/data/2.5/weather?lat=$lat&lon=$lon&appid=" . WEATHER_API_KEY . "&units=metric";

// ── Weather cache — kunin sa OWM max isang beses bawat 10 minuto ──
$cacheFile = sys_get_temp_dir() . '/mdrrmo_weather.json';
$cacheTTL  = 600; // 10 minuto

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
        $temp      = $data['main']['temp'];
        $humidity  = $data['main']['humidity'];
        $condition = $data['weather'][0]['description'] ?? 'N/A';
        $owm_icon  = $data['weather'][0]['icon'] ?? '01d';

        // ── Kalkulasyon ng Heat Index ──
        $t  = $temp;
        $rh = $humidity;

        $heatIndex = $t;

        if ($t >= 27 && $rh >= 40) {
            $heatIndex = -8.784695 + 1.61139411*$t + 2.338549*$rh
                - 0.14611605*$t*$rh - 0.012308094*($t*$t)
                - 0.016424828*($rh*$rh) + 0.002211732*($t*$t*$rh)
                + 0.00072546*($t*$rh*$rh) - 0.000003582*($t*$t*$rh*$rh);
        }

        // ── Antas ng panganib ──
        $level = 'low';

        if ($heatIndex >= 42) {
            $level = 'extreme';
        } elseif ($heatIndex >= 40) {
            $level = 'high';
        } elseif ($heatIndex >= 38) {
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

// ── Pinakamataas na antas ng kasalukuyang sakuna ──
// Kinukuha ang title, description, type, level para sa Disaster Modal
$disasterStmt   = $pdo->query("SELECT * FROM disasters WHERE status = 'ongoing' ORDER BY level DESC, started_at DESC LIMIT 1");
$activeDisaster = $disasterStmt->fetch();

// ── Ready Bag na payo batay sa sakuna o panahon ──
$advice = null;

if ($activeDisaster) {
    $type  = $activeDisaster['type'];
    $level = (int)$activeDisaster['level'];
    $stmt  = $pdo->prepare("SELECT * FROM ready_bag_templates
                             WHERE disaster_type = ?
                               AND level_min <= ?
                               AND level_max >= ?
                             ORDER BY level_min DESC
                             LIMIT 1");
    $stmt->execute([$type, $level, $level]);
    $advice = $stmt->fetch();
} elseif ($weather) {
    $type  = 'heat';
    $level = $weather['level'] === 'extreme' ? 4 :
             ($weather['level'] === 'high' ? 3 :
             ($weather['level'] === 'medium' ? 2 : 1));
    $stmt  = $pdo->prepare("SELECT * FROM ready_bag_templates
                             WHERE disaster_type = ?
                               AND level_min <= ?
                               AND level_max >= ?
                             ORDER BY level_min DESC
                             LIMIT 1");
    $stmt->execute([$type, $level, $level]);
    $advice = $stmt->fetch();
}

// ── Mga Anunsyo ──
$annStmt = $pdo->query("SELECT a.*, d.title AS disaster_title
                         FROM announcements a
                         LEFT JOIN disasters d ON d.id = a.disaster_id
                         ORDER BY a.is_pinned DESC, a.published_at DESC
                         LIMIT 6");
$announcements = $annStmt->fetchAll();

// ── ORAS NG ARAW ──
$currentHour = (int)date('H');
$isNightTime = ($currentHour >= 18 || $currentHour < 6);

// ── WEATHER HELPER FUNCTIONS ──

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

function wx_colors(string $cat, bool $isNight): array {
    if ($isNight) {
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

function wx_mascot_html(string $cat, bool $isNight, string $p = 'm'): string {
    if ($isNight) {
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

require_once __DIR__ . '/notify.php';
maybeSendDisasterNotification($pdo);

// ── Ready Bag modal data — JSON-safe para sa JS ──
$readyBagTitle   = $advice ? htmlspecialchars($advice['title']   ?? 'Ready Bag',   ENT_QUOTES) : 'Ready Bag';
$readyBagMessage = $advice ? htmlspecialchars($advice['message'] ?? '',             ENT_QUOTES) : '';
$readyBagJson    = json_encode([
    'title'   => $readyBagTitle,
    'message' => $readyBagMessage,
], JSON_UNESCAPED_UNICODE);

// ── Disaster modal data — mula sa disasters table, JSON-safe para sa JS ──
$disasterModalJson = json_encode($activeDisaster ? [
    'title'       => $activeDisaster['title']       ?? '',
    'type'        => $activeDisaster['type']         ?? '',
    'level'       => (int)($activeDisaster['level']  ?? 0),
    'description' => $activeDisaster['description']  ?? '',
    'status'      => $activeDisaster['status']        ?? '',
    'started_at'  => $activeDisaster['started_at']   ?? '',
] : null, JSON_UNESCAPED_UNICODE);

// ── Disaster alert level para sa JS notifications ──
$disasterLevel = $activeDisaster ? (int)$activeDisaster['level'] : 0;
$disasterType  = $activeDisaster ? htmlspecialchars($activeDisaster['type'], ENT_QUOTES) : '';

// ── Detect if running inside median.co WebView ──
$isMedianCo = isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'MedianWebView') !== false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Citizen Dashboard - MDRRMO San Ildefonso</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<link rel="stylesheet" href="../asset/css/userdashboard.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<?php if (!$isMedianCo): ?>
<!-- Only load OneSignal web SDK when NOT in median.co -->
<script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>
<?php endif; ?>
<script>
<?php if ($isMedianCo): ?>
  // median.co native push bridge
  window.MedialPush = window.MedialPush || {};
  window.MedialPush.onesignalAppId = "8704d450-f3b9-4bc8-a1a9-a376abd93131";
  
  // Auto‑register for push when device is ready
  function registerForPush() {
    if (window.MedialPush && window.MedialPush.registerForPushNotifications) {
      window.MedialPush.registerForPushNotifications();
      console.log("[median.co] Push registration requested");
    } else {
      console.warn("[median.co] MedialPush bridge not ready yet");
    }
  }
  
  // Wait for Cordova/median.co device ready event
  document.addEventListener('deviceready', function() {
    console.log("[median.co] Device ready – registering for push");
    registerForPush();
  }, false);
  
  // Fallback: if deviceready already fired, try after a short delay
  setTimeout(function() {
    if (window.MedialPush && window.MedialPush.registerForPushNotifications) {
      registerForPush();
    }
  }, 1000);
<?php else: ?>
  // Web (browser) OneSignal initialization
  window.OneSignalDeferred = window.OneSignalDeferred || [];
  OneSignalDeferred.push(async function(OneSignal) {
    await OneSignal.init({
      appId: "8704d450-f3b9-4bc8-a1a9-a376abd93131",
      serviceWorkerPath: "/OneSignalSDK.sw.js",
      promptOptions: {
        slidedown: {
          prompts: [{
            type: "push",
            autoPrompt: true,
            text: {
              actionMessage: "Nais mong makatanggap ng alerto sa sakuna at matinding init mula sa MDRRMO San Ildefonso?",
              acceptButton: "Oo, payagan",
              cancelButton: "Hindi muna",
            },
            delay: { timeDelay: 5, pageViews: 1 },
          }],
        },
      },
    });

    // Tag the user with their barangay for targeted alerts later
    const barangay = <?php echo json_encode($user['barangay_name'] ?? ''); ?>;
    const userId   = <?php echo json_encode((string)($user['id'] ?? '')); ?>;
    if (barangay) await OneSignal.User.addTag("barangay", barangay);
    if (userId)   await OneSignal.User.addTag("user_id", userId);
    await OneSignal.User.addTag("disaster_level", "<?php echo $disasterLevel; ?>");
  });
<?php endif; ?>
</script>
</head>
<body>

<!-- SIDEBAR OVERLAY AT DRAWER -->
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
      <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/></svg>Evacuate
    </a>
    <a href="#announcements" class="drawer-nav-item" onclick="closeSidebar()">
      <svg viewBox="0 0 24 24"><path d="M20 2H4a2 2 0 0 0-2 2v18l4-4h14a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z"/></svg>Announcements
    </a>
  </nav>
  <div class="drawer-footer">
    <a href="logout.php" class="drawer-logout">
      <svg viewBox="0 0 24 24"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5-5-5zM4 5h4V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>Logout
    </a>
  </div>
</div>

<!-- READY BAG MODAL -->
<div class="rbmodal-backdrop" id="rbModalBackdrop" onclick="closeReadyBagModal(event)">
  <div class="rbmodal-sheet" id="rbModalSheet">
    <div class="rbmodal-head">
      <div class="rbmodal-handle"></div>
      <div class="rbmodal-head-row">
        <div class="rbmodal-head-icon">🎒</div>
        <div>
          <div class="rbmodal-head-title" id="rbModalTitle">Ready Bag</div>
          <div class="rbmodal-head-sub">Suriin ang iyong mga gamit bago lumikas</div>
        </div>
      </div>
    </div>
    <div class="rbmodal-progress-wrap">
      <div class="rbmodal-progress-label">
        <span>Progreso</span>
        <span id="rbProgressText">0 / 0 nacheck</span>
      </div>
      <div class="rbmodal-progress-bar">
        <div class="rbmodal-progress-fill" id="rbProgressFill"></div>
      </div>
    </div>
    <div class="rbmodal-section-label">Mga Kailangan</div>
    <div id="rbChecklistWrap"></div>
    <button class="rbmodal-close-btn" onclick="closeReadyBagModal()">Naintindihan — Isara</button>
  </div>
</div>

<!-- DISASTER DETAIL MODAL -->
<div class="dsmodal-backdrop" id="dsModalBackdrop" onclick="closeDisasterModal(event)">
  <div class="dsmodal-sheet" id="dsModalSheet">
    <div class="dsmodal-head">
      <div class="dsmodal-handle"></div>
      <div class="dsmodal-head-row">
        <div class="dsmodal-head-icon" id="dsModalIcon">⚠️</div>
        <div>
          <div class="dsmodal-head-title" id="dsModalTitle">Alerto sa Sakuna</div>
          <div class="dsmodal-head-type"  id="dsModalType">Uri</div>
          <div class="dsmodal-level-badge" id="dsModalLevelBadge">
            <div class="dsmodal-level-dot"></div>
            <div class="dsmodal-level-text" id="dsModalLevelText">Signal #0</div>
          </div>
        </div>
      </div>
    </div>
    <div class="dsmodal-body">
      <div class="dsmodal-section-label">Mga Detalye</div>
      <div class="dsmodal-chips" id="dsModalChips"></div>
      <div class="dsmodal-section-label" id="dsModalDescLabel">Paglalarawan</div>
      <div class="dsmodal-desc-box" id="dsModalDesc">Walang karagdagang impormasyon na available.</div>
    </div>
    <button class="dsmodal-close-btn" onclick="closeDisasterModal()">Naintindihan — Isara</button>
  </div>
</div>

<!-- MOBILE VIEW -->
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
    <button class="hamburger-btn" onclick="openSidebar()"><span></span><span></span><span></span></button>
  </header>

  <div class="page-scroll">

    <?php if ($activeDisaster): ?>
    <div class="alert-banner alert-typhoon" id="current-alerts" onclick="openDisasterModal()" role="button" aria-label="Tingnan ang detalye ng sakuna">
      <div class="alert-icon">⚠️</div>
      <div class="alert-text">
        <div class="alert-title"><?php echo htmlspecialchars(ucfirst($activeDisaster['type'])); ?> Signal#<?php echo (int)$activeDisaster['level']; ?> — Aktibo</div>
        <div class="alert-sub"><?php $lvlLabel=['1'=>'Low','2'=>'Moderate','3'=>'High','4'=>'Severe']; echo ($lvlLabel[(string)(int)$activeDisaster['level']]??'Moderate').' na antas ng panganib · Pindutin para sa detalye'; ?></div>
      </div>
      <div class="alert-chevron">›</div>
    </div>
    <?php if ($advice): ?>
    <div class="readybag-card" onclick="openReadyBagModal()" role="button" aria-label="Buksan ang Ready Bag payo">
      <div class="readybag-tap-hint">Pindutin para makita ›</div>
      <div class="readybag-icon">🎒</div>
      <div>
        <div class="readybag-title">Payo sa Ready Bag</div>
        <div class="readybag-text"><?php echo htmlspecialchars(mb_substr($advice['message'],0,100)); ?>…</div>
      </div>
    </div>
    <?php endif; ?>
    <?php elseif ($weather && ($weather['level']==='high'||$weather['level']==='extreme')): ?>
    <div class="alert-banner alert-level-3" id="current-alerts">
      <div class="alert-icon">🌡️</div>
      <div class="alert-text">
        <div class="alert-title">ALERTO SA INIT — Heat Index: <?php echo round($weather['heat_index']); ?>°C</div>
        <div class="alert-sub">Uminom ng maraming tubig at iwasang lumabas · Manatiling ligtas</div>
      </div>
      <div class="alert-chevron">›</div>
    </div>
    <?php if ($advice): ?>
    <div class="readybag-card" onclick="openReadyBagModal()" role="button" aria-label="Buksan ang Ready Bag payo">
      <div class="readybag-tap-hint">Pindutin para makita ›</div>
      <div class="readybag-icon">🎒</div>
      <div>
        <div class="readybag-title">Payo sa Ready Bag</div>
        <div class="readybag-text"><?php echo htmlspecialchars(mb_substr($advice['message'],0,100)); ?>…</div>
      </div>
    </div>
    <?php endif; ?>
    <?php else: ?>
    <div class="alert-banner alert-none" id="current-alerts">
      <div class="alert-icon">✅</div>
      <div class="alert-text">
        <div class="alert-title">Walang aktibong sakuna sa ngayon</div>
        <div class="alert-sub">Manatiling handa at subaybayan ang mga update</div>
      </div>
    </div>
    <?php endif; ?>

    <div class="section-header"><h2>Weather Forecast</h2></div>
    <?php if ($weather): ?>
    <div class="weather-card" style="box-shadow:0 10px 38px <?php echo $wx_colors[3]; ?>;">
      <div class="weather-banner" style="background:linear-gradient(140deg,<?php echo $wx_colors[0]; ?> 0%,<?php echo $wx_colors[1]; ?> 45%,<?php echo $wx_colors[2]; ?> 100%);">
        <div class="weather-top-row">
          <div class="weather-left">
            <div class="weather-temp-big"><?php echo round($weather['temp_c']); ?><sup>°C</sup></div>
            <div class="weather-place-name">San Ildefonso, Bulacan</div>
            <div class="weather-condition-label"><?php echo htmlspecialchars($weather['condition_text']); ?></div>
          </div>
          <div class="weather-risk-pill <?php echo $weather['level']; ?>">
            <?php $riskLabels = ['low'=>'LOW','medium'=>'MODERATE','high'=>'HIGH','extreme'=>'SEVERE']; echo $riskLabels[$weather['level']]??strtoupper($weather['level']); ?> RISK
          </div>
        </div>
        <div class="weather-mascot-wrap">
          <span class="mascot-note"><?php echo $wx_ptcls[0]; ?></span>
          <span class="mascot-note" style="animation-delay:.9s"><?php echo $wx_ptcls[1]; ?></span>
          <span class="mascot-note" style="animation-delay:1.6s"><?php echo $wx_ptcls[2]; ?></span>
          <?php echo wx_mascot_html($wx_cat, $isNightTime, 'm'); ?>
        </div>
      </div>
      <div class="weather-stats-strip" style="background:linear-gradient(180deg,<?php echo $wx_colors[1]; ?> 0%,<?php echo $wx_colors[2]; ?> 100%);">
        <div class="w-stat-pill">
          <div class="stat-icon-3d">
            <svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
              <defs>
                <radialGradient id="dropBody" cx="38%" cy="28%" r="65%"><stop offset="0%" stop-color="#b3e5fc"/><stop offset="35%" stop-color="#29b6f6"/><stop offset="75%" stop-color="#0277bd"/><stop offset="100%" stop-color="#01579b"/></radialGradient>
                <radialGradient id="dropSpec" cx="30%" cy="22%" r="35%"><stop offset="0%" stop-color="rgba(255,255,255,.85)"/><stop offset="100%" stop-color="rgba(255,255,255,0)"/></radialGradient>
                <filter id="dropBlur"><feGaussianBlur stdDeviation="1.2"/></filter>
              </defs>
              <ellipse cx="20" cy="36" rx="8" ry="3" fill="rgba(2,119,189,.22)" filter="url(#dropBlur)"/>
              <path d="M20 4 Q27 14 28 22 A8 8 0 0 1 12 22 Q13 14 20 4Z" fill="url(#dropBody)"/>
              <path d="M12.4 22 Q12 18 15 13" stroke="rgba(100,210,255,.5)" stroke-width="1.5" fill="none" stroke-linecap="round"/>
              <path d="M20 4 Q27 14 28 22 A8 8 0 0 1 12 22 Q13 14 20 4Z" fill="url(#dropSpec)"/>
              <ellipse cx="17" cy="16" rx="3.5" ry="4.5" fill="rgba(255,255,255,.30)" transform="rotate(-15 17 16)"/>
            </svg>
          </div>
          <div class="stat-info"><div class="stat-val"><?php echo $weather['humidity']; ?>%</div><div class="stat-label">Humidity</div></div>
        </div>
        <div class="w-stat-pill">
          <div class="stat-icon-3d">
            <svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
              <defs>
                <linearGradient id="thermTube" x1="0" y1="0" x2="1" y2="0"><stop offset="0%" stop-color="#b0bec5"/><stop offset="30%" stop-color="#eceff1"/><stop offset="60%" stop-color="#cfd8dc"/><stop offset="100%" stop-color="#90a4ae"/></linearGradient>
                <linearGradient id="thermMercury" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#ff8a65"/><stop offset="60%" stop-color="#f4511e"/><stop offset="100%" stop-color="#bf360c"/></linearGradient>
                <radialGradient id="thermBulb" cx="40%" cy="35%" r="60%"><stop offset="0%" stop-color="#ff8a65"/><stop offset="50%" stop-color="#f4511e"/><stop offset="100%" stop-color="#bf360c"/></radialGradient>
                <radialGradient id="thermSpec" cx="35%" cy="28%" r="40%"><stop offset="0%" stop-color="rgba(255,255,255,.7)"/><stop offset="100%" stop-color="rgba(255,255,255,0)"/></radialGradient>
                <filter id="thermBlur"><feGaussianBlur stdDeviation="1"/></filter>
              </defs>
              <ellipse cx="20" cy="37" rx="6" ry="2.2" fill="rgba(0,0,0,.18)" filter="url(#thermBlur)"/>
              <rect x="16" y="5" width="8" height="22" rx="4" fill="url(#thermTube)"/>
              <rect x="17.5" y="6" width="2" height="20" rx="1" fill="rgba(255,255,255,.55)"/>
              <rect x="18" y="12" width="4" height="15" rx="2" fill="url(#thermMercury)"/>
              <circle cx="20" cy="31" r="6" fill="url(#thermBulb)"/>
              <circle cx="20" cy="31" r="6" fill="url(#thermSpec)"/>
              <line x1="24.5" y1="10" x2="26.5" y2="10" stroke="rgba(255,255,255,.7)" stroke-width="1" stroke-linecap="round"/>
              <line x1="24.5" y1="15" x2="26.5" y2="15" stroke="rgba(255,255,255,.7)" stroke-width="1" stroke-linecap="round"/>
              <line x1="24.5" y1="20" x2="26.5" y2="20" stroke="rgba(255,255,255,.7)" stroke-width="1" stroke-linecap="round"/>
              <line x1="24.5" y1="25" x2="26.5" y2="25" stroke="rgba(255,255,255,.7)" stroke-width="1" stroke-linecap="round"/>
            </svg>
          </div>
          <div class="stat-info"><div class="stat-val"><?php echo round($weather['heat_index'],1); ?>°C</div><div class="stat-label">Heat Index</div></div>
        </div>
      </div>
    </div>
    <?php else: ?>
    <div class="weather-card"><div class="weather-banner" style="padding-bottom:1rem;background:linear-gradient(135deg,#F97316,#FBBF24);"><p style="font-size:.82rem;color:rgba(255,255,255,.8);text-align:center;padding:.5rem 0;">Walang available na datos ng panahon.</p></div></div>
    <?php endif; ?>

    <div class="section-header"><h2>Evacuate</h2></div>
    <div class="evac-card">
      <p style="font-size:.80rem;color:#555;padding:.9rem 1rem .4rem;">Kapag available, hanapin ang pinakamalapit na evacuation center at mag-navigate mula sa iyong lokasyon.</p>
      <div style="padding:0 1rem 1rem;"><a href="navigation.php" class="btn-nav">Open Navigation</a></div>
    </div>

    <div class="section-header" id="announcements"><h2>Announcements</h2></div>
    <?php if (!$announcements): ?>
    <div class="ann-list"><div class="ann-empty">Wala pang anunsyo.</div></div>
    <?php else: ?>
    <div class="ann-list">
      <?php foreach ($announcements as $a): ?>
      <div class="ann-item">
        <div class="ann-dot <?php echo $a['is_pinned']?'pinned':''; ?>"></div>
        <div class="ann-body">
          <div class="ann-title">
            <?php if($a['is_pinned']): ?><span class="badge">NAKA-PIN</span><?php endif; ?>
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
    <a href="citizen_dashboard.php" class="nav-item active">
      <svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg><span>Home</span>
    </a>
    <!-- <a href="#current-alerts" class="nav-item">
      <svg viewBox="0 0 24 24"><path d="M12 22c1.1 0 2-.9 2-2h-4a2 2 0 0 0 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4a1.5 1.5 0 0 0-3 0v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg><span>Alerto</span>
    </a> -->
    <div class="nav-item nav-center" id="evacNavItem">
      <div class="nav-center-circle" id="evacFab">
        <div class="evac-fab-ring" id="evacRing"></div>
        <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z M12 11.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z"/></svg>
      </div>
      <div class="evac-hint" id="evacHint">Hold to Evacuate</div>
      <span>Evacuate</span>
    </div>
    <!-- <a href="#announcements" class="nav-item">
      <svg viewBox="0 0 24 24"><path d="M20 2H4a2 2 0 0 0-2 2v18l4-4h14a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z"/></svg><span>Update</span>
    </a> -->
    <button class="nav-item" onclick="openSidebar()">
      <svg viewBox="0 0 24 24"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg><span>Menu</span>
    </button>
  </nav>
</div>

<div class="evac-ripple-primary" id="evacRipplePrimary"></div>
<div class="evac-ripple-shimmer" id="evacRippleShimmer"></div>
<div class="evac-ripple-icon" id="evacRippleIcon">
  <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z M12 11.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z"/></svg>
  <span>LUMILIKAS NA</span>
</div>

<!-- DESKTOP VIEW -->
<div class="desktop-wrapper">
  <header class="desktop-topbar">
    <div class="drawer-logo" style="width:38px;height:38px;background:#eee;border-radius:50%;overflow:hidden;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <img src="../img/mdrrmo.png" alt="MDRRMO" onerror="this.style.display='none'" style="width:100%;height:100%;object-fit:cover;">
      <svg viewBox="0 0 24 24" style="width:20px;height:20px;fill:var(--red);"><path d="M12 2L3 7v5c0 5.25 3.75 10.15 9 11.35C17.25 22.15 21 17.25 21 12V7L12 2z"/></svg>
    </div>
    <div class="desktop-topbar-center">
      <div class="desktop-topbar-title">Citizen Dashboard</div>
      <div class="desktop-topbar-sub">Welcome, <?php echo htmlspecialchars($user['full_name']??'Citizen'); ?></div>
    </div>
    <div class="desktop-topbar-right">
      <div class="desktop-date-chip"><?php echo date('l, F j, Y'); ?></div>
      <button class="hamburger-btn" id="desktopHamburger" onclick="openSidebar()" aria-label="Buksan ang menu"><span></span><span></span><span></span></button>
    </div>
  </header>

  <div class="desktop-content">
    <div class="desktop-grid">
      <div class="desktop-col-left">
        <div class="desktop-card">
          <div class="desktop-card-header"><h2>Current Status</h2></div>
          <div class="desktop-card-body desktop-alert-wrap">
            <?php if ($activeDisaster): ?>
            <div class="alert-banner alert-typhoon" onclick="openDisasterModal()" role="button" style="cursor:pointer;">
              <div class="alert-icon">⚠️</div>
              <div class="alert-text">
                <div class="alert-title"><?php echo htmlspecialchars(ucfirst($activeDisaster['type'])); ?> Signal#<?php echo (int)$activeDisaster['level']; ?> — Aktibo</div>
                <div class="alert-sub"><?php $lvlLabel=['1'=>'Low','2'=>'Moderate','3'=>'High','4'=>'Severe']; echo ($lvlLabel[(string)(int)$activeDisaster['level']]??'Moderate').' na antas ng panganib · Pindutin para sa detalye'; ?></div>
              </div>
              <div class="alert-chevron">›</div>
            </div>
            <?php if ($advice): ?>
            <div class="desktop-readybag" onclick="openReadyBagModal()" role="button">
              <div class="desktop-readybag-icon">🎒</div>
              <div>
                <div class="desktop-readybag-title">Payo sa Ready Bag <small style="font-weight:400;color:#bcaaa4;font-size:.60rem;">· Pindutin para makita</small></div>
                <div class="desktop-readybag-text"><?php echo htmlspecialchars(mb_substr($advice['message'],0,100)); ?>…</div>
              </div>
            </div>
            <?php endif; ?>
            <?php elseif ($weather && ($weather['level']==='high'||$weather['level']==='extreme')): ?>
            <div class="alert-banner alert-level-3">
              <div class="alert-icon">🌡️</div>
              <div class="alert-text"><div class="alert-title">ALERTO SA INIT — Heat Index: <?php echo round($weather['heat_index']); ?>°C</div><div class="alert-sub">Uminom ng maraming tubig at iwasang lumabas</div></div>
              <div class="alert-chevron">›</div>
            </div>
            <?php if ($advice): ?>
            <div class="desktop-readybag" onclick="openReadyBagModal()" role="button">
              <div class="desktop-readybag-icon">🎒</div>
              <div>
                <div class="desktop-readybag-title">Payo sa Ready Bag <small style="font-weight:400;color:#bcaaa4;font-size:.60rem;">· Pindutin para makita</small></div>
                <div class="desktop-readybag-text"><?php echo htmlspecialchars(mb_substr($advice['message'],0,100)); ?>…</div>
              </div>
            </div>
            <?php endif; ?>
            <?php else: ?>
            <div class="alert-banner alert-none">
              <div class="alert-icon">✅</div>
              <div class="alert-text"><div class="alert-title">Walang aktibong sakuna sa ngayon</div><div class="alert-sub">Manatiling handa at subaybayan ang mga update</div></div>
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
                <div class="weather-risk-pill <?php echo $weather['level']; ?>"><?php $riskLabels=['low'=>'LOW','medium'=>'MODERATE','high'=>'HIGH','extreme'=>'SEVERE']; echo $riskLabels[$weather['level']]??strtoupper($weather['level']); ?> RISK</div>
              </div>
              <div class="weather-mascot-wrap" style="width:140px;height:140px;bottom:-10px;right:16px;">
                <span class="mascot-note"><?php echo $wx_ptcls[0]; ?></span>
                <span class="mascot-note" style="animation-delay:.9s"><?php echo $wx_ptcls[1]; ?></span>
                <span class="mascot-note" style="animation-delay:1.6s"><?php echo $wx_ptcls[2]; ?></span>
                <?php echo wx_mascot_html($wx_cat, $isNightTime, 'd'); ?>
              </div>
            </div>
            <div class="weather-stats-strip" style="background:linear-gradient(180deg,<?php echo $wx_colors[1]; ?> 0%,<?php echo $wx_colors[2]; ?> 100%);">
              <div class="w-stat-pill">
                <div class="stat-icon-3d">
                  <svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                      <radialGradient id="dDropBody" cx="38%" cy="28%" r="65%"><stop offset="0%" stop-color="#b3e5fc"/><stop offset="35%" stop-color="#29b6f6"/><stop offset="75%" stop-color="#0277bd"/><stop offset="100%" stop-color="#01579b"/></radialGradient>
                      <radialGradient id="dDropSpec" cx="30%" cy="22%" r="35%"><stop offset="0%" stop-color="rgba(255,255,255,.85)"/><stop offset="100%" stop-color="rgba(255,255,255,0)"/></radialGradient>
                      <filter id="dDropBlur"><feGaussianBlur stdDeviation="1.2"/></filter>
                    </defs>
                    <ellipse cx="20" cy="36" rx="8" ry="3" fill="rgba(2,119,189,.22)" filter="url(#dDropBlur)"/>
                    <path d="M20 4 Q27 14 28 22 A8 8 0 0 1 12 22 Q13 14 20 4Z" fill="url(#dDropBody)"/>
                    <path d="M12.4 22 Q12 18 15 13" stroke="rgba(100,210,255,.5)" stroke-width="1.5" fill="none" stroke-linecap="round"/>
                    <path d="M20 4 Q27 14 28 22 A8 8 0 0 1 12 22 Q13 14 20 4Z" fill="url(#dDropSpec)"/>
                    <ellipse cx="17" cy="16" rx="3.5" ry="4.5" fill="rgba(255,255,255,.30)" transform="rotate(-15 17 16)"/>
                  </svg>
                </div>
                <div class="stat-info"><div class="stat-val"><?php echo $weather['humidity']; ?>%</div><div class="stat-label">Humidity</div></div>
              </div>
              <div class="w-stat-pill">
                <div class="stat-icon-3d">
                  <svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                      <linearGradient id="dThermTube" x1="0" y1="0" x2="1" y2="0"><stop offset="0%" stop-color="#b0bec5"/><stop offset="30%" stop-color="#eceff1"/><stop offset="60%" stop-color="#cfd8dc"/><stop offset="100%" stop-color="#90a4ae"/></linearGradient>
                      <linearGradient id="dThermMercury" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#ff8a65"/><stop offset="60%" stop-color="#f4511e"/><stop offset="100%" stop-color="#bf360c"/></linearGradient>
                      <radialGradient id="dThermBulb" cx="40%" cy="35%" r="60%"><stop offset="0%" stop-color="#ff8a65"/><stop offset="50%" stop-color="#f4511e"/><stop offset="100%" stop-color="#bf360c"/></radialGradient>
                      <radialGradient id="dThermSpec" cx="35%" cy="28%" r="40%"><stop offset="0%" stop-color="rgba(255,255,255,.7)"/><stop offset="100%" stop-color="rgba(255,255,255,0)"/></radialGradient>
                      <filter id="dThermBlur"><feGaussianBlur stdDeviation="1"/></filter>
                    </defs>
                    <ellipse cx="20" cy="37" rx="6" ry="2.2" fill="rgba(0,0,0,.18)" filter="url(#dThermBlur)"/>
                    <rect x="16" y="5" width="8" height="22" rx="4" fill="url(#dThermTube)"/>
                    <rect x="17.5" y="6" width="2" height="20" rx="1" fill="rgba(255,255,255,.55)"/>
                    <rect x="18" y="12" width="4" height="15" rx="2" fill="url(#dThermMercury)"/>
                    <circle cx="20" cy="31" r="6" fill="url(#dThermBulb)"/>
                    <circle cx="20" cy="31" r="6" fill="url(#dThermSpec)"/>
                    <line x1="24.5" y1="10" x2="26.5" y2="10" stroke="rgba(255,255,255,.7)" stroke-width="1" stroke-linecap="round"/>
                    <line x1="24.5" y1="15" x2="26.5" y2="15" stroke="rgba(255,255,255,.7)" stroke-width="1" stroke-linecap="round"/>
                    <line x1="24.5" y1="20" x2="26.5" y2="20" stroke="rgba(255,255,255,.7)" stroke-width="1" stroke-linecap="round"/>
                    <line x1="24.5" y1="25" x2="26.5" y2="25" stroke="rgba(255,255,255,.7)" stroke-width="1" stroke-linecap="round"/>
                  </svg>
                </div>
                <div class="stat-info"><div class="stat-val"><?php echo round($weather['heat_index'],1); ?>°C</div><div class="stat-label">Heat Index</div></div>
              </div>
            </div>
          </div>
          <?php else: ?><p style="font-size:.82rem;color:#888;text-align:center;padding:1rem 0;">Walang available na datos ng panahon.</p><?php endif; ?>
        </div>

        <div class="desktop-card">
          <div class="desktop-card-header"><h2>Evacuate</h2></div>
          <div class="desktop-evac-body">
            <p style="font-size:.82rem;color:#555;margin-bottom:.9rem;">Kapag available, hanapin ang pinakamalapit na evacuation center at mag-navigate mula sa iyong lokasyon.</p>
            <a href="navigation.php" class="btn-nav">Open Navigation</a>
          </div>
        </div>
      </div>

      <div class="desktop-col-right">
        <div class="desktop-card">
          <div class="desktop-card-header" id="announcements"><h2>Announcements</h2></div>
          <?php if (!$announcements): ?>
          <div class="ann-empty">Wala pang anunsyo.</div>
          <?php else: ?>
          <div class="desktop-ann-list ann-list">
            <?php foreach ($announcements as $a): ?>
            <div class="ann-item">
              <div class="ann-dot <?php echo $a['is_pinned']?'pinned':''; ?>"></div>
              <div class="ann-body">
                <div class="ann-title">
                  <?php if($a['is_pinned']): ?><span class="badge">NAKA-PIN</span><?php endif; ?>
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
'use strict';

const READY_BAG_DATA = <?php echo $readyBagJson; ?>;
const DISASTER_DATA  = <?php echo $disasterModalJson ?? 'null'; ?>;
const DISASTER_LEVEL = <?php echo $disasterLevel; ?>;
const DISASTER_TYPE  = '<?php echo $disasterType; ?>';

function openSidebar() {
  document.getElementById('sidebarDrawer').classList.add('open');
  document.getElementById('sidebarOverlay').classList.add('open');
  document.querySelectorAll('.hamburger-btn').forEach(b => b.classList.add('open'));
  document.body.style.overflow = 'hidden';
}
function closeSidebar() {
  document.getElementById('sidebarDrawer').classList.remove('open');
  document.getElementById('sidebarOverlay').classList.remove('open');
  document.querySelectorAll('.hamburger-btn').forEach(b => b.classList.remove('open'));
  document.body.style.overflow = '';
}

function playAlertBeeps(level) {
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    if (level >= 4) {
      const totalDur = 2.0, pulseHz = 4, baseFreq = 880;
      for (let p = 0; p < Math.floor(totalDur * pulseHz); p++) {
        const osc = ctx.createOscillator(), gain = ctx.createGain();
        osc.connect(gain); gain.connect(ctx.destination);
        osc.type = 'sawtooth';
        osc.frequency.value = p % 2 === 0 ? baseFreq : baseFreq * 1.25;
        const start = ctx.currentTime + p * (1 / pulseHz), dur = 0.18;
        gain.gain.setValueAtTime(0, start);
        gain.gain.linearRampToValueAtTime(0.55, start + 0.02);
        gain.gain.linearRampToValueAtTime(0, start + dur);
        osc.start(start); osc.stop(start + dur + 0.01);
      }
      setTimeout(() => ctx.close(), (totalDur + 0.3) * 1000);
    } else {
      const beeps = level, freq = level >= 3 ? 880 : (level === 2 ? 660 : 523);
      const beepDur = level >= 3 ? 0.18 : 0.25, gap = level >= 3 ? 0.20 : 0.35;
      for (let i = 0; i < beeps; i++) {
        const osc = ctx.createOscillator(), gain = ctx.createGain();
        osc.connect(gain); gain.connect(ctx.destination);
        osc.type = 'sine'; osc.frequency.value = freq;
        const startAt = ctx.currentTime + i * (beepDur + gap);
        gain.gain.setValueAtTime(0, startAt);
        gain.gain.linearRampToValueAtTime(0.5, startAt + 0.01);
        gain.gain.linearRampToValueAtTime(0, startAt + beepDur);
        osc.start(startAt); osc.stop(startAt + beepDur + 0.01);
      }
      const totalDur = beeps * (beepDur + gap) + 0.3;
      setTimeout(() => ctx.close(), totalDur * 1000);
    }
  } catch (e) { console.warn('Audio alert not available:', e); }
}

function triggerVibration(level) {
  if (!('vibrate' in navigator)) return;
  const patterns = { 1: [400,200,400], 2: [300,150,300,150,300], 3: [200,100,200,100,200,100,200], 4: [500,100,300,100,500,100,500] };
  navigator.vibrate(patterns[level] || patterns[1]);
}

(function initPageLoadAlert() {
  if (DISASTER_LEVEL < 1) return;
  triggerVibration(DISASTER_LEVEL);
  let soundTriggered = false;
  function triggerPageLoadSound() {
    if (soundTriggered) return;
    soundTriggered = true;
    playAlertBeeps(DISASTER_LEVEL);
    document.removeEventListener('click', triggerPageLoadSound);
    document.removeEventListener('touchstart', triggerPageLoadSound);
  }
  document.addEventListener('click', triggerPageLoadSound, { once: true });
  document.addEventListener('touchstart', triggerPageLoadSound, { once: true, passive: true });
})();

function triggerBannerAlert() {
  if (DISASTER_LEVEL < 1) return;
  triggerVibration(DISASTER_LEVEL);
  playAlertBeeps(DISASTER_LEVEL);
}

const DISASTER_ICONS = { typhoon:'⚠️', flood:'⚠️', earthquake:'⚠️', heat:'⚠️', landslide:'⚠️', other:'⚠️' };
const LEVEL_LABELS = { 1:'Signal #1 — Mababa', 2:'Signal #2 — Katamtaman', 3:'Signal #3 — Mataas', 4:'Signal #4 — Sukdulan' };
const TYPE_LABELS = { typhoon:'Bagyo', flood:'Baha', earthquake:'Lindol', heat:'Init', landslide:'Landslide', other:'Iba pa' };
const STATUS_LABELS = { ongoing:'Nagpapatuloy', planned:'Nakaplano', resolved:'Naresolba' };

function openDisasterModal() {
  if (!DISASTER_DATA) return;
  const backdrop = document.getElementById('dsModalBackdrop');
  const iconEl = document.getElementById('dsModalIcon');
  const titleEl = document.getElementById('dsModalTitle');
  const typeEl = document.getElementById('dsModalType');
  const levelEl = document.getElementById('dsModalLevelText');
  const descEl = document.getElementById('dsModalDesc');
  const chipsEl = document.getElementById('dsModalChips');

  iconEl.textContent = DISASTER_ICONS[DISASTER_DATA.type] || '⚠️';
  titleEl.textContent = DISASTER_DATA.title || 'Alerto sa Sakuna';
  const typeTagalog = TYPE_LABELS[DISASTER_DATA.type] || DISASTER_DATA.type;
  typeEl.textContent = typeTagalog ? typeTagalog.charAt(0).toUpperCase() + typeTagalog.slice(1) + ' Event' : 'Kaganapan ng Sakuna';
  levelEl.textContent = LEVEL_LABELS[DISASTER_DATA.level] || ('Signal #' + DISASTER_DATA.level);
  descEl.textContent = DISASTER_DATA.description && DISASTER_DATA.description.trim() ? DISASTER_DATA.description : 'Walang karagdagang deskripsyon na ibinigay ng MDRRMO sa oras na ito. Mangyaring manatiling alerto at sumunod sa mga opisyal na tagubilin.';

  const startedStr = DISASTER_DATA.started_at ? new Date(DISASTER_DATA.started_at.replace(' ', 'T')).toLocaleString('en-PH', { month:'short', day:'numeric', year:'numeric', hour:'2-digit', minute:'2-digit' }) : 'Hindi alam';
  const displayType = TYPE_LABELS[DISASTER_DATA.type] || DISASTER_DATA.type || 'Hindi alam';
  const displayStatus = STATUS_LABELS[DISASTER_DATA.status] || DISASTER_DATA.status || 'Hindi alam';

  chipsEl.innerHTML = `
    <div class="dsmodal-chip"><span class="chip-label">Uri:</span>${escHtml(displayType.charAt(0).toUpperCase() + displayType.slice(1))}</div>
    <div class="dsmodal-chip"><span class="chip-label">Antas:</span>Signal #${DISASTER_DATA.level || 0}</div>
    <div class="dsmodal-chip"><span class="chip-label">Katayuan:</span>${escHtml(displayStatus)}</div>
    <div class="dsmodal-chip"><span class="chip-label">Nagsimula:</span>${escHtml(startedStr)}</div>
  `;

  backdrop.classList.add('open');
  document.body.style.overflow = 'hidden';
  triggerBannerAlert();
}

function closeDisasterModal(event) {
  if (event && event.target !== document.getElementById('dsModalBackdrop')) return;
  document.getElementById('dsModalBackdrop').classList.remove('open');
  document.body.style.overflow = '';
}

(function initDisasterModalSwipe() {
  const sheet = document.getElementById('dsModalSheet');
  if (!sheet) return;
  let startY = 0, isDragging = false;
  sheet.addEventListener('touchstart', (e) => { startY = e.touches[0].clientY; isDragging = true; }, { passive: true });
  sheet.addEventListener('touchmove', (e) => { if (!isDragging) return; const dy = e.touches[0].clientY - startY; if (dy > 0) sheet.style.transform = `translateY(${dy}px)`; }, { passive: true });
  sheet.addEventListener('touchend', (e) => { if (!isDragging) return; isDragging = false; const dy = e.changedTouches[0].clientY - startY; if (dy > 100) { document.getElementById('dsModalBackdrop').classList.remove('open'); document.body.style.overflow = ''; } sheet.style.transform = ''; });
})();

function parseReadyBagItems(message) {
  if (!message) return [];
  const byDash = message.split(/\s*[-•·]\s+/).filter(s => s.trim().length > 1);
  if (byDash.length > 1) return byDash.map(s => s.replace(/\.?\s*$/, '').trim());
  const bySentence = message.split(/\.\s+/).filter(s => s.trim().length > 3);
  if (bySentence.length > 1) return bySentence.map(s => s.trim());
  return [message.trim()];
}

let checklistState = {};

function openReadyBagModal() {
  const backdrop = document.getElementById('rbModalBackdrop');
  const title = document.getElementById('rbModalTitle');
  const wrap = document.getElementById('rbChecklistWrap');
  title.textContent = READY_BAG_DATA.title || 'Ready Bag';
  const items = parseReadyBagItems(READY_BAG_DATA.message);
  checklistState = {};
  items.forEach((_, i) => { checklistState[i] = false; });
  if (items.length > 1) {
    wrap.innerHTML = items.map((item, i) => `
      <div class="rbmodal-item" id="rbItem${i}">
        <div class="rbmodal-checkbox" id="rbChk${i}" onclick="toggleCheck(${i})"></div>
        <div class="rbmodal-item-text" id="rbItemText${i}">${escHtml(item)}</div>
      </div>
    `).join('');
  } else {
    wrap.innerHTML = `<div class="rbmodal-message">${escHtml(READY_BAG_DATA.message)}</div>`;
  }
  updateProgress(items.length);
  backdrop.classList.add('open');
  document.body.style.overflow = 'hidden';
  if ('vibrate' in navigator) navigator.vibrate(50);
}

function toggleCheck(index) {
  checklistState[index] = !checklistState[index];
  const chk = document.getElementById('rbChk' + index);
  const text = document.getElementById('rbItemText' + index);
  if (checklistState[index]) {
    chk.classList.add('checked');
    text.classList.add('checked-text');
    if ('vibrate' in navigator) navigator.vibrate(30);
  } else {
    chk.classList.remove('checked');
    text.classList.remove('checked-text');
  }
  updateProgress(Object.keys(checklistState).length);
}

function updateProgress(total) {
  const checked = Object.values(checklistState).filter(Boolean).length;
  const fill = document.getElementById('rbProgressFill');
  const label = document.getElementById('rbProgressText');
  if (total < 2) {
    const wrap = document.querySelector('.rbmodal-progress-wrap');
    if (wrap) wrap.style.display = 'none';
    return;
  }
  const pct = total > 0 ? Math.round((checked / total) * 100) : 0;
  if (fill) fill.style.width = pct + '%';
  if (label) label.textContent = checked + ' / ' + total + ' nacheck';
}

function closeReadyBagModal(event) {
  if (event && event.target !== document.getElementById('rbModalBackdrop')) return;
  document.getElementById('rbModalBackdrop').classList.remove('open');
  document.body.style.overflow = '';
}

function escHtml(str) {
  const d = document.createElement('div');
  d.appendChild(document.createTextNode(str));
  return d.innerHTML;
}

(function initSwipeToClose() {
  const sheet = document.getElementById('rbModalSheet');
  if (!sheet) return;
  let startY = 0, isDragging = false;
  sheet.addEventListener('touchstart', (e) => { startY = e.touches[0].clientY; isDragging = true; }, { passive: true });
  sheet.addEventListener('touchmove', (e) => { if (!isDragging) return; const dy = e.touches[0].clientY - startY; if (dy > 0) sheet.style.transform = `translateY(${dy}px)`; }, { passive: true });
  sheet.addEventListener('touchend', (e) => { if (!isDragging) return; isDragging = false; const dy = e.changedTouches[0].clientY - startY; if (dy > 100) { document.getElementById('rbModalBackdrop').classList.remove('open'); document.body.style.overflow = ''; } sheet.style.transform = ''; });
})();

// EVACUATION FAB — 1 SECOND HOLD
(function () {
  const HOLD_MS = 1000;
  const DEST = 'navigation.php';
  const fab = document.getElementById('evacFab');
  const hint = document.getElementById('evacHint');
  const navItem = document.getElementById('evacNavItem');
  const primary = document.getElementById('evacRipplePrimary');
  const shimmer = document.getElementById('evacRippleShimmer');
  const overlayIcon = document.getElementById('evacRippleIcon');
  if (!fab) return;

  let isHolding = false, isCompleted = false, animFrame = null, startTime = 0, rawProgress = 0;

  const evacIconSVG = `<svg viewBox="0 0 24 24" width="26" height="26" fill="white" style="width:26px;height:26px;pointer-events:none;"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/><circle cx="12" cy="9" r="2.5" fill="white"/></svg>`;
  const overlayIconSVG = `<svg viewBox="0 0 24 24" width="64" height="64" fill="white" style="width:64px;height:64px;"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/><circle cx="12" cy="9" r="2.5" fill="white"/></svg>`;

  while (fab.firstChild) fab.removeChild(fab.firstChild);
  const ringDiv = document.createElement('div');
  ringDiv.className = 'evac-fab-ring';
  ringDiv.id = 'evacRing';
  fab.appendChild(ringDiv);
  fab.insertAdjacentHTML('beforeend', evacIconSVG);

  if (overlayIcon) {
    while (overlayIcon.firstChild) overlayIcon.removeChild(overlayIcon.firstChild);
    overlayIcon.insertAdjacentHTML('beforeend', overlayIconSVG);
    const sp = document.createElement('span');
    sp.textContent = 'LUMILIKAS NA';
    overlayIcon.appendChild(sp);
  }

  let cx, cy, primaryDiam, shimmerDiam;

  function measureGeometry() {
    const r = fab.getBoundingClientRect();
    cx = r.left + r.width / 2;
    cy = r.top + r.height / 2;
    const maxRadius = Math.hypot(Math.max(cx, window.innerWidth - cx), Math.max(cy, window.innerHeight - cy)) * 1.18;
    primaryDiam = maxRadius * 2;
    shimmerDiam = maxRadius * 2.5;
  }

  function positionLayer(el, diam) {
    if (!el) return;
    el.style.width = diam + 'px';
    el.style.height = diam + 'px';
    el.style.left = (cx - diam / 2) + 'px';
    el.style.top = (cy - diam / 2) + 'px';
    el.style.transition = 'none';
    el.style.transform = 'scale(0)';
    el.style.opacity = '0';
  }

  function springEase(t) { return 1 - Math.pow(1 - t, 2.8); }

  function updatePrimaryRipple(t) {
    if (!primary) return;
    const eased = springEase(t);
    primary.style.transform = `scale(${eased * 1.02})`;
    primary.style.opacity = Math.min(t / 0.15, 1).toFixed(3);
  }

  function updateShimmerRipple(t) {
    if (!shimmer) return;
    const lagged = Math.max(0, t - 0.05);
    const eased = springEase(lagged);
    shimmer.style.transform = `scale(${eased * 0.92})`;
    shimmer.style.opacity = Math.min(lagged / 0.20, 0.72).toFixed(3);
  }

  function resetLayers() {
    if (primary) { primary.style.transition = 'none'; primary.style.transform = 'scale(0)'; primary.style.opacity = '0'; }
    if (shimmer) { shimmer.style.transition = 'none'; shimmer.style.transform = 'scale(0)'; shimmer.style.opacity = '0'; }
  }

  function updateRing(pct) {
    const r = document.getElementById('evacRing');
    if (r) r.style.setProperty('--pct', Math.min(pct, 100));
  }

  function startHold(e) {
    e.preventDefault();
    if (isCompleted) return;
    isHolding = true;
    isCompleted = false;
    rawProgress = 0;
    hint.textContent = 'Pindutin para lumikas';
    fab.classList.remove('done', 'shake');
    fab.classList.add('pressing');
    updateRing(0);
    fab.style.transform = '';
    measureGeometry();
    if (primary) positionLayer(primary, primaryDiam);
    if (shimmer) positionLayer(shimmer, shimmerDiam);
    startTime = Date.now();
    if ('vibrate' in navigator) navigator.vibrate(20);

    function updateHoldProgress() {
      if (!isHolding) return;
      const elapsed = Date.now() - startTime;
      const target = Math.min(elapsed / HOLD_MS, 1);
      rawProgress += (target - rawProgress) * 0.15;
      updateRing(rawProgress * 100);
      updatePrimaryRipple(rawProgress);
      updateShimmerRipple(rawProgress);
      fab.style.transform = `scale(${0.91 + rawProgress * 0.12})`;
      if (rawProgress >= 0.4 && hint.textContent !== 'Lumilikas…') {
        hint.textContent = 'Lumilikas…';
        if ('vibrate' in navigator) navigator.vibrate([30, 20, 30]);
      }
      if (target >= 1 && rawProgress > 0.97) {
        completeHold();
      } else {
        animFrame = requestAnimationFrame(updateHoldProgress);
      }
    }

    if (animFrame) cancelAnimationFrame(animFrame);
    animFrame = requestAnimationFrame(updateHoldProgress);
  }

  function cancelHold(e) {
    if (!isHolding || isCompleted) return;
    const elapsed = Date.now() - startTime;
    isHolding = false;
    if (animFrame) { cancelAnimationFrame(animFrame); animFrame = null; }
    fab.classList.remove('pressing');
    updateRing(0);
    fab.style.transform = '';
    hint.textContent = 'Pindutin para lumikas';
    if (primary) { primary.style.transition = 'transform .3s ease-out, opacity .3s ease-out'; primary.style.transform = 'scale(0)'; primary.style.opacity = '0'; }
    if (shimmer) { shimmer.style.transition = 'transform .3s ease-out, opacity .3s ease-out'; shimmer.style.transform = 'scale(0)'; shimmer.style.opacity = '0'; }
    if (elapsed > 200) {
      fab.classList.add('shake');
      setTimeout(() => fab.classList.remove('shake'), 300);
      if ('vibrate' in navigator) navigator.vibrate([50, 30, 50]);
    }
    setTimeout(resetLayers, 300);
  }

  function completeHold() {
    if (isCompleted) return;
    isHolding = false;
    isCompleted = true;
    if (animFrame) { cancelAnimationFrame(animFrame); animFrame = null; }
    if (primary) { primary.style.transition = 'transform .45s cubic-bezier(.19,1,.28,1.08), opacity .35s ease'; primary.style.transform = 'scale(1.04)'; primary.style.opacity = '1'; }
    if (shimmer) { shimmer.style.transition = 'transform .6s cubic-bezier(.19,1,.28,1.08), opacity .45s ease'; shimmer.style.transform = 'scale(1.0)'; shimmer.style.opacity = '0.75'; }
    fab.classList.remove('pressing');
    fab.classList.add('done');
    updateRing(100);
    hint.textContent = 'Lumilikas…';
    if ('vibrate' in navigator) navigator.vibrate([100, 50, 100, 50, 300]);
    overlayIcon.classList.add('visible');
    setTimeout(() => { window.location.href = DEST; }, 350);
  }

  fab.addEventListener('touchstart', startHold, { passive: false });
  fab.addEventListener('touchend', cancelHold);
  fab.addEventListener('touchcancel', cancelHold);
  fab.addEventListener('mousedown', startHold);
  document.addEventListener('mouseup', cancelHold);
  fab.addEventListener('contextmenu', (e) => e.preventDefault());

  setTimeout(() => {
    navItem.classList.add('hint-show');
    setTimeout(() => navItem.classList.remove('hint-show'), 2000);
  }, 1200);
})();
</script>
</body>
</html>