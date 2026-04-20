<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
<title>MDRRMO San Ildefonso · Ligtas na Bayan</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="./asset/css/web.css">
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&family=Share+Tech+Mono&display=swap" rel="stylesheet">
<style>

</style>
</head>
<body>

<div class="modal" id="qrModal">
  <div class="modal-content">
    <div class="modal-qr">
      <svg width="95" height="95" viewBox="0 0 100 100">
        <rect x="5" y="5" width="38" height="38" fill="rgba(214,62,44,0.7)" rx="4"/>
        <rect x="57" y="5" width="38" height="38" fill="rgba(214,62,44,0.7)" rx="4"/>
        <rect x="5" y="57" width="38" height="38" fill="rgba(214,62,44,0.7)" rx="4"/>
        <circle cx="50" cy="50" r="8" fill="var(--accent)" opacity="0.9"/>
      </svg>
    </div>
    <h3>MDRRMO Ready App</h3>
    <p>Scan to download on Android</p>
    <button class="modal-close-btn" onclick="closeModal()">Close</button>
  </div>
</div>

<div class="mobile-menu" id="mobileMenu">
  <button class="mobile-close" onclick="closeMobileMenu()">✕</button>
  <a href="#" onclick="closeMobileMenu()">Home</a>
  <a href="#services" onclick="closeMobileMenu()">Services</a>
  <a href="#steps" onclick="closeMobileMenu()">Guide</a>
  <a href="index.php" style="font-family:'Rajdhani',sans-serif;font-size:1.4rem;font-weight:800;letter-spacing:3px;text-transform:uppercase;text-decoration:none;color:var(--accent);transition:color 0.2s;" onclick="closeMobileMenu()">Website</a>
  <a href="#" class="btn-signup" style="margin-top:16px; text-decoration:none;" onclick="closeMobileMenu()">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M12 3v12m0 0-4-4m4 4 4-4"/><line x1="4" y1="21" x2="20" y2="21"/></svg>
    Download App
  </a>
  <div class="mobile-copyright">© 2026 CENTRIX - #BidaLagingHanda</div>
</div>

<header class="header" id="header">
  <div class="header-inner">
    <a href="#" class="logo">
      <div class="logo-mark">
        <img src="./img/mdrrmo.png" alt="MDRRMO" onerror="this.src='';this.parentElement.innerHTML='<div class=logo-mark-fallback>M</div>'">
      </div>
      <div class="logo-text">
        <h1>MDRRMO</h1>
        <p>San Ildefonso, Bulacan</p>
      </div>
    </a>
    <div class="nav-buttons">
<<<<<<< Updated upstream
      <!-- "Continue to Website" button — links to the main portal -->
      <a href="index.php" class="btn-website-header">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/></svg>
        Continue to Website
      </a>
      <!-- Download App button -->
      <button class="btn-login" id="downloadBtnHeader">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v12m0 0-4-4m4 4 4-4"/><line x1="4" y1="21" x2="20" y2="21"/></svg>
        Download App
      </button>
=======
      <button class="btn-login" id="downloadBtnHeader" href="download.php">Download App</button>
>>>>>>> Stashed changes
    </div>
    <button class="menu-btn" onclick="openMobileMenu()">☰</button>
  </div>
</header>

<section class="hero">
  <div class="hero-ambient"></div>
  <div class="hero-ambient-2"></div>
  <div class="hero-corner"></div>
  <div class="hero-corner-br"></div>
  <div class="particle particle-1"></div><div class="particle particle-2"></div><div class="particle particle-3"></div>
  <div class="particle particle-4"></div><div class="particle particle-5"></div><div class="particle particle-6"></div>
  <div class="container">
    <div class="hero-grid">
      <div>
        <div class="hero-badge"><span class="badge-dot"></span> Active Operations — 24/7</div>
        <h1 class="hero-title">
          Safe Evacuation.<br>Smart Alert.
          <span class="gradient-text">MDRRMO SAN ILDEFONSO</span>
        </h1>
        <p class="hero-desc">Mobile-based disaster alerts and evacuation guidance with real-time evacuation monitoring for San Ildefonso, Bulacan.</p>
        <div class="hero-buttons">
