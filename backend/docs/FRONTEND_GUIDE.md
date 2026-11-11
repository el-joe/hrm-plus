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
| `x-icon` | `name`, `size` sm/md/lg, `flip`. Add new outline icons (24 grid, 1.75 stroke) to the map inside the file. |
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
| `x-dropdown`, `x-dropdown.item`, `x-dropdown.divider` | `<details>` menu; items are links (`href`) or POST/DELETE forms (`action`, `method`). |
| `x-modal` | Native `<dialog>`; `id`, `title`, `size` sm/md/lg; slot `footer`. |
| `x-confirm-dialog`, `x-toast-region`, `x-toast` | Already in every app layout. Session `status` / `error` flashes become toasts. |
| `x-detail-list`, `x-detail-item` | Label/value grid for show pages. |
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

**New JS string** — add it to `lang/{ar,en}/app.php`, expose it in the `$clientStrings`
array in `partials/head.blade.php`, read it with `t('key')`.

**Charts** — `chart/1…6` tokens are ready; no charting library is installed yet (needs approval,
see PROJECT_PLAN.md §4).

## 7. Checks before you push

- `npm run build` (Tailwind only generates classes it finds in `resources/views`, `resources/js`,
  `app/Support` and `config/navigation.php` — never build class names by string concatenation).
- `php artisan test --compact`, `vendor/bin/pint --dirty`.
- Look at the page in Arabic and English, light and dark, at 1440 and 390 wide.
