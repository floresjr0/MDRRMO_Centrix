<?php
/**
 * print_archive.php
 * Printable Archive History Report — MDRRMO San Ildefonso
 *
 * Usage:
 *   print_archive.php              → prints ALL batches
 *   print_archive.php?label=XYZ   → prints ONE specific batch by archive_label
 *   print_archive.php?batch=2026-03-27 → prints batches archived on that date
 *
 * Place this file in the same /admin/ directory as evacuees.php
 */

require_once __DIR__ . '/../pages/session.php';
require_login('admin');

$pdo = db();

// ── Filter: specific label or all ──────────────────────────────────────────
$filterLabel = $_GET['label'] ?? null;
$filterDate  = $_GET['batch'] ?? null;

// ── Fetch batch summaries ──────────────────────────────────────────────────
$batchSql = "
    SELECT
        era.archive_label,
        era.disaster_id,
        era.archived_at,
        era.archived_by,
        d.title       AS disaster_title,
        d.type        AS disaster_type,
        d.level       AS disaster_level,
        u.full_name   AS archived_by_name,
        COUNT(*)              AS total_families,
        SUM(era.total_members)    AS total_evacuees,
        SUM(era.adults)           AS total_adults,
        SUM(era.children)         AS total_children,
        SUM(era.seniors)          AS total_seniors,
        SUM(era.pwds)             AS total_pwds
    FROM evac_registrations_archive era
    LEFT JOIN users u    ON u.id  = era.archived_by
    LEFT JOIN disasters d ON d.id = era.disaster_id
";

$params = [];
if ($filterLabel) {
    $batchSql .= " WHERE era.archive_label = ?";
    $params[] = $filterLabel;
} elseif ($filterDate) {
    $batchSql .= " WHERE DATE(era.archived_at) = ?";
    $params[] = $filterDate;
}

$batchSql .= " GROUP BY era.archive_label, era.disaster_id, DATE(era.archived_at), era.archived_by ORDER BY era.archived_at DESC";

$batches = $pdo->prepare($batchSql);
$batches->execute($params);
$batches = $batches->fetchAll();

// ── For each batch, fetch granular records ─────────────────────────────────
// Key: archive_label — we'll group records per batch
$batchDetails = [];

foreach ($batches as $batch) {
    $sql = "
        SELECT
            era.original_id,
            era.family_head_name,
            era.adults,
            era.children,
            era.seniors,
            era.pwds,
            era.total_members,
            era.created_at,
            ec.name        AS center_name,
            ec.address     AS center_address,
            b.name         AS barangay_name,
            u.full_name    AS registered_by,
            u.role         AS registered_by_role
        FROM evac_registrations_archive era
        LEFT JOIN evacuation_centers ec ON ec.id  = era.center_id
        LEFT JOIN barangays b           ON b.id   = era.barangay_id
        LEFT JOIN users u               ON u.id   = era.created_by
        WHERE era.archive_label = ?
        ORDER BY ec.name ASC, era.family_head_name ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$batch['archive_label']]);
    $records = $stmt->fetchAll();

    // Group records by evacuation center
    $byCentre = [];
    foreach ($records as $rec) {
        $byCentre[$rec['center_name']][] = $rec;
    }

    // Barangay breakdown for this batch
    $brgySql = "
        SELECT
            b.name AS barangay_name,
            SUM(era.adults)        AS adults,
            SUM(era.children)      AS children,
            SUM(era.seniors)       AS seniors,
            SUM(era.pwds)          AS pwds,
            SUM(era.total_members) AS total_members,
            COUNT(*)               AS families
        FROM evac_registrations_archive era
        LEFT JOIN barangays b ON b.id = era.barangay_id
        WHERE era.archive_label = ?
        GROUP BY b.id
        ORDER BY total_members DESC
    ";
    $brgyStmt = $pdo->prepare($brgySql);
    $brgyStmt->execute([$batch['archive_label']]);

    $batchDetails[$batch['archive_label']] = [
        'records'   => $records,
        'byCentre'  => $byCentre,
        'byBarangay'=> $brgyStmt->fetchAll(),
    ];
}

$printedAt = date('F j, Y \a\t g:i A');
$reportTitle = $filterLabel
    ? 'Archive Report: ' . $filterLabel
    : 'Full Archive History Report';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Print — <?php echo htmlspecialchars($reportTitle); ?> | MDRRMO</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
