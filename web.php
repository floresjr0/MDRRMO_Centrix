<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
<title>MDRRMO San Ildefonso · Ligtas na Bayan</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&family=Share+Tech+Mono&display=swap" rel="stylesheet">
<style>
*, *::before, *::after {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

:root {
  --primary: #d63e2c;
  --primary-dark: #b33222;
  --primary-light: #e86858;
  --accent: #e67e22;
  --accent-dark: #c06518;
  --accent-deep: #9a4d12;
  --accent-footer: #8b4513;
  --accent-footer-dark: #5c2d0c;
  --dark: #1a1e2b;
  --dark-2: #222738;
  --dark-3: #2c3145;
  --dark-card: #eef2f7;
  --border: rgba(214,62,44,0.2);
  --border-faint: rgba(0,0,0,0.06);
  --gray: #6b7280;
  --gray-light: #9ca3af;
  --white: #ffffff;
  --bg-light: #f8fafc;
}

html { scroll-behavior: smooth; }

body {
  font-family: 'DM Sans', sans-serif;
  background: var(--bg-light);
  color: var(--dark);
  line-height: 1.55;
  overflow-x: hidden;
}

body::before {
  content: '';
  position: fixed;
  inset: 0;
  background-image:
    linear-gradient(rgba(214,62,44,0.02) 1px, transparent 1px),
    linear-gradient(90deg, rgba(214,62,44,0.02) 1px, transparent 1px);
  background-size: 60px 60px;
  pointer-events: none;
  z-index: 0;
}

.container {
  max-width: 1300px;
  margin: 0 auto;
  padding: 0 48px;
  position: relative;
  z-index: 2;
}

.header {
  position: sticky;
  top: 0;
  z-index: 100;
  background: transparent;
  backdrop-filter: none;
  border-bottom: 1px solid transparent;
  transition: all 0.3s ease;
}

.header.scrolled {
  background: rgba(255,255,255,0.85);
  backdrop-filter: blur(16px);
  border-bottom-color: rgba(214,62,44,0.12);
  box-shadow: 0 4px 20px rgba(0,0,0,0.02);
}

.header-inner {
  max-width: 1300px;
  margin: 0 auto;
  padding: 14px 48px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.logo {
  display: flex;
  align-items: center;
  gap: 14px;
  text-decoration: none;
  flex-shrink: 0;
}

.logo-mark {
  width: 48px;
  height: 48px;
  background: linear-gradient(135deg, var(--primary), var(--accent));
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(214,62,44,0.2);
}

.logo-mark img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 50%;
}

.logo-mark-fallback {
  font-family: 'Rajdhani', sans-serif;
  font-weight: 800;
  font-size: 1.3rem;
  color: white;
}

.logo-text {
  display: flex;
  flex-direction: column;
}

.logo-text h1 {
  font-family: 'Rajdhani', sans-serif;
  font-size: 1.3rem;
  font-weight: 800;
  letter-spacing: 2px;
  color: var(--primary);
  line-height: 1.2;
}

.logo-text p {
  font-size: 0.58rem;
  color: var(--gray);
  font-weight: 600;
  letter-spacing: 1px;
  text-transform: uppercase;
  margin-top: 2px;
}

.nav-status {
  display: flex;
  align-items: center;
  gap: 6px;
  background: rgba(214,62,44,0.06);
  border: 1px solid var(--border);
  border-radius: 4px;
  padding: 5px 14px;
  font-family: 'Share Tech Mono', monospace;
  font-size: 0.65rem;
  color: var(--primary);
  letter-spacing: 1px;
}

.status-blink {
  width: 6px;
  height: 6px;
  background: var(--primary);
  border-radius: 50%;
  animation: blink 1.2s ease infinite;
  box-shadow: 0 0 4px var(--primary);
}

@keyframes blink {
  0%,100% { opacity: 1; }
  50% { opacity: 0.2; }
}

.nav-buttons {
  display: flex;
  gap: 12px;
  align-items: center;
}

.btn-login {
  background: transparent;
  border: 1px solid var(--border);
  padding: 9px 24px;
  border-radius: 4px;
  font-family: 'Rajdhani', sans-serif;
  font-weight: 700;
  font-size: 0.85rem;
  letter-spacing: 1.5px;
  color: var(--dark);
  cursor: pointer;
  transition: all 0.2s;
  text-transform: uppercase;
  display: inline-flex;
  align-items: center;
  gap: 10px;
}

.btn-login:hover {
  border-color: var(--primary);
  color: var(--primary);
  background: rgba(214,62,44,0.06);
}

.btn-signup {
  background: linear-gradient(135deg, var(--primary), var(--accent));
  border: none;
  padding: 9px 26px;
  border-radius: 4px;
  font-family: 'Rajdhani', sans-serif;
  font-weight: 800;
  font-size: 0.85rem;
  letter-spacing: 1.5px;
  color: white;
  cursor: pointer;
  text-transform: uppercase;
  transition: all 0.25s;
  clip-path: polygon(8px 0%, 100% 0%, calc(100% - 8px) 100%, 0% 100%);
}

.btn-signup:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 18px rgba(214,62,44,0.35);
}

