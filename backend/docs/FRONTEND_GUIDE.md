# Frontend guide

How to build HRM Plus screens (Blade + Tailwind 4 + vanilla ES modules, bundled by Vite).
No Livewire, Alpine, jQuery, React or Vue. The living reference is **`/admin/ui-kit`**
(Super Admin only): every component below is shown there with its markup in
`resources/views/admin/ui-kit.blade.php`.

## 1. Ground rules

- Arabic (RTL) is primary, English (LTR) mirrors it. One template serves both: use logical
  utilities only — `ms-/me-`, `ps-/pe-`, `start-/end-`, `text-start/text-end`, `border-s/e`,
  `rounded-s/e`. Never `ml-/mr-/left-/right-/text-left`. Directional icons get `flip`
  (`<x-icon name="chevron-right" flip />`); `x-button` flips arrows/chevrons itself.
- Every visible string goes through `__()` with keys in **both** `lang/ar/*.php` and
  `lang/en/*.php`. Interface strings live in `lang/*/app.php` (`app.ui.*` is the shared UI
  vocabulary). Strings JS needs are passed through `#app-i18n` (section 6).
- No business logic in Blade or JS. Controllers pass ready-to-render data; services compute it.
- No placeholder UI: a control is either wired to a real route/behaviour or not rendered.
  No hard-coded figures — numbers come from the controller (see `App\Services\Dashboard\AdminDashboard`).
- Progressive enhancement: links and forms must work without JS. Controls that only work with
  JS are rendered `hidden data-js-only`; `app.js` un-hides them on boot.

## 2. Design tokens (resources/css/app.css)

Source of truth: `DESIGN_REFERENCE.md`. Tailwind's default palette, type scale, radii and
shadows are **removed**, so only reference values exist.

| Kind | Use | Examples |
|---|---|---|
| Semantic colours (preferred) | `bg-` / `text-` / `border-` + token | `bg-surface-default`, `bg-surface-page`, `text-text-primary`, `text-text-secondary`, `border-border-subtle`, `bg-action-primary-default`, `hover:bg-action-primary-hover`, `bg-feedback-danger-bg`, `text-status-late-text`, `bg-sidebar-bg`, `bg-chart-1` |
| Primitive ramps (rare) | only when no semantic token fits | `primary-50…950`, `neutral-0…950`, `accent`, `green`, `red`, `blue`, `orange`, `violet` |
| Type scale | size + line height + weight in one class | `text-display-lg/md`, `text-h1…h5`, `text-body-lg/md/sm`, `text-label-lg/md/sm`, `text-caption-md/sm`, `text-numeric-xl/lg/md` |
| Radius | | `rounded-xs` 4, `-sm` 6, `-md` 8, `-lg` 12, `-xl` 16, `-2xl` 24, `-full` |
| Shadow | | `shadow-xs…xl`, `shadow-focus`, `shadow-focus-danger` |
| Spacing / breakpoints | Tailwind defaults (4px grid) | `w-68` = 272, `max-w-320` = 1280 |

Font: Cairo 400/500/600/700 (Arabic + Latin), downloaded at build time by the
`laravel-vite-plugin/fonts` `bunny()` provider and self-hosted from `public/build`
(`@fonts` in `partials/head.blade.php`). No runtime request to a font CDN.

### Dark mode

`<html data-theme="light|dark">`. Semantic tokens are redefined under
`:root[data-theme='dark']`, so components written with semantic tokens need **no** `dark:`
classes. The inline script in `partials/head.blade.php` sets the theme before first paint
from `localStorage('hrm-theme')`, falling back to `prefers-color-scheme` (no flash). Use the
`dark:` variant only for things tokens cannot express (e.g. swapping the moon/sun icon).

## 3. Layouts

