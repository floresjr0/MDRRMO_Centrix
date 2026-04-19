<?php
require_once __DIR__ . '/session.php';
require_login();
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>MDRRMO Navigation</title>
<link rel="stylesheet" href="../asset/css/usernavigation.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.css"/>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* ── Capacity bar inside center cards ─────────────────────────── */
.cap-bar-wrap {
  margin-top: 6px;
}
.cap-bar-track {
  width: 100%;
  height: 5px;
  background: rgba(0,0,0,0.10);
  border-radius: 99px;
  overflow: hidden;
}
.cap-bar-fill {
  height: 100%;
  border-radius: 99px;
  transition: width .4s ease;
}
.cap-bar-fill.fill-ok     { background: #18a850; }
.cap-bar-fill.fill-near   { background: #b07800; }
.cap-bar-fill.fill-full   { background: #d01030; }

.cap-label {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 3px;
  font-size: 10.5px;
  color: var(--text-muted, #7a7068);
  line-height: 1.2;
}
.cap-label .slots {
  font-weight: 600;
  font-size: 11px;
}
.cap-label .slots.ok   { color: #18a850; }
.cap-label .slots.near { color: #b07800; }
.cap-label .slots.full { color: #d01030; }

/* Status badge row */
.center-badges {
  display: flex;
  align-items: center;
  gap: 5px;
  flex-wrap: wrap;
  margin-top: 4px;
}
.cbadge {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  padding: 2px 7px;
  border-radius: 99px;
  font-size: 10px;
  font-weight: 600;
  letter-spacing: .2px;
  white-space: nowrap;
}
.cbadge-available   { background: #d4edda; color: #1a5e2a; }
.cbadge-near        { background: #fff3cd; color: #7a5000; }
.cbadge-full        { background: #fde8e8; color: #a00; }
.cbadge-temp        { background: #d6eaf8; color: #154360; }

/* Full-center dimming */
.center-item.is-full {
  opacity: 0.6;
  cursor: not-allowed;
}
.center-item.is-full .center-name {
  text-decoration: line-through;
  text-decoration-color: #d01030;
}

/* ── Reroute toast ─────────────────────────────────────────────── */
#rerouteToast {
  position: fixed;
  top: 70px;
  left: 50%;
  transform: translateX(-50%) translateY(-12px);
  background: #1A1A2E;
  color: #fff;
  padding: 10px 18px;
  border-radius: 24px;
  font-size: 13px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 8px;
  opacity: 0;
  pointer-events: none;
  transition: opacity .3s, transform .3s;
  z-index: 9999;
  max-width: 88vw;
  text-align: center;
  box-shadow: 0 4px 20px rgba(0,0,0,.35);
}
#rerouteToast.show {
  opacity: 1;
  transform: translateX(-50%) translateY(0);
}
#rerouteToast .toast-icon {
  font-size: 16px;
  flex-shrink: 0;
}
</style>
</head>

<body>
<div id="app">

  <!-- MAP -->
  <div id="map"></div>

  <!-- TOP DIRECTION CARD -->
  <div id="dirCard">
    <div id="turnArrowBox">
      <svg id="turnArrowSvg" width="34" height="34" viewBox="0 0 24 24">
        <path d="M12 3v15M12 3L7 8M12 3L17 8" stroke="white" stroke-width="2.5" stroke-linecap="round" fill="none"/>
      </svg>
    </div>
    <div class="dir-info">
      <div id="turnInstruction">Head toward destination</div>
      <div id="stepDist">Calculating…</div>
    </div>
    <div id="etaBadge">
      <div id="etaMin">--</div>
      <div id="etaLabel">min</div>
    </div>
  </div>

  <!-- OFF-ROUTE BANNER -->
  <div id="offrouteBanner">
    <svg class="offroute-icon" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M8 2L15 14H1L8 2Z" stroke="#fff" stroke-width="1.5" stroke-linejoin="round" fill="none"/>
      <line x1="8" y1="7" x2="8" y2="10" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/>
      <circle cx="8" cy="12.5" r="0.75" fill="#fff"/>
    </svg>
    <div>
      <div class="offroute-text">Off Route!</div>
      <div class="offroute-sub">Recalculating…</div>
    </div>
  </div>

  <!-- REROUTE TOAST (center became full) -->
  <div id="rerouteToast">
    <span class="toast-icon">🔄</span>
    <span id="rerouteToastMsg">Rerouting to next available center…</span>
  </div>

  <!-- BACK TO DASHBOARD -->
  <a id="backBtn" href="citizen_dashboard.php" title="Back to Dashboard">
    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M10 3L5 8L10 13" stroke="#d45f10" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
  </a>

  <!-- COMPASS -->
  <div id="compassWrap">
    <div id="compassRing" onclick="recenter()">
      <span id="compassNeedle">🧭</span>
    </div>
    <div id="compassLabel">N</div>
  </div>

  <!-- SPEED BUBBLE -->
  <div id="speedBubble">
    <div id="speedVal">0</div>
    <div id="speedUnit">km/h</div>
  </div>

  <!-- RECENTER -->
  <button id="recenterBtn" onclick="recenter()" title="Recenter map">
    <svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
      <circle cx="11" cy="11" r="4" stroke="#d45f10" stroke-width="1.8"/>
      <line x1="11" y1="1" x2="11" y2="5"  stroke="#d45f10" stroke-width="1.8" stroke-linecap="round"/>
      <line x1="11" y1="17" x2="11" y2="21" stroke="#d45f10" stroke-width="1.8" stroke-linecap="round"/>
      <line x1="1"  y1="11" x2="5"  y2="11" stroke="#d45f10" stroke-width="1.8" stroke-linecap="round"/>
      <line x1="17" y1="11" x2="21" y2="11" stroke="#d45f10" stroke-width="1.8" stroke-linecap="round"/>
    </svg>
  </button>

  <!-- SIDE TOGGLE BUTTON -->
  <button id="panelToggleBtn" onclick="togglePanel()" title="Toggle panel">
    <svg id="toggleArrow" viewBox="0 0 16 16">
      <polyline points="3,5 8,11 13,5"/>
    </svg>
  </button>

  <!-- BOTTOM PANEL -->
  <div id="bottomPanel">
    <div class="bottom-handle-wrap" id="panelHandle">
      <div class="bottom-handle"></div>
      <div class="handle-hint" id="handleHint">drag to hide</div>
    </div>

    <div id="destName" style="display:flex;align-items:center;gap:6px;">
      <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg" style="flex-shrink:0;">
        <path d="M7 1C4.79 1 3 2.79 3 5c0 3.25 4 8 4 8s4-4.75 4-8c0-2.21-1.79-4-4-4Z" fill="#d45f10"/>
        <circle cx="7" cy="5" r="1.5" fill="#fff"/>
      </svg>
      <span>Select an evacuation center</span>
    </div>
    <div id="remainDist">We will suggest the nearest available center.</div>

    <div class="mode-label">Evacuation Centers (nearest first)</div>
    <div id="centerList">Requesting your location…</div>

    <div class="mode-label">Travel Mode</div>
    <div id="modeSelector">

      <button class="mode-btn active" data-mode="walk" onclick="selectMode('walk')">
        <div class="mode-icon">
          <svg viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="12" cy="3.5" r="1.8" fill="#d45f10"/>
            <line x1="12" y1="5.3" x2="10.5" y2="10.5" stroke="#d45f10" stroke-width="1.6" stroke-linecap="round"/>
            <line x1="11.2" y1="7" x2="14.5" y2="9.5" stroke="#d45f10" stroke-width="1.5" stroke-linecap="round"/>
            <line x1="11" y1="7.5" x2="8" y2="9" stroke="#d45f10" stroke-width="1.5" stroke-linecap="round"/>
            <line x1="10.5" y1="10.5" x2="8" y2="15" stroke="#d45f10" stroke-width="1.6" stroke-linecap="round"/>
            <line x1="8" y1="15" x2="6.5" y2="18.5" stroke="#d45f10" stroke-width="1.5" stroke-linecap="round"/>
            <line x1="10.5" y1="10.5" x2="13" y2="15" stroke="#d45f10" stroke-width="1.6" stroke-linecap="round"/>
            <line x1="13" y1="15" x2="14.5" y2="18" stroke="#d45f10" stroke-width="1.5" stroke-linecap="round"/>
          </svg>
        </div>
        <span class="mode-name">Walk</span>
      </button>

      <button class="mode-btn" data-mode="bike" onclick="selectMode('bike')">
        <div class="mode-icon">
          <svg viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="5.5" cy="15.5" r="3.5" stroke="#d45f10" stroke-width="1.6"/>
            <circle cx="16.5" cy="15.5" r="3.5" stroke="#d45f10" stroke-width="1.6"/>
            <path d="M5.5 15.5L9 9H13L16.5 15.5H9" stroke="#d45f10" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
            <circle cx="13" cy="6.5" r="1.5" fill="#d45f10"/>
          </svg>
        </div>
        <span class="mode-name">Bike</span>
      </button>

      <button class="mode-btn" data-mode="moto" onclick="selectMode('moto')">
        <div class="mode-icon">
          <svg viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="4.5" cy="15.5" r="3" stroke="#d45f10" stroke-width="1.5"/>
            <circle cx="17.5" cy="15.5" r="3" stroke="#d45f10" stroke-width="1.5"/>
            <line x1="17.5" y1="12.5" x2="16" y2="8.5" stroke="#d45f10" stroke-width="1.4" stroke-linecap="round"/>
            <line x1="14.5" y1="8" x2="18" y2="8" stroke="#d45f10" stroke-width="1.6" stroke-linecap="round"/>
            <path d="M7 12.5 L10 8 L14.5 8.5 L16 12.5" stroke="#d45f10" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
            <line x1="7" y1="12.5" x2="13" y2="12.5" stroke="#d45f10" stroke-width="2" stroke-linecap="round"/>
            <line x1="7" y1="12.5" x2="4.5" y2="12.5" stroke="#d45f10" stroke-width="1.4" stroke-linecap="round"/>
            <path d="M7 14 Q5 15 3 16.5" stroke="#d45f10" stroke-width="1.2" stroke-linecap="round" fill="none" opacity="0.7"/>
          </svg>
        </div>
        <span class="mode-name">Moto</span>
      </button>

      <button class="mode-btn" data-mode="car" onclick="selectMode('car')">
        <div class="mode-icon">
          <svg viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M4 14H18M4 14V17M18 14V17M4 14L6 9H16L18 14" stroke="#d45f10" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
            <circle cx="6.5" cy="17" r="1.5" stroke="#d45f10" stroke-width="1.4"/>
            <circle cx="15.5" cy="17" r="1.5" stroke="#d45f10" stroke-width="1.4"/>
            <path d="M7.5 9L8.5 6H13.5L14.5 9" stroke="#d45f10" stroke-width="1.4" stroke-linecap="round"/>
          </svg>
        </div>
        <span class="mode-name">Car</span>
      </button>
    </div>

    <div class="mode-stats">
      <div class="stat-chip">
        <div class="stat-val" id="previewDist">--</div>
        <div class="stat-lbl">km</div>
      </div>
      <div class="stat-chip">
        <div class="stat-val" id="previewTime">--</div>
        <div class="stat-lbl">min ETA</div>
      </div>
      <div class="stat-chip">
        <div class="stat-val" id="previewSpeed">--</div>
        <div class="stat-lbl">avg km/h</div>
      </div>
    </div>

    <div class="traffic-legend">
      <div class="tleg"><div class="tleg-dot" style="background:#18a850"></div>Free</div>
      <div class="tleg"><div class="tleg-dot" style="background:#b07800"></div>Slow</div>
      <div class="tleg"><div class="tleg-dot" style="background:#d01030"></div>Jammed</div>
    </div>

    <button id="startBtn" class="nav-btn" onclick="startNavigation()">
      <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M8 2C10 2 13 3.5 13 8C13 11 11 13.5 8 14C5 13.5 3 11 3 8C3 3.5 6 2 8 2Z" stroke="#fff" stroke-width="1.4" fill="none"/>
        <path d="M8 2L9.5 5.5H12.5L10 7.5L11 11L8 9L5 11L6 7.5L3.5 5.5H6.5L8 2Z" fill="#fff" opacity="0.9"/>
      </svg>
      START NAVIGATION
    </button>
    <button id="stopBtn" class="nav-btn" onclick="stopNavigation()">
      <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="2" y="2" width="10" height="10" rx="2" fill="#d01030"/>
      </svg>
      END NAVIGATION
    </button>
  </div>

  <!-- ARRIVAL -->
  <div id="arrivalOverlay">
    <svg class="arrival-icon" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
      <circle cx="32" cy="32" r="30" fill="rgba(212,95,16,0.12)" stroke="#d45f10" stroke-width="2.5"/>
      <path d="M18 33L28 43L46 22" stroke="#d45f10" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    <div class="arrival-title">Arrived!</div>
    <div class="arrival-sub">You've reached your destination</div>
    <button class="arrival-close" onclick="closeArrival()">
      <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M2 7h10M7 2l5 5-5 5" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      Done
    </button>
  </div>

</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.js"></script>

<script>
// ─── CONFIG ───────────────────────────────────────────────────────────────
const DEFAULT_DEST = {
  lat: 15.137222,
  lon: 120.976111,
  name: 'San Miguel National High School'
};
let destLat = DEFAULT_DEST.lat;
let destLon = DEFAULT_DEST.lon;
let destName = DEFAULT_DEST.name;
const REROUTE_COOLDOWN      = 15000;
const STATUS_POLL_INTERVAL  = 30000; // check center status every 30 s during navigation

const MODES = {
  walk: { label:'Walking',    icon:'🚶', speed:5,  accentColor:'#18a850', offRouteM:40  },
  bike: { label:'Cycling',    icon:'🚲', speed:18, accentColor:'#0088cc', offRouteM:60  },
  moto: { label:'Motorcycle', icon:'🏍️', speed:45, accentColor:'#b07800', offRouteM:80  },
  car:  { label:'Driving',    icon:'🚗', speed:60, accentColor:'#d45f10', offRouteM:80  },
};

// ─── STATE ────────────────────────────────────────────────────────────────
let map, userMarker, routingControl, watchId, destMarker;
let compassHeading = 0, lastPosition = null;
let routeCoords = [], routeInstructions = [];
let currentStepIdx = 0;
let isNavigating = false, isOffRoute = false, isMapLocked = true;
let lastRerouteTime = 0;
let arrowLayers = [];
let selectedMode = 'walk';
let centers = [];          // full sorted center list (including full ones)
let userLoc = null;
let selectedCenterId = null;
let statusPollTimer = null; // interval handle for status polling

// ─── TRACKING ─────────────────────────────────────────────────────────────
let _trackedCenterId = null;

function trackSelectCenter(centerId, centerName) {
  if (_trackedCenterId === centerId) return;
  _trackedCenterId = centerId;
  fetch('citizen_track_navigation.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin',
    body: JSON.stringify({ action: 'select', center_id: centerId }),
  }).catch(() => {});

  if (window.opener && !window.opener.closed) {
    try {
      window.opener.postMessage(
        { type: 'evac_select', center_id: centerId, center_name: centerName },
        window.location.origin
      );
    } catch(e) {}
  }
}

function trackArrived() {
  _trackedCenterId = null;
  fetch('citizen_track_navigation.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin',
    body: JSON.stringify({ action: 'arrived' }),
  }).catch(() => {});
  if (window.opener && !window.opener.closed) {
    try { window.opener.postMessage({ type: 'evac_arrived' }, window.location.origin); } catch(e) {}
  }
}

function trackCancel() {
  if (_trackedCenterId === null) return;
  _trackedCenterId = null;
  fetch('citizen_track_navigation.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin',
    body: JSON.stringify({ action: 'cancel' }),
  }).catch(() => {});
  if (window.opener && !window.opener.closed) {
    try { window.opener.postMessage({ type: 'evac_cancel' }, window.location.origin); } catch(e) {}
  }
}

// ─── STATUS POLLING (runs during navigation) ───────────────────────────────
// Re-fetches the center list every STATUS_POLL_INTERVAL ms.
// If the currently selected center is now 'full', triggers an auto-reroute.

function startStatusPolling() {
  stopStatusPolling();
  statusPollTimer = setInterval(pollCenterStatus, STATUS_POLL_INTERVAL);
}

function stopStatusPolling() {
  if (statusPollTimer) {
    clearInterval(statusPollTimer);
    statusPollTimer = null;
  }
}

function pollCenterStatus() {
  if (!isNavigating || selectedCenterId === null) return;

  fetch('centers.php?action=list_available', { credentials: 'same-origin' })
    .then(r => r.ok ? r.json() : null)
    .then(data => {
      if (!data || !data.ok) return;

      // Refresh local center data with fresh occupancy figures
      const freshMap = {};
      (data.centers || []).forEach(c => { freshMap[c.id] = c; });

      // Update local centers array with fresh data
      centers = centers.map(c => freshMap[c.id]
        ? Object.assign({}, c, freshMap[c.id])
        : c
      );

      // Re-render the center list cards to show updated occupancy
      rebuildCenterList();

      // Check if our current destination became full
      const dest = centers.find(c => c.id === selectedCenterId);
      if (dest && dest.status === 'full') {
        autoRerouteFromFull(dest.name);
      }
    })
    .catch(() => {}); // silent fail — polling is best-effort
}

// ─── AUTO-REROUTE (called when current destination centre is full) ─────────
function autoRerouteFromFull(fullCenterName) {
  // Find the next nearest center that is NOT full/closed
  const ref = userLoc || lastPosition;
  const next = centers.find(c =>
    c.id !== selectedCenterId &&
    c.status !== 'full' &&
    c.status !== 'closed'
  );

  if (!next) {
    showRerouteToast('⚠ ' + (fullCenterName || 'Center') + ' is full. No other centers available right now.');
    return;
  }

  showRerouteToast(
    (fullCenterName || 'Center') + ' is full — rerouting to ' + next.name
  );

  speak((fullCenterName || 'Your destination') + ' is now full. Rerouting to ' + next.name);

  // Small delay so toast is readable before map jumps
  setTimeout(() => {
    chooseCenter(next.id, false);

    if (isNavigating && ref) {
      // Rebuild route to new destination
      clearLayers();
      if (routingControl) { map.removeControl(routingControl); routingControl = null; }
      currentStepIdx = 0; routeCoords = [];
      createOrUpdateRoute(ref.lat, ref.lon);
    }
  }, 1200);
}

// ─── REROUTE TOAST ────────────────────────────────────────────────────────
let toastTimer = null;
function showRerouteToast(msg) {
  const toast = document.getElementById('rerouteToast');
  document.getElementById('rerouteToastMsg').textContent = msg;
  toast.classList.add('show');
  if (toastTimer) clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toast.classList.remove('show'), 5000);
}

// ─── PANEL DRAG ───────────────────────────────────────────────────────────
let panelCollapsed = false;
let dragStartY = 0;
let isDragging = false;

function syncToggleBtn() {
  const btn   = document.getElementById('panelToggleBtn');
  const panel = document.getElementById('bottomPanel');
  if (panelCollapsed) {
    btn.classList.add('collapsed');
    btn.style.bottom = '5rem';
    document.getElementById('speedBubble').style.bottom = '4.2rem';
    document.getElementById('recenterBtn').style.bottom = '4.2rem';
  } else {
    btn.classList.remove('collapsed');
    const panelH = panel.offsetHeight || 300;
    btn.style.bottom = (panelH - 30) + 'px';
    document.getElementById('speedBubble').style.bottom = '18rem';
    document.getElementById('recenterBtn').style.bottom = '18rem';
  }
}

function togglePanel() { snapPanel(!panelCollapsed); }

function initPanelDrag() {
  const panel  = document.getElementById('bottomPanel');
  const handle = document.getElementById('panelHandle');
  const hint   = document.getElementById('handleHint');

  handle.addEventListener('click', (e) => {
    if (isDragging) return;
    if (panelCollapsed) snapPanel(false);
  });

  handle.addEventListener('touchstart', (e) => {
    dragStartY = e.touches[0].clientY;
    isDragging = false;
    panel.classList.add('no-transition');
  }, { passive: true });

  handle.addEventListener('touchmove', (e) => {
    const dy = e.touches[0].clientY - dragStartY;
    if (Math.abs(dy) > 6) isDragging = true;
    if (!isDragging) return;
    const newBottom = panelCollapsed
      ? Math.max(-88, Math.min(0, -(dy / window.innerHeight * 100)))
      : Math.max(-88, Math.min(0, dy < 0 ? 0 : -(dy / window.innerHeight * 100)));
    panel.style.bottom = newBottom + '%';
  }, { passive: true });

  handle.addEventListener('touchend', (e) => {
    if (!isDragging) return;
    const dy = e.changedTouches[0].clientY - dragStartY;
    if (dy > 60) snapPanel(true);
    else if (dy < -40) snapPanel(false);
    else snapPanel(panelCollapsed);
    isDragging = false;
  }, { passive: true });

  handle.addEventListener('mousedown', (e) => {
    dragStartY = e.clientY;
    isDragging = false;
    panel.classList.add('no-transition');
    function onMove(e) {
      const dy = e.clientY - dragStartY;
      if (Math.abs(dy) > 6) isDragging = true;
      if (!isDragging) return;
      const newBottom = Math.max(-88, Math.min(0, -(dy / window.innerHeight * 100)));
      panel.style.bottom = newBottom + '%';
    }
    function onUp(e) {
      document.removeEventListener('mousemove', onMove);
      document.removeEventListener('mouseup', onUp);
      if (!isDragging) return;
      const dy = e.clientY - dragStartY;
      if (dy > 60) snapPanel(true);
      else if (dy < -40) snapPanel(false);
      else snapPanel(panelCollapsed);
      isDragging = false;
    }
    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup', onUp);
  });
}

window.snapPanel = function(collapsed) {
  panelCollapsed = collapsed;
  const panel = document.getElementById('bottomPanel');
  const hint  = document.getElementById('handleHint');
  panel.classList.remove('no-transition');
  if (collapsed) {
    panel.classList.add('collapsed');
    panel.classList.remove('show');
    hint.textContent = 'tap to show';
  } else {
    panel.classList.remove('collapsed');
    panel.classList.add('show');
    hint.textContent = 'drag to hide';
  }
  panel.style.bottom = '';
  requestAnimationFrame(() => setTimeout(syncToggleBtn, 520));
};

// ─── INIT ─────────────────────────────────────────────────────────────────
function initApp() {
  initMap();
  initCompass();
  initPanelDrag();
  window.snapPanel(false);
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      pos => {
        userLoc = { lat: pos.coords.latitude, lon: pos.coords.longitude };
        updatePreview(userLoc.lat, userLoc.lon);
        loadCenters();
      },
      err => {
        document.getElementById('centerList').textContent =
          'Unable to get your location: ' + err.message;
        loadCenters();
      },
      { enableHighAccuracy: true }
    );
  } else {
    document.getElementById('centerList').textContent =
      'Geolocation not supported on this device.';
    loadCenters();
  }
}

function initMap() {
  map = L.map('map', { zoomControl: false, maxZoom: 20 }).setView([destLat, destLon], 15);
  L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
    attribution: '© CARTO © OSM', subdomains: 'abcd', maxZoom: 20
  }).addTo(map);
  updateDestinationMarker();
  map.on('dragstart', () => { isMapLocked = false; });
  window.addEventListener('resize', syncToggleBtn);
}

