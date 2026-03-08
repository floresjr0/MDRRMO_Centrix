<?php
require_once __DIR__ . '/../pages/session.php';
require_login('admin');

$pdo  = db();
$user = current_user();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$barangays = $pdo->query("SELECT id, name FROM barangays WHERE is_active = 1 ORDER BY name")->fetchAll();
$coordinators = $pdo->query("SELECT id, full_name FROM users WHERE role = 'coordinator' AND is_active = 1 ORDER BY full_name")->fetchAll();

$center = null;
if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM evacuation_centers WHERE id = ?");
    $stmt->execute([$id]);
    $center = $stmt->fetch();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name   = trim($_POST['name'] ?? '');
    $barangayId = (int)($_POST['barangay_id'] ?? 0);
    $address = trim($_POST['address'] ?? '');
    $lat     = trim($_POST['lat'] ?? '');
    $lng     = trim($_POST['lng'] ?? '');
    $maxCap  = (int)($_POST['max_capacity_people'] ?? 0);
    $maxFam  = (int)($_POST['max_capacity_families'] ?? 0);
    $status  = $_POST['status'] ?? 'available';
    $coordId = isset($_POST['coordinator_user_id']) && $_POST['coordinator_user_id'] !== ''
        ? (int)$_POST['coordinator_user_id'] : null;
    $notes   = trim($_POST['notes'] ?? '');

    if ($name === '') {
        $errors[] = 'Name is required.';
    }
    if (!$barangayId) {
        $errors[] = 'Barangay is required.';
    }
    if ($address === '') {
        $errors[] = 'Address is required.';
    }
    if (!is_numeric($lat) || !is_numeric($lng)) {
        $errors[] = 'Valid latitude and longitude are required.';
    }
    if ($maxCap <= 0) {
        $errors[] = 'Max capacity (people) must be greater than zero.';
    }
    if (!in_array($status, ['available','near_capacity','full','temp_shelter','closed'], true)) {
        $errors[] = 'Invalid status.';
    }

    if (!$errors) {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE evacuation_centers
                                   SET name = ?, barangay_id = ?, address = ?, lat = ?, lng = ?,
                                       max_capacity_people = ?, max_capacity_families = ?, status = ?,
                                       coordinator_user_id = ?, notes = ?
                                   WHERE id = ?");
            $stmt->execute([
                $name, $barangayId, $address, $lat, $lng,
                $maxCap, $maxFam, $status,
                $coordId, $notes, $id
            ]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO evacuation_centers
                                   (name, barangay_id, address, lat, lng,
                                    max_capacity_people, max_capacity_families, status,
                                    coordinator_user_id, notes)
                                   VALUES (?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $name, $barangayId, $address, $lat, $lng,
                $maxCap, $maxFam, $status,
                $coordId, $notes
            ]);
            $id = (int)$pdo->lastInsertId();
        }

        header('Location: centers.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $id ? 'Edit' : 'Add' ?> Evacuation Center - MDRRMO Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/index.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        body {
            margin: 0;
            height: 100vh;
            overflow: hidden;
        }
        .dashboard {
            height: calc(100vh - var(--topbar-height, 60px));
            padding: 0;
        }
        .side-by-side {
            display: flex;
            height: 100%;
        }
        .form-panel {
            width: 40%;
            min-width: 420px;
            max-width: 500px;
            padding: 2rem;
            overflow-y: auto;
            background: #ffffff;
            border-right: 1px solid #e0e0e0;
        }
        .map-panel {
            flex: 1;
            position: relative;
        }
        #map {
            position: absolute;
            inset: 0;
        }
        .auth-form label {
            display: block;
            margin-bottom: 1.4rem;
        }
        .auth-form input,
        .auth-form select,
        .auth-form textarea {
            width: 100%;
            padding: 0.8rem;
            margin-top: 0.4rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 1rem;
        }
        .auth-form button {
            margin-top: 1.5rem;
            padding: 0.9rem 1.5rem;
        }
    </style>
</head>
<body>

<header class="topbar">
    <div class="topbar-title"><?= $id ? 'Edit' : 'Add' ?> Evacuation Center</div>
    <div class="topbar-user">
        <?= htmlspecialchars($user['full_name']) ?> (Admin)
        <a href="../pages/logout.php">Logout</a>
    </div>
</header>

