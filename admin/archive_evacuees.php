<?php
/**
 * archive_evacuees.php
 * POST-only action. Copies all current evac_registrations into
 * evac_registrations_archive, then deletes the live records.
 * Resets all evacuation center statuses to 'available'.
 *
 * Place at: MDRRMO_CENTRIX/admin/archive_evacuees.php
 */
require_once __DIR__ . '/../pages/session.php';
require_login('admin');

$pdo  = db();
$user = current_user();

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: evacuees.php');
    exit;
}

$label      = trim($_POST['archive_label'] ?? '');
$disasterId = !empty($_POST['disaster_id']) ? (int)$_POST['disaster_id'] : null;
$archivedBy = (int)$user['id'];

if ($label === '') {
    header('Location: evacuees.php?error=label_required');
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Copy all live registrations into the archive table
    $insertSql = "
        INSERT INTO evac_registrations_archive
            (original_id, center_id, family_head_name, barangay_id,
             adults, children, seniors, pwds, total_members,
             created_by, created_at,
             archive_label, disaster_id, archived_by, archived_at)
        SELECT
            id, center_id, family_head_name, barangay_id,
            adults, children, seniors, pwds, total_members,
            created_by, created_at,
            :label, :disaster_id, :archived_by, NOW()
        FROM evac_registrations
    ";
    $stmt = $pdo->prepare($insertSql);
    $stmt->execute([
        ':label'       => $label,
        ':disaster_id' => $disasterId,
        ':archived_by' => $archivedBy,
    ]);

    $archivedCount = $stmt->rowCount();

    // 2. Delete live registrations
    $pdo->exec("DELETE FROM evac_registrations");

    // 3. Reset all evacuation center statuses to 'available'
    $pdo->exec("UPDATE evacuation_centers SET status = 'available'");

    $pdo->commit();

    header('Location: evacuees.php?archived=' . $archivedCount . '&label=' . urlencode($label));
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    // Log and redirect with error
    error_log('Archive error: ' . $e->getMessage());
    header('Location: evacuees.php?error=archive_failed');
    exit;
}