| Layout | For | Usage |
|---|---|---|
| `layouts.admin` | `/admin/*` | `@extends('layouts.admin', ['breadcrumbs' => [['label' => …, 'url' => …]], 'subtitle' => …])`, sections `title`, `page-actions`, `content`, stack `scripts`. The header (breadcrumbs → title → actions) is rendered for you; "Dashboard" and the current title are added to the breadcrumb trail automatically. Pass `'pageHeader' => false` to render your own. |
| `layouts.employee` | `/portal/*` | Same sections; `['hero' => true]` + `@section('hero')` for a brand band instead of the page header. Top nav on desktop, bottom nav on mobile. |
| `layouts.public` | public pages without an account (career page) | Sections `title`, `content`; pass `$company`. |
| `layouts.auth` | sign-in / password pages | Needs `$area` (`AuthArea`); sections `title`, `intro`, `content`. |

### Sidebar / portal navigation

Entries live in **`config/navigation.php`**; `App\Support\Navigation\NavigationBuilder` keeps
only items whose route exists and whose `ability` the user passes (`module.action` permission or
a Gate ability), and drops empty sections. A view composer in `AppServiceProvider` passes the
result to the layouts as `$navigation`. To add a module page:

1. Protect the route (`->can('employees.view')` or a policy).
2. Add `['key' => 'employees', 'route' => 'admin.employees.index', 'ability' => 'employees.view', 'icon' => 'users', 'active' => ['admin.employees.*']]` to the right section.
3. Add `app.nav.employees` (and `app.nav.sections.<key>` for a new section) in both languages.

Hiding a link is never the protection — the route must enforce the same permission.

## 4. Blade components (resources/views/components)

| Component | Notes |
|---|---|
| `x-button` | `variant` primary/secondary/ghost/danger, `size` sm/md/lg, `href` → `<a>`, `icon`, `icon-end`, `loading`. Default `type="submit"`; pass `type="button"` for JS triggers. |
| `x-icon-button` | Icon-only; `label` is required (aria-label + tooltip). |
| `x-icon` | `name`, `size` sm/md/lg, `flip` (Phase 8a added `laptop`, `target`, `book-open`). Add new outline icons (24 grid, 1.75 stroke) to the map inside the file. |
| `x-form.input` / `.select` / `.textarea` / `.file-input` | Label, help and error built in. `name` drives the id and the error-bag key (`scopes[branch]` → `scopes.branch`); `error-keys` adds keys such as `roles.*`; `hide-label` for filter bars. Other attributes (`required`, `value`, `maxlength`, `:disabled`) go on the control. Use `:required="$bool"`, not `@required()`, inside component tags. |
| `x-form.checkbox` / `.radio` / `.switch` | Inline label; `:checked="…"`. |
| `x-form.field` | Wrapper for a custom control (keeps label / help / error markup consistent). |
| `x-form.section` | Form card: intro column (title, description) + 2-column field grid. |
| `x-form.actions` | Sticky action bar; last child of the `<form>`, primary button last. |
| `x-card` | `title`, `subtitle`, `padding="none"` for flush tables; slots `actions`, `footer`. |
| `x-stat-card` | KPI: `label`, `value`, `icon`, `href`, `trend` up/down/neutral, `trend-value`, `hint`. Values from the controller only. |
| `x-badge` | `tone` neutral/primary/success/warning/danger/info, `dot`. |
| `x-status-badge` | `state` present/absent/late/on-leave/pending/approved/rejected/finalized/draft. |
| `x-avatar` | Initials (or `src`), `size` sm/md/lg/xl. |
| `x-alert` | `tone`, `title`, `dismissible`. |
| `x-empty-state` | `icon`, `title`, `description`, slot for actions. |
| `x-skeleton` | Loading block sized by classes. |
| `x-table`, `x-table.th`, `x-table.row`, `x-table.td`, `x-table.empty` | Slots `head`, default (rows), `footer`. Give each `x-table.td` a `label` — rows become stacked cards below `md`. `x-table.th sort="column"` renders a server-side sort link (`?sort=&direction=`, page reset); the controller must whitelist the column. |
| `x-pagination` | `:paginator`; results summary + pages. Call `->withQueryString()` in the controller. |
| `x-filter-bar` | GET form with Reset + Filter; keeps `sort`/`direction`. |
| `x-tabs` | Link tabs (`url`, `active`) or client tabs (`id` → panel `<div id data-tab-panel>`). |
| `x-breadcrumbs`, `x-page-header` | Used by the admin layout; available for custom headers. |
| `x-dropdown`, `x-dropdown.item`, `x-dropdown.divider` | `<details>` menu; items are links (`href`) or POST/DELETE forms (`action`, `method`). `direction="up"` opens above the trigger (bottom bars). |
| `x-modal` | Native `<dialog>`; `id`, `title`, `size` sm/md/lg; slot `footer`. |
| `x-confirm-dialog`, `x-toast-region`, `x-toast` | Already in every app layout. Session `status` / `error` flashes become toasts. |
| `x-detail-list`, `x-detail-item` | Label/value grid for show pages. |
| `x-date`, `x-number` | Dates / numbers in the configured display format and digits (Phase 8c, `App\Support\Formatter`). |
| `x-chart` | Chart.js chart + accessible data table (Phase 8c); data from `App\Support\Charts\ChartData`. |
| `x-logo` | Product mark. |

