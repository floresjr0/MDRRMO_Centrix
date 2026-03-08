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
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.css"/>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --accent:    #d45f10;
  --red:       #c0391e;
  --red-dark:  #8c2a10;
  --orange:    #d45f10;
  --green:     #18a850;
  --yellow:    #b07800;
  --red-alert: #d01030;
  --bg:        #f4f4f2;
  --surface:   #ffffff;
  --surface2:  #f0eeeb;
  --text:      #1a1410;
  --muted:     #7a7068;
  --border:    #ddd8d0;
  --font:      'Poppins', sans-serif;
}

html, body {
  width: 100%; height: 100%;
  overflow: hidden;
  background: var(--bg);
  font-family: var(--font);
  -webkit-font-smoothing: antialiased;
  color: var(--text);
}

#app {
  width: 100%; height: 100vh;
  position: relative;
  overflow: hidden;
}

/* ==============================================
   SPLASH SCREEN
   ============================================== */
#splash {
  position: fixed;
  inset: 0;
  z-index: 999;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0;
  background:
    radial-gradient(ellipse 70% 55% at 80% 15%, rgba(180,80,10,0.75) 0%, transparent 60%),
    url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='56' height='64' viewBox='0 0 56 64'%3E%3Cpolygon points='28,2 54,16 54,48 28,62 2,48 2,16' fill='none' stroke='rgba(255,255,255,0.06)' stroke-width='1.2'/%3E%3C/svg%3E") repeat top left / 56px 64px,
    linear-gradient(155deg, #5c1800 0%, #3a0e02 50%, #0d0400 100%);
  transition: opacity 0.5s ease;
}
#splash.hide { opacity: 0; pointer-events: none; }

.splash-pulse {
  font-size: 4rem;
  animation: sealDrop 0.9s cubic-bezier(0.34,1.56,0.64,1) 0.2s both;
  margin-bottom: 1rem;
}
@keyframes sealDrop {
  0%   { transform: scale(0) rotate(-20deg); opacity: 0; }
  100% { transform: scale(1) rotate(0deg);   opacity: 1; }
}

.splash-logo {
  font-family: var(--font);
  font-size: 1.5rem;
  font-weight: 800;
  letter-spacing: 0.04em;
  color: #fff;
  text-shadow: 0 3px 18px rgba(0,0,0,0.4);
  animation: titleRise 0.7s cubic-bezier(0.25,0.46,0.45,0.94) 0.5s both;
}
.splash-sub {
  font-size: 0.80rem;
  font-weight: 400;
  color: rgba(255,255,255,0.55);
  letter-spacing: 0.08em;
  text-transform: uppercase;
  margin-top: 0.3rem;
  animation: titleRise 0.7s cubic-bezier(0.25,0.46,0.45,0.94) 0.65s both;
}
@keyframes titleRise {
  from { opacity: 0; transform: translateY(20px); }
  to   { opacity: 1; transform: translateY(0); }
}

.splash-btn {
  margin-top: 2.5rem;
  padding: 0.85rem 2.4rem;
  border: none;
  border-radius: 50px;
  background: linear-gradient(135deg, var(--red) 0%, var(--orange) 100%);
  color: #fff;
  font-family: var(--font);
  font-size: 0.90rem;
  font-weight: 700;
  letter-spacing: 0.1em;
  cursor: pointer;
  box-shadow: 0 6px 24px rgba(192,57,30,0.45);
  animation: titleRise 0.6s ease 1s both;
  transition: transform 0.15s, box-shadow 0.15s, filter 0.15s;
  position: relative;
  overflow: hidden;
}
.splash-btn::before {
  content: '';
  position: absolute;
  top: 0; left: -80%;
  width: 55%; height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.18), transparent);
  transform: skewX(-18deg);
  transition: left 0.55s ease;
}
.splash-btn:hover::before { left: 160%; }
.splash-btn:hover  { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(192,57,30,0.55); filter: brightness(1.08); }
.splash-btn:active { transform: translateY(0); }

