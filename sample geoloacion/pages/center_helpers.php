<?php
require_once __DIR__ . '/db.php';

/**
 * Returns centers with barangay name and current occupancy (sum of total_members).
 *
 * @return array<int,array<string,mixed>>
 */
function get_centers_with_occupancy(): array
{
    $pdo = db();
    $sql = "SELECT c.*, b.name AS barangay_name,
                   COALESCE(SUM(r.total_members), 0) AS current_occupancy
            FROM evacuation_centers c
            JOIN barangays b ON b.id = c.barangay_id
            LEFT JOIN evac_registrations r ON r.center_id = c.id
            GROUP BY c.id
            ORDER BY c.name";
    return $pdo->query($sql)->fetchAll();
}

/**
 * Computes occupancy for one center.
 *
 * @return array{current:int,max:int,percent:float}
 */
function get_center_occupancy(int $centerId): array
{
    $pdo = db();
    $stmt = $pdo->prepare("SELECT c.max_capacity_people,
                                  COALESCE(SUM(r.total_members), 0) AS current_occupancy
                           FROM evacuation_centers c
                           LEFT JOIN evac_registrations r ON r.center_id = c.id
                           WHERE c.id = ?
                           GROUP BY c.id");
    $stmt->execute([$centerId]);
    $row = $stmt->fetch();
    if (!$row) {
        return ['current' => 0, 'max' => 0, 'percent' => 0.0];
    }
    $max = (int)$row['max_capacity_people'];
    $current = (int)$row['current_occupancy'];
    $percent = $max > 0 ? min(100.0, ($current / $max) * 100.0) : 0.0;
    return ['current' => $current, 'max' => $max, 'percent' => $percent];
}

/**
 * Updates center.status based on occupancy thresholds.
 * - full         : >= 100% of max_capacity_people
 * - near_capacity: >= 80% and < 100%
 * - available    : otherwise (if not already temp_shelter/closed)
 */
function refresh_center_status(int $centerId): void
{
    $pdo = db();
    $occ = get_center_occupancy($centerId);

    // Get current status
    $stmt = $pdo->prepare("SELECT status FROM evacuation_centers WHERE id = ?");
    $stmt->execute([$centerId]);
    $row = $stmt->fetch();
    if (!$row) {
        return;
    }
    $currentStatus = $row['status'];

    // Do not auto-change closed or temp_shelter centers.
    if (in_array($currentStatus, ['closed', 'temp_shelter'], true)) {
        return;
    }

    $newStatus = $currentStatus;
    if ($occ['max'] <= 0) {
        $newStatus = 'available';
    } elseif ($occ['percent'] >= 100.0) {
        $newStatus = 'full';
    } elseif ($occ['percent'] >= 80.0) {
        $newStatus = 'near_capacity';
    } else {
        $newStatus = 'available';
    }

    if ($newStatus !== $currentStatus) {
        $upd = $pdo->prepare("UPDATE evacuation_centers SET status = ? WHERE id = ?");
        $upd->execute([$newStatus, $centerId]);
    }
}

