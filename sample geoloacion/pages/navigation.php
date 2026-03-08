<?php
require_once __DIR__ . '/session.php';
require_login(); // any authenticated user can use navigation
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>MDRRMO Navigation</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.css"/>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Barlow+Condensed:wght@500;700;800&display=swap" rel="stylesheet">

<link href="../css/index.css" rel="stylesheet">
</head>

<body>
<div id="app">

  <!-- SPLASH -->
  <div id="splash">
    <div class="splash-pulse">🧭</div>
    <div class="splash-logo">MDRRMO San Ildefonso</div>
    <div class="splash-sub">Evacuation Navigation</div>
    <button class="splash-btn" onclick="initApp()">GET STARTED</button>
  </div>

  <!-- MAP -->
  <div id="map"></div>

  <!-- TOP DIRECTION CARD -->
  <div id="dirCard">
    <div id="turnArrowBox">
      <svg id="turnArrowSvg" width="34" height="34" viewBox="0 0 24 24">
        <path d="M12 3v15M12 3L7 8M12 3L17 8" stroke="black" stroke-width="2.5" stroke-linecap="round" fill="none"/>
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
    <div class="offroute-icon">⚠️</div>
    <div>
      <div class="offroute-text">Off Route!</div>
      <div class="offroute-sub">Recalculating…</div>
    </div>
  </div>

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
  <button id="recenterBtn" onclick="recenter()">⊕</button>

  <!-- BOTTOM PANEL -->
  <div id="bottomPanel">
    <div class="bottom-handle"></div>

    <div id="destName">📍 Select an evacuation center</div>
    <div id="remainDist">We will suggest the nearest available center.</div>

    <div class="mode-label">Evacuation Centers (nearest first)</div>
    <div id="centerList">Requesting your location…</div>

    <div class="mode-label">Travel Mode</div>
    <div id="modeSelector">
      <button class="mode-btn active" data-mode="walk" onclick="selectMode('walk')">
        <span class="mode-icon">🚶</span>
        <span class="mode-name">Walk</span>
      </button>
      <button class="mode-btn" data-mode="bike" onclick="selectMode('bike')">
        <span class="mode-icon">🚲</span>
        <span class="mode-name">Bike</span>
      </button>
      <button class="mode-btn" data-mode="moto" onclick="selectMode('moto')">
        <span class="mode-icon">🏍️</span>
        <span class="mode-name">Moto</span>
      </button>
      <button class="mode-btn" data-mode="car" onclick="selectMode('car')">
        <span class="mode-icon">🚗</span>
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
      <div class="tleg"><div class="tleg-dot" style="background:#00e676"></div>Free</div>
      <div class="tleg"><div class="tleg-dot" style="background:#ffea00"></div>Slow</div>
      <div class="tleg"><div class="tleg-dot" style="background:#ff1744"></div>Jammed</div>
    </div>

    <button id="startBtn" class="nav-btn" onclick="startNavigation()">🚀 START NAVIGATION</button>
    <button id="stopBtn" class="nav-btn" onclick="stopNavigation()">■ END NAVIGATION</button>
  </div>

  <!-- ARRIVAL -->
  <div id="arrivalOverlay">
    <div class="arrival-emoji">🎉</div>
    <div class="arrival-title">Arrived!</div>
    <div class="arrival-sub">You've reached your destination</div>
    <button class="arrival-close" onclick="closeArrival()">Done</button>
  </div>

</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.js"></script>

<script>
// ─── CONFIG ───────────────────────────────
const DEFAULT_DEST = {
  lat: 15.137222,
  lon: 120.976111,
  name: 'San Miguel National High School'
};
let destLat = DEFAULT_DEST.lat;
let destLon = DEFAULT_DEST.lon;
let destName = DEFAULT_DEST.name;
const REROUTE_COOLDOWN = 15000;

const MODES = {
  walk: { label:'Walking',    icon:'🚶', speed:5,  accentColor:'#00e676', offRouteM:40  },
  bike: { label:'Cycling',    icon:'🚲', speed:18, accentColor:'#00d4ff', offRouteM:60  },
  moto: { label:'Motorcycle', icon:'🏍️', speed:45, accentColor:'#ffea00', offRouteM:80  },
  car:  { label:'Driving',    icon:'🚗', speed:60, accentColor:'#ff6b35', offRouteM:80  },
};