/* ==============================================
   MAP
   ============================================== */
#map {
  position: absolute;
  inset: 0;
  z-index: 0;
}
.leaflet-routing-container { display: none !important; }

/* ==============================================
   TOP DIRECTION CARD
   ============================================== */
#dirCard {
  position: absolute;
  top: -120px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 50;
  width: calc(100% - 2rem);
  max-width: 460px;
  background: rgba(255,255,255,0.97);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border: 1px solid rgba(0,0,0,0.08);
  border-radius: 18px;
  padding: 0.75rem 1rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  box-shadow: 0 8px 32px rgba(0,0,0,0.12);
  transition: top 0.4s cubic-bezier(0.16,1,0.3,1);
}
#dirCard.show { top: 0.8rem; }

#turnArrowBox {
  width: 52px; height: 52px;
  border-radius: 14px;
  background: linear-gradient(135deg, var(--accent), #0099cc);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  box-shadow: 0 4px 14px rgba(0,0,0,0.15);
  transition: background 0.3s;
}
#turnArrowSvg path, #turnArrowSvg circle { stroke: #fff; fill: none; }
#turnArrowSvg circle:last-child { fill: #fff; }

.dir-info { flex: 1; min-width: 0; }
#turnInstruction {
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
#stepDist { font-size: 0.68rem; color: var(--muted); margin-top: 2px; }

#etaBadge {
  text-align: center;
  background: rgba(0,0,0,0.05);
  border-radius: 10px;
  padding: 0.4rem 0.7rem;
  flex-shrink: 0;
}
#etaMin  { font-size: 1.3rem; font-weight: 800; color: var(--accent); line-height: 1; }
#etaLabel { font-size: 0.58rem; color: var(--muted); }

/* ==============================================
   OFF-ROUTE BANNER
   ============================================== */
#offrouteBanner {
  position: absolute;
  top: 5rem;
  left: 50%;
  transform: translate(-50%, -20px);
  z-index: 60;
  background: linear-gradient(135deg, #e01030, #a00018);
  border-radius: 50px;
  padding: 0.55rem 1.2rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  box-shadow: 0 4px 20px rgba(220,16,48,0.35);
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.3s, transform 0.3s;
}
#offrouteBanner.show { opacity: 1; transform: translate(-50%, 0); pointer-events: all; }
.offroute-icon { font-size: 1rem; }
.offroute-text { font-size: 0.80rem; font-weight: 700; color: #fff; }
.offroute-sub  { font-size: 0.62rem; color: rgba(255,255,255,0.80); }

/* ==============================================
   COMPASS
   ============================================== */
#compassWrap {
  position: absolute;
  top: 1rem;
  right: 1rem;
  z-index: 40;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
}
#compassRing {
  width: 46px; height: 46px;
  border-radius: 50%;
  background: rgba(255,255,255,0.96);
  border: 1.5px solid var(--border);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer;
  backdrop-filter: blur(8px);
  box-shadow: 0 4px 14px rgba(0,0,0,0.10);
  transition: border-color 0.2s;
}
#compassRing:hover { border-color: var(--accent); }
#compassNeedle {
  font-size: 1.3rem;
  display: inline-block;
  transition: transform 0.15s ease-out;
}
#compassLabel {
  font-size: 0.58rem;
  font-weight: 700;
  color: var(--muted);
  letter-spacing: 0.05em;
}

/* ==============================================
   SPEED BUBBLE
   ============================================== */
#speedBubble {
  position: absolute;
  bottom: 18rem;
  left: 1rem;
  z-index: 40;
  background: rgba(255,255,255,0.96);
  border: 1.5px solid var(--border);
  border-radius: 14px;
  padding: 0.5rem 0.7rem;
  text-align: center;
  min-width: 52px;
  backdrop-filter: blur(8px);
  box-shadow: 0 4px 14px rgba(0,0,0,0.10);
  transition: bottom 0.35s cubic-bezier(0.16,1,0.3,1);
}
#speedVal  { font-size: 1.3rem; font-weight: 800; color: var(--text); line-height: 1; }
#speedUnit { font-size: 0.58rem; color: var(--muted); }