// ─── CAPACITY HELPERS ─────────────────────────────────────────────────────
function getCapacityClass(center) {
  if (center.status === 'full') return 'full';
  if (center.status === 'near_capacity') return 'near';
  return 'ok';
}

function getSlotsLabel(center) {
  const max   = center.max_capacity_people || 0;
  const occ   = center.current_occupancy   || 0;
  const slots = Math.max(0, max - occ);
  const cls   = getCapacityClass(center);
  if (center.status === 'full') return { text: 'FULL — no slots', cls: 'full' };
  if (center.status === 'near_capacity') return { text: slots + ' slots left', cls: 'near' };
  return { text: slots + ' slots available', cls: 'ok' };
}

function getOccupancyPct(center) {
  const max = center.max_capacity_people || 0;
  if (!max) return 0;
  return Math.min(100, Math.round((center.current_occupancy / max) * 100));
}

// ─── BUILD CENTER LIST UI ─────────────────────────────────────────────────
// Separated from loadCenters so it can be called again on poll refresh
function rebuildCenterList() {
  const listEl = document.getElementById('centerList');
  if (!centers.length) {
    listEl.textContent = 'No available evacuation centers at the moment.';
    return;
  }

  const frag = document.createDocumentFragment();
  centers.forEach(c => {
    const km      = c.distanceM != null ? (c.distanceM / 1000).toFixed(2) : '–';
    const isFull  = c.status === 'full';
    const capCls  = getCapacityClass(c);
    const slots   = getSlotsLabel(c);
    const pct     = getOccupancyPct(c);
    const isSelected = (c.id === selectedCenterId);

    // Status badge
    let badgeHtml = '';
    if (c.status === 'available') {
      badgeHtml = `<span class="cbadge cbadge-available">Available</span>`;
    } else if (c.status === 'near_capacity') {
      badgeHtml = `<span class="cbadge cbadge-near">Near Full</span>`;
    } else if (c.status === 'full') {
      badgeHtml = `<span class="cbadge cbadge-full">Full</span>`;
    } else if (c.status === 'temp_shelter') {
      badgeHtml = `<span class="cbadge cbadge-temp">Temp Shelter</span>`;
    }

    const div = document.createElement('div');
    div.className = 'center-item' + (isFull ? ' is-full' : '') + (isSelected ? ' selected' : '');
    div.dataset.centerId = c.id;

    // Full centers are not clickable
    if (!isFull) {
      div.onclick = () => chooseCenter(c.id);
    }

    div.innerHTML = `
      <div class="center-main">
        <div class="center-name">${c.name}</div>
        <div class="center-badges">
          ${badgeHtml}
        </div>
        <div class="center-sub" style="margin-top:3px">
          <svg width="10" height="10" viewBox="0 0 14 14" fill="none" style="flex-shrink:0;display:inline-block;vertical-align:middle">
            <path d="M7 1C4.79 1 3 2.79 3 5c0 3.25 4 8 4 8s4-4.75 4-8c0-2.21-1.79-4-4-4Z" fill="#d45f10"/>
          </svg>
          ${c.barangay}
        </div>
        <div class="center-sub" style="margin-top:2px;color:var(--accent);display:flex;align-items:center;gap:4px;flex-wrap:wrap;">
          <svg width="12" height="12" viewBox="0 0 12 12" fill="none" style="flex-shrink:0;">
            <circle cx="6" cy="4" r="2.2" stroke="#d45f10" stroke-width="1.3"/>
            <path d="M1.5 10.5C1.5 8.57 3.57 7 6 7s4.5 1.57 4.5 3.5" stroke="#d45f10" stroke-width="1.3" stroke-linecap="round" fill="none"/>
          </svg>
          ${c.coordinator_name ?? 'Unassigned'}
          ${c.coordinator_contact
            ? `&nbsp;·&nbsp;<svg width="12" height="12" viewBox="0 0 12 12" fill="none" style="display:inline-block;vertical-align:middle;"><path d="M2 2.5C2 2.5 3 4.5 4.5 6S9.5 10 9.5 10l1-1.5-1.5-1.5-1 1C7.5 8 5 5.5 4.5 4.5l1-1L4 2 2 2.5Z" stroke="#d45f10" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg> ${c.coordinator_contact}`
            : ''
          }
        </div>

        <!-- ── Capacity bar ── -->
        <div class="cap-bar-wrap">
          <div class="cap-bar-track">
            <div class="cap-bar-fill fill-${capCls}" style="width:${pct}%"></div>
          </div>
          <div class="cap-label">
            <span class="slots ${slots.cls}">${slots.text}</span>
            <span>${c.current_occupancy ?? 0} / ${c.max_capacity_people ?? '?'} people</span>
          </div>
        </div>
      </div>
      <div class="center-meta">
        <div class="center-distance">${km} km</div>
        <div class="center-status center-status-${c.status}">${c.status}</div>
      </div>
    `;
    frag.appendChild(div);
  });

  listEl.innerHTML = '';
  listEl.appendChild(frag);
}

