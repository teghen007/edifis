<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $schoolName }} — powered by EDIFIS</title>
  <meta name="theme-color" content="#0F2350">
  <link rel="icon" href="{{ asset('favicon.ico') }}">
  <style>
    :root{--b300:#93BBFD;--b400:#6098FA;--b500:#3B76F6;--b600:#2563EB;--b700:#1D4ED8;--b800:#1E40AF;--b950:#0F2350;--glow:#38BDF8;--ink:#0B1220;--body:#334155;--muted:#64748B;--bg:#F4F7FE;--border:#E2E8F0;--nav-h:68px;}
    *{box-sizing:border-box;margin:0;padding:0}html{scroll-behavior:smooth;overflow-x:hidden}
    body{font-family:system-ui,'Segoe UI',Roboto,sans-serif;color:var(--body);background:var(--bg);line-height:1.6;overflow-x:hidden}
    .wrap{max-width:1140px;margin:0 auto;padding:0 22px}a{text-decoration:none;color:inherit}img{max-width:100%;display:block}
    /* nav */
    .nav{position:sticky;top:0;z-index:60;height:var(--nav-h);background:rgba(15,35,80,.82);backdrop-filter:blur(14px);border-bottom:1px solid rgba(255,255,255,.1)}
    .navflex{height:var(--nav-h);display:flex;align-items:center;justify-content:space-between}
    .brand{color:#fff;font-weight:800;font-size:1.05rem;letter-spacing:.2px;max-width:62vw;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .menu{display:flex;align-items:center;gap:24px}
    .menu a.txt{color:#dbe8fe;font-weight:500;font-size:.95rem}.menu a.txt:hover{color:#fff}
    .menu a.ghost{padding:.55rem 1.05rem;border-radius:11px;border:1px solid rgba(255,255,255,.4);color:#fff;font-weight:600}
    .menu a.pill{padding:.55rem 1.1rem;border-radius:11px;color:#06245e;font-weight:700;background:linear-gradient(180deg,#cdecff,var(--glow));box-shadow:0 8px 22px -8px rgba(56,189,248,.8)}
    .burger{display:none;color:#fff;cursor:pointer;font-size:1.7rem;line-height:1}#nz{display:none}
    /* hero */
    .hero{position:relative;min-height:88vh;display:flex;align-items:center;color:#fff;overflow:hidden}
    .hero .bg{position:absolute;inset:0;background:url('{{ asset('brand/photos/hero.jpg') }}') center/cover no-repeat;transform:scale(1.05)}
    .hero .ov{position:absolute;inset:0;background:linear-gradient(115deg,rgba(15,35,80,.94) 0%,rgba(30,64,175,.86) 45%,rgba(37,99,235,.55) 100%)}
    .hero .blob{position:absolute;width:520px;height:520px;right:-120px;top:-160px;border-radius:50%;background:radial-gradient(circle,rgba(56,189,248,.45),transparent 65%);filter:blur(10px)}
    .hero .inner{position:relative;z-index:2;padding:90px 0}
    .badge{display:inline-block;font-size:.72rem;letter-spacing:.2em;text-transform:uppercase;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.3);padding:.45rem .9rem;border-radius:999px;backdrop-filter:blur(6px)}
    .hero h1{font-size:clamp(2.1rem,5.5vw,3.6rem);line-height:1.05;margin:18px 0 12px;letter-spacing:-.02em;max-width:14ch;text-shadow:0 4px 40px rgba(0,0,0,.35)}
    .hero p.lead{font-size:clamp(1rem,2.5vw,1.25rem);opacity:.95;max-width:560px}
    .cta{display:flex;gap:14px;margin-top:32px;flex-wrap:wrap}
    .btn{display:inline-flex;align-items:center;gap:.55rem;font-weight:700;font-size:1rem;padding:.95rem 1.6rem;border-radius:14px;transition:transform .18s,box-shadow .18s;cursor:pointer}
    .btn-primary{color:#06245e;background:linear-gradient(180deg,#cdecff,var(--glow));box-shadow:0 12px 34px -8px rgba(56,189,248,.8)}
    .btn-primary:hover{transform:translateY(-3px);box-shadow:0 18px 46px -8px rgba(56,189,248,.95)}
    .btn-ghost{color:#fff;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.45)}.btn-ghost:hover{background:rgba(255,255,255,.2);transform:translateY(-3px)}
    /* stats band */
    .stats{position:relative;z-index:3;margin-top:-44px}
    .statsgrid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;background:rgba(255,255,255,.92);backdrop-filter:blur(10px);border:1px solid #fff;border-radius:20px;padding:26px;box-shadow:0 30px 70px -34px rgba(15,35,80,.5)}
    .stat{text-align:center}.stat b{display:block;font-size:1.7rem;color:var(--b700);font-weight:800}.stat span{font-size:.85rem;color:var(--muted)}
    /* sections */
    section{padding:84px 0}.eyebrow{color:var(--b600);font-weight:700;letter-spacing:.12em;text-transform:uppercase;font-size:.78rem}
    .title{color:var(--ink);font-size:clamp(1.6rem,4vw,2.1rem);margin:6px 0 10px;letter-spacing:-.01em}
    .sub{color:var(--muted);max-width:640px}
    .two{display:grid;grid-template-columns:1.05fr 1fr;gap:46px;align-items:center}
    .two img{border-radius:20px;box-shadow:0 30px 60px -28px rgba(15,35,80,.55);width:100%;height:100%;object-fit:cover;max-height:420px}
    .cards{display:grid;grid-template-columns:repeat(4,1fr);gap:20px;margin-top:34px}
    .card{background:#fff;border:1px solid var(--border);border-radius:18px;padding:24px;box-shadow:0 22px 50px -28px rgba(15,35,80,.45);transition:transform .2s,box-shadow .2s}
    .card:hover{transform:translateY(-6px);box-shadow:0 34px 64px -28px rgba(15,35,80,.55)}
    .ico{width:48px;height:48px;border-radius:14px;display:grid;place-items:center;margin-bottom:14px;color:#fff;font-size:1.4rem;background:linear-gradient(135deg,var(--b600),var(--b400));box-shadow:0 10px 22px -8px rgba(37,99,235,.65)}
    .card h3{color:var(--b800);font-size:1.05rem;margin-bottom:6px}.card p{font-size:.92rem;color:var(--muted)}
    /* CTA band */
    .band{background:linear-gradient(135deg,var(--b800),var(--b600));color:#fff;border-radius:24px;padding:48px;text-align:center;position:relative;overflow:hidden;box-shadow:0 34px 80px -34px rgba(15,35,80,.6)}
    .band::after{content:"";position:absolute;inset:0;background:radial-gradient(600px 220px at 88% -20%,rgba(56,189,248,.4),transparent 60%)}
    .band h2{font-size:clamp(1.5rem,4vw,2rem);position:relative;margin-bottom:8px}.band p{opacity:.92;position:relative;margin-bottom:22px}
    /* reveal */
    .reveal{opacity:0;transform:translateY(26px);transition:opacity .7s ease,transform .7s ease}.reveal.in{opacity:1;transform:none}
    footer{background:var(--b950);color:#cbd5e1}
    footer .pw{display:inline-flex;align-items:center;gap:9px;font-size:.88rem;color:#93BBFD}footer .pw img{height:24px}
    .fgrid{display:grid;gap:30px;grid-template-columns:1.6fr 1fr 1fr;padding:48px 22px 28px}
    footer a:hover{color:#fff}
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
    /* responsive */
    @media(max-width:860px){
      .statsgrid{grid-template-columns:1fr 1fr}.cards{grid-template-columns:1fr 1fr}
      .two{grid-template-columns:1fr;gap:24px}
      .two img{height:auto;max-height:300px}
      .hero{min-height:auto}
      .hero .inner{padding:60px 0}
      section{padding:58px 0}
      .band{padding:34px 22px}
      .menu{position:fixed;inset:var(--nav-h) 0 auto 0;flex-direction:column;align-items:stretch;gap:0;background:rgba(15,35,80,.97);backdrop-filter:blur(14px);max-height:0;overflow:hidden;transition:max-height .3s ease;border-bottom:1px solid rgba(255,255,255,.1)}
      .menu a{padding:16px 22px;border-top:1px solid rgba(255,255,255,.08)}.menu a.pill,.menu a.ghost{margin:12px 22px;text-align:center;justify-content:center}
      #nz:checked~.menu{max-height:400px}.burger{display:block}
      .fgrid{grid-template-columns:1fr;gap:24px;padding:38px 22px 22px}
    }
    @media(max-width:520px){
      .statsgrid{grid-template-columns:1fr 1fr;padding:18px;gap:12px}
      .cards{grid-template-columns:1fr}
      .hero .inner{padding:46px 0}
      .stat b{font-size:1.4rem}
      .wrap{padding:0 18px}
      .band{padding:30px 18px}
    }
  </style>
</head>
<body>
  <nav class="nav"><div class="wrap navflex">
    <a class="brandwrap" href="/" style="display:flex;align-items:center;gap:10px">
      <img src="{{ asset('brand/logo-white.png') }}" alt="EDIFIS" style="height:26px">
      <span class="brand">{{ $schoolName }}</span>
    </a>
    <input type="checkbox" id="nz"><label class="burger" for="nz">&#9776;</label>
    <div class="menu">
      <a class="txt" href="#about">About</a>
      <a class="txt" href="#services">Services</a>
      <a class="txt" href="#contact">Contact</a>
      <a class="ghost" href="/staff">Staff Login</a>
      <a class="pill" href="/parents">Parent Login</a>
    </div>
  </div></nav>

  <header class="hero" id="top">
    <div class="bg"></div><div class="ov"></div><div class="blob"></div>
    <div class="wrap inner">
      <span class="badge">Welcome to</span>
      <h1>{{ $schoolName }}</h1>
      <p class="lead">Results, attendance, fees and school communication — together in one place, for staff and families. Online or offline.</p>
      <div class="cta">
        <a class="btn btn-primary" href="/parents">I'm a Parent →</a>
        <a class="btn btn-ghost" href="/staff">I'm Staff</a>
      </div>
    </div>
  </header>

  <div class="wrap stats"><div class="statsgrid reveal">
    <div class="stat"><b>Results</b><span>Termly, transparent</span></div>
    <div class="stat"><b>Attendance</b><span>Tracked daily</span></div>
    <div class="stat"><b>Fees</b><span>Clear balances</span></div>
    <div class="stat"><b>Offline</b><span>Works without internet</span></div>
  </div></div>

  <section id="about"><div class="wrap two">
    <div class="reveal">
      <div class="eyebrow">About</div>
      <h2 class="title">A connected school community</h2>
      <p class="sub">{{ $schoolName }} runs on EDIFIS — keeping teachers, the office and parents on the same page. Families see results, attendance and fees in real time, and the school keeps moving even when the network drops.</p>
    </div>
    <img class="reveal" src="{{ asset('brand/photos/about.jpg') }}" alt="Learning at {{ $schoolName }}">
  </div></section>

  <section id="services" style="background:#fff;border-top:1px solid var(--border);border-bottom:1px solid var(--border)"><div class="wrap">
    <div class="eyebrow reveal">Services</div>
    <h2 class="title reveal">Everything in one app</h2>
    <p class="sub reveal">For the parents and staff of {{ $schoolName }}.</p>
    <div class="cards">
      <div class="card reveal"><div class="ico">&#9733;</div><h3>Results</h3><p>View termly results and averages the moment they're published.</p></div>
      <div class="card reveal"><div class="ico">&#10003;</div><h3>Attendance</h3><p>Daily attendance with instant absence alerts to parents.</p></div>
      <div class="card reveal"><div class="ico">&#128179;</div><h3>Fees</h3><p>Check balances and payment history any time, anywhere.</p></div>
      <div class="card reveal"><div class="ico">&#128276;</div><h3>Notices</h3><p>School announcements and reminders straight to the phone.</p></div>
    </div>
  </div></section>

  <section><div class="wrap two">
    <img class="reveal" src="{{ asset('brand/photos/community.jpg') }}" alt="{{ $schoolName }} community">
    <div class="reveal">
      <div class="eyebrow">Why families love it</div>
      <h2 class="title">Always informed, never in the dark</h2>
      <p class="sub">No more guessing. Parents of {{ $schoolName }} get a clear window into their child's progress, and staff spend less time on paperwork and more time teaching.</p>
    </div>
  </div></section>

  <section class="getapp" id="get-app"><div class="wrap">
    <div class="eyebrow">Get started</div>
    <h2 class="title">Download the app</h2>
    <p class="sub" style="margin:0 auto">Parents and staff sign in on their phone — results, attendance, fees and notices in one place.</p>
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

  <section id="contact"><div class="wrap"><div class="band reveal">
    <h2>Join {{ $schoolName }} online</h2>
    <p>Parents, sign in to follow your child. Staff, sign in to manage your work.</p>
    <div class="cta" style="justify-content:center">
      <a class="btn btn-primary" href="/parents">Parent Login</a>
      <a class="btn btn-ghost" href="/staff">Staff Login</a>
    </div>
  </div></div></section>

  <footer style="background:var(--b950);color:#cbd5e1;padding:0">
    <div class="wrap fgrid">
      <div style="min-width:0">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
          <img src="{{ asset('brand/logo-white.png') }}" alt="EDIFIS" style="height:26px">
          <span style="color:#fff;font-weight:800;font-size:1.05rem">{{ $schoolName }}</span>
        </div>
        <p style="font-size:.9rem;color:#9fb3d6;max-width:34ch;line-height:1.6">Results, attendance, fees and school communication — together in one place, for staff and families.</p>
        <p style="margin-top:14px;font-size:.72rem;letter-spacing:.25em;color:#6f86b3;font-weight:700">GOD · KNOWLEDGE · GROWTH</p>
      </div>
      <div>
        <h4 style="color:#fff;font-weight:700;margin-bottom:14px;font-size:.95rem">Quick links</h4>
        <ul style="list-style:none;display:flex;flex-direction:column;gap:10px;font-size:.9rem">
          <li><a href="/parents" style="color:#bcd0f5">Parent login</a></li>
          <li><a href="/staff" style="color:#bcd0f5">Staff login</a></li>
          <li><a href="https://myedifis.com/app.apk" style="color:#bcd0f5">Download the app</a></li>
        </ul>
      </div>
      <div>
        <h4 style="color:#fff;font-weight:700;margin-bottom:14px;font-size:.95rem">Contact</h4>
        <ul style="list-style:none;display:flex;flex-direction:column;gap:10px;font-size:.9rem">
          <li><a href="https://wa.me/237674072084" style="color:#bcd0f5">WhatsApp 674 072 084</a></li>
          <li><a href="mailto:noreply.myedifis@gmail.com" style="color:#bcd0f5">noreply.myedifis@gmail.com</a></li>
        </ul>
      </div>
    </div>
    <div style="border-top:1px solid rgba(255,255,255,.08)">
      <div class="wrap" style="display:flex;flex-wrap:wrap;gap:8px;justify-content:space-between;align-items:center;padding:16px 22px;font-size:.8rem;color:#7e93bd">
        <span>© {{ date('Y') }} {{ $schoolName }}</span>
        <span class="pw" style="display:inline-flex;align-items:center;gap:8px;color:#93BBFD"><img src="{{ asset('brand/logo-white.png') }}" alt="EDIFIS" style="height:18px"> Powered by EDIFIS</span>
      </div>
    </div>
  </footer>

  <script>
    const io=new IntersectionObserver((es)=>es.forEach(e=>{if(e.isIntersecting){e.target.classList.add('in');io.unobserve(e.target)}}),{threshold:.12});
    document.querySelectorAll('.reveal').forEach(el=>io.observe(el));
  </script>
</body>
</html>