<main class="dashboard">
    <div class="side-by-side">
        <!-- LEFT: Form -->
        <div class="form-panel">
            <h2 style="margin-top: 0;"><?= $id ? 'Edit' : 'Add' ?> Evacuation Center</h2>

            <?php if ($errors): ?>
                <div class="auth-errors">
                    <ul>
                        <?php foreach ($errors as $err): ?>
                            <li><?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" class="auth-form">
                <label>
                    Name
                    <input type="text" name="name" required
                           value="<?= htmlspecialchars($_POST['name'] ?? ($center['name'] ?? '')) ?>">
                </label>

                <label>
                    Barangay
                    <select name="barangay_id" required>
                        <option value="">-- Select barangay --</option>
                        <?php
                        $selectedBarangay = $_POST['barangay_id'] ?? ($center['barangay_id'] ?? '');
                        foreach ($barangays as $b): ?>
                            <option value="<?= (int)$b['id'] ?>"
                                <?= (string)$selectedBarangay === (string)$b['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    Address
                    <input type="text" name="address" required
                           value="<?= htmlspecialchars($_POST['address'] ?? ($center['address'] ?? '')) ?>">
                </label>

                <label>
                    Latitude
                    <input type="text" name="lat" required
                           value="<?= htmlspecialchars($_POST['lat'] ?? ($center['lat'] ?? '')) ?>">
                </label>

                <label>
                    Longitude
                    <input type="text" name="lng" required
                           value="<?= htmlspecialchars($_POST['lng'] ?? ($center['lng'] ?? '')) ?>">
                </label>

                <label>
                    Max capacity (people)
                    <input type="number" name="max_capacity_people" min="1" required
                           value="<?= htmlspecialchars($_POST['max_capacity_people'] ?? ($center['max_capacity_people'] ?? '0')) ?>">
                </label>

                <label>
                    Max capacity (families, optional)
                    <input type="number" name="max_capacity_families" min="0"
                           value="<?= htmlspecialchars($_POST['max_capacity_families'] ?? ($center['max_capacity_families'] ?? '0')) ?>">
                </label>

                <label>
                    Status
                    <?php $selectedStatus = $_POST['status'] ?? ($center['status'] ?? 'available'); ?>
                    <select name="status">
                        <?php
                        $statuses = ['available','near_capacity','full','temp_shelter','closed'];
                        foreach ($statuses as $s): ?>
                            <option value="<?= $s ?>" <?= $selectedStatus === $s ? 'selected' : '' ?>>
                                <?= ucfirst(str_replace('_', ' ', $s)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    Coordinator (optional)
                    <?php $selectedCoord = $_POST['coordinator_user_id'] ?? ($center['coordinator_user_id'] ?? ''); ?>
                    <select name="coordinator_user_id">
                        <option value="">-- None --</option>
                        <?php foreach ($coordinators as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"
                                <?= (string)$selectedCoord === (string)$c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    Notes
                    <textarea name="notes" rows="4"><?= htmlspecialchars($_POST['notes'] ?? ($center['notes'] ?? '')) ?></textarea>
                </label>

                <button type="submit">
                    <?= $id ? 'Save Changes' : 'Create Center' ?>
                </button>
            </form>

            <p style="margin-top: 2rem; font-size: 0.95rem;">
                <a href="centers.php">← Back to centers</a>
            </p>
        </div>

        <!-- RIGHT: Map -->
        <div class="map-panel">
            <div id="map"></div>
        </div>
    </div>
</main>

<script>
// Map initialization
const defaultLat = parseFloat('<?= $center['lat'] ?? '15.0828' ?>');
const defaultLng = parseFloat('<?= $center['lng'] ?? '120.9417' ?>');

const map = L.map('map').setView([defaultLat, defaultLng], 15);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
}).addTo(map);

let marker = L.marker([defaultLat, defaultLng], {
    draggable: true
}).addTo(map);

// Update form inputs when marker is dragged
marker.on('dragend', function(e) {
    const pos = marker.getLatLng();
    document.querySelector('input[name="lat"]').value = pos.lat.toFixed(6);
    document.querySelector('input[name="lng"]').value = pos.lng.toFixed(6);
});

// Move marker when clicking on map
map.on('click', function(e) {
    marker.setLatLng(e.latlng);
    document.querySelector('input[name="lat"]').value = e.latlng.lat.toFixed(6);
    document.querySelector('input[name="lng"]').value = e.latlng.lng.toFixed(6);
});

// Optional: Update marker when lat/lng inputs change manually
document.querySelector('input[name="lat"]').addEventListener('change', updateMarkerFromInputs);
document.querySelector('input[name="lng"]').addEventListener('change', updateMarkerFromInputs);

function updateMarkerFromInputs() {
    const lat = parseFloat(document.querySelector('input[name="lat"]').value);
    const lng = parseFloat(document.querySelector('input[name="lng"]').value);
    if (!isNaN(lat) && !isNaN(lng)) {
        marker.setLatLng([lat, lng]);
        map.setView([lat, lng], 15);
    }
}
</script>

</body>
</html>