// ─── LOAD CENTERS FROM API ────────────────────────────────────────────────
function loadCenters() {
  const listEl = document.getElementById('centerList');
  listEl.textContent = 'Loading available centers…';

  fetch('centers.php?action=list_available', { credentials: 'same-origin' })
    .then(r => r.ok ? r.json() : Promise.reject(new Error('Failed to load centers')))
    .then(data => {
      if (!data.ok) throw new Error(data.error || 'Failed to load centers');
      centers = data.centers || [];

      if (userLoc) {
        centers.forEach(c => {
          c.distanceM = getDist(userLoc.lat, userLoc.lon, c.lat, c.lng);
        });
        // Sort: available/near first by distance, full ones pushed to bottom
        centers.sort((a, b) => {
          const aFull = a.status === 'full' ? 1 : 0;
          const bFull = b.status === 'full' ? 1 : 0;
          if (aFull !== bFull) return aFull - bFull;
          return (a.distanceM || Infinity) - (b.distanceM || Infinity);
        });
      }

      if (!centers.length) {
        listEl.textContent = 'No available evacuation centers at the moment.';
        return;
      }

      rebuildCenterList();

      // Auto-select nearest non-full center
      const firstAvailable = centers.find(c => c.status !== 'full');
      if (firstAvailable) {
        chooseCenter(firstAvailable.id, false);
      } else {
        // All centers are full — still select the nearest and warn
        chooseCenter(centers[0].id, false);
        showRerouteToast('⚠ All centers are currently full. Please contact MDRRMO.');
      }
      setTimeout(syncToggleBtn, 100);
    })
    .catch(err => {
      listEl.textContent = 'Unable to load centers: ' + err.message;
    });
}