## 5. JavaScript (resources/js)

`app.js` boots every module; behaviour is attached through data attributes, so a new page
usually needs **no** JS. Page-specific code goes in `resources/js/pages/<feature>.js`, imported
from `app.js` and guarded by a data-attribute check.

| Module | Hook | What it does |
|---|---|---|
| `lib/http.js` | `import { http, request, HttpError } from './lib/http'` | fetch wrapper: CSRF header, JSON in/out, normalises the API envelope (`{success:false, message, code, errors}`) and Laravel 422 JSON into `HttpError {status, code, errors, isValidation}`. |
| `lib/i18n.js` | `t('validation.required', {attribute})` | Strings from `#app-i18n`. |
| `ui/forms.js` | `form[data-validate]` | Client checks mirroring HTML attributes (`required`, `type=email`, `min/max`, `minlength/maxlength`, `data-match="field"`), messages from `lang/*/validation.php`, shown in the field's error slot. Server errors stay until the field is edited. Submit button gets the loading state; double submits blocked. |
| | `form[data-async]` | Submit via fetch; 422 → field errors; success → follow the redirect (or `data-async="event"` → `form:success` event). `showErrors(form, errors)`, `clearErrors(form)` are exported. |
| `ui/confirm.js` | `data-confirm="message"` on a form, submit button or link; optional `data-confirm-title`, `data-confirm-label`, `data-confirm-tone` (danger/warning/info) | Opens the confirmation dialog first. `await confirmDialog({...})` programmatically. |
| `ui/modal.js` | `data-modal-open="id"`, `data-modal-close` | Open/close `x-modal`; backdrop click and Escape close; focus returns to the trigger. `openModal(id)`, `closeModal(id)`. |
| `ui/toast.js` | `toast(message, {tone, title, timeout})`; `data-toast-trigger="success" data-toast-message="…"` | Danger toasts stay until closed. |
| `ui/filters.js` | `x-filter-bar`; `data-filter-debounce` on text inputs | Auto-submit on change / after typing; empty params and the page number are dropped from the URL. |
| `ui/data-table.js` | `tr[data-href]`; `data-table-select-all` + `data-table-select` | Row links; row selection with `aria-selected` and a `table:selection` event (only render selection when a bulk action exists). |
| `ui/check-group.js` | `data-check-all="g"` / `data-check-group="g"`; `data-check-count="n"` + `data-check-counted="n"` | Select-all with indeterminate state; live counters (used by the role permission matrix). |
| `ui/tabs.js` | `x-tabs` client mode | ARIA tabs, arrow keys mirrored in RTL, `#panel-id` deep links. |
| `ui/dropdown.js` | `x-dropdown` | One open at a time, outside click / Escape / arrow keys. |
| `ui/sidebar.js` | admin layout | Collapse to the 80px rail (remembered), off-canvas drawer below `lg` with scrim, Escape and focus trap. |
| `ui/theme.js` | `data-theme-toggle` | Light/dark switch (remembered; follows the OS until chosen). Emits `theme:change`. |
| `ui/locale.js` | `[data-locale-switcher]` | The switch is a plain POST form; JS adds select auto-submit and a busy state. |
| `ui/global-search.js` | admin top bar | Jumps to sidebar pages the admin can open; Enter on free text searches admin users (when permitted). `/` or Ctrl/Cmd+K focuses it. Later modules point the fallback `action` at their own search. |
| `ui/dismiss.js` | `data-dismiss="[data-alert]"` | Removes the closest matching ancestor. |
| `pages/org-chart.js` | `[data-org-chart]` (admin/structure/org-chart) | Collapse/expand branches, zoom (`--org-zoom`), find a node; the tree itself is server-rendered nested lists styled by `.org-tree` in app.css. |
| `pages/employee-form.js` | `form[data-employee-form]`; selects with `data-depends-on="branch_id"` and options with `data-parent` | Narrows department / team / position / location lists to the chosen branch / department (admin/employees/_placement-fields). The server validates the combination anyway. |
| `pages/pipeline-board.js` | `[data-pipeline-board]` (admin/recruitment/vacancies/_board) | Drag and drop of application cards between stage columns, and fetch submission of each card's stage form (the keyboard / no-JS alternative). Optimistic move; the card returns and a toast explains when the server refuses. |
| `ui/print.js` | `[data-print]` | Opens the print dialog (offer letter: print / save as PDF). |
| `pages/attendance-bulk.js` | `[data-bulk-attendance]` (admin/attendance/bulk) | "Mark all present except" list: client search over `data-search`, select all / none of the visible rows, apply one exception to the selected rows, and show the time inputs only for "late" (check-in) and "custom times" (both). Selection count through `ui/check-group.js`. Works without JS (all inputs visible; the server validates). |
| `pages/schedule-forms.js` | `form[data-shift-form]`, `[data-work-schedule-form]`, `[data-scope-form]`, `[data-schedule-change-form]` | Shift type fields and break rows, schedule pattern grid length, "applies to" record select, change/swap fields. |
| `pages/schedule-calendar.js` | `[data-schedule-calendar]` | Opens the day override modal from a calendar cell (cells are links to the day page without JS). |
| `pages/leave.js` | `form[data-leave-form]` (admin "request on behalf", portal "My leaves"); `#leave-reject-modal` + `[data-leave-reject]` (admin requests queue) | Offers only the day parts the chosen leave type allows (options `data-unit` / `data-units`), hides the end date for partial days and shows hours only for hourly leave, shows the attachment rule of the type. The queue's reject button opens one shared modal (reason required) instead of following its link to the request page. Works without JS (all fields visible; the server validates). |
| `pages/payroll.js` | `select[data-switch="group"]` + `[data-show-for="a b"]` (`data-switch-group`, `data-hide-when="group:value"`); `[data-repeater]` with `[data-repeater-rows]`, `<template data-repeater-template>` (`__INDEX__`), `[data-repeater-add]`, `[data-repeater-remove]` | Payroll forms (Phase 7a): shows only the fields the chosen calculation / recurrence / target uses (hidden fields are disabled, so not submitted); adds and removes structure lines and tax brackets. A bare `data-switch` (rendered by Blade as `data-switch="data-switch"`) is the default group. Works without JS (all fields visible, a few empty rows rendered; the server keeps only what the choice uses). |
| `pages/payroll-run.js` | `[data-payroll-progress]` (admin/payroll/runs/show while a queued calculation runs) | Polls the progress URL every 3 s, moves the bar (`inline-size` %) and reloads when the run leaves "calculating". Without JS the card shows a reload link. |
| `ui/notification-bell.js` | `[data-notification-bell]` (`partials/notification-bell`, both layouts) | Refreshes the unread badge every minute from `data-count-url` while the tab is visible. The bell itself is a `<details>` menu rendered server-side (works without JS). |
| `pages/notification-templates.js` | `[data-template-editor]` + `[data-placeholder]` chips (admin/notifications/templates/edit) | Inserts `{{name}}` at the cursor of the last focused field. Preview is a normal POST (`intent=preview`). |
| `pages/announcements.js` | `[data-format-toolbar]` (admin/announcements/form) | Optional bold / italic / list / link toolbar wrapping the selection in allowed tags; the textarea works without it and the server sanitises anyway. |
| `ui/chart.js` | `figure[data-chart]` (`x-chart`) | Chart.js wrapper (lazy chunk): tokens, dark mode rebuild, RTL, Cairo, formatted numbers. See *Charts* below. |
| `lib/format.js` | `formatNumber`, `formatDate`, `formatDigits` | Mirrors `App\Support\Formatter` from `<html data-digits data-date-format>`. |
| `pages/dashboard.js` | `form[data-dashboard-layout]` (admin/dashboard-customise) | Up / down buttons that move a widget row and renumber the position inputs. |
| `pages/employee-bulk.js` | `[data-bulk-form]` (employee directory) | Selection count and the target field of the chosen bulk action. Row checkboxes belong to the form through `form="bulk-form"`, so it works without JS. |

