<?php
require_once __DIR__ . '/../pages/session.php';
require_login('admin');

require_once __DIR__ . '/../pages/center_helpers.php';

$user = current_user();
$pdo  = db();

// Summary metrics
$summary = [
    'total_centers'     => 0,
    'total_evacuees'    => 0,
    'status_available'  => 0,
    'status_near'       => 0,
    'status_full'       => 0,
    'status_temp'       => 0,
    'status_closed'     => 0,
];

$row = $pdo->query("SELECT COUNT(*) AS c FROM evacuation_centers")->fetch();
if ($row) {
    $summary['total_centers'] = (int)$row['c'];
}

$row = $pdo->query("SELECT COALESCE(SUM(total_members),0) AS total FROM evac_registrations")->fetch();
if ($row) {
    $summary['total_evacuees'] = (int)$row['total'];
}

$st = $pdo->query("SELECT status, COUNT(*) AS c FROM evacuation_centers GROUP BY status");
foreach ($st as $s) {
    switch ($s['status']) {
        case 'available':
            $summary['status_available'] = (int)$s['c'];
            break;
        case 'near_capacity':
            $summary['status_near'] = (int)$s['c'];
            break;
        case 'full':
            $summary['status_full'] = (int)$s['c'];
            break;
        case 'temp_shelter':
            $summary['status_temp'] = (int)$s['c'];
            break;
        case 'closed':
            $summary['status_closed'] = (int)$s['c'];
            break;
    }
}

$centers = get_centers_with_occupancy();

// Latest weather + active disaster for quick admin view
// Live weather for San Ildefonso (no cron)
$lat = 15.0828;
$lon = 120.9417;

$weather = null;

if (defined('WEATHER_API_KEY') && WEATHER_API_KEY !== '') {
    $url = "https://api.openweathermap.org/data/2.5/weather?lat=$lat&lon=$lon&appid=" . WEATHER_API_KEY . "&units=metric";
    $json = @file_get_contents($url);

    if ($json !== false) {
        $data = json_decode($json, true);
        if (isset($data['main'])) {
            $tempC = (float)$data['main']['temp'];
            $humidity = (float)$data['main']['humidity'];

            // Heat index calculation (same as before)
            $t = $tempC;
            $rh = $humidity;
            $heatIndex = $t;
            if ($t >= 27 && $rh >= 40) {
                $heatIndex = -8.784695 + 1.61139411*$t + 2.338549*$rh
                    - 0.14611605*$t*$rh - 0.012308094*($t*$t)
                    - 0.016424828*($rh*$rh) + 0.002211732*($t*$t*$rh)
                    + 0.00072546*($t*$rh*$rh) - 0.000003582*($t*$t*$rh*$rh);
            }

            // Comfort level
            $level = 'low';
            if ($heatIndex >= 41) {
                $level = 'extreme';
            } elseif ($heatIndex >= 38) {
                $level = 'high';
            } elseif ($heatIndex >= 32) {
                $level = 'medium';
            }

            $condition = $data['weather'][0]['description'] ?? 'N/A';

            $weather = [
                'temp_c' => $tempC,
                'humidity' => $humidity,
                'heat_index' => round($heatIndex),
                'level' => $level,
                'condition_text' => $condition
            ];
        }
    }
}
$disasterStmt = $pdo->query("SELECT * FROM disasters WHERE status = 'ongoing' ORDER BY level DESC, started_at DESC LIMIT 1");
$activeDisaster = $disasterStmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin dashboard - MDRRMO San Ildefonso</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/index.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
</head>
<body>
<header class="topbar">
    <div class="topbar-title">MDRRMO Admin Dashboard</div>
    <div class="topbar-user">
        <?php echo htmlspecialchars($user['full_name']); ?> (Admin)
        <a href="../pages/logout.php">Logout</a>
    </div>
</header>

<main class="dashboard admin-dashboard">
    <section class="card">
        <h2>Overview</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Evacuation centers</div>
                <div class="stat-value"><?php echo $summary['total_centers']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total evacuees</div>
                <div class="stat-value"><?php echo $summary['total_evacuees']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Available</div>
                <div class="stat-value"><?php echo $summary['status_available']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Near capacity</div>
                <div class="stat-value"><?php echo $summary['status_near']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Full</div>
                <div class="stat-value"><?php echo $summary['status_full']; ?></div>
            </div>
        </div>
        <p>
            <a href="users.php">Check all users & add coordinator</a>
            <a href="centers.php" class="btn-primary">Manage evacuation centers</a>
            <a href="disasters.php" class="btn-secondary">Disasters</a>
            <a href="announcements.php" class="btn-secondary">Announcements</a>
        </p>
    </section>

    <section class="card">
        <h2>Current situation</h2>
        <?php if ($activeDisaster): ?>
            <p>
                <strong>Ongoing disaster:</strong>
                <?php echo htmlspecialchars(strtoupper($activeDisaster['type'])); ?>
                (Level <?php echo (int)$activeDisaster['level']; ?>)<br>
                <?php echo htmlspecialchars($activeDisaster['title']); ?>
            </p>
        <?php else: ?>
            <p>No ongoing disaster recorded.</p>
        <?php endif; ?>

        <?php if ($weather): ?>
            <p>
                <strong>Weather:</strong>
                <?php echo htmlspecialchars($weather['condition_text']); ?>,
                <?php echo htmlspecialchars($weather['temp_c']); ?> °C,
                heat index <?php echo htmlspecialchars($weather['heat_index']); ?> °C
                (<?php echo htmlspecialchars($weather['level']); ?>)
            </p>
        <?php else: ?>
            <p>Weather data is not yet available.</p>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Evacuation centers map</h2>
        <div id="adminMap" style="height:400px;border-radius:12px;overflow:hidden;"></div>
    </section>
</main>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    const centers = <?php echo json_encode(array_map(function ($c) {
        return [
            'id' => (int)$c['id'],
            'name' => $c['name'],
            'lat' => (float)$c['lat'],
            'lng' => (float)$c['lng'],
            'barangay' => $c['barangay_name'],
            'status' => $c['status'],
            'max_capacity_people' => (int)$c['max_capacity_people'],
            'current_occupancy' => (int)$c['current_occupancy'],
        ];
    }, $centers)); ?>;

    if (centers.length > 0) {
        const map = L.map('adminMap', {zoomControl: true});
        const first = centers[0];
        map.setView([first.lat, first.lng], 13);
        L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
            attribution: '© CARTO © OSM',
            subdomains: 'abcd',
            maxZoom: 20
        }).addTo(map);

        centers.forEach(c => {
            let color = '#00e676';
            if (c.status === 'near_capacity') color = '#ffea00';
            else if (c.status === 'full') color = '#ff1744';
            else if (c.status === 'temp_shelter') color = '#00b0ff';
            else if (c.status === 'closed') color = '#9e9e9e';

            const marker = L.circleMarker([c.lat, c.lng], {
                radius: 10,
                color,
                fillColor: color,
                fillOpacity: 0.9
            }).addTo(map);
            marker.bindPopup(
                '<strong>' + c.name + '</strong><br>' +
                c.barangay + '<br>' +
                'Status: ' + c.status + '<br>' +
                'Evacuees: ' + c.current_occupancy + ' / ' + c.max_capacity_people
            );
        });
    } else {
        document.getElementById('adminMap').innerHTML = '<p>No centers defined yet.</p>';
    }
</script>
</body>
</html>