// ─── CHOOSE CENTER ────────────────────────────────────────────────────────
function chooseCenter(centerId, speakIt = true) {
  const center = centers.find(c => c.id == centerId);
  if (!center) return;

  // Prevent selecting a full center manually
  if (center.status === 'full') return;

  selectedCenterId = center.id;
  destLat  = center.lat;
  destLon  = center.lng;
  destName = center.name;

  document.getElementById('destName').innerHTML = `
    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg" style="flex-shrink:0;">
      <path d="M7 1C4.79 1 3 2.79 3 5c0 3.25 4 8 4 8s4-4.75 4-8c0-2.21-1.79-4-4-4Z" fill="#d45f10"/>
      <circle cx="7" cy="5" r="1.5" fill="#fff"/>
    </svg>
    <span>${center.name} (${center.barangay})</span>
  `;

  // Highlight selected item
  document.querySelectorAll('.center-item').forEach(el => {
    el.classList.toggle('selected', el.dataset.centerId == centerId);
  });

  updateDestinationMarker();
  if (userLoc) updatePreview(userLoc.lat, userLoc.lon);
  if (speakIt) speak('Destination set to ' + center.name);

  trackSelectCenter(center.id, center.name);
}

function updateDestinationMarker() {
  if (!map) return;
  const destIcon = L.divIcon({
    className: '',
    html: `<div class="dest-pin-head">
             <svg class="dest-pin-icon" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
               <rect x="3" y="9" width="14" height="9" rx="1" stroke="#fff" stroke-width="1.4" fill="none"/>
               <path d="M1 10L10 4L19 10" stroke="#fff" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
               <rect x="8" y="13" width="4" height="5" rx="0.5" stroke="#fff" stroke-width="1.2" fill="none"/>
             </svg>
           </div>`,
    iconSize: [32, 40], iconAnchor: [16, 40]
  });
  if (destMarker) {
    destMarker.setLatLng([destLat, destLon]);
    destMarker.setPopupContent('<b>' + destName + '</b>');
  } else {
    destMarker = L.marker([destLat, destLon], { icon: destIcon }).addTo(map)
      .bindPopup('<b>' + destName + '</b>');
  }
}

