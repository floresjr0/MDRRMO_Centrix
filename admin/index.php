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

// Evacuation Summary per Center with coordinator info and demographics
$evacSummaryStmt = $pdo->query("
    SELECT
        ec.id,
        ec.name AS center_name,
        b.name  AS barangay_name,
        ec.status,
        ec.max_capacity_people,

        -- Coordinator info
        u.full_name    AS coordinator_name,
        u.contact_number AS coordinator_contact,

        -- Demographics aggregated
        COALESCE(SUM(er.adults),   0) AS total_adults,
        COALESCE(SUM(er.children), 0) AS total_children,
        COALESCE(SUM(er.seniors),  0) AS total_seniors,
        COALESCE(SUM(er.pwds),     0) AS total_pwds,
        COALESCE(SUM(er.total_members), 0) AS total_evacuees,
        COUNT(DISTINCT er.id) AS total_families

    FROM evacuation_centers ec
    LEFT JOIN barangays b        ON b.id = ec.barangay_id
    LEFT JOIN users u            ON u.id = ec.coordinator_user_id
    LEFT JOIN evac_registrations er ON er.center_id = ec.id
    GROUP BY ec.id
    ORDER BY total_evacuees DESC
");
$evacSummary = $evacSummaryStmt->fetchAll();

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

            // Heat index calculation
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
    <title>MDRRMO Dashboard | San Ildefonso, Bulacan</title>
    <link rel="stylesheet" href="../asset/css/admin_index.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
    </style>
</head>
<body>
    <div class="app-wrapper">
        <!-- Sidebar Toggle Button - Outside Sidebar -->
        <div class="sidebar-toggle-btn" id="sidebarToggleBtn">
            <i class="fas fa-chevron-left"></i>
        </div>

        <!-- Sidebar - No Scrollbar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <div class="logo-image">
                        <!-- MDRRMO Logo - Ready for image -->
                        <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRqukasrXgrajWG753eZaSE0F17M3XFWroASQ&s" alt="MDRRMO Logo" onerror="this.style.display='none'; this.parentElement.innerHTML='<span class=logo-icon-fallback></span>';">
                    </div>
                    <div class="logo-text">
                        <h3>MDRRMO</h3>
                        <p>San Ildefonso</p>
                    </div>
                </div>
            </div>

            <div class="sidebar-content">
                <div class="sidebar-section">
                    <div class="sidebar-section-title">Main</div>
                    <ul class="sidebar-menu">
                        <li><a href="#" class="sidebar-link active"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
                        <li><a href="centers.php" class="sidebar-link"><i class="fas fa-map-marker-alt"></i> <span>Evacuation Centers</span></a></li>
                        <li><a href="users.php" class="sidebar-link"><i class="fas fa-users"></i> <span>User Management</span></a></li>
                        <li><a href="disasters.php" class="sidebar-link"><i class="fas fa-exclamation-triangle"></i> <span>Disasters</span></a></li>
                    </ul>
                </div>

                <div class="sidebar-section">
                    <div class="sidebar-section-title">Operations</div>
                    <ul class="sidebar-menu">
                        <li><a href="announcements.php" class="sidebar-link"><i class="fas fa-bullhorn"></i> <span>Announcements</span></a></li>
                    </ul>
                </div>

                <div class="sidebar-section">
                    <div class="sidebar-section-title">Monitoring</div>
                    <ul class="sidebar-menu">
                        <li><a href="maps.php" class="sidebar-link"><i class="fas fa-map"></i> <span>Maps</span></a></li>
                        <li><a href="evacuees.php" class="sidebar-link"><i class="fas fa-people-arrows"></i> <span>Evacuees</span> <span class="sidebar-badge"><?php echo number_format($summary['total_evacuees']); ?></span></a></li>
                    </ul>
                </div>

                <div class="sidebar-section">
                    <div class="sidebar-section-title">Settings</div>
                    <ul class="sidebar-menu">
                        <li><a href="../pages/logout.php" class="sidebar-link"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
                    </ul>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content" id="mainContent">
            <!-- Top Navigation -->
            <div class="top-nav">
                <div class="page-title">
                    <button class="mobile-toggle" id="mobileToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1>Dashboard</h1>
                </div>

                <div class="user-menu">
                    <div class="user-profile">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($user['full_name'] ?? 'A', 0, 1)); ?>
                        </div>
                        <div class="user-info">
                            <span class="user-name"><?php echo htmlspecialchars($user['full_name'] ?? 'Admin'); ?></span>
                            <span class="user-role">MDRRMO Administrator</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dashboard Content -->
            <div class="dashboard">
                <!-- Welcome Bar -->
                <div class="welcome-bar">
                    <div class="welcome-text">
                        <h2>Welcome back, <?php echo htmlspecialchars(explode(' ', $user['full_name'] ?? 'Admin')[0]); ?>!</h2>
                        <p>San Ildefonso, Bulacan</p>
                    </div>
                    <div class="date-badge">
                        <?php echo date('F j, Y'); ?>
                    </div>
                </div>

                <!-- Minimized Stat Cards -->
                <div class="stats-row">
                    <div class="stat-card">
                        <div class="stat-icon-small"><i class="fas fa-building"></i></div>
                        <div class="stat-content">
                            <div class="stat-value-small"><?php echo $summary['total_centers']; ?></div>
                            <div class="stat-label-small">Centers</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon-small"><i class="fas fa-users"></i></div>
                        <div class="stat-content">
                            <div class="stat-value-small"><?php echo number_format($summary['total_evacuees']); ?></div>
                            <div class="stat-label-small">Evacuees</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon-small"><i class="fas fa-check-circle" style="color: var(--map-green);"></i></div>
                        <div class="stat-content">
                            <div class="stat-value-small"><?php echo $summary['status_available']; ?></div>
                            <div class="stat-label-small">Available</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon-small"><i class="fas fa-exclamation-triangle" style="color: var(--map-yellow);"></i></div>
                        <div class="stat-content">
                            <div class="stat-value-small"><?php echo $summary['status_near']; ?></div>
                            <div class="stat-label-small">Near Cap</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon-small"><i class="fas fa-times-circle" style="color: var(--map-red);"></i></div>
                        <div class="stat-content">
                            <div class="stat-value-small"><?php echo $summary['status_full']; ?></div>
                            <div class="stat-label-small">Full</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon-small"><i class="fas fa-thermometer-half"></i></div>
                        <div class="stat-content">
                            <div class="stat-value-small"><?php echo $weather['heat_index'] ?? '32'; ?>°C</div>
                            <div class="stat-label-small">Heat Index</div>
                        </div>
                    </div>
                </div>

                <!-- Main Two Column Layout -->
                <div class="main-grid">
                    <!-- Left Column: Current Situation Card (Weather Style) - Full Height -->
                    <!-- <div class="weather-card">
                        <div class="weather-header">
                            <h2>San Ildefonso</h2>
                            <span>Chance of rain 50%</span>
                        </div> -->

                        <!-- Hardcoded weather data for now -->
                        <!-- <div class="weather-main">
                            <div class="weather-temp-large">32°</div>
                            <div class="weather-info">
                                <div class="weather-condition">Partly Cloudy</div>
                                <div class="weather-detail">Heat Index: 36° • Humidity: 65%</div>
                            </div>
                        </div> -->

                        <!-- <div class="forecast-title">TODAY'S FORECAST</div>
                        <div class="forecast-grid">
                            <div class="forecast-item">
                                <div class="forecast-time">6:00 AM</div>
                                <div class="forecast-temp">23°</div>
                            </div>
                            <div class="forecast-item">
                                <div class="forecast-time">9:00 AM</div>
                                <div class="forecast-temp">31°</div>
                            </div>
                            <div class="forecast-item">
                                <div class="forecast-time">12:00 PM</div>
                                <div class="forecast-temp">32°</div>
                            </div>
                            <div class="forecast-item">
                                <div class="forecast-time">3:00 PM</div>
                                <div class="forecast-temp">26°</div>
                            </div>
                            <div class="forecast-item">
                                <div class="forecast-time">6:00 PM</div>
                                <div class="forecast-temp">25°</div>
                            </div>
                            <div class="forecast-item">
                                <div class="forecast-time">9:00 PM</div>
                                <div class="forecast-temp">21°</div>
                            </div>
                        </div> -->

                        <!-- Air Conditions Section - No Backend Needed
                        <div class="air-conditions">
                            <div class="air-conditions-title">AIR CONDITIONS</div>
                            <div class="air-conditions-grid">
                                <div class="air-condition-item">
                                    <span class="air-condition-label">
                                        <i class="fas fa-temperature-low"></i> Real Feel
                                    </span>
                                    <span class="air-condition-value">32<span class="air-condition-unit">°C</span></span>
                                    
                                </div>
                                <div class="air-condition-item">
                                    <span class="air-condition-label">
                                        <i class="fas fa-wind"></i> Wind
                                    </span>
                                    <span class="air-condition-value">12<span class="air-condition-unit">km/h</span></span>
                                    
                                </div>
                                <div class="air-condition-item">
                                    <span class="air-condition-label">
                                        <i class="fas fa-droplet"></i> Chance of rain
                                    </span>
                                    <span class="air-condition-value">50<span class="air-condition-unit">%</span></span>
                                    
                                </div>
                                <div class="air-condition-item">
                                    <span class="air-condition-label">
                                        <i class="fas fa-sun"></i> UV Index
                                    </span>
                                    <span class="air-condition-value">7<span class="air-condition-unit"></span></span>
                                    
                                </div>
                            </div>
                        </div> -->

                        <?php if ($activeDisaster): ?>
                        <div class="disaster-badge">
                            <span>⚠️ Active: <?php echo htmlspecialchars(strtoupper($activeDisaster['type'])); ?> (Signal <?php echo (int)$activeDisaster['level']; ?>)</span>
                            <span><?php echo htmlspecialchars($activeDisaster['title']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Right Column: Quick Stats and Centers -->
                    <div class="right-column">
                        <!-- Quick Stats Mini Cards -->
                        <div class="stats-mini-grid">
                            <div class="stat-mini-card">
                                <div class="stat-mini-label">Available</div>
                                <div class="stat-mini-value" style="color: var(--map-green);"><?php echo $summary['status_available']; ?></div>
                            </div>
                            <div class="stat-mini-card">
                                <div class="stat-mini-label">Near Capacity</div>
                                <div class="stat-mini-value" style="color: var(--map-yellow);"><?php echo $summary['status_near']; ?></div>
                            </div>
                            <div class="stat-mini-card">
                                <div class="stat-mini-label">Full</div>
                                <div class="stat-mini-value" style="color: var(--map-red);"><?php echo $summary['status_full']; ?></div>
                            </div>
                            <div class="stat-mini-card">
                                <div class="stat-mini-label">Temp Shelters</div>
                                <div class="stat-mini-value" style="color: var(--map-blue);"><?php echo $summary['status_temp']; ?></div>
                            </div>
                        </div>

                        <!-- Evacuation Centers Card -->
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-map-pin"></i> Evacuation Centers</h3>
                                <span class="badge"><?php echo count($centers); ?> Active</span>
                            </div>
                            
                            <div class="centers-list">
                                <?php 
                                $displayCenters = array_slice($centers, 0, 4);
                                foreach ($displayCenters as $center): 
                                    $dotClass = 'dot-gray';
                                    $fillClass = '';
                                    $capacityPercent = ($center['max_capacity_people'] > 0) 
                                        ? ($center['current_occupancy'] / $center['max_capacity_people']) * 100 
                                        : 0;
                                    
                                    if ($center['status'] === 'available') {
                                        $dotClass = 'dot-green';
                                        $fillClass = 'green';
                                    } else if ($center['status'] === 'near_capacity') {
                                        $dotClass = 'dot-yellow';
                                        $fillClass = 'yellow';
                                    } else if ($center['status'] === 'full') {
                                        $dotClass = 'dot-red';
                                    } else if ($center['status'] === 'temp_shelter') {
                                        $dotClass = 'dot-blue';
                                    }
                                ?>
                                <div class="center-item">
                                    <div class="center-info">
                                        <h4><?php echo htmlspecialchars($center['name']); ?></h4>
                                        <p><?php echo htmlspecialchars($center['barangay_name']); ?></p>
                                    </div>
                                    <div class="capacity-indicator">
                                        <div class="capacity-bar">
                                            <div class="capacity-fill <?php echo $fillClass; ?>" style="width: <?php echo min($capacityPercent, 100); ?>%;"></div>
                                        </div>
                                        <span class="capacity-dot <?php echo $dotClass; ?>"></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <div style="margin-top: 15px; text-align: center;">
                                <a href="centers.php" style="color: var(--primary-red); text-decoration: none; font-size: 13px; font-weight: 500;">
                                    View All Centers <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Evacuation Centers Summary - FULLY RESTORED with all columns -->
                <?php if (!empty($evacSummary)): ?>
                <div class="evac-summary-card card">
                    <div class="card-header">
                        <h3><i class="fas fa-people-arrows"></i> Evacuation Centers Summary</h3>
                    </div>

                    <div style="overflow-x: auto;">
                        <table class="evac-table">
                            <thead>
                                <tr>
                                    <th>Center</th>
                                    <th>Coordinator</th>
                                    <th>Children</th>
                                    <th>Adults</th>
                                    <th>Seniors</th>
                                    <th>PWD</th>
                                    <th>Families</th>
                                    <th>Total</th>
                                    <th>Capacity</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($evacSummary as $row):
                                    $pct = $row['max_capacity_people'] > 0
                                        ? min(round(($row['total_evacuees'] / $row['max_capacity_people']) * 100), 100)
                                        : 0;

                                    $barColor = '#2E7D32';
                                    $statusLabel = 'Available';
                                    $statusClass = 'es-available';
                                    if ($row['status'] === 'near_capacity') {
                                        $barColor = '#FFC107'; $statusLabel = 'Near Cap'; $statusClass = 'es-near';
                                    } elseif ($row['status'] === 'full') {
                                        $barColor = '#D32F2F'; $statusLabel = 'Full'; $statusClass = 'es-full';
                                    } elseif ($row['status'] === 'temp_shelter') {
                                        $barColor = '#3498DB'; $statusLabel = 'Temp'; $statusClass = 'es-temp';
                                    } elseif ($row['status'] === 'closed') {
                                        $barColor = '#95A5A6'; $statusLabel = 'Closed'; $statusClass = 'es-closed';
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <div class="es-center-name"><?php echo htmlspecialchars($row['center_name']); ?></div>
                                        <div class="es-center-brgy">
                                            <?php echo htmlspecialchars($row['barangay_name']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($row['coordinator_name']): ?>
                                            <div class="es-coord-name"><?php echo htmlspecialchars($row['coordinator_name']); ?></div>
                                            <div class="es-coord-contact">
                                                <?php echo htmlspecialchars($row['coordinator_contact'] ?? '—'); ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="es-no-coord">Unassigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="es-demo es-children"><?php echo number_format($row['total_children']); ?></span></td>
                                    <td><span class="es-demo es-adults"><?php echo number_format($row['total_adults']); ?></span></td>
                                    <td><span class="es-demo es-seniors"><?php echo number_format($row['total_seniors']); ?></span></td>
                                    <td><span class="es-demo es-pwd"><?php echo number_format($row['total_pwds']); ?></span></td>
                                    <td><span class="es-families"><?php echo number_format($row['total_families']); ?></span></td>
                                    <td><span class="es-total"><?php echo number_format($row['total_evacuees']); ?></span></td>
                                    <td>
                                        <div class="es-cap-wrap">
                                            <div class="es-cap-bar">
                                                <div class="es-cap-fill" style="width:<?php echo $pct; ?>%; background:<?php echo $barColor; ?>;"></div>
                                            </div>
                                            <div class="es-cap-text">
                                                <?php echo number_format($row['total_evacuees']); ?> / <?php echo number_format($row['max_capacity_people']); ?>
                                                (<?php echo $pct; ?>%)
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="es-status-badge <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>

                            <?php
                                $grandChildren  = array_sum(array_column($evacSummary, 'total_children'));
                                $grandAdults    = array_sum(array_column($evacSummary, 'total_adults'));
                                $grandSeniors   = array_sum(array_column($evacSummary, 'total_seniors'));
                                $grandPwds      = array_sum(array_column($evacSummary, 'total_pwds'));
                                $grandFamilies  = array_sum(array_column($evacSummary, 'total_families'));
                                $grandTotal     = array_sum(array_column($evacSummary, 'total_evacuees'));
                                $grandCap       = array_sum(array_column($evacSummary, 'max_capacity_people'));
                            ?>
                            <tfoot>
                                <tr>
                                    <td colspan="2"><strong>TOTAL</strong></td>
                                    <td><strong><?php echo number_format($grandChildren); ?></strong></td>
                                    <td><strong><?php echo number_format($grandAdults); ?></strong></td>
                                    <td><strong><?php echo number_format($grandSeniors); ?></strong></td>
                                    <td><strong><?php echo number_format($grandPwds); ?></strong></td>
                                    <td><strong><?php echo number_format($grandFamilies); ?></strong></td>
                                    <td><strong><?php echo number_format($grandTotal); ?></strong></td>
                                    <td colspan="2">
                                        <strong><?php echo number_format($grandTotal); ?> / <?php echo number_format($grandCap); ?></strong>
                                        (<?php echo $grandCap > 0 ? round(($grandTotal/$grandCap)*100) : 0; ?>% overall)
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Map Card with Legend at Top Right -->
                <div class="map-container">
                    <div id="adminMap"></div>
                    
                    <!-- Map Legend - Top Right -->
                    <div class="map-legend">
                        <div class="legend-item">
                            <span class="legend-color green"></span>
                            <span>A</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color yellow"></span>
                            <span>N</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color red"></span>
                            <span>F</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color blue"></span>
                            <span>T</span>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Sidebar Toggle with external button - Smooth Animation
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const toggleBtn = document.getElementById('sidebarToggleBtn');
        const mobileToggle = document.getElementById('mobileToggle');

        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            toggleBtn.classList.toggle('collapsed');
            
            // Change icon with smooth transition
            const icon = toggleBtn.querySelector('i');
            if (sidebar.classList.contains('collapsed')) {
                icon.className = 'fas fa-chevron-right';
            } else {
                icon.className = 'fas fa-chevron-left';
            }
        });

        mobileToggle.addEventListener('click', () => {
            sidebar.classList.toggle('show');
        });

        // Map with Custom Location Pin + Shelter Icon Markers - Perfectly Centered
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
            map.setView([first.lat, first.lng], 12);
            
            L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
                attribution: '© OpenStreetMap, © CartoDB',
                subdomains: 'abcd',
                maxZoom: 20
            }).addTo(map);

            // Create custom icon for each center
            centers.forEach((c) => {
                // Determine color based on status
                let pinColor = '#2E7D32'; // green - available
                if (c.status === 'near_capacity') pinColor = '#FFC107'; // yellow
                else if (c.status === 'full') pinColor = '#D32F2F'; // red
                else if (c.status === 'temp_shelter') pinColor = '#3498DB'; // blue

                // Create custom div icon with perfectly centered elements
                const customIcon = L.divIcon({
                    className: 'custom-marker',
                    html: `
                        <div class="marker-pin ${c.status}">
                            <i class="fas fa-home marker-icon"></i>
                        </div>
                    `,
                    iconSize: [30, 42],
                    iconAnchor: [15, 42],
                    popupAnchor: [0, -42]
                });

                const marker = L.marker([c.lat, c.lng], {
                    icon: customIcon
                }).addTo(map);
                
                // Calculate capacity percentage
                const capacityPercent = Math.min((c.current_occupancy / c.max_capacity_people) * 100, 100);
                
                // Format status for CSS class
                const statusClass = c.status.replace('_', '-');

                // Create ultra minimal popup content (200px wide)
                const popupContent = `
                    <div class="mini-modal">
                        <div class="mini-header">
                            <h3 class="mini-title">${c.name}</h3>
                            <span class="mini-status ${statusClass}">${c.status === 'available' ? 'A' : c.status === 'near_capacity' ? 'N' : c.status === 'full' ? 'F' : 'T'}</span>
                        </div>
                        
                        <div class="mini-location">
                            ${c.barangay}
                        </div>
                        
                        <div class="mini-stats">
                            <div class="mini-stat">
                                <div class="mini-stat-value">${c.max_capacity_people}</div>
                                <div class="mini-stat-label">CAP</div>
                            </div>
                            <div class="mini-stat">
                                <div class="mini-stat-value">${c.current_occupancy}</div>
                                <div class="mini-stat-label">EVA</div>
                            </div>
                            <div class="mini-stat">
                                <div class="mini-stat-value">${c.max_capacity_people - c.current_occupancy}</div>
                                <div class="mini-stat-label">AVL</div>
                            </div>
                        </div>
                        
                        <div class="mini-capacity">
                            <div class="mini-capacity-header">
                                <span>Fill</span>
                                <span>${Math.round(capacityPercent)}%</span>
                            </div>
                            <div class="mini-capacity-bar">
                                <div class="mini-capacity-fill" style="width: ${capacityPercent}%; background: ${pinColor};"></div>
                            </div>
                        </div>
                        
                        <div class="mini-footer">
                            <a href="centers.php?id=${c.id}" class="mini-btn mini-btn-primary">
                                View
                            </a>
                            <button class="mini-btn mini-btn-secondary" onclick="alert('Directions coming soon!')">
                                Directions
                            </button>
                        </div>
                    </div>
                `;

                // Bind popup with custom class
                marker.bindPopup(popupContent, {
                    className: 'custom-popup',
                    minWidth: 200,
                    maxWidth: 200
                });
            });
        } else {
            document.getElementById('adminMap').innerHTML = '<div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #95A5A6;">No evacuation centers defined yet.</div>';
        }
    </script>
</body>
</html>