## 6. Recipes

**List page** — `x-filter-bar` (GET) → `x-table` with `x-table.td :label` → `x-pagination`.
Controller: validate filters, `->paginate()->withQueryString()`, pass `$filters`.
Destructive row actions: a `DELETE` form with `data-confirm`.

**Form page** — `<form data-validate class="flex flex-col gap-4 lg:gap-6">` →
one or more `x-form.section` → `x-form.actions` (cancel = secondary link, save = primary).
Keep field names identical to the Form Request; errors appear automatically.

**Organisation-structure list / form** — see `admin/structure/*`: `StructureController` (filters,
status toggle, guarded delete, shared `form.blade.php`), partials `_filters`, `_id-filter`,
`_row-actions`, `_status-badge`, `_identity-fields`, `_active-switch`. Translatable names are
`name_ar` / `name_en` columns read through `HasLocalizedName` (`$model->name`).

**Employee screens** — `admin/employees/*`: link tabs (each tab is its own request, so viewing protected
data is logged per tab), `_placement-fields` for every placement form, `_status` badge, `tabs/*` partials.
Protected values (identity numbers, salary, bank details) are rendered only after the policy check in
`App\Services\Employees\EmployeeProfile`; files are always linked through the authorised download routes.
Money is formatted with `App\Support\Money::format()` (bcmath, never float).