// ─── MODE SELECTOR ────────────────────────────────────────────────────────
function selectMode(mode) {
  if (isNavigating) return;
  selectedMode = mode;
  document.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('active'));
  document.querySelector(`[data-mode="${mode}"]`).classList.add('active');
  document.documentElement.style.setProperty('--accent', MODES[mode].accentColor);
  if (lastPosition) updatePreview(lastPosition.lat, lastPosition.lon);
}

function updatePreview(lat, lon) {
  const dist = getDist(lat, lon, destLat, destLon);
  const km   = (dist / 1000).toFixed(2);
  const spd  = MODES[selectedMode].speed;
  const eta  = Math.round((dist / 1000) / spd * 60);
  document.getElementById('previewDist').textContent  = km;
  document.getElementById('previewTime').textContent  = eta;
  document.getElementById('previewSpeed').textContent = spd;
  document.getElementById('remainDist').textContent   = `~${km} km · ~${eta} min`;
}

// ─── COMPASS ──────────────────────────────────────────────────────────────
function initCompass() {
  if (!window.DeviceOrientationEvent) return;
  const attach = () => window.addEventListener('deviceorientation', handleOrientation);
  if (typeof DeviceOrientationEvent.requestPermission === 'function') {
    DeviceOrientationEvent.requestPermission().then(s => { if (s === 'granted') attach(); }).catch(() => {});
  } else { attach(); }
}