<<<<<<< Updated upstream
          <!-- Primary CTA: Download App -->
          <button class="btn-primary-lg" id="downloadBtnHero">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M12 3v12m0 0-4-4m4 4 4-4"/><line x1="4" y1="21" x2="20" y2="21"/></svg>
            Download App
          </button>
          <!-- Secondary CTA: Continue to Website -->
          <a href="index.php" class="btn-outline-lg">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/></svg>
            Continue to Website
          </a>
=======
          <!-- Download App button -->
          <button class="btn-primary-lg" id="downloadBtnHero" href="download.php">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
              <rect x="5" y="2" width="14" height="20" rx="2"/>
              <line x1="12" y1="18" x2="12.01" y2="18" stroke-width="2.5"/>
            </svg>
            Download App
          </button>

          <!-- Continue Browsing button -->
          <button class="btn-secondary-lg" id="continueBrowsingBtn" href="index.php">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"/>
              <polyline points="12 8 16 12 12 16"/>
              <line x1="8" y1="12" x2="16" y2="12"/>
            </svg>
            Continue Browsing
          </button>
        </div>
        <div class="stats">
          <div class="stat-item"><div class="stat-number">24/7</div><div class="stat-label">Operations Center</div></div>
          <div class="stat-item"><div class="stat-number">47</div><div class="stat-label">Barangays</div></div>
          <div class="stat-item"><div class="stat-number">12</div><div class="stat-label">Evac Centers</div></div>
>>>>>>> Stashed changes
        </div>
      </div>
      <div class="phone-container">
        <div class="phone-glow-ring"></div>
        <div class="float-card notif-card">
          <div class="notif-header"><span class="notif-dot-red"></span><span class="notif-label">MDRRMO Alert</span></div>
          <div class="notif-title">Weather Update: No direct threat</div>
          <div class="notif-time">2 minutes ago</div>
        </div>
        <div class="iphone-frame">
          <div class="iphone-notch"></div>
          <div class="iphone-screen">
            <img class="screen-img" src="./img/app-home.png" alt="App Preview" id="appScreenshot" onerror="this.style.display='none';document.getElementById('screenFallback').style.display='flex';">
            <div class="screen-fallback" id="screenFallback">
              <div class="fallback-icon"><svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.6"><path d="M12 2L3 7v6c0 5.25 3.75 10.15 9 11.25C17.25 23.15 21 18.25 21 13V7z"/><line x1="12" y1="9" x2="12" y2="13"/><circle cx="12" cy="16" r="0.7" fill="#fff"/></svg></div>
              <h4>MDRRMO Ready</h4>
              <p>Evacuation centers, weather & alerts</p>
              <div class="fallback-chip">📍 12 centers available</div>
            </div>
          </div>
        </div>
        <div class="float-card qr-card" onclick="openModal()">
          <div class="qr-badge">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M12 3v12m0 0-4-4m4 4 4-4"/><line x1="4" y1="21" x2="20" y2="21"/></svg>
            Free App
          </div>
          <div class="qr-box"><svg width="52" height="52" viewBox="0 0 100 100"><rect x="5" y="5" width="38" height="38" fill="rgba(214,62,44,0.6)" rx="4"/><rect x="57" y="5" width="38" height="38" fill="rgba(214,62,44,0.6)" rx="4"/><rect x="5" y="57" width="38" height="38" fill="rgba(214,62,44,0.6)" rx="4"/><circle cx="50" cy="50" r="8" fill="var(--accent)"/></svg></div>
          <div class="qr-text">Scan to download</div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="section" id="services">
  <div class="container">
    <div class="section-tag">Core Services</div>
    <h2 class="section-title">What We Offer</h2>
    <p class="section-sub">Comprehensive disaster risk reduction for every resident of San Ildefonso.</p>
    <div class="services-grid">
      <div class="service-card fade-up"><div class="service-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 2C8.686 2 6 4.686 6 8c0 5.25 6 13 6 13s6-7.75 6-13c0-3.314-2.686-6-6-6z"/><circle cx="12" cy="8" r="2.5"/></svg></div><h3>Locate Evacuation Centers</h3><p>Interactive map showing all 12 evacuation centers with real-time capacity and amenities.</p></div>
      <div class="service-card fade-up"><div class="service-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M18 8a6 6 0 00-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg></div><h3>Disaster Advisories</h3><p>Real-time official announcements for typhoons, floods, earthquakes, and other disasters.</p></div>
      <div class="service-card fade-up"><div class="service-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="5"/><line x1="12" y1="2" x2="12" y2="4"/><line x1="12" y1="20" x2="12" y2="22"/></svg></div><h3>Current Weather</h3><p>Live weather data via OpenWeatherMap — temperature, rainfall, and wind speed.</p></div>
      <div class="service-card fade-up"><div class="service-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg></div><h3>Select Intended Center</h3><p>Choose your designated evacuation center before disaster strikes — pre-register your family.</p></div>
      <div class="service-card fade-up"><div class="service-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 20h16a2 2 0 002-2V8a2 2 0 00-2-2H4a2 2 0 00-2 2v10a2 2 0 002 2z"/><path d="M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/><line x1="12" y1="11" x2="12" y2="17"/><line x1="9" y1="14" x2="15" y2="14"/></svg></div><h3>Preparedness Tips</h3><p>Automated safety checklists, emergency kit guides, and evacuation route planning.</p></div>
      <div class="service-card fade-up"><div class="service-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg></div><h3>Become As One</h3><p>Join the community resilience movement. Every citizen is a partner in disaster safety.</p></div>
    </div>
  </div>