**Attendance screens** — `admin/attendance/*` (operations) and `admin/scheduling/*` (configuration).
Partials: `_day-status` (status pill + "manual" marker + lock icon — use it wherever a processed day is shown, manual
attendance must always be distinguishable), `_sources` (source badges), `_summary` (month totals, also used by the
portal). Times are stored in UTC: render with `App\Services\Attendance\AttendanceClock::localTime($instant, $day->timezone)`
and minutes with `App\Support\Duration::format()`. Never compute attendance figures in Blade or JS — they come from
`attendance_days` (written only by `AttendanceEngine`).

**Attendance capture screens (Phase 5b)** — `admin/attendance/devices/*`, `events/index`, `reviews/*`, `face/index`,
`work-locations`, `dashboard`, `calendar`, `reports/*`. Anything produced by a simulated adapter / provider MUST show
`@include('admin.attendance._simulated')` (the "Simulated" badge). Charts: `_category-bar` is a stacked CSS bar (inline
`inline-size` percentage per category, colours from the `status-*-dot` tokens) — no chart library. Reports are defined
once in `App\Services\Attendance\Reports\AttendanceReports` (columns, query, row) and rendered generically by
`reports/show`; add a report there, not in a new view. The calendar cells are server-rendered; no JS.

**Leave screens (Phase 6)** — `admin/leave/*` (types, policies, requests, balances, calendar, reports) and
`employee/leaves`. Partials: `_status` (request status pill), `_period` (dates in LTR + the half-day / hours label — use it
wherever a request period is shown), `_swatch` (type colour, literal `bg-chart-*` classes), `_balance-cards` (balance per type;
`compact` for a short summary, `single` for a side card), `_request-fields` (the request form fields shared by admin and portal).
Balances are never computed in Blade: they come from `App\Services\Leave\LeaveBalanceCalculator` (bcmath strings). Reports
are defined once in `App\Services\Leave\LeaveReports` (columns, query, row) and rendered generically by `reports/show`. The
calendar is server-rendered like the schedule calendar; its scroll container is `relative` so `sr-only` labels inside cells
do not widen the page.