function handleOrientation(e) {
  let h = null;
  if (e.webkitCompassHeading != null) h = e.webkitCompassHeading;
  else if (e.alpha != null) h = (360 - e.alpha) % 360;
  if (h == null) return;
  compassHeading = h;
  document.getElementById('compassNeedle').style.transform = `rotate(${-h}deg)`;
  document.getElementById('compassLabel').style.color = (h < 20 || h > 340) ? '#d01030' : '#7a7068';
}

// ─── START NAVIGATION ─────────────────────────────────────────────────────
function startNavigation() {
  if (!navigator.geolocation) return alert('Geolocation not supported');
  isNavigating = true;
  document.querySelectorAll('.mode-btn').forEach(b => {
    b.style.opacity = '0.45'; b.style.pointerEvents = 'none';
  });
  document.getElementById('startBtn').style.display = 'none';
  document.getElementById('stopBtn').style.display  = 'flex';
  document.getElementById('dirCard').classList.add('show');
  document.getElementById('turnInstruction').textContent = 'Getting location…';

  watchId = navigator.geolocation.watchPosition(onPosition, onGeoError, {
    enableHighAccuracy: true, maximumAge: 0, timeout: 8000
  });

  // Start polling center status in the background
  startStatusPolling();
}

// ─── STOP NAVIGATION ──────────────────────────────────────────────────────
function stopNavigation() {
  isNavigating = false;
  stopStatusPolling();

  if (watchId) navigator.geolocation.clearWatch(watchId);
  if (routingControl) { map.removeControl(routingControl); routingControl = null; }
  clearLayers();

  document.querySelectorAll('.mode-btn').forEach(b => {
    b.style.opacity = '1'; b.style.pointerEvents = 'auto';
  });
  document.getElementById('startBtn').style.display = 'flex';
  document.getElementById('stopBtn').style.display  = 'none';
  document.getElementById('dirCard').classList.remove('show');
  document.getElementById('offrouteBanner').classList.remove('show');
  document.getElementById('turnInstruction').textContent = 'Head toward destination';
  document.getElementById('stepDist').textContent        = 'Calculating…';
  document.getElementById('etaMin').textContent          = '--';
  currentStepIdx = 0; isOffRoute = false; routeCoords = []; routeInstructions = [];

  trackCancel();
}