.menu-btn {
  display: none;
  background: rgba(214,62,44,0.06);
  border: 1px solid var(--border);
  padding: 10px 12px;
  border-radius: 4px;
  cursor: pointer;
  color: var(--primary);
  font-size: 1.1rem;
}

.mobile-menu {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(255,255,255,0.98);
  backdrop-filter: blur(30px);
  z-index: 1000;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 24px;
  padding-bottom: 60px;
}

.mobile-menu.open { display: flex; }

.mobile-close {
  position: absolute;
  top: 24px;
  right: 24px;
  background: rgba(214,62,44,0.08);
  border: 1px solid var(--border);
  width: 44px;
  height: 44px;
  border-radius: 4px;
  font-size: 18px;
  cursor: pointer;
  color: var(--primary);
}

.mobile-menu a {
  font-family: 'Rajdhani', sans-serif;
  font-size: 2rem;
  font-weight: 800;
  letter-spacing: 4px;
  text-transform: uppercase;
  text-decoration: none;
  color: var(--dark);
  transition: color 0.2s;
}

.mobile-menu a:hover { color: var(--primary); }

.mobile-copyright {
  position: absolute;
  bottom: 30px;
  left: 0;
  right: 0;
  text-align: center;
  font-family: 'Share Tech Mono', monospace;
  font-size: 0.7rem;
  color: var(--gray);
  letter-spacing: 1px;
}

