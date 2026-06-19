# DeepSeek Phase A4 — Full polish: app/contact section, navbar logo, parent page, staff-login theme

> VPS task. Touches: the school homepage Blade, the central homepage (static landing),
> a NEW parent page, and the Filament staff-login theme. Use the shared design in Part 1.
>
> ## RULES
> - START: `cd /opt/edifis && git pull`
> - The static central homepage lives at `edifis-infra/prod/landing/index.html` (Caddy serves it
>   live — NO rebuild needed for it, just edit + commit; on the server it's already pulled).
> - Backend/Blade + Filament changes → REBUILD: `docker compose -f docker-compose.prod.yml --env-file .env.prod up -d --build app horizon`
> - END: `git add -A && git commit -m "feat: A4 polish (app+contact, navbar logo, parent page, staff login theme)" && git push`
> - Delete this file; report "A4 done" + curl checks.

## Part 1 — SHARED "Get the app + Contact" section (reuse on every page)
Inline SVG icons, no external deps. Android links to the APK; iOS shows "coming soon"; WhatsApp →
`wa.me/237674072084`; email → `noreply.myedifis@gmail.com`. CSS first, then markup.
```html
<style>
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
</style>
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
```

## Part 2 — School homepage `resources/views/school-home.blade.php`
1. **Navbar: add the white EDIFIS logo + make it the home link.** Replace the `.brand` anchor with:
   ```html
   <a class="brandwrap" href="/" style="display:flex;align-items:center;gap:10px">
     <img src="{{ asset('brand/logo-white.png') }}" alt="EDIFIS" style="height:26px">
     <span class="brand">{{ $schoolName }}</span>
   </a>
   ```
2. **Insert the Part 1 section** just BEFORE the `#contact` CTA band.
3. **Center the footer** (it already is — confirm content is centered).
4. Point both **Parent Login** links to the new parent page: `href="/parents"` (Part 4).
5. Keep mobile clean (the new section is already responsive; verify at 360px).

## Part 3 — Central homepage `edifis-infra/prod/landing/index.html`
- Insert the Part 1 section (adapt: Android link is `/app.apk`, same icons/contact).
- **Center the footer** (currently space-between → make it a centered column).
- This file is static (Caddy) — just edit + commit; no rebuild.

## Part 4 — NEW parent page (route `/parents`)
- Tenant route `Route::get('/parents', fn() => view('parents', ['schoolName' => <tenant name>]))` (resolve
  the school name the same way SchoolHomeController does).
- View `resources/views/parents.blade.php`: a small page reusing the school-home `<head>`/nav styles
  with a short hero "Parents of {{ $schoolName }}" + the Part 1 section + a line:
  *"After installing, open the app and sign in with your phone number."* + a back link to `/`.

## Part 5 — Staff login theme (Filament)
The `/staff` login still looks default. Strengthen the render-hook CSS in the PanelProvider so the
login matches the brand: full glossy navy→blue background, a white rounded card, the logo on top.
Make sure the **brand logo + favicon** show on the login. Keep the primary color `#2563EB`.

## Verify (paste)
```bash
curl -s -o /dev/null -w "school home: %{http_code}\n" https://pssnkwen.myedifis.com/
curl -s -o /dev/null -w "parents pg:  %{http_code}\n" https://pssnkwen.myedifis.com/parents
curl -s https://pssnkwen.myedifis.com/ | grep -c "wa.me/237674072084"
curl -s -o /dev/null -w "central:     %{http_code}\n" https://myedifis.com/
curl -s https://myedifis.com/ | grep -c "wa.me/237674072084"
```
Report "A4 done" + outputs.
