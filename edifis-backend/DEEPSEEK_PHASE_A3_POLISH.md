# DeepSeek Phase A3 — School homepage: pictures + lively, production-grade design

> VPS task. Replace `resources/views/school-home.blade.php` with the upgraded design below
> (image-rich hero, glassmorphism, scroll animations, perfect mobile responsiveness), and add
> **3 real photos**. Keep the same controller/route/`$schoolName`/`$parentUrl` it already uses.
>
> ## RULES
> - START: `cd /opt/edifis && git pull`
> - Backend code is baked into the image → after changes **rebuild**:
>   `docker compose -f docker-compose.prod.yml --env-file .env.prod up -d --build app horizon`
> - END: `git add -A && git commit -m "feat: school homepage v2 (photos + responsive)" && git push`
> - Delete this file when done; report "A3 done" + the curl checks + which photos you used.

## 1. Add 3 photos
Create `edifis-backend/public/brand/photos/` and add **royalty-free** education photos (prefer
African students / classroom / school building — Unsplash or Pexels, free license), saved as:
- `hero.jpg`      (wide, ~1600px — students/classroom, bright)
- `about.jpg`     (~1000px — a teacher or pupils learning)
- `community.jpg` (~1000px — group of students / school life)
Download with `wget`/`curl` on the server, or use the user's photos if provided. Verify each loads
(`curl -sI https://pssnkwen.myedifis.com/brand/photos/hero.jpg` → 200). Optimize if huge (<400KB each ideal).

## 2. Replace `resources/views/school-home.blade.php` with this
```blade
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $schoolName }} — powered by EDIFIS</title>
  <meta name="theme-color" content="#0F2350">
  <link rel="icon" href="{{ asset('favicon.ico') }}">
  <style>
    :root{--b300:#93BBFD;--b400:#6098FA;--b500:#3B76F6;--b600:#2563EB;--b700:#1D4ED8;--b800:#1E40AF;--b950:#0F2350;--glow:#38BDF8;--ink:#0B1220;--body:#334155;--muted:#64748B;--bg:#F4F7FE;--border:#E2E8F0;--nav-h:68px;}
    *{box-sizing:border-box;margin:0;padding:0}html{scroll-behavior:smooth}
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
    footer{background:var(--b950);color:#cbd5e1;padding:40px 0;text-align:center}
    footer .pw{display:inline-flex;align-items:center;gap:9px;font-size:.88rem;color:#93BBFD}footer .pw img{height:24px}
    /* responsive */
    @media(max-width:860px){
      .statsgrid{grid-template-columns:1fr 1fr}.cards{grid-template-columns:1fr 1fr}.two{grid-template-columns:1fr;gap:26px}
      .menu{position:fixed;inset:var(--nav-h) 0 auto 0;flex-direction:column;align-items:stretch;gap:0;background:rgba(15,35,80,.97);backdrop-filter:blur(14px);max-height:0;overflow:hidden;transition:max-height .3s ease;border-bottom:1px solid rgba(255,255,255,.1)}
      .menu a{padding:16px 22px;border-top:1px solid rgba(255,255,255,.08)}.menu a.pill,.menu a.ghost{margin:12px 22px;text-align:center;justify-content:center}
      #nz:checked~.menu{max-height:360px}.burger{display:block}
    }
    @media(max-width:520px){.statsgrid{grid-template-columns:1fr 1fr}.cards{grid-template-columns:1fr}.hero{min-height:92vh}}
  </style>
</head>
<body>
  <nav class="nav"><div class="wrap navflex">
    <a class="brand" href="#top">{{ $schoolName }}</a>
    <input type="checkbox" id="nz"><label class="burger" for="nz">&#9776;</label>
    <div class="menu">
      <a class="txt" href="#about">About</a>
      <a class="txt" href="#services">Services</a>
      <a class="txt" href="#contact">Contact</a>
      <a class="ghost" href="/staff">Staff Login</a>
      <a class="pill" href="{{ $parentUrl ?? 'https://myedifis.com/app.apk' }}">Parent Login</a>
    </div>
  </div></nav>

  <header class="hero" id="top">
    <div class="bg"></div><div class="ov"></div><div class="blob"></div>
    <div class="wrap inner">
      <span class="badge">Welcome to</span>
      <h1>{{ $schoolName }}</h1>
      <p class="lead">Results, attendance, fees and school communication — together in one place, for staff and families. Online or offline.</p>
      <div class="cta">
        <a class="btn btn-primary" href="{{ $parentUrl ?? 'https://myedifis.com/app.apk' }}">I'm a Parent →</a>
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

  <section id="contact"><div class="wrap"><div class="band reveal">
    <h2>Join {{ $schoolName }} online</h2>
    <p>Parents, sign in to follow your child. Staff, sign in to manage your work.</p>
    <div class="cta" style="justify-content:center">
      <a class="btn btn-primary" href="{{ $parentUrl ?? 'https://myedifis.com/app.apk' }}">Parent Login</a>
      <a class="btn btn-ghost" href="/staff">Staff Login</a>
    </div>
  </div></div></section>

  <footer><div class="wrap">
    <div class="pw"><img src="{{ asset('brand/logo-white.png') }}" alt="EDIFIS"> Powered by EDIFIS · GOD · KNOWLEDGE · GROWTH</div>
  </div></footer>

  <script>
    const io=new IntersectionObserver((es)=>es.forEach(e=>{if(e.isIntersecting){e.target.classList.add('in');io.unobserve(e.target)}}),{threshold:.12});
    document.querySelectorAll('.reveal').forEach(el=>io.observe(el));
  </script>
</body>
</html>
```

## 3. Verify (paste)
```bash
curl -s -o /dev/null -w "home: %{http_code}\n"  https://pssnkwen.myedifis.com/
curl -s -o /dev/null -w "hero img: %{http_code}\n" https://pssnkwen.myedifis.com/brand/photos/hero.jpg
curl -s -o /dev/null -w "central: %{http_code}\n" https://myedifis.com/
```
All 200. Report "A3 done" + outputs + the photo sources you used.