.hero {
  padding: 80px 0 100px;
  position: relative;
  overflow: hidden;
  min-height: 90vh;
  display: flex;
  align-items: center;
  background: linear-gradient(145deg, #ffffff, #f0f4f8);
}

.particle {
  position: absolute;
  background: rgba(214,62,44,0.06);
  border-radius: 50%;
  pointer-events: none;
  animation: floatParticle 12s ease-in-out infinite;
}

@keyframes floatParticle {
  0%,100% { transform: translateY(0) translateX(0) rotate(0deg); opacity: 0.15; }
  50% { transform: translateY(-40px) translateX(20px) rotate(180deg); opacity: 0.4; }
}

.particle-1 { width: 80px; height: 80px; top: 15%; left: 5%; animation-duration: 14s; }
.particle-2 { width: 50px; height: 50px; bottom: 20%; right: 8%; animation-duration: 10s; animation-delay: 2s; }
.particle-3 { width: 35px; height: 35px; top: 40%; right: 20%; animation-duration: 8s; animation-delay: 1s; }
.particle-4 { width: 60px; height: 60px; bottom: 30%; left: 12%; animation-duration: 16s; animation-delay: 3s; }
.particle-5 { width: 25px; height: 25px; top: 60%; left: 25%; animation-duration: 7s; animation-delay: 0s; }
.particle-6 { width: 45px; height: 45px; top: 20%; right: 15%; animation-duration: 11s; animation-delay: 4s; }

.hero-ambient {
  position: absolute;
  top: -200px;
  right: -200px;
  width: 800px;
  height: 800px;
  background: radial-gradient(ellipse, rgba(214,62,44,0.05), transparent 65%);
  pointer-events: none;
}

.hero-ambient-2 {
  position: absolute;
  bottom: -100px;
  left: -100px;
  width: 600px;
  height: 600px;
  background: radial-gradient(ellipse, rgba(230,126,34,0.03), transparent 65%);
  pointer-events: none;
}

.hero-corner {
  position: absolute;
  top: 40px;
  left: 0;
  width: 180px;
  height: 180px;
  border-top: 1px solid rgba(214,62,44,0.12);
  border-left: 1px solid rgba(214,62,44,0.12);
  pointer-events: none;
}

.hero-corner-br {
  position: absolute;
  bottom: 40px;
  right: 0;
  width: 180px;
  height: 180px;
  border-bottom: 1px solid rgba(214,62,44,0.1);
  border-right: 1px solid rgba(214,62,44,0.1);
  pointer-events: none;
}

.hero-grid {
  display: grid;
  grid-template-columns: 1fr 420px;
  gap: 70px;
  align-items: center;
  position: relative;
  z-index: 2;
}

.hero-badge {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  background: rgba(214,62,44,0.06);
  border: 1px solid var(--border);
  border-radius: 4px;
  padding: 6px 16px 6px 10px;
  font-family: 'Share Tech Mono', monospace;
  font-size: 0.68rem;
  color: var(--primary);
  letter-spacing: 1.5px;
  margin-bottom: 30px;
  text-transform: uppercase;
  clip-path: polygon(6px 0%, 100% 0%, calc(100% - 6px) 100%, 0% 100%);
}

.badge-dot {
  width: 7px;
  height: 7px;
  background: var(--primary);
  border-radius: 50%;
  animation: blink 1.8s infinite;
}

.hero-title {
  font-family: 'Rajdhani', sans-serif;
  font-size: clamp(3rem, 5.5vw, 4.8rem);
  font-weight: 800;
  line-height: 1.08;
  letter-spacing: 1.5px;
  margin-bottom: 22px;
  text-transform: uppercase;
  color: var(--dark);
}

.gradient-text {
  background: linear-gradient(110deg, var(--primary), var(--accent));
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
  display: block;
  margin-top: 6px;
  font-size: 0.85em;
  letter-spacing: 3px;
  font-weight: 800;
}

.hero-desc {
  font-size: 1rem;
  color: var(--gray);
  max-width: 480px;
  margin-bottom: 36px;
  line-height: 1.75;
  font-weight: 400;
}

.hero-buttons {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
  margin-bottom: 52px;
}

.btn-primary-lg {
  background: linear-gradient(135deg, var(--primary), var(--accent));
  color: white;
  font-family: 'Rajdhani', sans-serif;
  font-weight: 800;
  font-size: 0.95rem;
  letter-spacing: 2px;
  text-transform: uppercase;
  padding: 14px 36px;
  border: none;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 10px;
  transition: all 0.25s;
  clip-path: polygon(10px 0%, 100% 0%, calc(100% - 10px) 100%, 0% 100%);
}

.btn-primary-lg:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 25px rgba(214,62,44,0.35);
}

.btn-outline-lg {
  background: transparent;
  color: var(--dark);
  font-family: 'Rajdhani', sans-serif;
  font-weight: 800;
  font-size: 0.95rem;
  letter-spacing: 2px;
  text-transform: uppercase;
  padding: 14px 36px;
  border: 1.5px solid rgba(0,0,0,0.12);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 10px;
  transition: all 0.2s;
  clip-path: polygon(10px 0%, 100% 0%, calc(100% - 10px) 100%, 0% 100%);
}

.btn-outline-lg:hover {
  border-color: var(--primary);
  color: var(--primary);
  background: rgba(214,62,44,0.04);
}