/* ==============================================
   RECENTER BUTTON
   ============================================== */
#recenterBtn {
  position: absolute;
  bottom: 18rem;
  right: 1rem;
  z-index: 40;
  width: 46px; height: 46px;
  border-radius: 50%;
  border: 1.5px solid var(--border);
  background: rgba(255,255,255,0.96);
  color: var(--text);
  font-size: 1.4rem;
  cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  backdrop-filter: blur(8px);
  box-shadow: 0 4px 14px rgba(0,0,0,0.10);
  transition: border-color 0.2s, background 0.2s, bottom 0.35s cubic-bezier(0.16,1,0.3,1);
}
#recenterBtn:hover { border-color: var(--accent); background: rgba(212,95,16,0.08); }

/* ==============================================
   PANEL SIDE TOGGLE BUTTON  ← NEW
   ============================================== */
#panelToggleBtn {
  position: absolute;
  /* vertically centered relative to the panel's top edge */
  right: 0;
  z-index: 55;
  width: 32px;
  height: 56px;
  background: linear-gradient(160deg, var(--red) 0%, var(--orange) 100%);
  border: none;
  border-radius: 10px 0 0 10px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: -3px 0 14px rgba(0,0,0,0.14);
  transition:
    bottom 0.5s cubic-bezier(0.16,1,0.3,1),
    background 0.25s,
    transform 0.2s;
  /* default: sits just above the panel handle area */
  bottom: calc(var(--panel-show-offset, 18rem) + 2px);
}
#panelToggleBtn:hover {
  background: linear-gradient(160deg, #a0220f 0%, #b84a00 100%);
  transform: scaleX(1.12);
}
#panelToggleBtn:active { transform: scaleX(0.96); }

/* Arrow SVG inside toggle */
#toggleArrow {
  width: 16px; height: 16px;
  fill: none;
  stroke: #fff;
  stroke-width: 2.5;
  stroke-linecap: round;
  stroke-linejoin: round;
  transition: transform 0.3s cubic-bezier(0.34,1.56,0.64,1);
}
/* When panel is collapsed, rotate arrow to point up */
#panelToggleBtn.collapsed #toggleArrow {
  transform: rotate(180deg);
}

/* ==============================================
   BOTTOM PANEL
   ============================================== */
#bottomPanel {
  position: absolute;
  bottom: -100%;
  left: 0; right: 0;
  z-index: 40;
  background: rgba(255,255,255,0.99);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border-radius: 24px 24px 0 0;
  border-top: 1px solid rgba(0,0,0,0.07);
  padding: 0.6rem 1.2rem 1.4rem;
  box-shadow: 0 -8px 40px rgba(0,0,0,0.10);
  transition: bottom 0.5s cubic-bezier(0.16,1,0.3,1);
  max-height: 72vh;
  overflow-y: auto;
  scrollbar-width: none;
  touch-action: none;
}
#bottomPanel::-webkit-scrollbar { display: none; }
#bottomPanel.show { bottom: 0; }
#bottomPanel.collapsed { bottom: -88%; }
#bottomPanel.no-transition { transition: none; }

/* Handle area */
.bottom-handle-wrap {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 0.3rem 0 0.5rem;
  cursor: grab;
  user-select: none;
  -webkit-user-select: none;
}
.bottom-handle-wrap:active { cursor: grabbing; }

.bottom-handle {
  width: 38px; height: 4px;
  background: rgba(0,0,0,0.15);
  border-radius: 2px;
  transition: background 0.2s, width 0.2s;
}
.bottom-handle-wrap:hover .bottom-handle {
  background: var(--accent);
  width: 48px;
}

