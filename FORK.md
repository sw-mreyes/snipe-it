# Fork features, decisions and history

This file is the single reference for everything this fork adds on top of
upstream Snipe-IT v8.6.3. It records **what the custom features do**, **why they
work the way they do**, and **the traps found while building them**.

---

## 1. Background

| Branch | Role |
|---|---|
| `v5.4.1-PATCH` | The original fork, which ran in production. Behavioural source of truth — where this document says "must", it is usually because production did it that way. |
| `v8.6.3-PATCH` | A first reimplementation on Laravel 12, produced in one unsupervised pass. Useful as a reference, but it contained regressions against production and is superseded. |
| `v8.6.3-PATCH-rework` | This work: the three features rebuilt deliberately, feature by feature, each with tests and an explicit decision record. |

A direct merge of the v5.4.1 work into 8.6.3 was never viable — three major
Laravel versions apart, 2100+ conflicts. Everything here is a reimplementation
against current conventions.

### Ground rules

1. **Custom tables are `sw_`-prefixed.** Native upstream tables are never renamed or altered, so a future upstream table can never collide with ours.
2. **Environment variable names never change** without an explicit, documented decision.
3. **Every user-visible string is a translation key**, provided in `en-US`, `de-DE` (formal *Sie*) and `de-if` (informal *Du*).
4. **Build assets with `npm run prod` before committing** (see §6.4).

---

## 2. Feature: network label printing

Sends a label to an external network print-server daemon over HTTP, choosing the
printer from the item's location. Sits **alongside** the native PDF/DYMO label
engine, which only renders PDFs for the browser and cannot address this
connection model.

### Configuration

Both variables are semicolon-separated `key=value` pairs.

```env
PRINT_SERVER="berlin=http://10.0.0.5:9100;munich=http://10.0.1.5:9100"
LOCATION_MAPPING="-1=berlin;3=berlin;12=munich"
```

- `PRINT_SERVER` — `printer name = print-server base URL`. The name is arbitrary; it is only a label used by the UI and by `LOCATION_MAPPING`.
- `LOCATION_MAPPING` — `location record id = printer name`. The key is a Snipe-IT **location id** (the number in `/locations/12`), the value one of the printers above.

Two rules about the lookup:

- Only **top-level (root)** locations are consulted. An asset in `Munich Office > Floor 2 > Room 5` resolves under *Munich Office*, so you map sites, not rooms.
- `-1` applies **only** to items with no location at all. It is not a catch-all: an item whose root location is unmapped refuses to print and says so, deliberately, so a Munich label can never silently print in Berlin.

**Empty config disables the feature** — the print buttons do not render, rather
than offering an action that can only fail.

### Decisions

| # | Decision | v5.4.1 did | Why |
|---|---|---|---|
| L1 | **One syntax (`;`) for both variables** | `PRINT_SERVER` was `&`-separated (`parse_str`), `LOCATION_MAPPING` `;`-separated | Two separators for two variables of the same shape is a trap. Breaking change to `PRINT_SERVER` only; upgrade action is replacing `&` with `;`. |
| L2 | **Unmapped location refuses to print** | same | The alternative (fall back to the `-1` default) silently prints a site's labels at another site. |
| L3 | **Keep the legacy wire format** `/print?&data=<base64>` | same | The stray `&` and un-encoded base64 are exactly what the deployed daemon has always received. `?data=` is unverified against it. Do not "clean it up". |
| L4 | **Route scheme `/network-label/{type}/{id}`** | `/{type}/{id}/printlabel` | Groups the feature in one route file, cannot collide with upstream's own label routes. Old paths were internal `GET` links only. |
| L5 | **Print action on all five item types** | same | The first reimplementation shipped it on assets only, leaving four working routes unreachable and the printer picker dead. |
| L6 | **`available_actions.network_print` carries the URL** | `'print' => true` | More useful than a bare flag — clients no longer have to build the URL. |

### Security

The print-server host **always** comes from server-side config. The optional
`?printer=` parameter can only *select* a configured printer by key and is never
interpolated into a URL, so it is not an SSRF vector. The location parent walk is
cycle-guarded.

