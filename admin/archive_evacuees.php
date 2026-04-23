<?php
/**
 * archive_evacuees.php
 * POST-only action. Copies all current evac_registrations into
 * evac_registrations_archive, then deletes the live records.
 * Resets all evacuation center statuses to 'available'.
 */

ob_start();

require_once __DIR__ . '/../pages/session.php';
require_login('admin');

$pdo  = db();
$user = current_user();

function redirect(string $url): void {
    ob_end_clean();
    if (!headers_sent()) {
        header('Location: ' . $url);
    } else {
        echo '<script>window.location.href=' . json_encode($url) . ';</script>';
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('evacuees.php');
}

$label      = trim($_POST['archive_label'] ?? '');
$disasterId = !empty($_POST['disaster_id']) ? (int)$_POST['disaster_id'] : null;
$archivedBy = (int)$user['id'];

if ($label === '') {
    redirect('evacuees.php?error=label_required');
}

$count = (int)$pdo->query("SELECT COUNT(*) FROM evac_registrations")->fetchColumn();
if ($count === 0) {
    redirect('evacuees.php?error=nothing_to_archive');
}

try {
    $pdo->beginTransaction();

    // --- Copy all fields, including contact_number and birthday ---
    $stmt = $pdo->prepare("
        INSERT INTO evac_registrations_archive
            (original_id, center_id, family_head_name, contact_number, birthday, barangay_id,
             adults, children, seniors, pwds, total_members,
             created_by, created_at,
             archive_label, disaster_id, archived_by, archived_at)
        SELECT
            id, center_id, family_head_name, contact_number, birthday, barangay_id,
            adults, children, seniors, pwds, total_members,
            created_by, created_at,
            :label, :disaster_id, :archived_by, NOW()
        FROM evac_registrations
    ");
    $stmt->execute([
        ':label'       => $label,
        ':disaster_id' => $disasterId,
        ':archived_by' => $archivedBy,
    ]);

    $archivedCount = $stmt->rowCount();

    $pdo->exec("DELETE FROM evac_registrations");
    $pdo->exec("UPDATE evacuation_centers SET status = 'available'");

    $pdo->commit();

    redirect('evacuees.php?archived=' . $archivedCount . '&label=' . urlencode($label));

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Archive error: ' . $e->getMessage());
    redirect('evacuees.php?error=archive_failed');
}