// ─── POSITION UPDATE ──────────────────────────────────────────────────────
function onPosition(pos) {
  const lat   = pos.coords.latitude;
  const lon   = pos.coords.longitude;
  const speed = pos.coords.speed ? (pos.coords.speed * 3.6) : 0;

  const sv = document.getElementById('speedVal');
  sv.textContent = Math.round(speed);
  const modeSpd = MODES[selectedMode].speed;
  sv.style.color = speed < 3 ? 'var(--text)' : speed < modeSpd ? 'var(--green)' : speed < modeSpd * 1.4 ? 'var(--yellow)' : 'var(--red)';

  if (userMarker) {
    userMarker.setLatLng([lat, lon]);
  } else {
    const icon = L.divIcon({
      className: '',
      html: `<div class="user-dot-wrap">
               <div class="user-halo"></div>
               <div class="user-dot"></div>
               <div class="user-pip"></div>
             </div>`,
      iconSize: [36, 36], iconAnchor: [18, 18]
    });
    userMarker = L.marker([lat, lon], { icon, zIndexOffset: 1000 }).addTo(map);
  }

  if (lastPosition) {
    const bearing = getBearing(lastPosition.lat, lastPosition.lon, lat, lon);
    const el = userMarker.getElement();
    if (el) {
      const wrap = el.querySelector('.user-dot-wrap');
      if (wrap) wrap.style.transform = `rotate(${bearing}deg)`;
    }
  }

  if (isMapLocked) map.setView([lat, lon], 17, { animate: true, duration: 0.8 });

  if (routeCoords.length > 0) {
    const offDist = distanceToRoute(lat, lon, routeCoords);
    if (offDist > MODES[selectedMode].offRouteM) {
      triggerOffRoute(lat, lon);
    } else if (isOffRoute) {
      isOffRoute = false;
      document.getElementById('offrouteBanner').classList.remove('show');
    }
  }

  updateCurrentStep(lat, lon);
  createOrUpdateRoute(lat, lon);

  if (getDist(lat, lon, destLat, destLon) < 20) { onArrival(); return; }

  const remDist = getDist(lat, lon, destLat, destLon);
  const remKm   = (remDist / 1000).toFixed(1);
  const eta     = Math.round((remDist / 1000) / MODES[selectedMode].speed * 60);
  document.getElementById('remainDist').textContent = `${remKm} km remaining`;
  document.getElementById('etaMin').textContent     = eta;
  updatePreview(lat, lon);

  lastPosition = { lat, lon };
}

// ─── ROUTE ────────────────────────────────────────────────────────────────
function createOrUpdateRoute(lat, lon) {
  if (routingControl) {
    routingControl.setWaypoints([L.latLng(lat, lon), L.latLng(destLat, destLon)]);
    return;
  }
  routingControl = L.Routing.control({
    waypoints: [L.latLng(lat, lon), L.latLng(destLat, destLon)],
    lineOptions:       { styles: [{ color: 'transparent', weight: 0 }] },
    createMarker:      () => null,
    addWaypoints:      false,
    draggableWaypoints:false,
    fitSelectedRoutes: false,
    show:              false,
  }).addTo(map);

  routingControl.on('routesfound', e => {
    const route = e.routes[0];
    routeCoords       = route.coordinates;
    routeInstructions = route.instructions || [];
    drawTrafficRoute(routeCoords);
    drawRouteArrows(routeCoords);
    updateStepDisplay();
    if (lastPosition) updatePreview(lastPosition.lat, lastPosition.lon);
  });
}

function drawTrafficRoute(coords) {
  arrowLayers.filter(l => l._isRoute).forEach(l => map.removeLayer(l));

  const border = L.polyline(coords.map(c => [c.lat, c.lng]), {
    color: 'rgba(0,0,0,0.15)', weight: 12, opacity: 0.5, lineCap: 'round', lineJoin: 'round'
  });
  border._isRoute = true; border.addTo(map); border.bringToBack(); arrowLayers.push(border);

  for (let i = 1; i < coords.length; i++) {
    const p    = coords[i-1], c = coords[i];
    const seed = (i * 7 + Math.round(p.lat * 1000)) % 10;
    const color = seed < 5 ? '#18a850' : seed < 8 ? '#b07800' : '#d01030';
    const seg = L.polyline([[p.lat,p.lng],[c.lat,c.lng]], {
      color, weight: 7, opacity: 0.88, lineCap: 'round', lineJoin: 'round'
    });
    seg._isRoute = true; seg.addTo(map); arrowLayers.push(seg);
  }
}

function drawRouteArrows(coords) {
  arrowLayers.filter(l => l._isArrow).forEach(l => map.removeLayer(l));
  let accum = 0;
  for (let i = 1; i < coords.length; i++) {
    const p = coords[i-1], c = coords[i];
    accum += getDist(p.lat, p.lng, c.lat, c.lng);
    if (accum >= 120) {
      accum = 0;
      const bearing = getBearing(p.lat, p.lng, c.lat, c.lng);
      const mid  = [(p.lat+c.lat)/2, (p.lng+c.lng)/2];
      const icon = L.divIcon({
        className: '',
        html: `<svg width="16" height="16" viewBox="0 0 16 16" style="transform:rotate(${bearing}deg);filter:drop-shadow(0 1px 3px rgba(0,0,0,0.3))">
          <polygon points="8,1 14,13 8,10 2,13" fill="white" opacity="0.90"/>
        </svg>`,
        iconSize: [16,16], iconAnchor: [8,8]
      });
      const m = L.marker(mid, { icon, zIndexOffset: -50, interactive: false });
      m._isArrow = true; m.addTo(map); arrowLayers.push(m);
    }
  }
}

function clearLayers() { arrowLayers.forEach(l => map.removeLayer(l)); arrowLayers = []; }

// ─── TURN INSTRUCTIONS ────────────────────────────────────────────────────
const TURN_SVG = {
  Straight:    `<path d="M12 3v15M12 3L7 8M12 3L17 8" stroke="white" stroke-width="2.5" stroke-linecap="round" fill="none"/>`,
  Right:       `<path d="M7 20 Q7 10 17 5M17 5l-4 1M17 5l-1 4" stroke="white" stroke-width="2.5" stroke-linecap="round" fill="none"/>`,
  SlightRight: `<path d="M8 20 Q10 8 17 6M17 6l-4 0M17 6l0 4" stroke="white" stroke-width="2.5" stroke-linecap="round" fill="none"/>`,
  SharpRight:  `<path d="M5 20 Q14 16 17 5M17 5l-4 2M17 5l-2 4" stroke="white" stroke-width="2.5" stroke-linecap="round" fill="none"/>`,
  Left:        `<path d="M17 20 Q17 10 7 5M7 5l4 1M7 5l1 4" stroke="white" stroke-width="2.5" stroke-linecap="round" fill="none"/>`,
  SlightLeft:  `<path d="M16 20 Q14 8 7 6M7 6l4 0M7 6l0 4" stroke="white" stroke-width="2.5" stroke-linecap="round" fill="none"/>`,
  SharpLeft:   `<path d="M19 20 Q10 16 7 5M7 5l4 2M7 5l2 4" stroke="white" stroke-width="2.5" stroke-linecap="round" fill="none"/>`,
  Dest:        `<circle cx="12" cy="12" r="7" fill="none" stroke="white" stroke-width="2.5"/><circle cx="12" cy="12" r="3" fill="white"/>`,
};

