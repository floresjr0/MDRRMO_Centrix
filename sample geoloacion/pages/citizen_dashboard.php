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
    <title>Citizen dashboard - MDRRMO San Ildefonso</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/index.css">
</head>
<body>
<header class="topbar">
    <div class="topbar-title">MDRRMO San Ildefonso</div>
    <div class="topbar-user">
        <?php echo htmlspecialchars($user['full_name']); ?>,
        <?php echo htmlspecialchars($user['barangay_name']); ?>,
        <?php echo htmlspecialchars($user['house_number']); ?>
        <a href="logout.php">Logout</a>
    </div>
</header>

<main class="dashboard">
    <section class="card">
        <h2>Current alerts</h2>
        <?php if ($activeDisaster): ?>
            <div class="alert-banner alert-level-<?php echo (int)$activeDisaster['level']; ?>">
                <div class="alert-title">
                    <?php echo htmlspecialchars(strtoupper($activeDisaster['type'])); ?>
                    (Level <?php echo (int)$activeDisaster['level']; ?>)
                </div>
                <div class="alert-body">
                    <?php echo htmlspecialchars($activeDisaster['title']); ?>
                </div>
            </div>
        <?php else: ?>

    <?php if ($weather && ($weather['level'] === 'high' || $weather['level'] === 'extreme')): ?>

        <div class="alert-banner alert-level-3">
            <div class="alert-title">
                HEAT ALERT
            </div>
            <div class="alert-body">
                Dangerous heat conditions detected. Heat Index:
                <strong><?php echo round($weather['heat_index']); ?>°C</strong>.
                Stay hydrated and avoid outdoor activities.
            </div>
        </div>

    <?php else: ?>
        <p>No ongoing disaster is recorded at the moment.</p>
    <?php endif; ?>
<?php endif; ?>
    </section>
    <div class="card weather-card">
    <h3>🌤 Weather Status</h3>

    <?php if($weather): ?>

        <p><strong>Temperature:</strong> <?php echo $weather['temp_c']; ?> °C</p>
        <p><strong>Humidity:</strong> <?php echo $weather['humidity']; ?> %</p>
        <p><strong>Heat Index:</strong> <?php echo round($weather['heat_index']); ?> °C</p>
        <p><strong>Condition:</strong> <?php echo htmlspecialchars($weather['condition_text']); ?></p>

        <p>
            <strong>Risk Level:</strong>
            <span class="status-<?php echo $weather['level']; ?>">
                <?php echo strtoupper($weather['level']); ?>
            </span>
        </p>

    <?php else: ?>

        <p>No weather data available.</p>

    <?php endif; ?>
</div>

    <section class="card">
        <h2>Announcements</h2>
        <?php if (!$announcements): ?>
            <p>No announcements yet.</p>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($announcements as $a): ?>
                    <li>
                        <?php if ($a['is_pinned']): ?>
                            <span class="badge">PINNED</span>
                        <?php endif; ?>
                        <strong><?php echo htmlspecialchars($a['title']); ?></strong>
                        <?php if ($a['disaster_title']): ?>
                            <span class="badge badge-disaster">
                                <?php echo htmlspecialchars($a['disaster_title']); ?>
                            </span>
                        <?php endif; ?>
                        <div class="announcement-body">
                            <?php echo nl2br(htmlspecialchars(mb_substr($a['body'], 0, 200))); ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Evacuation assistance</h2>
        <p>
            When available, you will be able to find the nearest evacuation center and open navigation
            from here.
        </p>
        <a href="navigation.php" class="btn-primary">Open navigation prototype</a>
    </section>
</main>
</body>
</html>