.handle-hint {
  font-size: 0.56rem;
  font-weight: 600;
  color: var(--muted);
  letter-spacing: 0.06em;
  text-transform: uppercase;
  opacity: 0.7;
  transition: opacity 0.2s;
}
#bottomPanel.collapsed .handle-hint { opacity: 1; color: var(--accent); }

#destName {
  font-size: 0.88rem;
  font-weight: 700;
  color: var(--text);
  margin-bottom: 0.2rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
#remainDist {
  font-size: 0.70rem;
  color: var(--muted);
  margin-bottom: 0.9rem;
}

.mode-label {
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--muted);
  margin-bottom: 0.5rem;
}

#centerList {
  margin-bottom: 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  max-height: 160px;
  overflow-y: auto;
  scrollbar-width: none;
}
#centerList::-webkit-scrollbar { display: none; }

.center-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.65rem 0.9rem;
  background: var(--surface2);
  border-radius: 12px;
  border: 1.5px solid transparent;
  cursor: pointer;
  transition: border-color 0.2s, background 0.2s;
}
.center-item:hover,
.center-item.selected { border-color: var(--accent); background: rgba(212,95,16,0.07); }
.center-name { font-size: 0.78rem; font-weight: 600; color: var(--text); }
.center-sub  { font-size: 0.62rem; color: var(--muted); margin-top: 1px; }
.center-meta { text-align: right; flex-shrink: 0; }
.center-distance { font-size: 0.72rem; font-weight: 700; color: var(--accent); }
.center-status { font-size: 0.58rem; font-weight: 700; text-transform: uppercase; margin-top: 2px; }
.center-status-available { color: var(--green); }
.center-status-full      { color: var(--red-alert); }
.center-status-closed    { color: var(--muted); }

#modeSelector {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 0.4rem;
  margin-bottom: 0.8rem;
}
.mode-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.25rem;
  padding: 0.55rem 0.3rem;
  border: 1.5px solid var(--border);
  border-radius: 12px;
  background: var(--surface2);
  cursor: pointer;
  transition: border-color 0.2s, background 0.2s;
  font-family: var(--font);
}
.mode-btn.active { border-color: var(--accent); background: rgba(212,95,16,0.09); }
.mode-btn:hover  { border-color: rgba(212,95,16,0.4); }
.mode-icon { font-size: 1.1rem; }
.mode-name { font-size: 0.60rem; font-weight: 600; color: var(--text); }

.mode-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.4rem;
  margin-bottom: 0.8rem;
}
.stat-chip {
  background: var(--surface2);
  border-radius: 10px;
  padding: 0.55rem 0.4rem;
  text-align: center;
  border: 1px solid var(--border);
}
.stat-val { font-size: 1.0rem; font-weight: 800; color: var(--accent); }
.stat-lbl { font-size: 0.57rem; color: var(--muted); margin-top: 1px; }

.traffic-legend {
  display: flex;
  gap: 1rem;
  margin-bottom: 0.9rem;
}
.tleg { display: flex; align-items: center; gap: 0.35rem; font-size: 0.65rem; color: var(--muted); }
.tleg-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }

.nav-btn {
  width: 100%;
  padding: 0.90rem;
  border: none;
  border-radius: 50px;
  font-family: var(--font);
  font-size: 0.90rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  cursor: pointer;
  position: relative;
  overflow: hidden;
  transition: transform 0.15s, box-shadow 0.15s, filter 0.15s;
  margin-top: 0.4rem;
}
#startBtn {
  background: linear-gradient(135deg, var(--red) 0%, var(--orange) 100%);
  color: #fff;
  box-shadow: 0 6px 22px rgba(192,57,30,0.28);
}
#startBtn:hover  { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(192,57,30,0.40); filter: brightness(1.06); }
#startBtn:active { transform: translateY(0); }