// ─── STATE ────────────────────────────────
let map, userMarker, routingControl, watchId, destMarker;
let compassHeading = 0, lastPosition = null;
let routeCoords = [], routeInstructions = [];
let currentStepIdx = 0;
let isNavigating = false, isOffRoute = false, isMapLocked = true;
let lastRerouteTime = 0;
let arrowLayers = [];
let selectedMode = 'walk';
let centers = [];
let userLoc = null;

// ─── INIT ─────────────────────────────────
function initApp() {
  document.getElementById('splash').classList.add('hide');
  setTimeout(() => {
    document.getElementById('splash').style.display = 'none';
    initMap();
    initCompass();
    document.getElementById('bottomPanel').classList.add('show');
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
          loadCenters(); // still load centers without distance sorting
        },
        { enableHighAccuracy: true }
      );
    } else {
      document.getElementById('centerList').textContent =
        'Geolocation not supported on this device.';
      loadCenters();
    }
  }, 600);
}

function initMap() {
  map = L.map('map', { zoomControl: false, maxZoom: 20 }).setView([destLat, destLon], 15);
  L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
    attribution: '© CARTO © OSM', subdomains: 'abcd', maxZoom: 20
  }).addTo(map);

  updateDestinationMarker();

  map.on('dragstart', () => { isMapLocked = false; });
}

