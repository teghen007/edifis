# DeepSeek — Fix Filament login: invisible white text on the white card

> VPS task (backend / Filament). The `/staff` login card is white but the text (labels, headings, typed
> input) renders WHITE because the browser is in dark mode → invisible. Force light mode + guarantee
> dark text on the card. File: `app/Providers/Filament/StaffPanelProvider.php`.
>
> ## RULES
> - START: `cd /opt/edifis && git pull`
> - REBUILD: `... up -d --build app horizon`; then `php artisan filament:optimize`
> - END: `git add -A && git commit -m "fix(admin): force light mode + readable login contrast" && git push`
> - Delete this file; report "login fix done" + confirm the login labels are now visible.

## Edit 1 — force light mode on the panel
In the `panel()` builder chain (near `->colors([...])`), add:
```php
->darkMode(false)
```
(This stops the browser's dark preference from flipping the panel to white-on-white.)

## Edit 2 — strengthen the render-hook CSS for contrast
In the existing `FilamentView::registerRenderHook(PanelsRenderHook::HEAD_END, ...)` `<style>`, ADD these
rules (keep the existing background/card/button rules):
```css
/* readable dark text on the white login card, regardless of mode */
.fi-simple-main, .fi-simple-main h1, .fi-simple-main h2,
.fi-simple-main label, .fi-simple-main .fi-fo-field-wrp-label,
.fi-simple-main .fi-checkbox-input-label, .fi-simple-main p, .fi-simple-main span{
  color:#0F2350 !important;
}
.fi-simple-main .fi-input{ color:#0B1220 !important; background:#fff !important; }
.fi-simple-main .fi-input::placeholder{ color:#94a3b8 !important; }
.fi-simple-main a{ color:#2563EB !important; }
/* keep the primary button text white */
.fi-simple-main .fi-btn.fi-color-primary, .fi-simple-main .fi-btn.fi-color-primary *{ color:#fff !important; }
```

## Verify
- Open `https://pssnkwen.myedifis.com/staff/login` (try it with the browser in DARK mode too) — the
  heading "Sign in", "Email address" / "Password" labels, and typed text must all be clearly visible
  (dark on white), button blue with white text.
- Log in as `admin@pssnkwen.local` / `secret` → reaches the admin dashboard.
Report "login fix done" + confirm visible + login works.