#stopBtn {
  background: rgba(208,16,48,0.08);
  color: var(--red-alert);
  border: 1.5px solid rgba(208,16,48,0.25);
  display: none;
}
#stopBtn:hover { background: rgba(208,16,48,0.15); }

/* ==============================================
   USER / DEST MAP MARKERS
   ============================================== */
.user-dot-wrap {
  position: relative;
  width: 36px; height: 36px;
  display: flex; align-items: center; justify-content: center;
}
.user-halo {
  position: absolute;
  width: 36px; height: 36px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(212,95,16,0.25) 0%, transparent 70%);
  animation: halo 2s ease-in-out infinite;
}
@keyframes halo {
  0%,100% { transform: scale(1); opacity: 0.6; }
  50%      { transform: scale(1.4); opacity: 0.2; }
}
.user-dot {
  position: absolute;
  width: 16px; height: 16px;
  border-radius: 50%;
  background: var(--accent);
  border: 2.5px solid #fff;
  box-shadow: 0 2px 8px rgba(0,0,0,0.25);
  z-index: 2;
}
.user-pip {
  position: absolute;
  top: 2px;
  width: 6px; height: 10px;
  background: rgba(255,255,255,0.85);
  border-radius: 3px;
  z-index: 3;
  clip-path: polygon(50% 0%,100% 100%,0% 100%);
}

.dest-pin-head {
  width: 38px; height: 38px;
  border-radius: 50% 50% 50% 0;
  transform: rotate(-45deg);
  background: linear-gradient(135deg, var(--red), var(--orange));
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 4px 14px rgba(0,0,0,0.20);
}
.dest-pin-icon {
  transform: rotate(45deg);
  font-size: 1.1rem;
}

/* ==============================================
   ARRIVAL OVERLAY
   ============================================== */
#arrivalOverlay {
  position: fixed;
  inset: 0;
  z-index: 500;
  background: rgba(244,244,242,0.90);
  backdrop-filter: blur(6px);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.4s ease;
}
#arrivalOverlay.show { opacity: 1; pointer-events: all; }

.arrival-emoji  { font-size: 4rem; animation: sealDrop 0.6s cubic-bezier(0.34,1.56,0.64,1) both; }
.arrival-title  { font-size: 2rem; font-weight: 800; color: var(--text); }
.arrival-sub    { font-size: 0.85rem; color: var(--muted); }
.arrival-close  {
  margin-top: 1.2rem;
  padding: 0.75rem 2rem;
  border: none;
  border-radius: 50px;
  background: linear-gradient(135deg, var(--red), var(--orange));
  color: #fff;
  font-family: var(--font);
  font-size: 0.90rem;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 6px 22px rgba(192,57,30,0.28);
  transition: filter 0.15s;
}
.arrival-close:hover { filter: brightness(1.08); }

</style>
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

  <!-- SIDE TOGGLE BUTTON (NEW) -->
  <button id="panelToggleBtn" onclick="togglePanel()" title="Toggle panel">
    <svg id="toggleArrow" viewBox="0 0 16 16">
      <!-- Down chevron — points DOWN when panel is open (to collapse) -->
      <polyline points="3,5 8,11 13,5"/>
    </svg>
  </button>

  <!-- BOTTOM PANEL -->
  <div id="bottomPanel">
    <div class="bottom-handle-wrap" id="panelHandle">
      <div class="bottom-handle"></div>
      <div class="handle-hint" id="handleHint">drag to hide</div>
    </div>

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
      <div class="tleg"><div class="tleg-dot" style="background:#18a850"></div>Free</div>
      <div class="tleg"><div class="tleg-dot" style="background:#b07800"></div>Slow</div>
      <div class="tleg"><div class="tleg-dot" style="background:#d01030"></div>Jammed</div>
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
  walk: { label:'Walking',    icon:'🚶', speed:5,  accentColor:'#18a850', offRouteM:40  },
  bike: { label:'Cycling',    icon:'🚲', speed:18, accentColor:'#0088cc', offRouteM:60  },
  moto: { label:'Motorcycle', icon:'🏍️', speed:45, accentColor:'#b07800', offRouteM:80  },
  car:  { label:'Driving',    icon:'🚗', speed:60, accentColor:'#d45f10', offRouteM:80  },
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