.stats {
  display: flex;
  gap: 0;
  border: 1px solid var(--border-faint);
  border-radius: 4px;
  overflow: hidden;
  background: white;
  box-shadow: 0 2px 8px rgba(0,0,0,0.02);
}

.stat-item {
  flex: 1;
  padding: 18px 24px;
  border-right: 1px solid var(--border-faint);
}

.stat-item:last-child { border-right: none; }

.stat-number {
  font-family: 'Rajdhani', sans-serif;
  font-size: 2rem;
  font-weight: 800;
  color: var(--primary);
  line-height: 1;
  letter-spacing: 1px;
}

.stat-label {
  font-size: 0.65rem;
  color: var(--gray);
  font-weight: 600;
  margin-top: 6px;
  letter-spacing: 1px;
  text-transform: uppercase;
}

.phone-container {
  position: relative;
  display: flex;
  justify-content: center;
  align-items: center;
}

.phone-glow-ring {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 400px;
  height: 400px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(214,62,44,0.1), transparent 70%);
  animation: pulseGlow 3s ease-in-out infinite;
  pointer-events: none;
  z-index: 0;
}

@keyframes pulseGlow {
  0%,100% { opacity: 0.3; transform: translate(-50%, -50%) scale(1); }
  50% { opacity: 0.7; transform: translate(-50%, -50%) scale(1.05); }
}

.iphone-frame {
  width: 300px;
  background: #1a1e2b;
  border-radius: 50px;
  padding: 12px;
  border: 1px solid rgba(255,255,255,0.08);
  box-shadow: 0 35px 60px rgba(0,0,0,0.25), 0 0 0 1px rgba(214,62,44,0.15);
  position: relative;
  z-index: 10;
  transition: transform 0.2s ease;
}

.iphone-notch {
  position: absolute;
  top: 0;
  left: 50%;
  transform: translateX(-50%);
  width: 110px;
  height: 28px;
  background: #1a1e2b;
  border-radius: 0 0 20px 20px;
  z-index: 15;
}

.iphone-screen {
  border-radius: 42px;
  height: 600px;
  overflow: hidden;
  position: relative;
  background: linear-gradient(145deg, #eef2f7, #e0e6ed);
}

.screen-img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: top;
  display: block;
}

.screen-fallback {
  position: absolute;
  inset: 0;
  display: none;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background: linear-gradient(160deg, #eef2f7, #e0e6ed);
  text-align: center;
  gap: 16px;
  padding: 30px;
}

.fallback-icon {
  width: 75px;
  height: 75px;
  background: linear-gradient(135deg, var(--primary), var(--accent));
  border-radius: 22px;
  display: flex;
  align-items: center;
  justify-content: center;
  clip-path: polygon(12px 0%, 100% 0%, calc(100% - 12px) 100%, 0% 100%);
}

.fallback-icon svg {
  width: 36px;
  height: 36px;
  stroke: white;
}

.screen-fallback h4 {
  font-family: 'Rajdhani', sans-serif;
  color: var(--dark);
  font-size: 1.3rem;
  font-weight: 800;
  letter-spacing: 2px;
  text-transform: uppercase;
}

.screen-fallback p {
  color: var(--gray);
  font-size: 0.8rem;
  font-weight: 500;
}

.fallback-chip {
  background: rgba(214,62,44,0.1);
  border: 1px solid var(--border);
  border-radius: 40px;
  padding: 6px 18px;
  font-family: 'Share Tech Mono', monospace;
  font-size: 0.65rem;
  color: var(--primary);
  font-weight: 600;
}

.float-card {
  position: absolute;
  background: white;
  border: 1px solid var(--border-faint);
  border-radius: 12px;
  padding: 14px 18px;
  backdrop-filter: blur(20px);
  box-shadow: 0 15px 35px rgba(0,0,0,0.12);
  animation: floatCard 5s ease-in-out infinite;
  z-index: 20;
}

@keyframes floatCard {
  0%,100% { transform: translateY(0); }
  50% { transform: translateY(-10px); }
}

.notif-card {
  top: 30px;
  left: -95px;
  width: 200px;
  background: white;
  z-index: 25;
  border-left: 3px solid var(--primary);
}

.notif-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
}

