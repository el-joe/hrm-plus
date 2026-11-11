# HRM Plus — Design Reference (from Figma)

Source of truth for all UI code (Blade + Tailwind 4, and Flutter). Extracted from the Figma file; do not invent colours, sizes or type styles outside this document.

- **Figma file:** https://www.figma.com/design/F6GhAOTTYlI93nUtDf1255 (key `F6GhAOTTYlI93nUtDf1255`)
- **Scope (client decision):** Figma holds the design system, components and layout shells only. Individual pages are built in code from these.
- **Font:** Cairo (400, 500, 600, 700) for Arabic and Latin.
- **Direction:** Arabic RTL is primary; English LTR mirrors. Use CSS logical properties (`ms-`, `me-`, `ps-`, `pe-`, `start-`, `end-`, `text-start`) so one template serves both.
- **Digits:** Western (0–9) by default; Arabic-Indic is a system setting.
- **Themes:** Light and Dark, switched by swapping the semantic tokens below.

## Layout frames in Figma

| Frame | Node id |
|---|---|
| Admin shell, dashboard, 1440 RTL light | `18:1271` |
| Admin shell, dark | `19:1097` |
| Admin shell, sidebar collapsed | `19:1160` |
| Admin shell, English LTR | `19:1828` |
| Admin form page (tabs, sections, sticky action bar) | `20:2652` |
| Admin 390 mobile | `20:2903` |
| Admin 390, off-canvas navigation open | `20:2904` |
| Admin, modal open | `21:3012` |
| Admin, confirmation dialog + toast | `21:3093` |
| Admin login | `21:3287` |
| Employee portal home, 1440 | `22:4490` |
| Employee portal home, 390 | `22:4627` |
| Employee login | `22:4688` |
| Flutter home, light | `23:4293` |
| Flutter home, dark | `23:4294` |

## Colour primitives

| Ramp | Values |
|---|---|
| primary | 50 #EEFBF8, 100 #D5F5EE, 200 #ADEADF, 300 #77D8CB, 400 #41BDB0, 500 #1FA198, 600 #12827D, 700 #116866, 800 #125353, 900 #134545, 950 #06292B |
| neutral | 0 #FFFFFF, 25 #FAFBFC, 50 #F5F7F9, 100 #ECEFF3, 200 #DDE2E9, 300 #C4CCD7, 400 #97A3B4, 500 #6B788C, 600 #4D596C, 700 #3A4455, 800 #252D3B, 900 #171D29, 950 #0E131C |
| accent | 50 #FFF8EB, 100 #FEEBC6, 200 #FDD68A, 300 #FBBB4D, 400 #F8A024, 500 #EE820C, 600 #D26107, 700 #AE440A, 800 #8D350F, 900 #742D10, 950 #431605 |
| green | 50 #ECFDF3, 100 #D1FADF, 200 #A6F4C5, 300 #6CE9A6, 400 #32D583, 500 #12B76A, 600 #039855, 700 #027A48, 800 #05603A, 900 #054F31, 950 #053321 |
| red | 50 #FEF3F2, 100 #FEE4E2, 200 #FECDCA, 300 #FDA29B, 400 #F97066, 500 #F04438, 600 #D92D20, 700 #B42318, 800 #912018, 900 #7A271A, 950 #55160C |
| blue | 50 #EFF8FF, 100 #D1E9FF, 200 #B2DDFF, 300 #84CAFF, 400 #53B1FD, 500 #2E90FA, 600 #1570EF, 700 #175CD3, 800 #1849A9, 900 #194185, 950 #102A56 |
| orange | 50 #FFF6ED, 100 #FFEAD5, 200 #FDDCAB, 300 #FEB273, 400 #FD853A, 500 #FB6514, 600 #EC4A0A, 700 #C4320A, 800 #9C2A10, 900 #7E2410, 950 #511C10 |
| violet | 50 #F4F3FF, 100 #EBE9FE, 200 #D9D6FE, 300 #BDB4FE, 400 #9B8AFB, 500 #7A5AF8, 600 #6938EF, 700 #5925DC, 800 #4A1FB8, 900 #3E1C96, 950 #27115F |

## Semantic tokens (CSS variable = `--color-` + name with slashes as dashes)

