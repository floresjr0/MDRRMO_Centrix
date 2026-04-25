<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/center_helpers.php';

if (isset($_GET['action']) && $_GET['action'] === 'list_available') {

    header('Content-Type: application/json');

    try {
        $pdo = db();

        // Include 'full' centers too — navigation.php needs them to show
        // capacity info and to auto-skip them during rerouting.
        // Only 'closed' centers are excluded.
        $stmt = $pdo->query("
            SELECT
                c.id,
                c.name,
                c.lat,
                c.lng,
                c.status,
                c.max_capacity_people,
                c.max_capacity_families,
                COALESCE(SUM(r.total_members), 0)  AS current_occupancy,
                c.max_capacity_people
                    - COALESCE(SUM(r.total_members), 0) AS slots_remaining,
                b.name           AS barangay,
                u.full_name      AS coordinator_name,
                u.contact_number AS coordinator_contact
            FROM evacuation_centers c
            JOIN  barangays b ON b.id  = c.barangay_id
            LEFT JOIN users u ON u.id  = c.coordinator_user_id
            LEFT JOIN evac_registrations r ON r.center_id = c.id
            WHERE c.status != 'closed'
            GROUP BY c.id
            ORDER BY c.name
        ");

        $centers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Cast numeric fields so JS gets numbers, not strings
        foreach ($centers as &$row) {
            $row['lat']                  = (float) $row['lat'];
            $row['lng']                  = (float) $row['lng'];
            $row['max_capacity_people']  = (int)   $row['max_capacity_people'];
            $row['max_capacity_families']= (int)   $row['max_capacity_families'];
            $row['current_occupancy']    = (int)   $row['current_occupancy'];
            $row['slots_remaining']      = (int)   $row['slots_remaining'];
        }
        unset($row);

        echo json_encode([
            'ok'      => true,
            'centers' => $centers,
        ]);
        exit;

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'ok'    => false,
            'error' => $e->getMessage(),
        ]);
        exit;
    }
}
?>

<!-- pogi si marte -->