### Wire format

```
POST <base-url>/print?&data=<base64("<tag>|<name>|<subtitle>")>
```

| Type | tag | name | subtitle |
|---|---|---|---|
| Asset | `asset_tag` | asset name | category |
| Accessory | `AC-<id>` | name | category |
| Component | `CM-<id>` | name | category |
| Consumable | `CS-<id>` | name | category |
| Location | `BX-<id>` | name | **parent location** |

Responses: `200` success · `403` permission denied · `0` unreachable (logged) ·
anything else reported with the code. 10-second timeout — the original had none,
so a dead printer hung the request.

### Files

`config/sw-label-printer.php` · `app/Services/NetworkLabelPrinter/{PrinterResolver,PrintServerClient}.php` ·
`app/Http/Controllers/NetworkLabelPrinterController.php` · `routes/web/network-label-printer.php` ·
`resources/views/blade/button/network-label.blade.php` · `resources/lang/*/label-printer.php`

---

## 3. Feature: global cross-entity search

Replaces the navbar's asset-tag lookup with a search across assets, accessories,
components, consumables, locations, categories and asset models.

### How a query is processed

1. Split on `,` into terms; union the results.
2. If a term matches a printed tag (`SW-000123`, `AC-12`, `CM-3`, `CS-7`, `BX-5`), resolve it directly to that item and stop — scanning a label should not also return everything named like it.
3. Otherwise: text-search assets/accessories/components/consumables via each model's `TextSearch` scope, and name-match locations/categories/asset-models.
4. **Containers expand**: a matching location, category or asset model contributes both its own row *and* the items inside it.
5. Deduplicate by type+id, cap at 50 per type.

Only entity types the user may `index` are queried, so results degrade
gracefully instead of 403-ing.

### Decisions

| # | Decision | v5.4.1 did | v8.6.3-PATCH did |
|---|---|---|---|
| S1 | Category / asset-model match returns **the row *and* its members** | members only | row only |
| S2 | **Tag lookup restored**, including `BX-` | had it, `BX-` commented out | dropped |
| S3 | Location match returns **the row *and* its contents** | contents only | not searched at all |
| S4 | Comma-separated query **splits into terms** | split | single literal term |

Accepted consequences: a hit on a large site or category produces many rows (the
per-type cap contains it), and a literal comma can no longer be searched for.

**S2 matters most.** The label printer stamps `AC-12` on physical hardware; if
search cannot resolve that string, scanning a label finds nothing. `ItemTag` is
the single source of truth for both directions — the printer formats tags with
it, the search parses them with it — so the two cannot drift.

### Gotchas baked into the code

- **`select('<table>.*')` before `TextSearch`.** The scope joins related tables; without this their columns bleed into the model, nulling `id`/`asset_tag` and 500-ing the API.
- **The term is read from `q`, not `search`.** bootstrap-table always sends its own (usually empty) `search` parameter, which would clobber the term baked into the table's URL. `search` remains as a fallback for direct API calls.
- **Advanced/column search is disabled** on this table — it emits per-column filters the search API does not understand.

### Files

`app/Services/GlobalSearch/{GlobalSearchService,ItemTag}.php` ·
`app/Http/Controllers/{SearchController,Api/SearchController}.php` ·
`app/Http/Transformers/SearchTransformer.php` · `app/Presenters/SearchResultPresenter.php` ·
`resources/views/search/index.blade.php` · `routes/web/search.php` · `resources/lang/*/global-search.php`

---

## 4. Feature: asset reservations

Books one or more assets for a user over a time window, independently of
checkout. This is **not** upstream's requestable-items flow: there is no
approval step, no fulfilment, and no eligibility flag.

### Data model

```
sw_reservations          id, name, user_id → users, start, end, notes,
                         timestamps, deleted_at, index(start, end)
sw_asset_reservation     id, asset_id → assets (cascade),
                         reservation_id → sw_reservations (cascade),
                         unique(asset_id, reservation_id), timestamps
```

