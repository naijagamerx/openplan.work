# Design System: OpenPlan Work (Flutter)

Monochrome design language for the native app, ported from the web mobile UI
(`mobile/views/dashboard.php`, `partials/bottom-nav.php`, `docs/DESIGN.md`) and
refined with taste-design rules. Single source of truth for the Flutter theme.

## 1. Visual Theme & Atmosphere
Clinical, confident, monochrome. A near-grayscale interface where **one ink accent**
(black on light, white on dark) does all the emphasis, and color appears **only** to
encode data (task priority). Border-defined cards over shadows, generous rounding,
and a signature of **uppercase, letter-tracked micro-labels**. Density ~5 (daily-app
balanced); motion restrained-but-tactile (press scale, skeleton shimmer, staggered
reveals). No gradients, no glows, no purple/neon.

## 2. Color Palette & Roles
**Light**
- Canvas `#F9FAFB` — scaffold background
- Surface `#FFFFFF` — card / container fill
- Border `#E5E7EB` — 1px structural lines (cards have border, not shadow)
- Ink `#0A0A0A` — the single accent: primary buttons, progress bars, active nav
- On-Ink `#FFFFFF` — text/icons on an ink fill
- Text Primary `#18181B` (zinc-900) · Muted `#6B7280` · Faint `#9CA3AF`

**Dark** (ink inverts to white)
- Canvas `#0A0A0A` · Surface `#18181B` (zinc-900) · Border `#27272A` (zinc-800)
- Ink `#FAFAFA` · On-Ink `#0A0A0A`
- Text Primary `#F4F4F5` · Muted `#A1A1AA` · Faint `#71717A`

**Semantic (data only — never decorative):** priority pills — urgent `#DC2626`,
high `#EA580C`, medium `#EAB308` (black text), low `#16A34A`. Error `#DC2626`.

## 3. Typography
- **Display/Body:** Geist (via `google_fonts`). Hierarchy through weight + color, not size.
- **Mono:** Geist Mono — all numbers, stat values, timers.
- Greeting 24/700 · stat value 30/700 (mono) · section heading 13/700 UPPERCASE
  `letterSpacing 1.5` · micro-label 10/700 UPPERCASE `letterSpacing 1.5` (muted) ·
  body 14–16/400–500.
- Banned: serif anywhere; decorative oversize headers.

## 4. Components
- **Buttons** (`MonoButton`): primary = ink fill + inverted text; secondary = 1px ink
  outline. Tactile press (scale 0.96). No glow. ≥44px tap target.
- **Stat card** (`StatCard`): `rounded-2xl` (16) bordered tile — uppercase label +
  heroicon (top-right) + big mono value + sub-line OR thin ink progress bar.
- **Section card** (`SectionCard`): `rounded-3xl` (24) bordered — uppercase heading +
  optional trailing action.
- **Loaders:** skeletal shimmer matching layout (`Skeleton` / `DashboardSkeleton`) — no spinners.
- **Empty states** (`EmptyState`): heroicon + message + optional CTA.
- **Icons:** Heroicons outline only, rendered via inline SVG (`HeroIcon`).

## 5. Layout
- Single column, max content width = phone. Page padding 16; section gap 24.
- Stat grid = 2×2 (`IntrinsicHeight` rows so paired cards match height).
- Bottom nav: 4 tabs (Dashboard · Tasks · Habits · Settings), top border, no shadow,
  `w-6` icons, 10px uppercase labels; active = ink, inactive = faint.

## 6. Motion
Tactile button press (90ms scale). Skeleton shimmer (~1.1s). Pull-to-refresh.
Animate transform/opacity only. Respect reduced-motion.

## 7. Anti-Patterns (Banned)
No emojis. No pure `#000000` (use ink `#0A0A0A`). No shadows on cards (border instead).
No color except priority/error. No neon/glow/gradient. No serif. No 3-equal-column
rows. No fabricated metrics. Icons = Heroicons only (never icon fonts / Material glyphs).
