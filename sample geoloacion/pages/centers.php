<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/center_helpers.php';

if (isset($_GET['action']) && $_GET['action'] === 'list_available') {

    header('Content-Type: application/json');

    try {
        $pdo = db();

        $stmt = $pdo->query("
            SELECT 
                c.id,
                c.name,
                c.lat,
                c.lng,
                c.status,
                b.name AS barangay
            FROM evacuation_centers c
            JOIN barangays b ON b.id = c.barangay_id
            WHERE c.status != 'closed'
        ");

        $centers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'ok' => true,
            'centers' => $centers
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode([
            'ok' => false,
            'error' => $e->getMessage()
        ]);
        exit;
    }
}
?>