</section>

<section class="steps-section" id="steps">
  <div class="container">
    <div class="section-tag">How to Get Started</div>
    <h2 class="section-title">Sign Up & Log In Guide</h2>
    <p class="section-sub">Follow these simple steps to access the MDRRMO mobile app and emergency portal.</p>
    <div class="steps-flow">
      <div class="step-item fade-up"><div class="step-number-circle">1</div><h4>Open the App</h4><p>Download MDRRMO Ready</p></div>
      <div class="step-item fade-up"><div class="step-number-circle">2</div><h4>Tap "Sign Up"</h4><p>Press the sign-up button</p></div>
      <div class="step-item fade-up"><div class="step-number-circle">3</div><h4>Provide Details</h4><p>Name, address, contact</p></div>
      <div class="step-item fade-up"><div class="step-number-circle">4</div><h4>Create Credentials</h4><p>Username & password</p></div>
      <div class="step-item fade-up"><div class="step-number-circle">5</div><h4>Enter Verification PIN</h4><p>6-digit code via SMS<br><span class="pin-chip"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="11" width="14" height="11" rx="2"/><path d="M8 11V8c0-2.2 1.8-4 4-4s4 1.8 4 4v3"/><circle cx="12" cy="16" r="1.5"/></svg> Verification PIN</span></p></div>
      <div class="step-item fade-up"><div class="step-number-circle">6</div><h4>Tap "Register"</h4><p>Account created</p></div>
      <div class="step-item fade-up"><div class="step-number-circle">7</div><h4>Log In</h4><p>Enter credentials</p></div>
      <div class="step-item fade-up"><div class="step-number-circle">8</div><h4>Dashboard</h4><p>Access all features</p></div>
    </div>
    <div class="become-banner fade-up">
      <span>BECOME AS ONE</span> — Be prepared and be part of a resilient community.
    </div>
  </div>
</section>