function getTurnType(text) {
  if (!text) return 'Straight';
  const t = text.toLowerCase();
  if (t.includes('arrive') || t.includes('destination')) return 'Dest';
  if (t.includes('sharp right')) return 'SharpRight';
  if (t.includes('slight right')||t.includes('bear right')||t.includes('keep right')) return 'SlightRight';
  if (t.includes('right')) return 'Right';
  if (t.includes('sharp left')) return 'SharpLeft';
  if (t.includes('slight left')||t.includes('bear left')||t.includes('keep left')) return 'SlightLeft';
  if (t.includes('left')) return 'Left';
  return 'Straight';
}

function updateStepDisplay() {
  if (!routeInstructions.length) return;
  const step = routeInstructions[Math.min(currentStepIdx, routeInstructions.length-1)];
  document.getElementById('turnInstruction').textContent = step.text;
  document.getElementById('stepDist').textContent        = `In ${Math.round(step.distance)} m`;
  const type = getTurnType(step.text);
  document.getElementById('turnArrowSvg').innerHTML = TURN_SVG[type] || TURN_SVG.Straight;
  const isArrival = type === 'Dest';
  document.getElementById('turnArrowBox').style.background = isArrival
    ? 'linear-gradient(135deg,#18a850,#0e7a36)'
    : `linear-gradient(135deg,${MODES[selectedMode].accentColor},#0088cc)`;
}

function updateCurrentStep(lat, lon) {
  if (!routeInstructions.length) return;
  const remDist = getDist(lat, lon, destLat, destLon);
  for (let i = currentStepIdx+1; i < routeInstructions.length; i++) {
    if (remDist < routeInstructions[i].distance + 50) {
      currentStepIdx = i;
      updateStepDisplay();
      speak(routeInstructions[i].text);
      break;
    }
  }
}

// ─── OFF-ROUTE ────────────────────────────────────────────────────────────
function distanceToRoute(lat, lon, coords) {
  let min = Infinity;
  for (let i = 1; i < coords.length; i++) {
    const d = ptSegDist(lat, lon, coords[i-1].lat, coords[i-1].lng, coords[i].lat, coords[i].lng);
    if (d < min) min = d;
  }
  return min;
}
function ptSegDist(px, py, ax, ay, bx, by) {
  const dx = bx-ax, dy = by-ay;
  if (!dx && !dy) return getDist(px, py, ax, ay);
  const t = Math.max(0, Math.min(1, ((px-ax)*dx+(py-ay)*dy)/(dx*dx+dy*dy)));
  return getDist(px, py, ax+t*dx, ay+t*dy);
}

function triggerOffRoute(lat, lon) {
  if (isOffRoute) return;
  isOffRoute = true;
  document.getElementById('offrouteBanner').classList.add('show');
  speak('Off route. Recalculating.');
  const now = Date.now();
  if (now - lastRerouteTime > REROUTE_COOLDOWN) {
    lastRerouteTime = now;
    setTimeout(() => reroute(lat, lon), 1500);
  }
}

function reroute(lat, lon) {
  if (!isNavigating) return;
  clearLayers();
  if (routingControl) { map.removeControl(routingControl); routingControl = null; }
  currentStepIdx = 0; routeCoords = [];
  document.getElementById('offrouteBanner').classList.remove('show');
  isOffRoute = false;
  createOrUpdateRoute(lat, lon);
  speak('Route updated.');
}

// ─── ARRIVAL ──────────────────────────────────────────────────────────────
function onArrival() {
  speak(`You have arrived! Great ${MODES[selectedMode].label.toLowerCase()}!`);
  trackArrived();
  stopNavigation();
  document.getElementById('arrivalOverlay').classList.add('show');
}
function closeArrival() { document.getElementById('arrivalOverlay').classList.remove('show'); }

// ─── RECENTER ─────────────────────────────────────────────────────────────
function recenter() {
  isMapLocked = true;
  if (userMarker) map.flyTo(userMarker.getLatLng(), 17, { duration: 0.8 });
}

// ─── SPEECH ───────────────────────────────────────────────────────────────
function speak(text) {
  if (!window.speechSynthesis) return;
  window.speechSynthesis.cancel();
  const u = new SpeechSynthesisUtterance(text);
  u.lang = 'en-US'; u.rate = 1.05;
  window.speechSynthesis.speak(u);
}

// ─── MATH ─────────────────────────────────────────────────────────────────
function getDist(lat1, lon1, lat2, lon2) {
  const R = 6371e3, r = Math.PI/180;
  const p1=lat1*r, p2=lat2*r, dp=(lat2-lat1)*r, dl=(lon2-lon1)*r;
  const a = Math.sin(dp/2)**2 + Math.cos(p1)*Math.cos(p2)*Math.sin(dl/2)**2;
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
}
function getBearing(lat1, lon1, lat2, lon2) {
  const r=Math.PI/180, f1=lat1*r, f2=lat2*r, dl=(lon2-lon1)*r;
  const y=Math.sin(dl)*Math.cos(f2), x=Math.cos(f1)*Math.sin(f2)-Math.sin(f1)*Math.cos(f2)*Math.cos(dl);
  return (Math.atan2(y,x)*180/Math.PI+360)%360;
}
function onGeoError(err) {
  document.getElementById('turnInstruction').textContent = '⚠ ' + err.message;
}

// ─── BOOT ─────────────────────────────────────────────────────────────────
initApp();
</script>
</body>
</html>