| Token | Light | Dark |
|---|---|---|
| surface/page | neutral/50 | neutral/950 |
| surface/default | neutral/0 | neutral/900 |
| surface/subtle | neutral/50 | neutral/800 |
| surface/muted | neutral/100 | neutral/700 |
| surface/raised | neutral/0 | neutral/800 |
| surface/inverse | neutral/900 | neutral/0 |
| surface/scrim | neutral/950 (60% opacity) | neutral/950 |
| surface/brand | primary/700 | primary/900 |
| surface/brand-strong | primary/900 | primary/950 |
| border/subtle | neutral/100 | neutral/800 |
| border/default | neutral/200 | neutral/700 |
| border/strong | neutral/300 | neutral/600 |
| border/focus | primary/500 | primary/400 |
| text/primary | neutral/900 | neutral/25 |
| text/secondary | neutral/600 | neutral/300 |
| text/tertiary | neutral/500 | neutral/400 |
| text/disabled | neutral/400 | neutral/600 |
| text/inverse | neutral/0 | neutral/900 |
| text/on-primary | neutral/0 | neutral/0 |
| text/link | primary/700 | primary/300 |
| text/on-brand-muted | primary/100 | primary/200 |
| action/primary/default | primary/600 | primary/500 |
| action/primary/hover | primary/700 | primary/400 |
| action/primary/pressed | primary/800 | primary/300 |
| action/primary/subtle | primary/50 | primary/950 |
| action/primary/subtle-hover | primary/100 | primary/900 |
| action/primary/disabled | neutral/200 | neutral/700 |
| action/neutral/hover | neutral/100 | neutral/700 |
| action/neutral/pressed | neutral/200 | neutral/600 |
| action/danger/default | red/600 | red/500 |
| action/danger/hover | red/700 | red/400 |
| action/danger/pressed | red/800 | red/300 |
| action/danger/subtle | red/50 | red/950 |
| accent/default | accent/500 | accent/400 |
| accent/subtle | accent/50 | accent/950 |
| sidebar/bg | neutral/900 | neutral/950 |
| sidebar/border | neutral/800 | neutral/800 |
| sidebar/text | neutral/300 | neutral/300 |
| sidebar/text-muted | neutral/500 | neutral/500 |
| sidebar/text-active | neutral/0 | neutral/0 |
| sidebar/item-hover | neutral/800 | neutral/800 |
| sidebar/item-active | primary/600 | primary/600 |
| chart/1 | primary/500 | primary/400 |
| chart/2 | accent/400 | accent/300 |
| chart/3 | blue/500 | blue/400 |
| chart/4 | violet/500 | violet/400 |
| chart/5 | orange/500 | orange/400 |
| chart/6 | neutral/400 | neutral/500 |

**Feedback** — `feedback/{success|warning|danger|info}/{bg|border|text|solid}`; ramps: success = green, warning = accent, danger = red, info = blue.

| Part | Light step | Dark step |
|---|---|---|
| bg | 50 | 950 |
| border | 200 | 800 |
| text | 700 | 300 |
| solid | 600 | 500 |

**Status badges** — `status/{state}/{bg|text|dot}`.

| State | Ramp |
|---|---|
| present | green |
| absent | red |
| late | orange |
| on-leave | violet |
| pending | accent |
| approved | primary |
| rejected | red |
| finalized | blue |
| draft | neutral (bg 100/700, text 700/200, dot 500/400) |

| Part | Light step | Dark step |
|---|---|---|
| bg | 50 | 950 |
| text | 700 | 300 |
| dot | 500 | 400 |

## Type scale (Cairo)

| Style | Size / line height / weight |
|---|---|
| display/lg | 48 / 68 / 700 |
| display/md | 36 / 54 / 700 |
| heading/h1 | 30 / 46 / 700 |
| heading/h2 | 24 / 38 / 600 |
| heading/h3 | 20 / 32 / 600 |
| heading/h4 | 18 / 30 / 600 |
| heading/h5 | 16 / 26 / 600 |
| body/lg | 16 / 28 / 400 |
| body/md (default) | 14 / 24 / 400 |
| body/sm | 13 / 22 / 400 |
| label/lg | 16 / 26 / 500 |
| label/md | 14 / 22 / 500 |
| label/sm | 12 / 18 / 500 |
| caption/md | 12 / 18 / 400 |
| caption/sm | 11 / 16 / 400 |
| numeric/xl | 32 / 44 / 700 |
| numeric/lg | 24 / 34 / 600 |
| numeric/md | 14 / 22 / 600 |

## Sizes

| Group | Values (px) |
|---|---|
| radius | xs 4, sm 6, md 8, lg 12, xl 16, 2xl 24, full |
| control height | sm 32, md 40, lg 48 |
| icon | sm 16, md 20, lg 24 (1.75 stroke, outline style) |
| avatar | sm 32, md 40, lg 56, xl 80 |
| spacing | Tailwind default 4px scale |
| breakpoints | Tailwind defaults (sm 640, md 768, lg 1024, xl 1280, 2xl 1536) |

## Shadows (colour rgb(14,19,28))