<footer class="footer">
  <div class="container">
    <div class="footer-grid">
      <div>
        <div class="footer-brand">
          <div class="footer-icon"><img src="./img/mdrrmo.png" alt="MDRRMO" onerror="this.src='';this.parentElement.innerHTML='<div class=footer-icon-fallback>M</div>'"></div>
          <div class="footer-brand-text">MDRRMO</div>
        </div>
        <p class="footer-desc">Building a disaster-resilient community through preparedness, rapid response, and shared responsibility.</p>
        <div class="social-links">
          <div class="social-link" onclick="window.open('https://facebook.com','_blank')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg></div>
          <div class="social-link" onclick="window.location.href='mailto:mdrrmo@sanildefonso.gov.ph'"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></div>
        </div>
      </div>
      <div class="footer-col">
        <h4>Hotlines</h4>
        <div class="hotline-item"><div class="hotline-num">(044) 415-9999</div><div class="hotline-label">MDRRMO Operations Center</div></div>
        <div class="hotline-item"><div class="hotline-num">9-1-1</div><div class="hotline-label">National Emergency</div></div>
      </div>
      <div class="footer-col">
        <h4>Contact</h4>
        <div class="contact-item"><div class="contact-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg></div><span>Municipal Hall Complex, San Ildefonso, Bulacan 3007</span></div>
        <div class="contact-item"><div class="contact-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.362 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.338 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg></div><span>(044) 415-9999</span></div>
        <div class="contact-item"><div class="contact-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></div><span>mdrrmo@sanildefonso.gov.ph</span></div>
      </div>
    </div>
    <div class="footer-bottom"><p>© 2026 CENTRIX · #BidaLagingHanda</p></div>
  </div>
</footer>

<script>
function openModal() { document.getElementById('qrModal').classList.add('active'); document.body.style.overflow = 'hidden'; }
function closeModal() { document.getElementById('qrModal').classList.remove('active'); document.body.style.overflow = ''; }
document.getElementById('qrModal').addEventListener('click', function(e) { if (e.target === this) closeModal(); });
function openMobileMenu() { document.getElementById('mobileMenu').classList.add('open'); document.body.style.overflow = 'hidden'; }
function closeMobileMenu() { document.getElementById('mobileMenu').classList.remove('open'); document.body.style.overflow = ''; }
window.addEventListener('scroll', function() { const header = document.getElementById('header'); if (window.scrollY > 50) header.classList.add('scrolled'); else header.classList.remove('scrolled'); });

function downloadAPK() {
<<<<<<< Updated upstream
  const apkPath = 'app/CENTRIX.apk';
=======
  // Adjust the path to match your server structure
  const apkPath = 'download.php';   // relative to the current HTML file
  // Or use an absolute path: '/app/app.apk'

  // Create a temporary anchor element
>>>>>>> Stashed changes
  const link = document.createElement('a');
  link.href = apkPath;
  link.download = 'MDRRMO_San_Ildefonso.apk';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

document.addEventListener('DOMContentLoaded', () => {
  const headerBtn = document.getElementById('downloadBtnHeader');
<<<<<<< Updated upstream
  const heroBtn = document.getElementById('downloadBtnHero');
  if (headerBtn) headerBtn.addEventListener('click', (e) => { e.preventDefault(); downloadAPK(); });
  if (heroBtn) heroBtn.addEventListener('click', (e) => { e.preventDefault(); downloadAPK(); });
=======
  const heroBtn   = document.getElementById('downloadBtnHero');
  const browseBtn = document.getElementById('continueBrowsingBtn');

  if (headerBtn) {
    headerBtn.addEventListener('click', (e) => {
      e.preventDefault();
      downloadAPK();
    });
  }

  if (heroBtn) {
    heroBtn.addEventListener('click', (e) => {
      e.preventDefault();
      downloadAPK();
    });
  }

  // Continue Browsing — smooth-scroll down to Services section
  if (browseBtn) {
    browseBtn.addEventListener('click', (e) => {
      e.preventDefault();
      const servicesSection = document.getElementById('index.php');
      if (servicesSection) {
        servicesSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  }
>>>>>>> Stashed changes
});

const observer = new IntersectionObserver((entries) => { entries.forEach((e) => { if (e.isIntersecting) { e.target.classList.add('visible'); observer.unobserve(e.target); } }); }, { threshold: 0.08 });
document.querySelectorAll('.fade-up').forEach(el => observer.observe(el));
document.querySelectorAll('a[href^="#"]').forEach(anchor => { anchor.addEventListener('click', function(e) { const id = this.getAttribute('href').slice(1); const target = document.getElementById(id); if (target) { e.preventDefault(); target.scrollIntoView({ behavior: 'smooth', block: 'start' }); } }); });
</script>
<!-- asdsad -->
</body>
</html>