.notif-dot-red {
  width: 7px;
  height: 7px;
  background: var(--primary);
  border-radius: 50%;
  animation: blink 1.2s infinite;
}

.notif-label {
  font-family: 'Share Tech Mono', monospace;
  font-size: 0.58rem;
  font-weight: 800;
  color: var(--primary);
  letter-spacing: 1.5px;
  text-transform: uppercase;
}

.notif-title {
  font-weight: 700;
  font-size: 0.8rem;
  margin-bottom: 5px;
  color: var(--dark);
}

.notif-time {
  font-family: 'Share Tech Mono', monospace;
  font-size: 0.58rem;
  color: var(--gray);
}

.qr-card {
  bottom: 40px;
  right: -95px;
  width: 135px;
  text-align: center;
  padding: 12px 10px;
  cursor: pointer;
  animation: floatCard2 4.5s ease infinite;
  background: white;
  z-index: 25;
  border-top: 2px solid var(--accent);
}

@keyframes floatCard2 {
  0%,100% { transform: translateY(0) rotate(2deg); }
  50% { transform: translateY(-10px) rotate(1deg); }
}

.qr-badge {
  background: linear-gradient(135deg, var(--accent-dark), var(--accent-deep));
  color: white;
  font-family: 'Rajdhani', sans-serif;
  font-size: 0.62rem;
  font-weight: 800;
  letter-spacing: 1px;
  padding: 4px 12px;
  border-radius: 4px;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  margin-bottom: 10px;
  text-transform: uppercase;
  clip-path: polygon(6px 0%, 100% 0%, calc(100% - 6px) 100%, 0% 100%);
}

.qr-box {
  width: 85px;
  height: 85px;
  margin: 0 auto 8px;
  background: rgba(214,62,44,0.04);
  border: 1px solid var(--border);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.qr-text {
  font-family: 'Share Tech Mono', monospace;
  font-size: 0.58rem;
  color: var(--gray);
  letter-spacing: 0.5px;
  text-transform: uppercase;
  font-weight: 600;
}

.section {
  padding: 100px 0;
  background: white;
  position: relative;
}

.section-tag {
  font-family: 'Share Tech Mono', monospace;
  font-size: 0.7rem;
  font-weight: 700;
  color: var(--primary);
  letter-spacing: 3px;
  text-transform: uppercase;
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 18px;
}

.section-tag::before {
  content: '//';
  color: var(--accent-dark);
  font-weight: 800;
}

.section-title {
  font-family: 'Rajdhani', sans-serif;
  font-size: clamp(2rem, 3.5vw, 2.9rem);
  font-weight: 800;
  letter-spacing: 1px;
  margin-bottom: 12px;
  text-transform: uppercase;
  line-height: 1.1;
  color: var(--dark);
}

.section-sub {
  color: var(--gray);
  font-size: 0.95rem;
  max-width: 480px;
  margin-bottom: 60px;
  font-weight: 400;
  line-height: 1.75;
}

.services-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 2px;
  background: var(--border-faint);
  border: 1px solid var(--border-faint);
}

.service-card {
  background: var(--dark-card);
  padding: 36px 30px;
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}

.service-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: linear-gradient(90deg, var(--primary), var(--accent));
  transform: scaleX(0);
  transform-origin: left;
  transition: transform 0.4s ease;
}

.service-card:hover {
  background: #e8ecf3;
  transform: translateY(-4px);
}

.service-card:hover::before {
  transform: scaleX(1);
}

.service-icon {
  width: 52px;
  height: 52px;
  background: rgba(214,62,44,0.06);
  border: 1px solid var(--border);
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 26px;
  transition: all 0.3s;
  clip-path: polygon(6px 0%, 100% 0%, calc(100% - 6px) 100%, 0% 100%);
}

.service-icon svg {
  stroke: var(--primary);
  transition: stroke 0.2s;
  width: 24px;
  height: 24px;
}