/* ═══════════════════════════════════════════════
   BASE & PRINT RESET
═══════════════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --red:    #C0392B;
    --red-dk: #922B21;
    --blue:   #1A5276;
    --gray:   #566573;
    --light:  #F8F9FA;
    --border: #D5D8DC;
    --text:   #1C2833;
    --muted:  #7F8C8D;
}

body {
    font-family: 'Segoe UI', Arial, sans-serif;
    font-size: 12px;
    color: var(--text);
    background: #fff;
    padding: 0;
}

/* ─── Screen preview styles ─── */
@media screen {
    body { background: #EAECEE; padding: 20px; }
    .print-page {
        background: #fff;
        max-width: 900px;
        margin: 0 auto 40px;
        padding: 36px 40px;
        box-shadow: 0 2px 20px rgba(0,0,0,.12);
        border-radius: 4px;
    }
    .print-controls {
        max-width: 900px;
        margin: 0 auto 16px;
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
    }
    .btn {
        padding: 9px 20px;
        border: none;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
    }
    .btn-print  { background: var(--red);  color: #fff; }
    .btn-back   { background: #fff; color: var(--text); border: 1.5px solid var(--border); }
    .btn-print:hover { background: var(--red-dk); }
    .btn-back:hover  { background: var(--light); }
}

/* ─── Print layout ─── */
@media print {
    body { padding: 0; font-size: 11px; }
    .print-controls { display: none !important; }
    .print-page { padding: 18mm 20mm; page-break-after: always; }
    .print-page:last-child { page-break-after: avoid; }
    .no-break { page-break-inside: avoid; }
    thead { display: table-header-group; }
    @page { margin: 10mm; size: A4 portrait; }
}

/* ═══════════════════════════════════════════════
   HEADER
═══════════════════════════════════════════════ */
.report-header {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    padding-bottom: 16px;
    border-bottom: 3px solid var(--red);
    margin-bottom: 20px;
}
.header-logo-box {
    width: 54px; height: 54px;
    background: var(--red);
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.header-logo-box span { color: #fff; font-size: 22px; font-weight: 700; }
.header-org h1  { font-size: 17px; font-weight: 700; color: var(--red); line-height: 1.2; }
.header-org p   { font-size: 11px; color: var(--gray); margin-top: 2px; }
.header-right   { margin-left: auto; text-align: right; }
.header-right .report-title { font-size: 14px; font-weight: 700; color: var(--blue); }
.header-right .report-meta  { font-size: 10.5px; color: var(--muted); margin-top: 3px; }

/* ═══════════════════════════════════════════════
   BATCH HEADER CARD
═══════════════════════════════════════════════ */
.batch-header {
    background: #FDEDEC;
    border-left: 4px solid var(--red);
    border-radius: 0 6px 6px 0;
    padding: 12px 16px;
    margin-bottom: 16px;
}
.batch-title { font-size: 14px; font-weight: 700; color: var(--red-dk); }
.batch-meta  { font-size: 11px; color: var(--gray); margin-top: 4px; }
.batch-meta span { margin-right: 14px; }
.batch-meta i { margin-right: 4px; }

/* ═══════════════════════════════════════════════
   SUMMARY MATRIX
═══════════════════════════════════════════════ */
.section-title {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .7px;
    color: var(--blue);
    margin-bottom: 8px;
    padding-bottom: 4px;
    border-bottom: 1px solid var(--border);
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 8px;
    margin-bottom: 20px;
}
.summary-card {
    background: var(--light);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 10px 8px;
    text-align: center;
}
.summary-card .val {
    font-size: 20px;
    font-weight: 700;
    color: var(--red);
    line-height: 1;
}
.summary-card .lbl {
    font-size: 10px;
    color: var(--muted);
    margin-top: 4px;
    text-transform: uppercase;
    letter-spacing: .5px;
}

/* ═══════════════════════════════════════════════
   DEMOGRAPHIC MATRIX TABLE
═══════════════════════════════════════════════ */
.matrix-wrap { margin-bottom: 22px; overflow-x: auto; }

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11.5px;
}
table th {
    background: var(--blue);
    color: #fff;
    padding: 7px 9px;
    text-align: left;
    font-weight: 600;
    font-size: 10.5px;
    white-space: nowrap;
}
table th.center { text-align: center; }
table td {
    padding: 6px 9px;
    border-bottom: 1px solid var(--border);
    vertical-align: top;
}
table tr:nth-child(even) td { background: #F8F9FA; }
table tr:last-child td { border-bottom: none; }
table tfoot td {
    background: #EBF5FB;
    font-weight: 700;
    color: var(--blue);
    border-top: 2px solid var(--blue);
    font-size: 11px;
}
td.num { text-align: right; font-variant-numeric: tabular-nums; }
td.center-col { font-weight: 600; color: var(--text); }
td.center-col small { display: block; font-weight: 400; color: var(--muted); font-size: 10px; }

/* Demographic chips inside table */
.chip {
    display: inline-block;
    padding: 1px 7px;
    border-radius: 20px;
    font-size: 10.5px;
    font-weight: 600;
    margin: 0 1px;
}
.chip-c  { background: #D6EAF8; color: #1A5276; }
.chip-a  { background: #D5F5E3; color: #1E8449; }
.chip-s  { background: #EDE7F6; color: #6A1B9A; }
.chip-p  { background: #FEF9E7; color: #B7950B; }
.chip-t  { background: #FDEDEC; color: #C0392B; }

/* ═══════════════════════════════════════════════
   CENTER SECTION
═══════════════════════════════════════════════ */
.centre-block { margin-bottom: 24px; }
.centre-heading {
    background: #EBF5FB;
    border-left: 3px solid var(--blue);
    padding: 7px 12px;
    font-size: 12px;
    font-weight: 700;
    color: var(--blue);
    border-radius: 0 4px 4px 0;
    margin-bottom: 8px;
}
.centre-heading small { font-weight: 400; color: var(--gray); font-size: 10.5px; margin-left: 8px; }

/* ═══════════════════════════════════════════════
   BARANGAY TABLE
═══════════════════════════════════════════════ */
.brgy-table th { background: #1E8449; }

/* ═══════════════════════════════════════════════
   FOOTER
═══════════════════════════════════════════════ */
.report-footer {
    margin-top: 28px;
    padding-top: 12px;
    border-top: 1.5px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    font-size: 10.5px;
    color: var(--muted);
}
.sig-line {
    border-top: 1px solid var(--text);
    width: 180px;
    margin-top: 28px;
    padding-top: 4px;
    font-size: 10.5px;
    text-align: center;
    color: var(--text);
}
</style>
</head>
<body>

<!-- ── Screen Controls ─────────────────────────────────────────────────── -->
<div class="print-controls">
    <a href="evacuees.php" class="btn btn-back">← Back to Evacuees</a>
    <button onclick="window.print()" class="btn btn-print">🖨 Print / Save as PDF</button>
    <?php if (count($batches) > 1): ?>
    <span style="font-size:12px;color:#7F8C8D">Showing <strong><?php echo count($batches); ?></strong> archive batch(es)</span>
    <?php endif; ?>
</div>

<?php if (empty($batches)): ?>
<div class="print-page">
    <p style="color:#7F8C8D;text-align:center;padding:40px 0">No archive records found.</p>
</div>
<?php endif; ?>

<?php foreach ($batches as $bi => $batch):
    $label   = $batch['archive_label'];
    $detail  = $batchDetails[$label];
    $records = $detail['records'];
    $byCentre= $detail['byCentre'];
    $byBrgy  = $detail['byBarangay'];

    $grandChildren = (int)$batch['total_children'];
    $grandAdults   = (int)$batch['total_adults'];
    $grandSeniors  = (int)$batch['total_seniors'];
    $grandPwds     = (int)$batch['total_pwds'];
    $grandTotal    = (int)$batch['total_evacuees'];
    $grandFamilies = (int)$batch['total_families'];
?>

<!-- ════════════════════════════════════════════
     PAGE: BATCH OVERVIEW + BARANGAY MATRIX
════════════════════════════════════════════ -->
<div class="print-page">

    <!-- Header -->
    <div class="report-header no-break">
        <div class="header-logo-box"><span>M</span></div>
        <div class="header-org">
            <h1>MDRRMO — Municipality of San Ildefonso</h1>
            <p>Municipal Disaster Risk Reduction and Management Office · San Ildefonso, Bulacan</p>
        </div>
        <div class="header-right">
            <div class="report-title">Evacuee Archive Report</div>
            <div class="report-meta">Printed: <?php echo $printedAt; ?></div>
        </div>
    </div>

    <!-- Batch identification -->
    <div class="batch-header no-break">
        <div class="batch-title"><?php echo htmlspecialchars($label); ?></div>
        <div class="batch-meta">
            <span>📅 Archived: <?php echo date('F j, Y g:i A', strtotime($batch['archived_at'])); ?></span>
            <span>👤 By: <?php echo htmlspecialchars($batch['archived_by_name'] ?? 'Admin'); ?></span>
            <?php if ($batch['disaster_title']): ?>
            <span>⚠ Disaster: <?php echo htmlspecialchars(ucfirst($batch['disaster_type']) . ' – ' . $batch['disaster_title'] . ' (Level ' . $batch['disaster_level'] . ')'); ?></span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Summary Totals -->
    <div class="section-title">Summary Totals</div>
    <div class="summary-grid no-break">
        <div class="summary-card">
            <div class="val"><?php echo number_format($grandTotal); ?></div>
            <div class="lbl">Total Evacuees</div>
        </div>
        <div class="summary-card">
            <div class="val"><?php echo number_format($grandFamilies); ?></div>
            <div class="lbl">Families</div>
        </div>
        <div class="summary-card">
            <div class="val"><?php echo number_format($grandAdults); ?></div>
            <div class="lbl">Adults</div>
        </div>
        <div class="summary-card">
            <div class="val"><?php echo number_format($grandChildren); ?></div>
            <div class="lbl">Children</div>
        </div>
        <div class="summary-card">
            <div class="val"><?php echo number_format($grandSeniors); ?></div>
            <div class="lbl">Seniors</div>
        </div>
        <div class="summary-card">
            <div class="val"><?php echo number_format($grandPwds); ?></div>
            <div class="lbl">PWDs</div>
        </div>
    </div>

    <!-- Evacuation Centers Matrix -->
    <div class="section-title">Demographic Matrix by Evacuation Center</div>
    <div class="matrix-wrap no-break">
        <table>
            <thead>
                <tr>
                    <th style="width:24%">Evacuation Center</th>
                    <th class="center">Families</th>
                    <th class="center">Children</th>
                    <th class="center">Adults</th>
                    <th class="center">Seniors</th>
                    <th class="center">PWDs</th>
                    <th class="center">Total</th>
                    <th class="center">% Share</th>
                </tr>
            </thead>
            <tbody>
            <?php
            // Aggregate per center from records
            $centreStats = [];
            foreach ($records as $rec) {
                $cn = $rec['center_name'] ?? 'Unknown Center';
                if (!isset($centreStats[$cn])) {
                    $centreStats[$cn] = ['families'=>0,'adults'=>0,'children'=>0,'seniors'=>0,'pwds'=>0,'total'=>0,'address'=>$rec['center_address']??''];
                }
                $centreStats[$cn]['families']++;
                $centreStats[$cn]['adults']   += $rec['adults'];
                $centreStats[$cn]['children'] += $rec['children'];
                $centreStats[$cn]['seniors']  += $rec['seniors'];
                $centreStats[$cn]['pwds']     += $rec['pwds'];
                $centreStats[$cn]['total']    += $rec['total_members'];
            }
            foreach ($centreStats as $cname => $cs):
                $pct = $grandTotal > 0 ? round(($cs['total'] / $grandTotal) * 100, 1) : 0;
            ?>
            <tr>
                <td class="center-col">
                    <?php echo htmlspecialchars($cname); ?>
                    <?php if ($cs['address']): ?>
                    <small><?php echo htmlspecialchars($cs['address']); ?></small>
                    <?php endif; ?>
                </td>
                <td class="num"><?php echo number_format($cs['families']); ?></td>
                <td class="num"><span class="chip chip-c"><?php echo number_format($cs['children']); ?></span></td>
                <td class="num"><span class="chip chip-a"><?php echo number_format($cs['adults']); ?></span></td>
                <td class="num"><span class="chip chip-s"><?php echo number_format($cs['seniors']); ?></span></td>
                <td class="num"><span class="chip chip-p"><?php echo number_format($cs['pwds']); ?></span></td>
                <td class="num"><span class="chip chip-t"><?php echo number_format($cs['total']); ?></span></td>
                <td class="num"><?php echo $pct; ?>%</td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td>TOTAL</td>
                    <td class="num"><?php echo number_format($grandFamilies); ?></td>
                    <td class="num"><?php echo number_format($grandChildren); ?></td>
                    <td class="num"><?php echo number_format($grandAdults); ?></td>
                    <td class="num"><?php echo number_format($grandSeniors); ?></td>
                    <td class="num"><?php echo number_format($grandPwds); ?></td>
                    <td class="num"><?php echo number_format($grandTotal); ?></td>
                    <td class="num">100%</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Barangay of Origin Matrix -->
    <?php if (!empty($byBrgy)): ?>
    <div class="section-title">Barangay of Origin Breakdown</div>
    <div class="matrix-wrap no-break">
        <table class="brgy-table">
            <thead>
                <tr>
                    <th style="width:26%">Barangay</th>
                    <th class="center">Families</th>
                    <th class="center">Children</th>
                    <th class="center">Adults</th>
                    <th class="center">Seniors</th>
                    <th class="center">PWDs</th>
                    <th class="center">Total</th>
                    <th class="center">% Share</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($byBrgy as $br):
                $pct = $grandTotal > 0 ? round(($br['total_members'] / $grandTotal) * 100, 1) : 0;
            ?>
            <tr>
                <td class="center-col"><?php echo htmlspecialchars($br['barangay_name']); ?></td>
                <td class="num"><?php echo number_format($br['families']); ?></td>
                <td class="num"><span class="chip chip-c"><?php echo number_format($br['children']); ?></span></td>
                <td class="num"><span class="chip chip-a"><?php echo number_format($br['adults']); ?></span></td>
                <td class="num"><span class="chip chip-s"><?php echo number_format($br['seniors']); ?></span></td>
                <td class="num"><span class="chip chip-p"><?php echo number_format($br['pwds']); ?></span></td>
                <td class="num"><span class="chip chip-t"><?php echo number_format($br['total_members']); ?></span></td>
                <td class="num"><?php echo $pct; ?>%</td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td>TOTAL</td>
                    <td class="num"><?php echo number_format($grandFamilies); ?></td>
                    <td class="num"><?php echo number_format($grandChildren); ?></td>
                    <td class="num"><?php echo number_format($grandAdults); ?></td>
                    <td class="num"><?php echo number_format($grandSeniors); ?></td>
                    <td class="num"><?php echo number_format($grandPwds); ?></td>
                    <td class="num"><?php echo number_format($grandTotal); ?></td>
                    <td class="num">100%</td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>

    <!-- Page footer for overview page -->
    <div class="report-footer">
        <div>
            <strong>MDRRMO San Ildefonso, Bulacan</strong><br>
            This document is an official record of disaster evacuation data.
        </div>
        <div style="text-align:right">
            Page 1 — <?php echo htmlspecialchars($label); ?><br>
            Printed <?php echo $printedAt; ?>
        </div>
    </div>

</div><!-- /overview page -->


<!-- ════════════════════════════════════════════
     PAGES: PER-CENTER FAMILY ROSTER
════════════════════════════════════════════ -->
<?php foreach ($byCentre as $centreName => $centreRecs): ?>
<div class="print-page">

    <!-- Mini header (compact for sub-pages) -->
    <div class="report-header no-break" style="padding-bottom:10px;margin-bottom:14px">
        <div class="header-logo-box" style="width:36px;height:36px"><span style="font-size:15px">M</span></div>
        <div class="header-org">
            <h1 style="font-size:13px">MDRRMO — San Ildefonso, Bulacan</h1>
            <p><?php echo htmlspecialchars($label); ?></p>
        </div>
        <div class="header-right">
            <div class="report-title" style="font-size:12px">Family Roster</div>
            <div class="report-meta">Printed: <?php echo $printedAt; ?></div>
        </div>
    </div>

    <!-- Center heading -->
    <div class="centre-heading no-break">
        <?php echo htmlspecialchars($centreName); ?>
        <small>
            <?php
            $ct = $centreRecs[0]['center_address'] ?? '';
            echo $ct ? htmlspecialchars($ct) . ' · ' : '';
            echo count($centreRecs); ?> famil<?php echo count($centreRecs)===1?'y':'ies';?>
            &nbsp;·&nbsp;
            <?php echo number_format(array_sum(array_column($centreRecs,'total_members'))); ?> evacuees
        </small>
    </div>

    <!-- Per-center mini totals -->
    <?php
    $cFam  = count($centreRecs);
    $cAdlt = array_sum(array_column($centreRecs,'adults'));
    $cChld = array_sum(array_column($centreRecs,'children'));
    $cSnr  = array_sum(array_column($centreRecs,'seniors'));
    $cPwd  = array_sum(array_column($centreRecs,'pwds'));
    $cTot  = array_sum(array_column($centreRecs,'total_members'));
    ?>
    <div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap" class="no-break">
        <span style="background:#FDEDEC;color:#C0392B;padding:4px 11px;border-radius:20px;font-size:11px;font-weight:700"><?php echo number_format($cTot); ?> total</span>
        <span style="background:#D6EAF8;color:#1A5276;padding:4px 11px;border-radius:20px;font-size:11px"><?php echo number_format($cChld); ?> children</span>
        <span style="background:#D5F5E3;color:#1E8449;padding:4px 11px;border-radius:20px;font-size:11px"><?php echo number_format($cAdlt); ?> adults</span>
        <span style="background:#EDE7F6;color:#6A1B9A;padding:4px 11px;border-radius:20px;font-size:11px"><?php echo number_format($cSnr); ?> seniors</span>
        <span style="background:#FEF9E7;color:#B7950B;padding:4px 11px;border-radius:20px;font-size:11px"><?php echo number_format($cPwd); ?> PWDs</span>
    </div>

    <!-- Family Roster Table -->
    <div class="matrix-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:28px">#</th>
                    <th style="width:22%">Family Head</th>
                    <th>Barangay of Origin</th>
                    <th class="center" style="width:38px">C</th>
                    <th class="center" style="width:38px">A</th>
                    <th class="center" style="width:38px">S</th>
                    <th class="center" style="width:38px">P</th>
                    <th class="center" style="width:46px">Total</th>
                    <th>Registered By</th>
                    <th style="white-space:nowrap">Date Registered</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($centreRecs as $ri => $rec): ?>
            <tr>
                <td style="color:var(--muted);font-size:10px"><?php echo $ri+1; ?></td>
                <td style="font-weight:600"><?php echo htmlspecialchars($rec['family_head_name']); ?></td>
                <td><?php echo htmlspecialchars($rec['barangay_name']); ?></td>
                <td class="num"><span class="chip chip-c"><?php echo $rec['children']; ?></span></td>
                <td class="num"><span class="chip chip-a"><?php echo $rec['adults']; ?></span></td>
                <td class="num"><span class="chip chip-s"><?php echo $rec['seniors']; ?></span></td>
                <td class="num"><span class="chip chip-p"><?php echo $rec['pwds']; ?></span></td>
                <td class="num"><span class="chip chip-t"><?php echo $rec['total_members']; ?></span></td>
                <td style="font-size:10.5px;color:var(--muted)"><?php echo htmlspecialchars($rec['registered_by'] ?? '—'); ?></td>
                <td style="font-size:10.5px;white-space:nowrap">
                    <?php echo date('M j, Y', strtotime($rec['created_at'])); ?><br>
                    <span style="color:var(--muted)"><?php echo date('g:i A', strtotime($rec['created_at'])); ?></span>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" style="font-size:10.5px;text-transform:uppercase;letter-spacing:.5px">Center Totals</td>
                    <td class="num"><?php echo $cChld; ?></td>
                    <td class="num"><?php echo $cAdlt; ?></td>
                    <td class="num"><?php echo $cSnr; ?></td>
                    <td class="num"><?php echo $cPwd; ?></td>
                    <td class="num"><?php echo $cTot; ?></td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Page footer -->
    <div class="report-footer">
        <div>
            <strong>MDRRMO San Ildefonso, Bulacan</strong> — Confidential evacuation records.
        </div>
        <div style="text-align:right">
            Center: <?php echo htmlspecialchars($centreName); ?><br>
            Printed <?php echo $printedAt; ?>
        </div>
    </div>

</div><!-- /center roster page -->
<?php endforeach; /* byCentre */ ?>


<!-- ════════════════════════════════════════════
     LAST PAGE: SIGNATURE / CERTIFICATION BLOCK
════════════════════════════════════════════ -->
<div class="print-page">

    <div class="report-header no-break" style="padding-bottom:10px;margin-bottom:24px">
        <div class="header-logo-box" style="width:36px;height:36px"><span style="font-size:15px">M</span></div>
        <div class="header-org">
            <h1 style="font-size:13px">MDRRMO — San Ildefonso, Bulacan</h1>
            <p>Certification Page · <?php echo htmlspecialchars($label); ?></p>
        </div>
    </div>

    <div class="section-title">Final Summary — <?php echo htmlspecialchars($label); ?></div>
    <div class="matrix-wrap no-break" style="margin-bottom:30px">
        <table>
            <thead>
                <tr>
                    <th>Demographic Category</th>
                    <th class="center">Count</th>
                    <th class="center">% of Total</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Children (0–17 years old)</td>
                    <td class="num"><?php echo number_format($grandChildren); ?></td>
                    <td class="num"><?php echo $grandTotal>0?round(($grandChildren/$grandTotal)*100,1):0; ?>%</td>
                    <td style="font-size:10.5px;color:var(--muted)">Prioritize in medical, nutrition, psychosocial support</td>
                </tr>
                <tr>
                    <td>Adults (18–59 years old)</td>
                    <td class="num"><?php echo number_format($grandAdults); ?></td>
                    <td class="num"><?php echo $grandTotal>0?round(($grandAdults/$grandTotal)*100,1):0; ?>%</td>
                    <td style="font-size:10.5px;color:var(--muted)">Potential volunteers / workforce for response</td>
                </tr>
                <tr>
                    <td>Senior Citizens (60 years and above)</td>
                    <td class="num"><?php echo number_format($grandSeniors); ?></td>
                    <td class="num"><?php echo $grandTotal>0?round(($grandSeniors/$grandTotal)*100,1):0; ?>%</td>
                    <td style="font-size:10.5px;color:var(--muted)">Prioritize medical monitoring and mobility support</td>
                </tr>
                <tr>
                    <td>Persons with Disabilities (PWDs)</td>
                    <td class="num"><?php echo number_format($grandPwds); ?></td>
                    <td class="num"><?php echo $grandTotal>0?round(($grandPwds/$grandTotal)*100,1):0; ?>%</td>
                    <td style="font-size:10.5px;color:var(--muted)">Ensure accessible facilities and dedicated assistance</td>
                </tr>
                <tr>
                    <td><strong>Total Evacuees</strong></td>
                    <td class="num"><strong><?php echo number_format($grandTotal); ?></strong></td>
                    <td class="num"><strong>100%</strong></td>
                    <td style="font-size:10.5px;color:var(--muted)"><?php echo number_format($grandFamilies); ?> families across <?php echo count($byCentre); ?> center(s)</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Certification statement -->
    <div style="border:1px solid var(--border);border-radius:6px;padding:16px 20px;margin-bottom:32px;background:#FDFEFE" class="no-break">
        <p style="font-size:11px;line-height:1.8;color:var(--text)">
            I hereby certify that the information contained in this report is true and accurate to the best of my knowledge,
            representing the official evacuee records of the <strong>Municipal Disaster Risk Reduction and Management Office
            of San Ildefonso, Bulacan</strong> for the event labeled
            "<strong><?php echo htmlspecialchars($label); ?></strong>",
            archived on <strong><?php echo date('F j, Y', strtotime($batch['archived_at'])); ?></strong>.
        </p>
    </div>

    <!-- Signature blocks -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:24px;margin-top:10px" class="no-break">
        <div style="text-align:center">
            <div class="sig-line">Prepared by</div>
            <div style="font-size:10px;color:var(--muted);margin-top:4px">MDRRMO Staff / Coordinator</div>
        </div>
        <div style="text-align:center">
            <div class="sig-line">Reviewed by</div>
            <div style="font-size:10px;color:var(--muted);margin-top:4px">MDRRMO Officer-in-Charge</div>
        </div>
        <div style="text-align:center">
            <div class="sig-line">Approved by</div>
            <div style="font-size:10px;color:var(--muted);margin-top:4px">MDRRMO Head / LGU Official</div>
        </div>
    </div>

    <div class="report-footer" style="margin-top:40px">
        <div>
            <strong>MDRRMO San Ildefonso, Bulacan</strong><br>
            This document is an official government record. Handle with appropriate confidentiality.
        </div>
        <div style="text-align:right">
            Archive: <?php echo htmlspecialchars($label); ?><br>
            Printed <?php echo $printedAt; ?>
        </div>
    </div>

</div><!-- /certification page -->

<?php endforeach; /* batches */ ?>

</body>
</html>