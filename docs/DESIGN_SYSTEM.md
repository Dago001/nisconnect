# NISconnect — Design System

A dedicated identity for an official Nigeria Immigration Service platform. Professional,
clean, readable, **green as the brand colour** (not everywhere), and **Arial throughout**.
This is not a Telegram clone and not a generic AI/SaaS template.

## 1. Brand foundation

The palette is drawn from the NIS crest (deep green, gold, white) and tuned for a calm,
trustworthy communication surface. Green anchors identity; the working surface stays neutral
so long chat sessions remain comfortable.

## 2. Colour tokens

| Token | Light | Dark | Use |
|-------|-------|------|-----|
| `primaryGreen`   | `#0B6B3A` | `#1B8A54` | Primary brand, buttons, active nav, sent bubble accent |
| `darkGreen`      | `#064A28` | `#0B3D22` | Headers, splash, pressed states |
| `secondaryGreen` | `#2E9E63` | `#3FB574` | Secondary actions, links, highlights |
| `lightGreen`     | `#E6F4EC` | `#123524` | Tints, selection, received-bubble tint |
| `gold`           | `#C9A227` | `#D8B23A` | Sparing accent from crest (badges, official marks) |
| `white`          | `#FFFFFF` | `#0E1512` | Cards / surfaces |
| `offWhite`       | `#F5F7F5` | `#131A16` | App background |
| `neutralGrey`    | `#6B7770` | `#8A968F` | Secondary text, meta |
| `darkText`       | `#14201A` | `#EAF1EC` | Primary text |
| `borderGrey`     | `#E2E7E3` | `#26302B` | Dividers, borders, input outline |
| `error`          | `#C62828` | `#EF5350` | Errors, destructive |
| `warning`        | `#E08600` | `#F6A821` | Warnings |
| `success`        | `#2E7D32` | `#66BB6A` | Success, delivered/read |

Usage rules:
- Green is brand, not decoration — most surfaces are white/off‑white with dark text.
- One green accent per view hierarchy; avoid green‑on‑green.
- Gold is a *sparing* official accent (verified badge, channel mark), never large fills.
- No gradients-as-identity, no glassmorphism, no oversized display type.

## 3. Typography — Arial only

Single typeface, **Arial**, used on every screen and every component (splash, auth,
chat, groups, calls, directory, profile, settings, admin, forms, buttons, headings, body,
labels, dialogs, errors). No Inter/Roboto/Poppins/Montserrat/Open Sans/Lato/Nunito.

| Style | Size / weight | Use |
|-------|---------------|-----|
| Display | 28 / bold | Splash, screen hero |
| H1 | 22 / bold | Screen titles |
| H2 | 18 / bold | Section headers |
| Title | 16 / semibold | List item titles, names |
| Body | 15 / regular | Message text, content |
| Label | 13 / medium | Buttons, form labels |
| Caption | 12 / regular | Timestamps, meta, status |

**Licensing note:** Arial is a proprietary Microsoft font not redistributable in an open
repo. The app centralises the family in `AppTypography` referencing font family `Arial`.
For deployment, place a licensed Arial (or the metric‑compatible **Liberation Sans**, or
**Arimo**, both Apache‑2.0 and metrically identical to Arial) at `mobile/assets/fonts/` and
register it as family `Arial` in `pubspec.yaml`. The single indirection means components
never change. This limitation is documented per requirement §34.

## 4. Spacing, radius, elevation

- Spacing scale: 4 / 8 / 12 / 16 / 24 / 32.
- Radius: inputs & buttons 10; cards 12; bubbles 14 (tail corner 4); avatars full.
- Elevation: flat surfaces with 1px `borderGrey`; subtle shadow only on floating elements
  (FAB, incoming‑call banner). No heavy drop shadows.

## 5. Components

- **Buttons:** primary (solid `primaryGreen`, white label), secondary (outline), text,
  destructive (`error`). 44dp min height (accessible touch target).
- **Message bubbles:** sent = light green tint with green status ticks; received = white
  card with `borderGrey`. Timestamp + status in caption. Never mimic Telegram's exact layout.
- **Inputs:** outlined, `borderGrey` → `primaryGreen` on focus; numeric Service Number field
  uses a digits‑only formatter and numeric keyboard.
- **Nav bar:** Chats · Calls · Directory · Groups · Profile — active icon/label in
  `primaryGreen`.
- **Status ticks:** sending (clock), sent (single), delivered (double), read (double green),
  failed (red retry).

## 6. Imagery

Provided HQ photography (`assets/images/*`) is used tastefully on splash/welcome/about and
empty states — never crowding chat surfaces. The **NIS crest** (`assets/logos/nis-logo.jpg`)
appears on splash, welcome, auth headers, official channels and About. Assets are used as
provided (aspect ratio preserved, correct light/dark variant); they are never regenerated,
distorted or replaced with stock.

## 7. Dark mode & accessibility

- Light / Dark / System, all screens designed for both.
- Contrast targets WCAG AA; touch targets ≥ 44dp; semantic labels for TalkBack/VoiceOver;
  supports large text scaling.
