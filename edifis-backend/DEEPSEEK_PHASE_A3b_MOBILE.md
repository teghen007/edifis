# DeepSeek Phase A3b — School homepage: mobile responsiveness only

> VPS task. ONLY fix mobile responsiveness in `resources/views/school-home.blade.php`.
> Do NOT change the desktop look. Two small edits below.
>
> ## RULES
> - START: `cd /opt/edifis && git pull`
> - After editing, REBUILD: `docker compose -f docker-compose.prod.yml --env-file .env.prod up -d --build app horizon`
> - END: `git add -A && git commit -m "fix: school homepage mobile responsiveness" && git push`
> - Delete this file; report "A3b done" + the curl check.

## Edit 1 — add overflow guard to `html`
Find:
```
html{scroll-behavior:smooth}
```
Replace with:
```
html{scroll-behavior:smooth;overflow-x:hidden}
```

## Edit 2 — replace BOTH `@media` blocks
Find the two media blocks (they currently look like this):
```
    @media(max-width:860px){
      .statsgrid{grid-template-columns:1fr 1fr}.cards{grid-template-columns:1fr 1fr}.two{grid-template-columns:1fr;gap:26px}
      .menu{position:fixed;inset:var(--nav-h) 0 auto 0;flex-direction:column;align-items:stretch;gap:0;background:rgba(15,35,80,.97);backdrop-filter:blur(14px);max-height:0;overflow:hidden;transition:max-height .3s ease;border-bottom:1px solid rgba(255,255,255,.1)}
      .menu a{padding:16px 22px;border-top:1px solid rgba(255,255,255,.08)}.menu a.pill,.menu a.ghost{margin:12px 22px;text-align:center;justify-content:center}
      #nz:checked~.menu{max-height:360px}.burger{display:block}
    }
    @media(max-width:520px){.statsgrid{grid-template-columns:1fr 1fr}.cards{grid-template-columns:1fr}.hero{min-height:92vh}}
```
Replace with:
```
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
    }
    @media(max-width:520px){
      .statsgrid{grid-template-columns:1fr 1fr;padding:18px;gap:12px}
      .cards{grid-template-columns:1fr}
      .hero .inner{padding:46px 0}
      .stat b{font-size:1.4rem}
      .wrap{padding:0 18px}
      .band{padding:30px 18px}
    }
```

## Verify
```bash
curl -s -o /dev/null -w "home: %{http_code}\n" https://pssnkwen.myedifis.com/
curl -s https://pssnkwen.myedifis.com/ | grep -c "min-height:auto"   # expect 1 (fix applied)
```
Report "A3b done" + outputs. (Tip: I tested at 360px / 768px widths — cards stack, photos show full, hamburger works, no sideways scroll.)