The migration is **upgrade-aware**: it renames the legacy `reservations` /
`asset_reservation` tables when they exist (preserving live rows and keys), and
creates them fresh otherwise. Column types are `increments()`/`unsignedInteger()`
so both paths converge on an identical schema.

### Behaviour

- **Overlap is rejected.** Two windows overlap iff `start1 <= end2 AND start2 >= start1`; a reservation never conflicts with itself when edited. Enforced server-side in `Reservation::conflictsExist()`, and previewed live in the form.
- **Checkout warns, never blocks.** Checking out a reserved asset shows a callout naming who reserved it and until when. v5.4.1 shipped an unused `checkout_blocked` string; the behaviour was always warn-only.
- **Authorization reuses Asset permissions** — `view` for reads, `checkout` for writes. No dedicated permission set, by decision.
- **Notifications on create**: mail to the reserving user, a message to the team webhook, and mail to whoever currently holds each reserved asset.

Responsible-holder resolution, which is subtle:

```
not checked out        -> nobody
checked out to a user  -> that user
checked out to location-> that location's manager, walking up parents
checked out to an asset-> recurse into that asset's holder
```

### Decisions

| # | Decision | Why |
|---|---|---|
| R1 | **Native `<input type="datetime-local">`** | No npm dependency, native picker, 24-hour under a German locale. v5.4.1 used TUI, the first rewrite added flatpickr. |
| R2 | **FullCalendar for the calendar view** | Real month/week/list navigation and click-for-details. Costs an npm dependency and a 226 KB bundle, loaded only on that page. |
| R3 | **Reservations are *not* in global search** | They are time-bound events, not inventory; neither previous version indexed them. |
| R4 | **`sync()` on update** | v5.4.1 only ever *added* assets, so deselecting one never detached it. |
| R5 | **Overlap checked against the submitted assets** | v5.4.1 validated against the stored set, ignoring what was posted. |
| R6 | **Notification failures are swallowed and logged** | The reservation is already committed when notifications go out; letting a dead SMTP server throw reports an error for an operation that succeeded. |
| R7 | **`AssetLabel`: name → asset tag → `#id`** | Seeded installs have bare numeric asset tags, so "no name" used to render as a meaningless number. |

### API

```
GET    /api/v1/reservations                       filters, sorting, pagination
GET    /api/v1/reservations/forasset/{asset_id}
GET    /api/v1/reservations/{reservation}/assets
GET|POST|PUT|DELETE  /api/v1/reservations[/{reservation}]
```

Filters: `start_from`, `start_to`, `end_from`, `end_to`, `start`, `end`, `user`,
`name_search`, `note_search`, `search`.

**Load-bearing default:** with no range parameter the API returns only
`end >= today`. Supplying any range honours it verbatim — this is what lets the
calendar show past months.

Rows carry dates twice on purpose: display-formatted objects for the table, ISO
8601 for the calendar and the client-side conflict checker.

### Files

`app/Models/Reservation.php` · `app/Http/Requests/{Store,Update}ReservationRequest.php` ·
`app/Http/Controllers/{ReservationsController,Api/ReservationsController}.php` ·
`app/Http/Transformers/ReservationsTransformer.php` · `app/Presenters/ReservationPresenter.php` ·
`app/Services/Reservations/ReservationNotifier.php` · `app/Notifications/Reservation*.php` ·
`app/Services/AssetLabel.php` · `resources/views/reservations/*` ·
`resources/assets/js/reservations-{calendar,form}.js` · `resources/lang/*/reservations.php`

---

## 5. Testing

```bash
# all fork features (93 tests)
php artisan test tests/Unit/Services tests/Feature/NetworkLabelPrinter \
                 tests/Feature/GlobalSearch tests/Feature/Reservations

# fast loop: unit only, no database, ~1s
php artisan test tests/Unit/Services
```