.service-card:hover .service-icon svg {
  stroke: var(--accent);
}

.service-card h3 {
  font-family: 'Rajdhani', sans-serif;
  font-size: 1.2rem;
  font-weight: 800;
  letter-spacing: 1px;
  margin-bottom: 12px;
  text-transform: uppercase;
  color: var(--dark);
}

.service-card p {
  font-size: 0.85rem;
  color: var(--gray);
  line-height: 1.7;
  margin-bottom: 20px;
}

.service-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: rgba(214,62,44,0.06);
  border: 1px solid var(--border);
  border-radius: 4px;
  padding: 4px 12px;
  font-family: 'Share Tech Mono', monospace;
  font-size: 0.65rem;
  font-weight: 700;
  color: var(--primary);
  letter-spacing: 1px;
}

.steps-section {
  background: var(--bg-light);
  padding: 100px 0;
  position: relative;
}

.steps-flow {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 2px;
  background: var(--border-faint);
  border: 1px solid var(--border-faint);
  margin-top: 52px;
}

.step-item {
  background: white;
  padding: 28px 22px;
  text-align: center;
  transition: all 0.25s;
  position: relative;
}

.step-item::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  height: 2px;
  background: linear-gradient(90deg, var(--primary), var(--accent));
  transform: scaleX(0);
  transform-origin: left;
  transition: transform 0.35s ease;
}

.step-item:hover::after { transform: scaleX(1); }
.step-item:hover { background: #f5f7fb; }

.step-number-circle {
  width: 44px;
  height: 44px;
  background: rgba(214,62,44,0.08);
  border: 1px solid var(--border);
  color: var(--primary);
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: 'Rajdhani', sans-serif;
  font-weight: 800;
  font-size: 1.3rem;
  margin: 0 auto 18px;
  clip-path: polygon(4px 0%, 100% 0%, calc(100% - 4px) 100%, 0% 100%);
  transition: all 0.3s;
}

.step-item:hover .step-number-circle {
  background: var(--primary);
  color: white;
}

.step-item h4 {
  font-family: 'Rajdhani', sans-serif;
  font-weight: 800;
  letter-spacing: 1px;
  margin-bottom: 8px;
  font-size: 0.95rem;
  text-transform: uppercase;
  color: var(--dark);
}

.step-item p {
  font-size: 0.75rem;
  color: var(--gray);
  line-height: 1.5;
}

.pin-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: rgba(230,126,34,0.08);
  border: 1px solid rgba(230,126,34,0.2);
  border-radius: 4px;
  padding: 3px 10px;
  font-family: 'Share Tech Mono', monospace;
  font-size: 0.6rem;
  font-weight: 700;
  color: var(--accent-dark);
  margin-top: 8px;
  letter-spacing: 1px;
  text-transform: uppercase;
}

.become-banner {
  margin-top: 24px;
  background: rgba(230,126,34,0.06);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 18px 24px;
  text-align: center;
  font-family: 'Rajdhani', sans-serif;
  font-weight: 800;
  letter-spacing: 2px;
  font-size: 0.9rem;
  text-transform: uppercase;
  color: var(--dark);
}

.become-banner span { color: var(--accent-dark); }

.footer {
  background: linear-gradient(145deg, #2d1a0a 0%, #1a0f05 100%);
  color: #e2e8f0;
  padding: 60px 0 30px;
  position: relative;
  border-top: 1px solid rgba(230,126,34,0.3);
}

.footer::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 2px;
  background: linear-gradient(90deg, transparent, var(--accent-deep), var(--accent-footer), var(--accent-deep), transparent);
  opacity: 0.8;
}

.footer-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 48px;
  padding-bottom: 40px;
  border-bottom: 1px solid rgba(230,126,34,0.2);
  max-width: 1000px;
  margin: 0 auto;
}

.footer-brand {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 18px;
}

.footer-icon {
  width: 48px;
  height: 48px;
  background: linear-gradient(135deg, var(--accent), var(--accent-dark));
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.footer-icon img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 50%;
}