// ─── CENTER SELECTION ─────────────────────
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
        centers.sort((a, b) => (a.distanceM || Infinity) - (b.distanceM || Infinity));
      }

      if (!centers.length) {
        listEl.textContent = 'No available evacuation centers at the moment.';
        return;
      }

      const frag = document.createDocumentFragment();
      centers.forEach(c => {
        const km = c.distanceM != null ? (c.distanceM / 1000).toFixed(2) : '–';
        const div = document.createElement('div');
        div.className = 'center-item';
        div.onclick = () => chooseCenter(c.id);
        div.innerHTML = `
          <div class="center-main">
            <div class="center-name">${c.name}</div>
            <div class="center-sub">${c.barangay}</div>
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

      // Auto-select nearest center initially
      chooseCenter(centers[0].id, false);
    })
    .catch(err => {
      listEl.textContent = 'Unable to load centers: ' + err.message;
    });
}

function chooseCenter(centerId, speakIt = true) {
  const center = centers.find(c => c.id === centerId);
  if (!center) return;
  destLat = center.lat;
  destLon = center.lng;
  destName = center.name;

  document.getElementById('destName').textContent =
    `📍 ${center.name} (${center.barangay})`;

  updateDestinationMarker();

  if (userLoc) {
    updatePreview(userLoc.lat, userLoc.lon);
  }

  if (speakIt) {
    speak('Destination set to ' + center.name);
  }
}

function updateDestinationMarker() {
  if (!map) return;
  const destIcon = L.divIcon({
    className: '',
    html: `<div class="dest-pin-head"><span class="dest-pin-icon">🏫</span></div>`,
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

// ─── MODE SELECTOR ────────────────────────
function selectMode(mode) {
  if (isNavigating) return;
  selectedMode = mode;
  document.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('active'));
  document.querySelector(`[data-mode="${mode}"]`).classList.add('active');
  // Swap accent color to match mode
  document.documentElement.style.setProperty('--accent', MODES[mode].accentColor);
  if (lastPosition) updatePreview(lastPosition.lat, lastPosition.lon);
}

function updatePreview(lat, lon) {
  const dist = getDist(lat, lon, destLat, destLon);
  const km = (dist / 1000).toFixed(2);
  const spd = MODES[selectedMode].speed;
  const eta = Math.round((dist / 1000) / spd * 60);
  document.getElementById('previewDist').textContent = km;
  document.getElementById('previewTime').textContent = eta;
  document.getElementById('previewSpeed').textContent = spd;
  document.getElementById('remainDist').textContent = `~${km} km · ~${eta} min`;
}

// ─── COMPASS ──────────────────────────────
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
  document.getElementById('compassLabel').style.color = (h < 20 || h > 340) ? '#ff1744' : '#8892b0';
}

// ─── START / STOP ─────────────────────────
function startNavigation() {
  if (!navigator.geolocation) return alert('Geolocation not supported');
  isNavigating = true;
  document.querySelectorAll('.mode-btn').forEach(b => { b.style.opacity='0.45'; b.style.pointerEvents='none'; });
  document.getElementById('startBtn').style.display = 'none';
  document.getElementById('stopBtn').style.display = 'block';
  document.getElementById('dirCard').classList.add('show');
  document.getElementById('turnInstruction').textContent = `${MODES[selectedMode].icon} Getting location…`;

  watchId = navigator.geolocation.watchPosition(onPosition, onGeoError, {
    enableHighAccuracy: true, maximumAge: 0, timeout: 8000
  });
}

function stopNavigation() {
  isNavigating = false;
  if (watchId) navigator.geolocation.clearWatch(watchId);
  if (routingControl) { map.removeControl(routingControl); routingControl = null; }
  clearLayers();

  document.querySelectorAll('.mode-btn').forEach(b => { b.style.opacity='1'; b.style.pointerEvents='auto'; });
  document.getElementById('startBtn').style.display = 'block';
  document.getElementById('stopBtn').style.display = 'none';
  document.getElementById('dirCard').classList.remove('show');
  document.getElementById('offrouteBanner').classList.remove('show');
  document.getElementById('turnInstruction').textContent = 'Head toward destination';
  document.getElementById('stepDist').textContent = 'Calculating…';
  document.getElementById('etaMin').textContent = '--';
  currentStepIdx = 0; isOffRoute = false; routeCoords = []; routeInstructions = [];
}

// ─── POSITION UPDATE ──────────────────────
function onPosition(pos) {
  const lat = pos.coords.latitude;
  const lon = pos.coords.longitude;
  const speed = pos.coords.speed ? (pos.coords.speed * 3.6) : 0;

  // Speed display + color
  const sv = document.getElementById('speedVal');
  sv.textContent = Math.round(speed);
  const modeSpd = MODES[selectedMode].speed;
  sv.style.color = speed < 3 ? 'var(--text)' : speed < modeSpd ? 'var(--green)' : speed < modeSpd * 1.4 ? 'var(--yellow)' : 'var(--red)';

  // User location dot
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

  // Rotate pip (direction indicator) based on movement bearing
  if (lastPosition) {
    const bearing = getBearing(lastPosition.lat, lastPosition.lon, lat, lon);
    const el = userMarker.getElement();
    if (el) {
      const wrap = el.querySelector('.user-dot-wrap');
      if (wrap) wrap.style.transform = `rotate(${bearing}deg)`;
    }
  }

  if (isMapLocked) map.setView([lat, lon], 17, { animate: true, duration: 0.8 });

  // Off-route detection
  if (routeCoords.length > 0) {
    const offDist = distanceToRoute(lat, lon, routeCoords);
    if (offDist > (MODES[selectedMode].offRouteM)) {
      triggerOffRoute(lat, lon);
    } else if (isOffRoute) {
      isOffRoute = false;
      document.getElementById('offrouteBanner').classList.remove('show');
    }
  }

  updateCurrentStep(lat, lon);
  createOrUpdateRoute(lat, lon);

  if (getDist(lat, lon, destLat, destLon) < 20) { onArrival(); return; }

  // ETA based on selected mode avg speed
  const remDist = getDist(lat, lon, destLat, destLon);
  const remKm = (remDist / 1000).toFixed(1);
  const eta = Math.round((remDist / 1000) / MODES[selectedMode].speed * 60);
  document.getElementById('remainDist').textContent = `${remKm} km remaining`;
  document.getElementById('etaMin').textContent = eta;
  updatePreview(lat, lon);

  lastPosition = { lat, lon };
}

// ─── ROUTE ────────────────────────────────
function createOrUpdateRoute(lat, lon) {
  if (routingControl) {
    routingControl.setWaypoints([L.latLng(lat, lon), L.latLng(destLat, destLon)]);
    return;
  }
  routingControl = L.Routing.control({
    waypoints: [L.latLng(lat, lon), L.latLng(destLat, destLon)],
    lineOptions: { styles: [{ color: 'transparent', weight: 0 }] },
    createMarker: () => null,
    addWaypoints: false, draggableWaypoints: false, fitSelectedRoutes: false, show: false
  }).addTo(map);

  routingControl.on('routesfound', e => {
    const route = e.routes[0];
    routeCoords = route.coordinates;
    routeInstructions = route.instructions || [];
    drawTrafficRoute(routeCoords);
    drawRouteArrows(routeCoords);
    updateStepDisplay();
    if (lastPosition) updatePreview(lastPosition.lat, lastPosition.lon);
  });
}

function drawTrafficRoute(coords) {
  arrowLayers.filter(l => l._isRoute).forEach(l => map.removeLayer(l));

  // Shadow/border
  const border = L.polyline(coords.map(c => [c.lat, c.lng]), {
    color: 'rgba(0,0,0,0.4)', weight: 12, opacity: 0.65, lineCap: 'round', lineJoin: 'round'
  });
  border._isRoute = true; border.addTo(map); border.bringToBack(); arrowLayers.push(border);

  // Traffic-colored segments
  for (let i = 1; i < coords.length; i++) {
    const p = coords[i-1], c = coords[i];
    const seed = (i * 7 + Math.round(p.lat * 1000)) % 10;
    const color = seed < 5 ? '#00e676' : seed < 8 ? '#ffea00' : '#ff1744';
    const seg = L.polyline([[p.lat,p.lng],[c.lat,c.lng]], {
      color, weight: 7, opacity: 0.92, lineCap: 'round', lineJoin: 'round'
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
      const mid = [(p.lat+c.lat)/2, (p.lng+c.lng)/2];
      const icon = L.divIcon({
        className: '',
        html: `<svg width="16" height="16" viewBox="0 0 16 16" style="transform:rotate(${bearing}deg);filter:drop-shadow(0 1px 3px rgba(0,0,0,0.8))">
          <polygon points="8,1 14,13 8,10 2,13" fill="white" opacity="0.85"/>
        </svg>`,
        iconSize: [16,16], iconAnchor: [8,8]
      });
      const m = L.marker(mid, { icon, zIndexOffset: -50, interactive: false });
      m._isArrow = true; m.addTo(map); arrowLayers.push(m);
    }
  }
}

function clearLayers() { arrowLayers.forEach(l => map.removeLayer(l)); arrowLayers = []; }

// ─── TURN INSTRUCTIONS ────────────────────
const TURN_SVG = {
  Straight:    `<path d="M12 3v15M12 3L7 8M12 3L17 8" stroke="black" stroke-width="2.5" stroke-linecap="round" fill="none"/>`,
  Right:       `<path d="M7 20 Q7 10 17 5M17 5l-4 1M17 5l-1 4" stroke="black" stroke-width="2.5" stroke-linecap="round" fill="none"/>`,
  SlightRight: `<path d="M8 20 Q10 8 17 6M17 6l-4 0M17 6l0 4" stroke="black" stroke-width="2.5" stroke-linecap="round" fill="none"/>`,
  SharpRight:  `<path d="M5 20 Q14 16 17 5M17 5l-4 2M17 5l-2 4" stroke="black" stroke-width="2.5" stroke-linecap="round" fill="none"/>`,
  Left:        `<path d="M17 20 Q17 10 7 5M7 5l4 1M7 5l1 4" stroke="black" stroke-width="2.5" stroke-linecap="round" fill="none"/>`,
  SlightLeft:  `<path d="M16 20 Q14 8 7 6M7 6l4 0M7 6l0 4" stroke="black" stroke-width="2.5" stroke-linecap="round" fill="none"/>`,
  SharpLeft:   `<path d="M19 20 Q10 16 7 5M7 5l4 2M7 5l2 4" stroke="black" stroke-width="2.5" stroke-linecap="round" fill="none"/>`,
  Dest:        `<circle cx="12" cy="12" r="7" fill="none" stroke="black" stroke-width="2.5"/><circle cx="12" cy="12" r="3" fill="black"/>`,
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
  document.getElementById('stepDist').textContent = `In ${Math.round(step.distance)} m`;
  const type = getTurnType(step.text);
  document.getElementById('turnArrowSvg').innerHTML = TURN_SVG[type] || TURN_SVG.Straight;
  const isArrival = type === 'Dest';
  document.getElementById('turnArrowBox').style.background = isArrival
    ? 'linear-gradient(135deg,#00e676,#00a844)'
    : `linear-gradient(135deg,${MODES[selectedMode].accentColor},#0099cc)`;
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

// ─── OFF-ROUTE ────────────────────────────
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

// ─── ARRIVAL ──────────────────────────────
function onArrival() {
  speak(`You have arrived! Great ${MODES[selectedMode].label.toLowerCase()}!`);
  stopNavigation();
  document.getElementById('arrivalOverlay').classList.add('show');
}
function closeArrival() { document.getElementById('arrivalOverlay').classList.remove('show'); }

// ─── RECENTER ─────────────────────────────
function recenter() {
  isMapLocked = true;
  if (userMarker) map.flyTo(userMarker.getLatLng(), 17, { duration: 0.8 });
}

// ─── SPEECH ───────────────────────────────
function speak(text) {
  if (!window.speechSynthesis) return;
  window.speechSynthesis.cancel();
  const u = new SpeechSynthesisUtterance(text);
  u.lang = 'en-US'; u.rate = 1.05;
  window.speechSynthesis.speak(u);
}

// ─── MATH ─────────────────────────────────
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
</script>
</body>
</html>

