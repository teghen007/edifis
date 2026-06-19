<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Parents of {{ $schoolName }} — EDIFIS</title>
  <meta name="theme-color" content="#0F2350">
  <link rel="icon" href="{{ asset('favicon.ico') }}">
  <style>
    :root{--b300:#93BBFD;--b400:#6098FA;--b500:#3B76F6;--b600:#2563EB;--b700:#1D4ED8;--b800:#1E40AF;--b950:#0F2350;--glow:#38BDF8;--ink:#0B1220;--body:#334155;--muted:#64748B;--bg:#F4F7FE;--border:#E2E8F0;--nav-h:68px;}
    *{box-sizing:border-box;margin:0;padding:0}html{scroll-behavior:smooth;overflow-x:hidden}
    body{font-family:system-ui,'Segoe UI',Roboto,sans-serif;color:var(--body);background:var(--bg);line-height:1.6;overflow-x:hidden}
    .wrap{max-width:1140px;margin:0 auto;padding:0 22px}a{text-decoration:none;color:inherit}img{max-width:100%;display:block}
    .nav{position:sticky;top:0;z-index:60;height:var(--nav-h);background:rgba(15,35,80,.82);backdrop-filter:blur(14px);border-bottom:1px solid rgba(255,255,255,.1)}
    .navflex{height:var(--nav-h);display:flex;align-items:center;justify-content:space-between}
    .brand{color:#fff;font-weight:800;font-size:1.05rem;letter-spacing:.2px}
    .hero{position:relative;color:#fff;padding:64px 0 48px;text-align:center;background:radial-gradient(1100px 420px at 84% -14%,rgba(56,189,248,.40),transparent 60%),linear-gradient(135deg,var(--b950),var(--b800) 46%,var(--b600))}
    .hero h1{font-size:clamp(1.8rem,4.5vw,2.6rem);margin-bottom:12px}
    .hero p.lead{font-size:1.1rem;opacity:.93;max-width:560px;margin:0 auto}
    .getapp{padding:74px 0;text-align:center}
    .getapp .apps{display:flex;gap:16px;justify-content:center;flex-wrap:wrap;margin:24px 0 10px}
    .store{display:inline-flex;align-items:center;gap:12px;padding:.8rem 1.3rem;border-radius:14px;background:#0F2350;color:#fff;text-decoration:none;min-width:210px;box-shadow:0 14px 30px -14px rgba(15,35,80,.6);transition:transform .15s}
    .store:hover{transform:translateY(-3px)}
    .store svg{width:30px;height:30px;flex:0 0 auto;fill:currentColor}
    .store small{display:block;font-size:.72rem;opacity:.8;text-align:left}.store b{display:block;font-size:1.05rem;text-align:left}
    .store.soon{background:#e9eef7;color:#64748B;box-shadow:none;cursor:default}
    .contactrow{display:flex;gap:14px;justify-content:center;flex-wrap:wrap;margin-top:18px}
    .chip{display:inline-flex;align-items:center;gap:9px;padding:.7rem 1.15rem;border-radius:999px;font-weight:600;text-decoration:none;font-size:.95rem}
    .chip svg{width:20px;height:20px}
    .chip.wa{background:#25D366;color:#063}.chip.mail{background:#E8F0FE;color:#1D4ED8}
    .chip:hover{filter:brightness(.96)}
    .eyebrow{color:var(--b600);font-weight:700;letter-spacing:.12em;text-transform:uppercase;font-size:.78rem}
    .title{color:var(--ink);font-size:clamp(1.6rem,4vw,2.1rem);margin:6px 0 10px;letter-spacing:-.01em}
    .sub{color:var(--muted);max-width:640px}
    footer{background:var(--b950);color:#cbd5e1;padding:40px 0;text-align:center}
    footer .pw{display:inline-flex;align-items:center;gap:9px;font-size:.88rem;color:#93BBFD}footer .pw img{height:24px}
    .back{margin-top:30px}.back a{color:var(--b600);font-weight:600}
    @media(max-width:520px){.wrap{padding:0 18px}.hero{padding:46px 0 32px}}
  </style>
</head>
<body>
  <nav class="nav"><div class="wrap navflex">
    <a class="brand" href="/">← {{ $schoolName }}</a>
  </div></nav>

  <header class="hero">
    <div class="wrap">
      <h1>Parents of {{ $schoolName }}</h1>
      <p class="lead">After installing, open the app and sign in with your phone number.</p>
    </div>
  </header>

  <section class="getapp"><div class="wrap">
    <div class="eyebrow">Get started</div>
    <h2 class="title">Download the app</h2>
    <p class="sub" style="margin:0 auto">Parents sign in on their phone — results, attendance, fees and notices in one place.</p>
    <div class="apps">
      <a class="store" href="https://myedifis.com/app.apk" download>
        <svg viewBox="0 0 24 24"><path d="M17.6 9.48l1.84-3.18a.5.5 0 10-.87-.5l-1.86 3.22a11.4 11.4 0 00-8.62 0L6.23 5.8a.5.5 0 10-.87.5L7.2 9.48A10.7 10.7 0 001 18h22a10.7 10.7 0 00-5.4-8.52zM7 15.5A1.25 1.25 0 117 13a1.25 1.25 0 010 2.5zm10 0A1.25 1.25 0 1117 13a1.25 1.25 0 010 2.5z"/></svg>
        <div><small>Download for</small><b>Android</b></div>
      </a>
      <span class="store soon">
        <svg viewBox="0 0 24 24"><path d="M16.36 1.43c0 1.14-.49 2.27-1.18 3.08-.74.9-1.98 1.57-2.98 1.57-.12 0-.23-.02-.3-.03-.01-.06-.04-.22-.04-.39 0-1.15.57-2.27 1.2-2.98.8-.94 2.14-1.64 3.25-1.68.03.13.05.28.05.43zm4.57 15.71c-.03.07-.46 1.58-1.52 3.12-.94 1.34-1.94 2.71-3.43 2.71-1.51 0-1.9-.88-3.63-.88-1.7 0-2.3.91-3.67.91-1.38 0-2.33-1.26-3.43-2.8C3.55 18.4 2.5 15.6 2.5 12.95c0-4.28 2.8-6.55 5.55-6.55 1.45 0 2.68.95 3.6.95.86 0 2.22-1.01 3.9-1.01.64 0 2.95.06 4.47 2.22-.12.07-2.62 1.53-2.62 4.57 0 3.51 3.07 4.74 3.16 4.78z"/></svg>
        <div><small>Coming soon on</small><b>iOS</b></div>
      </span>
    </div>
    <div class="contactrow">
      <a class="chip wa" href="https://wa.me/237674072084">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M.06 24l1.69-6.16a11.87 11.87 0 01-1.59-5.95C.16 5.34 5.5 0 12.05 0a11.82 11.82 0 018.41 3.49 11.82 11.82 0 013.48 8.41c0 6.56-5.34 11.89-11.89 11.89a11.9 11.9 0 01-5.69-1.45L.06 24zm6.6-3.81c1.68 1 3.28 1.59 5.39 1.59 5.45 0 9.89-4.43 9.89-9.88 0-5.46-4.42-9.89-9.88-9.89-5.45 0-9.89 4.43-9.89 9.88a9.86 9.86 0 001.59 5.3l-1 3.65 3.91-1.65zm11.39-5.46c-.07-.12-.27-.2-.57-.35-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.16-.17.2-.35.22-.64.07-.3-.15-1.26-.46-2.39-1.47-.88-.79-1.48-1.76-1.65-2.06-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.61-.92-2.21-.24-.58-.49-.5-.67-.51l-.57-.01c-.2 0-.52.07-.79.37s-1.04 1.02-1.04 2.48 1.07 2.88 1.21 3.07c.15.2 2.1 3.2 5.08 4.49.71.3 1.26.49 1.69.63.71.22 1.36.19 1.87.12.57-.09 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.41z"/></svg>
        WhatsApp 674 072 084
      </a>
      <a class="chip mail" href="mailto:noreply.myedifis@gmail.com">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
        noreply.myedifis@gmail.com
      </a>
    </div>
  </div></section>

  <footer><div class="wrap">
    <div class="pw"><img src="{{ asset('brand/logo-white.png') }}" alt="EDIFIS"> Powered by EDIFIS · GOD · KNOWLEDGE · GROWTH</div>
  </div></footer>
</body>
</html>
