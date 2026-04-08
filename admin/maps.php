<?php
require_once __DIR__ . '/../pages/session.php';
require_login('admin');

require_once __DIR__ . '/../pages/center_helpers.php';

$user    = current_user();
$pdo     = db();
$centers = get_centers_with_occupancy();

$summary = [
    'total_centers'    => 0,
    'total_evacuees'   => 0,
    'status_available' => 0,
    'status_near'      => 0,
    'status_full'      => 0,
    'status_temp'      => 0,
    'status_closed'    => 0,
];

$row = $pdo->query("SELECT COUNT(*) AS c FROM evacuation_centers")->fetch();
if ($row) $summary['total_centers'] = (int)$row['c'];

$row = $pdo->query("SELECT COALESCE(SUM(total_members),0) AS total FROM evac_registrations")->fetch();
if ($row) $summary['total_evacuees'] = (int)$row['total'];

$st = $pdo->query("SELECT status, COUNT(*) AS c FROM evacuation_centers GROUP BY status");
foreach ($st as $s) {
    switch ($s['status']) {
        case 'available':     $summary['status_available'] = (int)$s['c']; break;
        case 'near_capacity': $summary['status_near']      = (int)$s['c']; break;
        case 'full':          $summary['status_full']      = (int)$s['c']; break;
        case 'temp_shelter':  $summary['status_temp']      = (int)$s['c']; break;
        case 'closed':        $summary['status_closed']    = (int)$s['c']; break;
    }
}

// Sidebar badges
$_badgeCenters       = (int)$pdo->query("SELECT COUNT(*) FROM evacuation_centers")->fetchColumn();
$_badgeOngoing       = (int)$pdo->query("SELECT COUNT(*) FROM disasters WHERE status = 'ongoing'")->fetchColumn();
$_badgeAnnouncements = (int)$pdo->query("SELECT COUNT(*) FROM announcements")->fetchColumn();
$_badgeEvacuees      = (int)$pdo->query("SELECT COALESCE(SUM(total_members),0) FROM evac_registrations")->fetchColumn();
$_badgeUsers        = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Maps | MDRRMO San Ildefonso</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../asset/css/admin_maps.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>

    </style>