**Payroll component screens (Phase 7a)** — `admin/payroll/*` (components, structures, entries for both allowances and
deductions — one set of views, `$resource` = `allowances` | `deductions`, statutory, loans), the profile tab
`admin/employees/tabs/compensation` and `employee/loans/*`. Partials: `_money` (DECIMAL + currency, LTR), `_request-status`
(entry / change status), `_loan-status`, `_demo` (the "DEMO — not legal advice" badge — show it wherever a demo statutory
table appears), `_approval-history`, `_target-fields` (applies-to select + one record select per target), `entries/_fields`,
`entries/_value`, `loans/_request-fields` (shared by admin and portal), `loans/_limits`, `structures/_lines` + `_line`,
`statutory/_bracket`. Amounts are never computed in Blade or JS: they come from `App\Services\Payroll\*` (bcmath strings).

**Payroll run screens (Phase 7b)** — `admin/payroll/periods/index`, `runs/*` (index, `create` = wizard step 1,
`preview` = step 2, `show` = review / approval / finalisation with link tabs employees · totals · approvals · history,
`scope`), `items/show` (the per-employee breakdown), `corrections/index`, `payslips/*`, `final-settlement/create`, the
portal `employee/payroll/*`. Partials: `runs/_wizard` (step pills: 1 scope … 5 finalise), `runs/_scope-fields` (shared
by the wizard and "change scope": checkbox lists per dimension, native multi-selects for include / exclude),
`runs/_status`, `runs/_warnings` (compact badges, or `full` sentences), `items/_lines` (lines with source and
explanation sentences from `PayrollItemLine::explanationLines()`), and `payslips/_document` — the payslip in its own
language (`dir` on the wrapper, independent of the interface language), shared by the admin and the portal. The PDF
template `payslips/pdf` is plain tables + inline CSS for mPDF; wrap dates / references in `<bdo dir="ltr">` there
(mPDF's bidi otherwise reorders hyphenated numbers in Arabic). Money stat grids use
`grid-cols-1 sm:grid-cols-2 lg:grid-cols-N` (large amounts do not fit two per row at 375 px). Inside a page that sets
`$currency`, pass `'currency' => null` to `admin.payroll._money` in table cells — includes inherit parent variables.

**Phase 8a module screens** — `admin/performance/*`, `admin/training/*`, `admin/assets/*`, `admin/disciplinary/*` and the portal
`employee/{performance,training,assets,disciplinary}/*`; strings in `lang/*/{performance,training,assets,disciplinary}.php`. They reuse
the payroll form helpers (`select[data-switch]`, `[data-repeater]` from `pages/payroll.js`) — no new JS. Partials worth reusing:
`admin/assets/_status`, `_condition`, `_return-forms`; `admin/training/_level` (skill level dots), `_validity` (certificate
valid / expiring / expired), `_session-when`. Wide grids (skills matrix) sit in a `relative overflow-x-auto` container (sr-only
labels otherwise widen the page); a `lg:col-span-*` column holding cards needs `min-w-0`. Disciplinary pages show the
"restricted — every view is logged" alert; never render case details outside a `disciplinary.view` check.

**Employee profile module tabs** — a module adds a tab by implementing `App\Services\Employees\Contracts\ProfileTab`
(`visibleTo()`, `data()`) and registering it in `EmployeeProfile::MODULE_TABS` (view `admin/employees/tabs/{tab}`); timeline
events come from a `TimelineSource` registered in `EmployeeProfile::TIMELINE_SOURCES` and render as kind `event`
(`title`, `description`, `url`, `badge`, `tone`, `module` → dot colour). Return base collections (`->toBase()`), not Eloquent ones.

**Phase 8b screens** — HR requests (`admin/hr-requests/**`, portal `employee/requests/**`), announcements
(`admin/announcements/**`, `employee/announcements/**`), the notification centre (`notifications/index` and
`notifications/preferences` are ONE view each for both areas: `@extends($area === 'admin' ? 'layouts.admin' : 'layouts.employee')`,
routes `{admin|portal}.notifications.*`, `{admin|portal}.notification-preferences.*`), notification templates and the delivery
log (`admin/notifications/{templates,deliveries}`), and settings extras (`admin/settings/extras/{group}` is included under the
generic settings form when it exists: test e-mail, FCM key upload, test push). Rules: internal notes of HR requests are
rendered only in the admin views and never loaded by portal/API code (`employeeMessages` / `employeeAttachments`); announcement
bodies are output raw ONLY through `admin/announcements/_body`, and only the stored sanitised HTML
(`App\Services\Announcements\HtmlSanitizer`); anything from the simulated push provider shows `admin.attendance._simulated`.
Secret settings (`type => secret`) render as an empty password field + "stored" hint + clear checkbox; never pass their value
to a view. The bell is `partials/notification-bell` (view composer in AppServiceProvider supplies `$bellUnread`, `$bellItems`).
Notification texts are not Blade: they are templates (`{{placeholder}}`) edited by admins — never build notification
text in a view.

**Portal navigation overflow** — portal items flagged `'more' => true` in `config/navigation.php` go into the *More* menu
(desktop dropdown in the top bar; on mobile the bottom bar shows the first four primary items plus *More*, which opens upwards:
`<x-dropdown direction="up">`, scrollable at 70 vh). Add new portal pages as `more` items.

**New JS string** — add it to `lang/{ar,en}/app.php`, expose it in the `$clientStrings`
array in `partials/head.blade.php`, read it with `t('key')`.

**Charts (Phase 8c)** — Chart.js 4 (npm `chart.js`, bundled by Vite into its own chunk; no CDN) behind
`resources/js/ui/chart.js`. Never call Chart.js directly: build the data in PHP with
`App\Support\Charts\ChartData::make($type, $labels, $datasets, $options)` (or `::series()` for one dataset; types
`bar`, `line`, `doughnut`, `pie`; colours are tokens `chart-1…6`; options `stacked`, `horizontal`, `percent`, `decimals`,
`fill`) and render `<x-chart :chart="$chart" :label="…" height="16rem" />`. The component outputs a `<figure data-chart>`
with the JSON config in a `<script type="application/json">`, a `<canvas role="img">` and an accessible data table in a
`<details>` underneath (the no-JS / screen-reader fallback). `app.js` imports the module only when a chart is on the
page. The wrapper reads colours from the CSS variables (`--color-chart-*`, text and border tokens) and rebuilds every
chart on `theme:change`; in RTL legends / tooltips run right-to-left, the category axis is reversed and the value axis
sits on the right; the font is the page font (Cairo); axis and tooltip numbers go through `lib/format.js`. Check
`ChartData::isEmpty()` and show an empty message instead of an empty chart. CSS bars (`admin/attendance/_category-bar`)
remain fine for tiny inline breakdowns.

**Display formatting (Phase 8c)** — Settings → Localisation *date format*, *time format* and *Arabic-Indic digits* are
applied by `App\Support\Formatter` (`date`, `dateTime`, `time`, `number`, `money` (bcmath), `percent`, `digits`; digits
convert only while the interface is Arabic and the setting is on). Blade: `@fmtDate($d)`, `@fmtDateTime($d)`,
`@fmtNumber($n)`, `@fmtMoney($a)`, `@fmtDigits($s)` and the components `<x-date :value format="date|datetime|time" />`
(`<time datetime>` + LTR) and `<x-number :value decimals money percent />`. JS: `lib/format.js` (`formatNumber`,
`formatDate`, `formatDigits`) reads `<html data-digits="arab|latn" data-date-format>` set by the admin and portal layouts.
Applied so far: dashboard, reports centre (screen + PDF; Excel / CSV get the date format but keep Western digits so
the files stay machine-readable), `x-stat-card` values, `x-pagination` summary, `x-chart` tables. Older screens still
print `->format('Y-m-d')` / `number_format()` directly — convert them to `x-date` / `x-number` when you touch them.

**Admin dashboard (Phase 8c)** — `admin/dashboard` + `admin/dashboard/_widget`. Widgets are registered in
`App\Services\Dashboard\WidgetRegistry` (permission any-of + required permissions + data-scope note + honoured filters +
size `sm|md|lg` + icon + method) and computed by `App\Services\Dashboard\DashboardWidgets` as plain arrays of type
`stat`, `list`, `kpis` or `chart` (already formatted; drill-down `url` into the matching report with the filters).
`AdminDashboard::widgets()` caches each widget 120 s per admin + scope + permissions + filters (`?refresh=1` recomputes).
To add a widget: a method in `DashboardWidgets`, an entry in `WidgetRegistry::WIDGETS`, `dashboard.widgets.{key}.title` in
both languages. Per-admin show / hide / order: `admin/dashboard-customise` (`dashboard_preferences`; position inputs
work without JS, `pages/dashboard.js` adds up / down buttons).

**Reports centre (Phase 8c)** — `admin/reports/{index,show,exports,pdf,_filters}`. One small class per report under
`App\Services\Reports\Definitions\*` extending `App\Services\Reports\Report` (key, area, `filters()` from
`ReportFilters::SHARED`, `extraFilters()` selects or `type => date`, `defaults()`, `columns()` of `Column` (types
text/code/number/decimal/money/percent/date/datetime/duration, `total`, guard `financial`/`disciplinary`), `query()`
builder or collection, `row()`, `sortable()`, optional `chart()`), registered in `ReportRegistry`. Apply employee
filters with `ReportFilters::applyEmployeeFilters($query, 'table.employee_id', $filters, $snapshotColumns)` — it always
enforces the data scope, even on tables that are not scoped themselves. Never format inside `row()`: return raw values
(dates, DECIMAL strings, ints); `ReportExporter::display()` formats screen and PDF cells. Phase 5b attendance and Phase 6
leave reports are reused through `LegacyAttendanceReport` / `LegacyLeaveReport` adapters. Exports: `ReportExporter`
(openspout XLSX / CSV with a totals row, mPDF PDF through `App\Support\Pdf\ArabicPdf` — the payslip setup, Cairo font);
above `config/reports.php` thresholds the export is queued (`GenerateReportExport`, runs as the admin with their scope)
and announced through the notification centre; *My exports* serves the file to its owner only.

**Employee portal (Phase 9)** — every portal page reads the signed-in user's own employee record through the `Portal*`
services (`PortalProfile`, `PortalHome`, `PortalDocuments`, `PortalLeave`, …); never look a record up by an id from the
URL without the employee constraint (ids of others answer **404**; `tests/Feature/Portal/OwnershipTest.php` probes every
portal and `me/*` route with an id automatically — register the parameter there when you add one). Pages:
`employee/home` (cards from `App\Services\Employees\PortalHome`, shared with `GET /api/v1/me/home` — today, month
totals via `x-stat-card`, balances, pending counts, latest payslip, upcoming shifts / holidays, tasks), `employee/documents/index`
(shared documents, contracts, certificates, HR letters, payslips from `PortalDocuments`), `employee/account` (password,
language, theme, browsers and app devices from `App\Services\Auth\AccountSessions`, recent sign-ins). New portal
pages go in `config/navigation.php` as `more` items. Phone widths: check 375 **and 320** px — the top bar keeps
`gap-3` below `md`, bottom-bar labels may wrap to two lines (`line-clamp-2`, icons top-aligned), long dates never wrap
(`x-date` is `whitespace-nowrap`). Lists on phones are stacked `<li>` rows or `x-table` with `x-table.td :label`.

**Display formatting in the portal (Phase 9)** — portal pages now use `x-date` / `x-number` / `Formatter::*` for dates,
amounts and counts (the shared partials `admin/payroll/_money` and `admin/leave/_balance-cards` too, and `x-stat-card`
digit-converts its hint). Times already converted to another zone (interviews) pass the zone:
`Formatter::dateTime($local, $timezone)`. Machine values (`route()` parameters, `<input value>`, comparisons) keep
`->format('Y-m-d')`.

## 7. Checks before you push

- `npm run build` (Tailwind only generates classes it finds in `resources/views`, `resources/js`,
  `app/Support` and `config/navigation.php` — never build class names by string concatenation).
- `php artisan test --compact`, `vendor/bin/pint --dirty`.
- Look at the page in Arabic and English, light and dark, at 1440, 375 and 320 wide.
