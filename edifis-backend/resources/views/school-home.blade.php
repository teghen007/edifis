<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $schoolName }} — powered by EDIFIS</title>
  <meta name="theme-color" content="#0F2350">
  <link rel="icon" href="{{ asset('favicon.ico') }}">
  <style>
    :root{--b400:#6098FA;--b500:#3B76F6;--b600:#2563EB;--b700:#1D4ED8;--b800:#1E40AF;--b950:#0F2350;--glow:#38BDF8;--ink:#0B1220;--body:#334155;--muted:#64748B;--bg:#F4F7FE;--border:#E2E8F0;--nav-h:66px;}
    *{box-sizing:border-box;margin:0;padding:0}html{scroll-behavior:smooth}
    body{font-family:system-ui,Segoe UI,Roboto,sans-serif;color:var(--body);background:var(--bg);line-height:1.55}
    .wrap{max-width:1080px;margin:0 auto;padding:0 22px}a{text-decoration:none;color:inherit}
    .nav{position:sticky;top:0;z-index:50;height:var(--nav-h);background:rgba(15,35,80,.9);backdrop-filter:blur(12px);border-bottom:1px solid rgba(255,255,255,.08)}
    .navflex{height:var(--nav-h);display:flex;align-items:center;justify-content:space-between}
    .brand{color:#fff;font-weight:700;font-size:1.05rem;letter-spacing:.2px}
    .menu{display:flex;align-items:center;gap:22px}.menu a{color:#dbe8fe;font-weight:500;font-size:.95rem}
    .menu a.pill{padding:.5rem 1rem;border-radius:11px;color:#06245e;font-weight:600;background:linear-gradient(180deg,#bfe6ff,var(--glow))}
    .menu a.ghost{padding:.5rem 1rem;border-radius:11px;border:1px solid rgba(255,255,255,.35);color:#fff}
    @media(max-width:760px){.menu a.txt{display:none}}
    .hero{position:relative;overflow:hidden;color:#fff;padding:80px 0 96px;background:radial-gradient(1100px 420px at 84% -14%,rgba(56,189,248,.40),transparent 60%),linear-gradient(135deg,var(--b950),var(--b800) 46%,var(--b600));text-align:center}
    .hero .badge{display:inline-block;font-size:.7rem;letter-spacing:.18em;text-transform:uppercase;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.25);padding:.4rem .85rem;border-radius:999px}
    .hero h1{font-size:2.8rem;margin:16px 0 8px;letter-spacing:-.02em}
    .hero p.lead{font-size:1.15rem;opacity:.93;max-width:620px;margin:0 auto}
    .cta{display:flex;gap:13px;justify-content:center;margin-top:30px;flex-wrap:wrap}
    .btn{display:inline-flex;align-items:center;gap:.5rem;font-weight:600;font-size:1rem;padding:.9rem 1.5rem;border-radius:14px;transition:transform .15s}
    .btn-primary{color:#06245e;background:linear-gradient(180deg,#bfe6ff,var(--glow));box-shadow:0 10px 30px -6px rgba(56,189,248,.7)}
    .btn-primary:hover{transform:translateY(-2px)}
    .btn-ghost{color:#fff;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.38)}
    section{padding:74px 0}.title{color:var(--ink);font-size:1.7rem;text-align:center;margin-bottom:8px}
    .sub{color:var(--muted);text-align:center;max-width:620px;margin:0 auto 34px}
    .cards{display:grid;grid-template-columns:repeat(4,1fr);gap:18px}
    .card{background:#fff;border:1px solid var(--border);border-radius:16px;padding:22px;box-shadow:0 18px 44px -24px rgba(15,35,80,.4)}
    .card h3{color:var(--b800);font-size:1.02rem;margin-bottom:6px}.card p{font-size:.9rem;color:var(--muted)}
    .about{background:#fff;border-top:1px solid var(--border);border-bottom:1px solid var(--border)}
    .contact{text-align:center}.contact a.btn{margin-top:10px}
    footer{background:var(--b950);color:#cbd5e1;padding:34px 0;text-align:center}
    footer .pw{display:inline-flex;align-items:center;gap:8px;font-size:.85rem;color:#93BBFD}
    footer .pw img{height:22px;width:auto}
    @media(max-width:760px){.cards{grid-template-columns:1fr 1fr}.hero h1{font-size:2.1rem}}
    @media(max-width:480px){.cards{grid-template-columns:1fr}}
  </style>
</head>
<body>
  <nav class="nav"><div class="wrap navflex">
    <a class="brand" href="#top">{{ $schoolName }}</a>
    <div class="menu">
      <a class="txt" href="#about">About</a>
      <a class="txt" href="#services">Services</a>
      <a class="txt" href="#contact">Contact</a>
      <a class="ghost" href="/staff">Staff Login</a>
      <a class="pill" href="{{ $parentUrl ?? 'https://myedifis.com/app.apk' }}">Parent Login</a>
    </div>
  </div></nav>

  <header class="hero" id="top"><div class="wrap">
    <span class="badge">Welcome to</span>
    <h1>{{ $schoolName }}</h1>
    <p class="lead">Results, attendance, fees and school communication — all in one place, for staff and parents.</p>
    <div class="cta">
      <a class="btn btn-primary" href="{{ $parentUrl ?? 'https://myedifis.com/app.apk' }}">I'm a Parent</a>
      <a class="btn btn-ghost" href="/staff">I'm Staff</a>
    </div>
  </div></header>

  <section class="about" id="about"><div class="wrap">
    <h2 class="title">About {{ $schoolName }}</h2>
    <p class="sub">{{ $schoolName }} uses EDIFIS to keep families and staff connected — transparent results, real-time attendance, clear fees, and instant notices, online or offline.</p>
  </div></section>

  <section id="services"><div class="wrap">
    <h2 class="title">What you can do</h2>
    <p class="sub">For parents and staff of {{ $schoolName }}.</p>
    <div class="cards">
      <div class="card"><h3>Results</h3><p>View termly results and averages as soon as they're published.</p></div>
      <div class="card"><h3>Attendance</h3><p>See daily attendance and get alerts when your child is absent.</p></div>
      <div class="card"><h3>Fees</h3><p>Check balances and payment history any time.</p></div>
      <div class="card"><h3>Notices</h3><p>Get school announcements and reminders straight to your phone.</p></div>
    </div>
  </div></section>

  <section class="contact" id="contact"><div class="wrap">
    <h2 class="title">Contact {{ $schoolName }}</h2>
    <p class="sub">Reach the school office for admissions and enquiries. (Add the school's phone/email/address here.)</p>
    <a class="btn btn-primary" href="/staff">Staff sign-in</a>
  </div></section>

  <footer><div class="wrap">
    <div class="pw"><img src="{{ asset('brand/logo-white.png') }}" alt="EDIFIS"> Powered by EDIFIS · GOD · KNOWLEDGE · GROWTH</div>
  </div></footer>
</body>
</html>