// ─── PANEL DRAG ───────────────────────────
let panelCollapsed = false;
let dragStartY = 0;
let isDragging = false;

/* Helper: sync the side toggle button position & state */
function syncToggleBtn() {
  const btn = document.getElementById('panelToggleBtn');
  const panel = document.getElementById('bottomPanel');

  if (panelCollapsed) {
    btn.classList.add('collapsed');
    // Panel is nearly hidden — button floats just above the tiny peek strip
    btn.style.bottom = '5rem';
    document.getElementById('speedBubble').style.bottom = '4.2rem';
    document.getElementById('recenterBtn').style.bottom = '4.2rem';
  } else {
    btn.classList.remove('collapsed');
    // Position button near top of the panel (just below the rounded corner)
    const panelH = panel.offsetHeight || 300;
    btn.style.bottom = (panelH - 30) + 'px';
    document.getElementById('speedBubble').style.bottom = '18rem';
    document.getElementById('recenterBtn').style.bottom = '18rem';
  }
}

/* Click handler for the side toggle button */
function togglePanel() {
  snapPanel(!panelCollapsed);
}

function initPanelDrag() {
  const panel  = document.getElementById('bottomPanel');
  const handle = document.getElementById('panelHandle');
  const hint   = document.getElementById('handleHint');

  function snapPanel(collapsed) {
    window.snapPanel(collapsed);
  }

  // Tap handle to expand when collapsed
  handle.addEventListener('click', (e) => {
    if (isDragging) return;
    if (panelCollapsed) snapPanel(false);
  });

  // ── Touch drag ──
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

  // ── Mouse drag (desktop) ──
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

/* Global snapPanel so both drag and toggle button share the same logic */
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
  // Sync button after transition settles
  requestAnimationFrame(() => setTimeout(syncToggleBtn, 520));
};

// ─── INIT ─────────────────────────────────
function initApp() {
  document.getElementById('splash').classList.add('hide');
  setTimeout(() => {
    document.getElementById('splash').style.display = 'none';
    initMap();
    initCompass();
    initPanelDrag();
    window.snapPanel(false); // show panel + position toggle btn
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
  }, 600);
}

function initMap() {
  map = L.map('map', { zoomControl: false, maxZoom: 20 }).setView([destLat, destLon], 15);
  L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
    attribution: '© CARTO © OSM', subdomains: 'abcd', maxZoom: 20
  }).addTo(map);

  updateDestinationMarker();
  map.on('dragstart', () => { isMapLocked = false; });

  // Reposition toggle button when window resizes
  window.addEventListener('resize', syncToggleBtn);
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

      chooseCenter(centers[0].id, false);
      // Reposition toggle button now that panel content has height
      setTimeout(syncToggleBtn, 100);
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

  if (userLoc) updatePreview(userLoc.lat, userLoc.lon);
  if (speakIt) speak('Destination set to ' + center.name);
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
  document.getElementById('compassLabel').style.color = (h < 20 || h > 340) ? '#d01030' : '#7a7068';
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

  const border = L.polyline(coords.map(c => [c.lat, c.lng]), {
    color: 'rgba(0,0,0,0.15)', weight: 12, opacity: 0.5, lineCap: 'round', lineJoin: 'round'
  });
  border._isRoute = true; border.addTo(map); border.bringToBack(); arrowLayers.push(border);

  for (let i = 1; i < coords.length; i++) {
    const p = coords[i-1], c = coords[i];
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
      const mid = [(p.lat+c.lat)/2, (p.lng+c.lng)/2];
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

// ─── TURN INSTRUCTIONS ────────────────────
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
  document.getElementById('stepDist').textContent = `In ${Math.round(step.distance)} m`;
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