| Name | Layers (y, blur, alpha) |
|---|---|
| xs | 0 1 2 6% |
| sm | 0 1 3 8% + 0 1 2 4% |
| md | 0 4 12 8% + 0 2 4 4% |
| lg | 0 12 24 10% + 0 4 8 4% |
| xl | 0 24 48 16% + 0 8 16 6% |
| focus ring | 0 0 0 3px rgba(31,161,152,0.32) |
| focus ring danger | 0 0 0 3px rgba(240,68,56,0.28) |

## Layout measurements

**Admin shell**
- Sidebar 272 expanded, 80 collapsed, padding 16, on the start side, dark (`sidebar/*` tokens) in both themes. Off-canvas drawer below `lg`.
- Nav item 44 high, radius 8, icon-label gap 12; sub item 36 high; rail item 48×44; group gap 12. Active item uses `sidebar/item-active`.
- Sidebar groups: dashboard, structure, employees, recruitment, attendance, leave, payroll, performance, reports, permissions, settings.
- Top bar 64 high, horizontal padding 24; search 360 wide; controls 40 (notifications, language, theme, profile).
- Content padding 32, vertical gap between blocks 24; suggested max width 1280 centred.
- Page header: breadcrumbs, 12 gap, title in heading/h2 with subtitle, actions on the end side.
- KPI row: 4 columns from `xl`, 2 below, gap 24. KPI card: padding 20, radius 16, `border/subtle`, shadow xs.
- Chart row: fluid chart card + fixed 352 side card, gap 24; chart area 232 high.
- Generic card: `surface/default`, `border/subtle`, radius 16, padding 20, shadow xs.
- Filter bar: padding 16, gap 12, radius 12, controls 40 high, selects about 170 wide.
- Table: radius 12, 1px `border/default`, header 44, row 56, cell horizontal padding 16; rows have hover and selected states; becomes stacked cards below `md`.
- Pagination: items 36×36, gap 6, with a results summary.

**Form page**
- Section card padding 24, radius 16; intro column 260 wide with 32 gap to the fields.
- Field grid 2 columns, column gap 24, row gap 20; inputs 40 high, radius 8, label gap 6.
- Sticky action bar 72 high, horizontal padding 32, top border, shadow lg.

**Overlays**
- Modal 560 wide, radius 16, shadow xl; header and footer padding 24×16, body padding 24.
- Confirmation dialog 440 wide, padding 24; tones danger, warning, info.
- Toast 400 wide, top-end, 24 from the edge and below the top bar; tones success, warning, danger, info.

**Admin at 390**
- Top bar 56; page padding and gaps 16; KPI grid 2 columns; drawer 272 from the start side over the scrim.

**Employee portal** (deliberately different from admin: top navigation, no sidebar)
- Top nav 72 high, horizontal padding 32, nav pills 40 high.
- Container side padding 96 at 1440 (1248 content width).
- Hero band on `surface/brand`, vertical padding 40, with a 440-wide attendance card.
- Content vertical padding 32, gap 24; stat row 4 columns; week strip 7 equal cells 104 high; lower row fluid + 400 column.
- Mobile: top bar 56, bottom navigation 72, padding 16, 2-column summary grid with gap 16.

**Auth**
- Admin login: 600 form panel (padding 64, 400 form column) + fluid `surface/brand-strong` panel (padding 80).
- Employee login: centred 480 card, padding 40, radius 24, on `surface/brand`.

**Flutter (390×844)**
- App bar 56; body padding 16, gap 16; bottom navigation 72 with 5 destinations.
- Attendance button: 200 circle inside a 248 halo; states check in, check out, processing, disabled.
- Summary cards 2 columns, gap 16, padding 16, radius 16; list tile padding 12, radius 12, leading 40.

## Component inventory (Figma names)

Button (primary, secondary, ghost, danger × sm, md, lg × default, hover, focus, disabled), Icon button, Input, Select, Date input, Textarea, Checkbox, Radio, Toggle, File uploader, Search field, Status badge, Badge (neutral, primary, success, warning, danger, info), Avatar, Tabs, Breadcrumbs, Pagination, Table header/body rows and cells, Filter bar, Stat card (trend up, down, neutral), Chart card, Modal, Confirmation dialog, Toast, Empty state, Skeleton, Admin sidebar, Admin top bar, Page header, Form action bar, Employee portal top nav, Calendar day cell (default, today, selected, outside month, weekend, present, absent, late, on leave, holiday), Org chart node, Mobile app bar, Mobile bottom navigation, Mobile list tile, Mobile attendance button, Mobile summary card, and a 50-icon outline set.

## Known gaps in the Figma file

- Dark mode is drawn only for the admin dashboard and Flutter home; LTR only for the admin dashboard. Other screens derive from the tokens.
- No pressed or loading button state is drawn (pressed tokens exist); add a loading state in code.
- The chart card is an illustration only.
- The employee column in the 1440 table truncates subtitles; give it more width in code.