| Suite | Tests |
|---|---|
| `tests/Unit/Services/**` (DB-free) | 24 |
| `tests/Feature/NetworkLabelPrinter` | 14 |
| `tests/Feature/GlobalSearch` | 15 |
| `tests/Feature/Reservations` | 40 |

Notes:

- Tests read **`.env.testing`** and refuse to run without it (`tests/TestCase.php` guards against wiping the dev database). It points at a separate `snipeit_testing` database.
- The first DB-backed test in a process pays ~75 s rebuilding the schema (446 migrations). The assertions themselves take under a second.
- **Unit tests deliberately avoid `Tests\TestCase`**, which initializes settings from the database on every `setUp()` and so forces that rebuild. They extend `Illuminate\Foundation\Testing\TestCase` with `CreatesApplication` instead, and run in ~1 s.
- Fork tests live in fork-only directories. `tests/Feature/Search` belongs to **upstream** — pointing a path at it drags in `SearchableTraitTest`, hence `tests/Feature/GlobalSearch` for ours.
- If a run is killed mid-migration the test schema is left half-built and every later run fails with *"table `migrations` doesn't exist"*. Fix: `php artisan migrate:fresh --env=testing --force`.

---

## 6. Traps worth remembering

### 6.1 Blade compiles components inside strings

```php
// BROKEN: the <x-icon> tag is compiled mid-string, mangling the echo,
// and the raw expression is printed to the page.
{!! $errors->first('user_id', '<span><x-icon type="x" /> :message</span>') !!}
```

Use plain markup inside strings passed to `$errors->first()`.

### 6.2 Translation keys are echoed when missing

`trans('table.status')` printed the literal key because `table.php` has no
`status`. Verify a key exists before using it — `general.status` was the right one.

### 6.3 Snipe-IT returns HTTP 200 for API validation failures

`Handler::invalidJson()` answers with `200` plus `{"status":"error"}`, not `422`.
Tests must assert the payload, not the status code.

### 6.4 Build with `npm run prod`, never `npm run dev`, before committing

The repo **tracks compiled assets** (`public/js/dist`, `public/css/build`) —
production installs deploy without running npm. A `dev` build rewrites every
tracked bundle unminified (`all.js` 673 KB → 1,549 KB) and produces a manifest
full of spurious hash changes. A `prod` build leaves everything untouched except
the genuinely new files.

Both the bundle **and** the manifest entry must be committed: manifest alone
gives a 404, bundle alone makes `mix()` throw.

### 6.5 MySQL DDL breaks the test transaction

`RefreshDatabase` forces a full schema rebuild whenever a test ends its wrapping
transaction, and in MySQL **any DDL implicitly commits**. Upstream tests that
create custom fields (`Schema::table(...)`) therefore make the *next* test
re-migrate — which is why a full `php artisan test` takes ~50 minutes here. To
make it bearable on a dev box:

```sql
SET GLOBAL innodb_flush_log_at_trx_commit = 2;
SET GLOBAL sync_binlog = 0;
```

---

## 7. Deliberately dropped from the old fork

| Dropped | Reason |
|---|---|
| Component/accessory/consumable checkin/checkout API | Native upstream now |
| Laravel modernization fixes (`Input::get`, `env()`, double-includes) | Superseded by three major-version upgrades |
| Slack `Settings->notify()` HTTP 500 workaround | That code path was rewritten; the bug is gone |
| `tag` columns on accessories/components/consumables | Would alter native tables; tags are derived from ids instead |
| Location selectlist fix, accessory-view permission workaround, asset-tag fallback display | Fixed or native upstream |
| `zerofill_count` handling | Native upstream setting; reused by tag lookup |

**Not ported, still open:** per-category column presets (`categories.json` driving
default column visibility on asset tables). It has no upstream equivalent. Decide
whether it is still wanted.

---

## 8. Open items

- Global search returns the whole capped result set in one response (≤350 rows) rather than paginating server-side. Fine at current data volumes; revisit if it feels slow.
- `LdapTest` fails locally with *"Undefined constant LDAP_OPT_REFERRALS"* — the `ldap` PHP extension is not installed. Environmental, unrelated to fork code.