.footer-icon-fallback {
  font-family: 'Rajdhani', sans-serif;
  font-weight: 800;
  font-size: 1.1rem;
  color: white;
}

.footer-brand-text {
  font-family: 'Rajdhani', sans-serif;
  font-weight: 800;
  font-size: 1.3rem;
  letter-spacing: 3px;
  color: var(--accent);
}

.footer-desc {
  font-size: 0.8rem;
  color: #c0a080;
  line-height: 1.65;
  max-width: 260px;
  font-weight: 400;
}

.footer-col h4 {
  font-family: 'Rajdhani', sans-serif;
  font-size: 0.8rem;
  font-weight: 800;
  color: var(--accent);
  letter-spacing: 2px;
  margin-bottom: 20px;
  text-transform: uppercase;
  border-left: 3px solid var(--accent);
  padding-left: 12px;
}

.social-links {
  display: flex;
  gap: 20px;
  margin-top: 16px;
}

.social-link {
  width: 40px;
  height: 40px;
  background: rgba(230,126,34,0.15);
  border: 1px solid rgba(230,126,34,0.3);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.25s;
  cursor: pointer;
  color: #e2e8f0;
}

.social-link svg {
  stroke: currentColor;
  width: 18px;
  height: 18px;
}

.social-link:hover {
  background: rgba(230,126,34,0.4);
  transform: translateY(-3px);
  border-color: var(--accent);
}

.contact-item {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 14px;
  font-size: 0.85rem;
  color: #d4b896;
}

.contact-icon {
  width: 32px;
  height: 32px;
  background: rgba(230,126,34,0.12);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.contact-icon svg {
  width: 16px;
  height: 16px;
  stroke: currentColor;
}

.hotline-num {
  font-family: 'Rajdhani', sans-serif;
  font-weight: 800;
  font-size: 1.15rem;
  letter-spacing: 1px;
  color: var(--accent);
  margin-bottom: 4px;
}

.hotline-label {
  font-size: 0.68rem;
  color: #c0a080;
  letter-spacing: 0.5px;
  font-weight: 500;
}

.hotline-item {
  margin-bottom: 16px;
}

.footer-bottom {
  padding: 24px 0 0;
  text-align: center;
  font-family: 'Share Tech Mono', monospace;
  font-size: 0.65rem;
  color: rgba(255,255,255,0.25);
  letter-spacing: 1px;
  font-weight: 500;
}

@media (max-width: 768px) {
  .footer {
    display: none;
  }
}

.modal {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.6);
  backdrop-filter: blur(12px);
  z-index: 2000;
  display: none;
  align-items: center;
  justify-content: center;
}

.modal.active { display: flex; }

.modal-content {
  background: white;
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 44px;
  max-width: 360px;
  width: 90%;
  text-align: center;
  position: relative;
  box-shadow: 0 30px 60px rgba(0,0,0,0.2);
}

.modal-content::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: linear-gradient(90deg, var(--primary), var(--accent));
}

