# EDIFIS — Design Tokens (single source of truth)

> National school-management system for Cameroon. Web (Filament/Tailwind) and the
> Flutter app MUST read from these exact values so they look like one product.
> Architect defines this file; DeepSeek implements from it.

## Brand
- **Name:** EDIFIS
- **Motto:** `GOD · KNOWLEDGE · GROWTH`
- **Positioning:** "School management for every school in Cameroon" (NOT PEA-specific — PEA is the first customer, not the brand).
- **Identity:** "Wisdom Blue" — blue = wisdom, knowledge, learning. Glossy, "lit-up" feel (gradients + cyan glow), never flat.

## Color scale — Wisdom Blue
| Token | Hex | Use |
|---|---|---|
| blue-50  | `#EFF5FF` | tint backgrounds |
| blue-100 | `#DBE8FE` | |
| blue-200 | `#BFD7FE` | |
| blue-300 | `#93BBFD` | |
| blue-400 | `#6098FA` | |
| blue-500 | `#3B76F6` | interactive/hover |
| **blue-600** | **`#2563EB`** | **PRIMARY** — buttons, links, active |
| blue-700 | `#1D4ED8` | |
| blue-800 | `#1E40AF` | |
| blue-900 | `#1E3A8A` | |
| **blue-950** | **`#0F2350`** | **Navy** — headers, hero base, footer, depth |
| **glow**  | **`#38BDF8`** | cyan glow — CTAs, highlights ("light up") |
| glow-2 | `#22D3EE` | brighter glow |

### Neutrals & semantic
- ink `#0B1220` · body `#334155` · muted `#64748B`
- surface `#FFFFFF` · page bg `#F4F7FE` (blue-tinted) · border `#E2E8F0`
- success `#16A34A` · warning `#F59E0B` · danger `#DC2626` · info `#0EA5E9`

## Glossy treatment (rules)
- **Hero/headers:** linear-gradient navy→blue-600 + a radial cyan bloom in one corner.
- **Primary buttons:** gradient `#BFE6FF → glow`, inner white highlight, soft cyan glow shadow, lift on hover.
- **Cards:** frosted glass — white ~85% + backdrop-blur + soft blue shadow.
- **Shadows:** always blue-tinted (`rgba(15,35,80,.35)`), never neutral grey.

## Icons
- **Lucide** everywhere. Web: `lucide` (inline SVG). Flutter: `lucide_icons` package. Same set on both.
- Line style, `stroke-width:2`, round caps/joins, `currentColor`.

## Typography
- System UI stack for now (system-ui / Segoe UI / Roboto). Revisit a brand font after logo.

## Logo (pending from user)
- User supplies scaffold logo colored in: **black**, **white**, and **primary `#2563EB`**, in an `images/` folder.
- Need: full-color, all-white (for navy backgrounds), all-mono; SVG preferred + PNG @2x; plus a square mark for favicon + app icon.

## Implementation targets
1. **Filament panel:** set primary color to blue-600; theme to glossy; logo + favicon.
2. **Public site (`/srv/landing`):** rebuild homepage from `edifis-brand/palette-preview.html`.
3. **Flutter app:** ThemeData seeded with these tokens; `lucide_icons`; animated/glossy home.