</head>
<body>
<div class="app-wrapper">

    <div class="sidebar-toggle-btn" id="sidebarToggleBtn">
        <i class="fas fa-chevron-left"></i>
    </div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <div class="logo-image">
                    <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRqukasrXgrajWG753eZaSE0F17M3XFWroASQ&s" alt="MDRRMO"
                         onerror="this.style.display='none';this.parentElement.innerHTML='<span style=color:white;font-weight:700;font-size:18px>M</span>'">
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
                    <li><a href="index.php"     class="sidebar-link"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
                    <li><a href="centers.php"   class="sidebar-link"><i class="fas fa-map-marker-alt"></i><span>Evacuation Centers</span><?php if($_badgeCenters > 0): ?><span class="sidebar-badge"><?php echo $_badgeCenters; ?></span><?php endif; ?></a></li>
                    <li><a href="users.php"     class="sidebar-link"><i class="fas fa-users"></i><span>User Management</span><?php if($_badgeUsers > 0): ?><span class="sidebar-badge"><?php echo $_badgeUsers; ?></span><?php endif; ?></a></li>
                    <li><a href="disasters.php" class="sidebar-link"><i class="fas fa-exclamation-triangle"></i><span>Disasters</span><?php if($_badgeOngoing > 0): ?><span class="sidebar-badge"><?php echo $_badgeOngoing; ?></span><?php endif; ?></a></li>
                </ul>
            </div>
            <div class="sidebar-section">
                <div class="sidebar-section-title">Operations</div>
                <ul class="sidebar-menu">
                    <li><a href="announcements.php" class="sidebar-link"><i class="fas fa-bullhorn"></i><span>Announcements</span><?php if($_badgeAnnouncements > 0): ?><span class="sidebar-badge"><?php echo $_badgeAnnouncements; ?></span><?php endif; ?></a></li>
                </ul>
            </div>
            <div class="sidebar-section">
                <div class="sidebar-section-title">Monitoring</div>
                <ul class="sidebar-menu">
                    <li><a href="maps.php"     class="sidebar-link active"><i class="fas fa-map"></i><span>Maps</span></a></li>
                    <li><a href="evacuees.php" class="sidebar-link">
                        <i class="fas fa-people-arrows"></i><span>Evacuees</span>
                        <?php if($_badgeEvacuees > 0): ?><span class="sidebar-badge"><?php echo $_badgeEvacuees; ?></span><?php endif; ?>
                    </a></li>
                </ul>
            </div>
            <div class="sidebar-section">
                <div class="sidebar-section-title">Settings</div>
                <ul class="sidebar-menu">
                    <li><a href="../pages/logout.php" class="sidebar-link"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
                </ul>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content" id="mainContent">

        <div class="top-nav">
            <div class="page-title">
                <button class="mobile-toggle" id="mobileToggle"><i class="fas fa-bars"></i></button>
                <h1>Maps</h1>
            </div>
            <div class="user-menu">
                <div class="user-profile">
                    <div class="user-avatar"><?php echo strtoupper(substr($user['full_name'] ?? 'A', 0, 1)); ?></div>
                    <div>
                        <div class="user-name"><?php echo htmlspecialchars($user['full_name'] ?? 'Admin'); ?></div>
                        <div class="user-role">MDRRMO Administrator</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard">

            <!-- Welcome Bar -->
            <div class="welcome-bar">
                <div class="welcome-text">
                    <h2>Evacuation Map</h2>
                    <p>
                        <i class="fas fa-map-marker-alt" style="color:var(--primary-red)"></i>
                        San Ildefonso, Bulacan — Live center locations &amp; status
                        <span class="date-badge"><i class="far fa-calendar"></i><?php echo date('F j, Y'); ?></span>
                    </p>
                </div>
                <div style="display:flex;gap:8px">
                    <a href="evacuees.php" style="text-decoration:none">
                        <span class="badge" style="padding:6px 14px;cursor:pointer"><i class="fas fa-people-arrows"></i> View Evacuees</span>
                    </a>
                    <a href="centers.php" style="text-decoration:none">
                        <span class="badge yellow" style="padding:6px 14px;cursor:pointer"><i class="fas fa-list"></i> Centers</span>
                    </a>
                </div>
            </div>

            <!-- Stat Cards — identical to index.php -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon-small"><i class="fas fa-building"></i></div>
                    <div class="stat-content">
                        <div class="stat-value-small"><?php echo $summary['total_centers']; ?></div>
                        <div class="stat-label-small">Centers</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-small blue"><i class="fas fa-users"></i></div>
                    <div class="stat-content">
                        <div class="stat-value-small"><?php echo number_format($summary['total_evacuees']); ?></div>
                        <div class="stat-label-small">Evacuees</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-small green"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-content">
                        <div class="stat-value-small"><?php echo $summary['status_available']; ?></div>
                        <div class="stat-label-small">Available</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-small yellow"><i class="fas fa-exclamation-triangle"></i></div>
                    <div class="stat-content">
                        <div class="stat-value-small"><?php echo $summary['status_near']; ?></div>
                        <div class="stat-label-small">Near Cap</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-small"><i class="fas fa-times-circle"></i></div>
                    <div class="stat-content">
                        <div class="stat-value-small"><?php echo $summary['status_full']; ?></div>
                        <div class="stat-label-small">Full</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-small blue"><i class="fas fa-house-damage"></i></div>
                    <div class="stat-content">
                        <div class="stat-value-small"><?php echo $summary['status_temp']; ?></div>
                        <div class="stat-label-small">Temp Shelter</div>
                    </div>
                </div>
            </div>

            <!-- Map + Panel — same 1.5fr/1fr as index.php -->
            <div class="grid-2">

                <!-- Map Card -->
                <div class="card map-card">
                    <div class="map-header">
                        <h3><i class="fas fa-map"></i> San Ildefonso, Bulacan</h3>
                        <div class="map-legend" style="position:static;box-shadow:none;border:1px solid #EDE7E7">
                            <div class="legend-item"><span class="legend-dot" style="background:var(--map-green)"></span>Available</div>
                            <div class="legend-item"><span class="legend-dot" style="background:var(--map-yellow)"></span>Near Cap</div>
                            <div class="legend-item"><span class="legend-dot" style="background:var(--map-red)"></span>Full</div>
                            <div class="legend-item"><span class="legend-dot" style="background:var(--map-blue)"></span>Temp</div>
                            <div class="legend-item"><span class="legend-dot" style="background:#95A5A6"></span>Closed</div>
                        </div>
                    </div>
                    <div class="map-wrapper">
                        <div id="mainMap"></div>
                        <div class="map-controls">
                            <button class="map-ctrl-btn active" id="layerStreet" onclick="switchLayer('street')"><i class="fas fa-road"></i> Street</button>
                            <button class="map-ctrl-btn"        id="layerLight"  onclick="switchLayer('light')"><i class="fas fa-sun"></i> Light</button>
                        </div>
                    </div>
                </div>

                <!-- Right Panel -->
                <div class="panel-col">

                    <!-- Centers list — same card style as index.php Evacuation Centers -->
                    <div class="card" style="overflow:hidden">
                        <div class="card-header">
                            <h3><i class="fas fa-map-pin"></i> Evacuation Centers</h3>
                            <span class="badge" id="centerCountBadge"><?php echo count($centers); ?> Active</span>
                        </div>

                        <div class="filter-tabs">
                            <button class="ftab active" onclick="filterCards('all',this)">All</button>
                            <button class="ftab" onclick="filterCards('available',this)">Open</button>
                            <button class="ftab" onclick="filterCards('near_capacity',this)">Near</button>
                            <button class="ftab" onclick="filterCards('full',this)">Full</button>
                        </div>

                        <div class="center-list" id="centerList"></div>

                        <div class="view-all-link">
                            <a href="centers.php">View All Centers <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>

                </div>
            </div><!-- /grid-2 -->

        </div><!-- /dashboard -->
    </main>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    /* ── Sidebar toggle — identical to index.php ── */
    const sidebar      = document.getElementById('sidebar');
    const mainContent  = document.getElementById('mainContent');
    const toggleBtn    = document.getElementById('sidebarToggleBtn');
    const mobileToggle = document.getElementById('mobileToggle');

    toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('expanded');
        toggleBtn.classList.toggle('collapsed');
        const icon = toggleBtn.querySelector('i');
        icon.className = sidebar.classList.contains('collapsed') ? 'fas fa-chevron-right' : 'fas fa-chevron-left';
    });
    mobileToggle.addEventListener('click', () => sidebar.classList.toggle('show'));

    /* ── Centers data ── */
    const centers = <?php echo json_encode(array_map(function($c) {
        return [
            'id'                  => (int)$c['id'],
            'name'                => $c['name'],
            'lat'                 => (float)$c['lat'],
            'lng'                 => (float)$c['lng'],
            'barangay'            => $c['barangay_name'],
            'status'              => $c['status'],
            'max_capacity_people' => (int)$c['max_capacity_people'],
            'current_occupancy'   => (int)$c['current_occupancy'],
        ];
    }, $centers)); ?>;

    function statusColor(s) {
        if (s === 'near_capacity') return '#FFC107';
        if (s === 'full')          return '#D32F2F';
        if (s === 'temp_shelter')  return '#3498DB';
        if (s === 'closed')        return '#95A5A6';
        return '#2E7D32';
    }
    function statusLabel(s) {
        const map = { available:'Available', near_capacity:'Near Cap', full:'Full', temp_shelter:'Temp', closed:'Closed' };
        return map[s] || s;
    }
    function statusShort(s) {
        const map = { available:'A', near_capacity:'N', full:'F', temp_shelter:'T', closed:'C' };
        return map[s] || '?';
    }

    /* ── Map — tile layers ── */
    const map = L.map('mainMap', { zoomControl: true });
    const tileLayers = {
        street: L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                    { attribution:'© OpenStreetMap', maxZoom:19 }),
        light:  L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
                    { attribution:'© OpenStreetMap, © CartoDB', subdomains:'abcd', maxZoom:20 })
    };
    tileLayers.street.addTo(map);
    map.setView([15.0828, 120.9417], 13);

    /* Municipal boundary */
    L.polygon([
        [15.1050,120.9100],[15.1100,120.9300],[15.1080,120.9600],
        [15.0950,120.9800],[15.0800,120.9900],[15.0600,120.9850],
        [15.0400,120.9700],[15.0350,120.9400],[15.0450,120.9100],
        [15.0650,120.9000],[15.0850,120.9000],[15.1050,120.9100]
    ], {
        color:'#D32F2F', weight:2, opacity:.4,
        fillColor:'#D32F2F', fillOpacity:.03, dashArray:'6 4'
    }).addTo(map).bindTooltip('San Ildefonso, Bulacan', { sticky: true });

    /* ── Markers — Custom Location Pin + Shelter Icon (Perfectly Centered) ── */
    const markerMap = {};

    if (centers.length > 0) {
        centers.forEach(c => {
            // Determine color based on status
            const pinColor = statusColor(c.status);
            const pct = c.max_capacity_people > 0
                ? Math.min((c.current_occupancy / c.max_capacity_people) * 100, 100) : 0;
            
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

            const sc = c.status.replace(/_/g, '-');

            const popupContent = `
                <div class="mini-modal">
                    <div class="mini-header">
                        <h3 class="mini-title">${c.name}</h3>
                        <span class="mini-status ${sc}">${statusShort(c.status)}</span>
                    </div>
                    <div class="mini-location">
                        <i class="fas fa-map-marker-alt"></i><span>${c.barangay}</span>
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
                        <div class="mini-capacity-header"><span>Fill</span><span>${Math.round(pct)}%</span></div>
                        <div class="mini-capacity-bar">
                            <div class="mini-capacity-fill" style="width:${pct}%;background:${pinColor}"></div>
                        </div>
                    </div>
                    <div class="mini-footer">
                        <a href="centers.php?id=${c.id}" class="mini-btn mini-btn-primary">
                            <i class="fas fa-eye"></i> View
                        </a>
                        <button class="mini-btn mini-btn-secondary" onclick="window.open('https://www.google.com/maps/dir/?api=1&destination=${c.lat},${c.lng}','_blank')">
                            <i class="fas fa-directions"></i>
                        </button>
                    </div>
                </div>`;

            marker.bindPopup(popupContent, { className:'custom-popup', minWidth:200, maxWidth:200 });
            marker.on('click', () => highlightCard(c.id));
            markerMap[c.id] = marker;
        });

        const group = L.featureGroup(Object.values(markerMap));
        map.fitBounds(group.getBounds().pad(.2));
    } else {
        document.getElementById('mainMap').innerHTML =
            '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#95A5A6">No evacuation centers defined yet.</div>';
    }

    /* ── Panel cards ── */
    function renderCards(filter) {
        const list   = document.getElementById('centerList');
        const badge  = document.getElementById('centerCountBadge');
        list.innerHTML = '';
        const filtered = filter === 'all' ? centers : centers.filter(c => c.status === filter);
        badge.textContent = filtered.length + (filter === 'all' ? ' Active' : '');

        if (!filtered.length) {
            list.innerHTML = '<div style="text-align:center;color:#95A5A6;padding:20px;font-size:12px">No centers found.</div>';
            return;
        }
        filtered.forEach(c => {
            const color = statusColor(c.status);
            const pct   = c.max_capacity_people > 0
                ? Math.min(Math.round((c.current_occupancy / c.max_capacity_people) * 100), 100) : 0;

            const div = document.createElement('div');
            div.className = 'center-card';
            div.id = `card-${c.id}`;
            div.innerHTML = `
                <div class="cc-head">
                    <div class="cc-name">${c.name}</div>
                    <span class="cc-status ${c.status}">${statusLabel(c.status)}</span>
                </div>
                <div class="cc-meta">
                    <span><i class="fas fa-map-marker-alt"></i>${c.barangay}</span>
                    <span><i class="fas fa-users"></i>${c.current_occupancy} / ${c.max_capacity_people} persons</span>
                </div>
                <div class="cc-bar-wrap">
                    <div class="cc-bar-hdr"><span>Occupancy</span><span>${pct}%</span></div>
                    <div class="cc-bar"><div class="cc-bar-fill" style="width:${pct}%;background:${color}"></div></div>
                </div>`;

            div.addEventListener('click', () => {
                map.flyTo([c.lat, c.lng], 16, { animate: true, duration: .8 });
                if (markerMap[c.id]) {
                    markerMap[c.id].openPopup();
                }
                highlightCard(c.id);
            });
            list.appendChild(div);
        });
    }

    function highlightCard(id) {
        document.querySelectorAll('.center-card').forEach(el => el.classList.remove('highlighted'));
        const card = document.getElementById(`card-${id}`);
        if (card) { 
            card.classList.add('highlighted'); 
            card.scrollIntoView({ behavior:'smooth', block:'nearest' }); 
        }
    }

    function filterCards(f, btn) {
        document.querySelectorAll('.ftab').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        renderCards(f);
    }

    renderCards('all');

    /* ── Layer switcher ── */
    let currentLayer = tileLayers.street;
    function switchLayer(type) {
        map.removeLayer(currentLayer);
        currentLayer = tileLayers[type];
        currentLayer.addTo(map);
        document.getElementById('layerStreet').classList.toggle('active', type === 'street');
        document.getElementById('layerLight').classList.toggle('active', type === 'light');
    }
</script>
</body>
</html>