.modal-qr {
  width: 140px;
  height: 140px;
  margin: 0 auto 22px;
  background: rgba(214,62,44,0.04);
  border: 1px solid var(--border);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.modal-content h3 {
  font-family: 'Rajdhani', sans-serif;
  font-weight: 800;
  font-size: 1.3rem;
  letter-spacing: 2px;
  text-transform: uppercase;
  color: var(--dark);
}

.modal-content p {
  color: var(--gray);
  margin-top: 8px;
  font-size: 0.85rem;
  font-weight: 500;
}

.modal-close-btn {
  background: linear-gradient(135deg, var(--primary), var(--accent));
  color: white;
  border: none;
  padding: 11px 30px;
  font-family: 'Rajdhani', sans-serif;
  font-weight: 800;
  font-size: 0.9rem;
  letter-spacing: 2px;
  text-transform: uppercase;
  margin-top: 22px;
  cursor: pointer;
  clip-path: polygon(8px 0%, 100% 0%, calc(100% - 8px) 100%, 0% 100%);
  transition: all 0.2s;
}

.modal-close-btn:hover {
  box-shadow: 0 6px 18px rgba(214,62,44,0.35);
}

.fade-up {
  opacity: 0;
  transform: translateY(30px);
  transition: opacity 0.7s cubic-bezier(0.16, 1, 0.3, 1), transform 0.7s cubic-bezier(0.16, 1, 0.3, 1);
}

.fade-up.visible {
  opacity: 1;
  transform: translateY(0);
}

@media (max-width: 1050px) {
  .hero-grid { grid-template-columns: 1fr; gap: 60px; text-align: center; }
  .hero-desc, .hero-buttons, .stats { margin-left: auto; margin-right: auto; justify-content: center; }
  .stats { max-width: 400px; }
  .services-grid { grid-template-columns: repeat(2, 1fr); }
  .steps-flow { grid-template-columns: repeat(4, 1fr); }
  .footer-grid { grid-template-columns: repeat(2, 1fr); gap: 32px; }
  .notif-card { left: -20px; top: -10px; }
  .qr-card { right: -20px; }
}

@media (max-width: 768px) {
  .container { padding: 0 24px; }
  .header-inner { padding: 12px 24px; }
  .nav-buttons { display: none; }
  .nav-status { display: none; }
  .menu-btn { display: flex; }
  .services-grid { grid-template-columns: 1fr; }
  .steps-flow { grid-template-columns: repeat(2, 1fr); }
  .iphone-frame { width: 270px; }
  .iphone-screen { height: 540px; }
  .notif-card { display: none; }
  .qr-card { right: -15px; bottom: 15px; width: 115px; }
  .qr-box { width: 70px; height: 70px; }
  .hero-corner, .hero-corner-br { display: none; }
  .particle { display: none; }
  .phone-glow-ring { width: 280px; height: 280px; }
}
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
  <a href="#" class="btn-signup" style="margin-top:16px; text-decoration:none;" onclick="closeMobileMenu()">Sign Up</a>
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
      <button class="btn-login" id="downloadBtnHeader">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v12m0 0-4-4m4 4 4-4"/><line x1="4" y1="21" x2="20" y2="21"/></svg>
        Download App
      </button>
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
          Safe Evacuation.<br>Smart Alerts.
          <span class="gradient-text">MDRRMO SAN ILDEFONSO</span>
        </h1>
        <p class="hero-desc">Mobile-based disaster alerts and evacuation guidance with real-time evacuation monitoring for San Ildefonso, Bulacan.</p>
        <div class="hero-buttons">
          <button class="btn-primary-lg" id="downloadBtnHero">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M12 3v12m0 0-4-4m4 4 4-4"/><line x1="4" y1="21" x2="20" y2="21"/></svg>
            Download App
          </button>
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
  const apkPath = 'app/CENTRIX.apk';
  const link = document.createElement('a');
  link.href = apkPath;
  link.download = 'MDRRMO_San_Ildefonso.apk';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}
document.addEventListener('DOMContentLoaded', () => {
  const headerBtn = document.getElementById('downloadBtnHeader');
  const heroBtn = document.getElementById('downloadBtnHero');
  if (headerBtn) headerBtn.addEventListener('click', (e) => { e.preventDefault(); downloadAPK(); });
  if (heroBtn) heroBtn.addEventListener('click', (e) => { e.preventDefault(); downloadAPK(); });
});

const observer = new IntersectionObserver((entries) => { entries.forEach((e) => { if (e.isIntersecting) { e.target.classList.add('visible'); observer.unobserve(e.target); } }); }, { threshold: 0.08 });
document.querySelectorAll('.fade-up').forEach(el => observer.observe(el));
document.querySelectorAll('a[href^="#"]').forEach(anchor => { anchor.addEventListener('click', function(e) { const id = this.getAttribute('href').slice(1); const target = document.getElementById(id); if (target) { e.preventDefault(); target.scrollIntoView({ behavior: 'smooth', block: 'start' }); } }); });
</script>
</body>
</html>