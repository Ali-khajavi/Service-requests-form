# Service Requests Form — Developer Reference

**Plugin version: 0.10.90**  
**Main file: `service-requests-form.php`**  
**Text domain: `service-requests-form`**  
**Minimum declared versions: WordPress 6.0, PHP 7.4**

This document describes the source shipped in version 0.10.90. The administrator/customer guide is [`README.md`](README.md).

## Architecture overview

The plugin implements two request paths on the same `service_request` custom post type:

- predefined service requests from `[service_request_form]`;
- custom 3D-print project orders from `[project_request_form]`.

Custom projects store `_sr_request_type = project`. They are priced from uploaded geometry, not from a predefined `sr_service` record.

The main bootstrap defines `SRF_VERSION`, loads classes, registers hooks, installs/upgrades quote tables, seeds enabled Bambu starter records, initializes WooCommerce integration, and refreshes My Account rewrite rules once per version.

## Repository map

```text
service-requests-form/
├── service-requests-form.php
├── uninstall.php
├── README.md
├── README-DEVELOPERS.md
├── Structure.txt
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   ├── frontend.css
│   │   └── project-stepper-0.10.90.css
│   └── js/
│       ├── admin.js
│       ├── calculator.js
│       ├── frontend.js
│       ├── model-worker.js
│       ├── project-viewer-webgl.js
│       ├── project.js
│       ├── uploader.js
│       └── viewer.js
├── includes/
│   ├── class-sr-cpt.php
│   ├── class-sr-services-cpt.php
│   ├── class-sr-service-data.php
│   ├── class-sr-form-handler.php
│   ├── class-sr-myaccount.php
│   ├── class-sr-settings.php
│   ├── class-srf-admin-*.php
│   ├── class-srf-google-auth.php
│   ├── class-srf-print-profiles.php
│   ├── class-srf-project-pricing.php
│   ├── class-srf-quote-db.php
│   ├── class-srf-woocommerce.php
│   └── printers/
│       ├── class-srf-printer-brand-registry.php
│       └── brands/
│           ├── bambulab.php
│           ├── formlabs.php
│           └── stratasys.php
└── templates/
    ├── form.php
    ├── project-form.php
    ├── service-info.php
    ├── frontend-list.php
    └── myaccount/service-requests.php
```

## Major classes

### `Service_Requests_Form`

Singleton bootstrap. It defines constants, loads classes, initializes hooks, runs idempotent data upgrades, seeds Bambu starter rows, initializes the hidden project product, and performs one rewrite flush per plugin version.

### `SR_Form_Handler`

Owns both shortcodes, asset registration/localization, form validation, customer profile reads, upload handling, storage quotas, request persistence, admin email generation, project access checks, project checkout branching, and Done-status file cleanup.

### `SRF_Project_Pricing`

Authoritative formula `2.1` geometry/pricing engine. It parses STL, OBJ, and 3MF; aggregates model metrics; validates build volume; resolves process settings; calculates material/time/fees/margin/tax; and returns a quote snapshot.

### `SRF_Print_Profiles`

Defines Bambu process profiles, resolves named versus custom settings, creates display labels, detects Bambu printers, and seeds missing Bambu PLA/printer starter rows without overwriting existing rows.

### `SRF_Quote_DB`

Creates and operates the custom material/printer tables. `dbDelta()` is used for upgrades.

### `SRF_WooCommerce`

Synchronizes predefined-service products, maintains the hidden project carrier product, protects add-to-cart operations, injects request prices/cart metadata, writes order-item metadata, links orders back to requests, and maps order/payment states to request states.

### `SR_Settings`

Registers settings, access modes, checkout options, quote defaults, Bambu starter settings, WooCommerce routing, Google OAuth settings, and destructive-uninstall opt-in.

### `SRF_Admin_Status`

Renders request status/file meta boxes, validates status changes, and emits `srf_request_marked_done`.

### `SRF_MyAccount`

Registers account endpoints, scopes requests to the current customer, handles secure plugin download/export routes, and renders request details.

## Frontend entry points and assets

`SR_Form_Handler::init()` registers:

```php
add_shortcode( 'service_request_form', array( __CLASS__, 'shortcode_service_request_form' ) );
add_shortcode( 'project_request_form', array( __CLASS__, 'shortcode_project_request_form' ) );
```

Base frontend CSS/JS are registered once. The project shortcode additionally enqueues:

- `assets/css/project-stepper-0.10.90.css` after the shared frontend stylesheet;
- `assets/js/project-viewer-webgl.js`, registered as the dependency-free WebGL 1 studio renderer;
- `assets/js/project.js`, dependent on the WebGL renderer, for the three-step UI, worker orchestration, viewer state, profile controls, fit preview, browser estimate, and Canvas 2D fallback;
- `assets/js/model-worker.js` as a worker URL localized to `window.srfProject`.

The worker URL is data, not a normal script enqueue. WordPress guarantees the WebGL renderer is loaded before `project.js` through the registered dependency. Legacy project functions in `frontend.js` return early when `window.srfProject` exists, preventing duplicate handlers from an older implementation.

## Project request sequence

1. Resolve coming-soon state and access mode.
2. Load active materials/printers and built-in profiles.
3. Validate nonce, honeypot, access, guest contact, title, description, terms, printer/material IDs, compatibility, layer range, and file presence.
4. Resolve the submitted process on the server. Named Bambu profiles ignore browser-tampered cost-driving fields.
5. Insert a `service_request` post and initial metadata.
6. Validate/move uploads and create attachment posts.
7. Call `SRF_Project_Pricing::calculate_final_quote()` using attached-file paths and database objects.
8. On any upload/pricing exception, delete partial attachments, release quota, delete the request, and return the error.
9. Persist normalized production metadata, price components, formula version, and `_sr_quote_snapshot`.
10. When checkout is enabled and available, add one protected cart line and redirect to cart/checkout with request status `pending-payment`.
11. Otherwise set `quote-ready`, email the administrator, and redirect back to the form.
12. Paid project notification occurs through WooCommerce hooks, not before payment.

## Browser model worker

`model-worker.js` receives:

```js
{
  id,
  name,
  extension,             // "stl" or "obj"
  maxPreviewTriangles,
  buffer                 // transferred ArrayBuffer
}
```

Successful response fields include:

```js
{
  id,
  ok: true,
  format,
  triangleCount,
  previewTriangleCount,
  volumeMm3,
  volumeCm3,
  surfaceAreaMm2,
  bounds,
  limits,
  center,
  radius,
  hasEmbeddedColors,
  previewPositions,       // transferred Float32Array, xyz per vertex
  previewFlatNormals,     // transferred Float32Array
  previewSmoothNormals,   // transferred Float32Array
  previewColors           // transferred Float32Array or empty array
}
```

The worker parses binary/ASCII STL and OBJ away from the main thread. Its preview ceiling is 160,000 triangles. It keeps every triangle at or below that ceiling and uses reservoir sampling above it while continuing to accumulate full local geometry metrics. Sampling storage uses bounded typed arrays rather than one JavaScript object per triangle. OBJ input is scanned one line at a time from the decoded string and faces are triangulated as they are encountered instead of retaining a second full face list.

Flat normals are generated for every preview. Up to 90,000 triangles, smooth normals are accumulated by rounded vertex position; above that threshold, a bounded-memory face/radial blend avoids a very large adjacency map. OBJ `v x y z r g b` vertex colours and common Materialise Magics/VisCAM binary STL colour conventions are exposed through `previewColors`. Binary STL detection accepts valid files with trailing bytes. 3MF and files above the browser safety threshold are marked server-only.

The browser estimate is explicitly non-authoritative. Do not expose any path that prices from worker output alone.

## WebGL studio viewer

`assets/js/project-viewer-webgl.js` exposes:

```js
window.SRFStudioModelRenderer
window.SRFStudioColorFromText
```

`ProjectOrderForm` instantiates `SRFStudioModelRenderer` first. If WebGL initialization throws or WebGL is unavailable, it replaces the already-context-bound canvas and instantiates the legacy `CanvasModelRenderer`. The fallback intentionally receives at most 20,000 preview triangles because its per-frame JavaScript transform, depth sort, and Canvas 2D drawing path is CPU-bound.

The WebGL renderer uses two small shader programs:

- a solid model program with smooth/flat normal selection, base or vertex colour, studio key/fill/ambient/specular/rim lighting, and a red invalid-fit blend;
- an unlit RGBA program for the bed, grid, build cage, axes, shadow, and wireframe.

The model is uploaded as non-indexed triangle buffers. Rendering is event-driven: upload, pointer movement, wheel zoom, view/orientation changes, quote/scale/printer/material changes, resize, or WebGL context restoration schedules one frame. There is no permanent `requestAnimationFrame` loop while the model is idle.

Viewer state includes:

- warm-white, grey, black, blue, red, green, representative filament colour, and embedded-file colour modes;
- smooth/flat shading and lazy wireframe generation;
- front, left, top, isometric, and fit views;
- automatic selection among six axis permutations plus manual X/Y/Z 90-degree rotations;
- printer build plate, metric grid, X/Y axes, build-volume cage, soft footprint shadow, scale/build/fit HUD, and invalid-fit styling;
- WebGL context-loss recovery.

`setSceneOptions()` receives the selected printer build dimensions, scale, material preview colour, and the browser quote fit state. The material colour is inferred from the material's `color_availability` text and name; it is representative only. Manual viewer orientation is not persisted into the request and does not alter pricing. Server build-fit validation remains authoritative.

At the 160,000-triangle ceiling, each xyz or normal Float32 buffer is about 5.76 MB decimal (5.49 MiB). Positions plus flat and smooth normals use about 17.28 MB on the main thread and a similar amount in GPU buffers; embedded colours add about 5.76 MB on each side. Wireframe is created only when enabled and adds temporary/main-thread and GPU line buffers. Exact totals vary by browser and driver. The worker transfers final typed-array buffers to avoid a second structured-clone copy.

Embedded colour support is deliberately limited to OBJ vertex colours and common binary STL colour extensions. OBJ MTL/texture bundles and 3MF colour/material resources are not rendered. 3MF remains server-analysis-only in the browser workflow.

## Server geometry parsers

### STL

- Reads the 84-byte binary header and little-endian face count.
- Treats the file as binary when `84 + faces × 50 <= file size`.
- Otherwise parses ASCII `vertex` records in triangle groups.
- Calculates signed tetrahedron volume, triangle area, triangle count, and bounds.

### OBJ

- Interprets `v` coordinates as millimetres.
- Supports positive and negative face indexes and ignores texture/normal suffixes.
- Fan-triangulates polygon faces.
- Calculates metrics during streaming line reads.

### 3MF

- Requires ZipArchive and DOM/XML.
- Limits package entries and uncompressed model XML size.
- Reads a preferred `3D/3dmodel.model` or another `.model` entry.
- Parses unit conversion, mesh resources, component graphs, build items, and affine transforms.
- Scales translation terms to the model unit.
- Detects transform reflections so mirrored and normal instances do not cancel signed volume.
- Limits recursion, vertices, resource instances, components, and expanded triangles.

### Safety limits

- `MAX_TRIANGLES = 4,000,000`
- `MAX_VERTICES = 2,000,000`
- `MAX_3MF_XML_BYTES = 134,217,728`
- `MAX_3MF_ENTRIES = 20,000`

The browser has separate preview limits and may defer sooner.

## Quote formula 2.1

Inputs are normalized to bounded values. Named Bambu profiles come from `SRF_Print_Profiles`; non-Bambu printers resolve to custom settings.

Key stages:

```text
scale_linear = scale / 100
scale_area   = scale_linear²
scale_volume = scale_linear³
solid_cm³    = mesh_volume_mm³ / 1000 × scale_volume

line_width = printer line width or nozzle × 1.05
shell_equivalent_mm = max(line_width,
                          line_width × wall_loops
                          + layer_height × (top + bottom) × 0.25)
shell_cm³ = min(solid_cm³, surface_mm² × scale_area × shell_equivalent_mm / 1000)
printed_cm³ = shell-only OR shell + interior × infill_fraction
with_support_cm³ = printed_cm³ × support_factor
adjusted_cm³ = with_support_cm³ × wastage_factor
weight_g = adjusted_cm³ × density
```

Material unit cost is the greater of volume-based and weight-based cost, multiplied by material quality and process material factors. Quantity and minimum material charge are then applied.

Machine time uses printer throughput, material machine factor, printer efficiency, layer factor, wall/cap factors, process time factor, quantity, and fixed setup/warm-up/post-process hours. Pricing models can disable material or machine-time components.

Final commercial stages:

```text
items subtotal
+ service fee
+ setup fee
+ margin (global or printer override)
+ minimum job adjustment
+ configured tax
= total price
```

The result includes raw/rounded components, model metrics, selected process values, printer/material identifiers, fit data, currency, `formula_version`, and `calculation_version`.

## Build-volume validation

`assert_models_fit_printer()` checks each model independently after scale. `dimensions_fit()` tries all axis permutations. It does not nest multiple models, pack a tray, model brims/rafts/support extents, or verify machine exclusions.

## Bambu profiles

Profile keys:

```text
bambu-008-extra-fine
bambu-008-high-quality
bambu-012-fine
bambu-012-high-quality
bambu-016-optimal
bambu-016-high-quality
bambu-020-standard
bambu-020-strength
bambu-024-draft
bambu-028-extra-draft
```

`PRESETS_VERSION` is independent from the plugin version. Seeding is idempotent and records `srf_bambu_presets_version` after a successful pass.

Starter printer `default_speed` values use `cm3/h`. The generic throughput resolver also handles common linear speed units with a line-width/layer-height conversion fallback.

## Custom database tables

Tables use the active WordPress site prefix.

### `{prefix}srf_quote_materials`

Contains identity/description, weight and volume prices, density, machine/surface/wastage factors, colour/finish/support capabilities, status, and timestamps.

### `{prefix}srf_quote_printers`

Contains identity/brand/family/settings JSON, technology, build volume, resolution/nozzle/layer constraints, throughput/cost/fixed time, material/profile compatibility, pricing rules, FDM/resin/PolyJet capability fields, status, and timestamps.

Database methods accept arrays and rely on the explicit admin sanitizers for user input. Do not pass unsanitized request data directly into insert/update methods.

## Post types and metadata

### `service_request`

Both workflows use this post type. Project-specific core metadata includes:

```text
_sr_request_type                 project
_sr_project_title
_sr_description
_sr_name / _sr_company / _sr_email / _sr_phone
_sr_user_id
_sr_access_mode
_sr_status
_sr_payment_required
_sr_payment_status
_sr_file_ids
_sr_material_id / _sr_material_name
_sr_printer_id / _sr_printer_name
_sr_print_profile_key / _sr_print_profile_name
_sr_layer_height / _sr_infill / _sr_wall_loops
_sr_top_layers / _sr_bottom_layers / _sr_infill_pattern
_sr_supports / _sr_shell_mode / _sr_scale / _sr_quantity
_sr_model_count / _sr_model_formats / _sr_model_triangles
_sr_model_bounds_mm / _sr_scaled_bounds_mm
_sr_model_volume_cm3 / _sr_effective_volume_cm3
_sr_adjusted_volume_cm3 / _sr_estimated_weight_g
_sr_unit_print_hours / _sr_estimated_print_hours
_sr_estimated_print_minutes
_sr_unit_material_cost / _sr_unit_printer_cost
_sr_material_cost / _sr_printer_cost
_sr_service_fee / _sr_setup_fee
_sr_profit_margin_percent / _sr_profit_margin_amount
_sr_tax_rate / _sr_tax_amount
_sr_subtotal_before_margin / _sr_subtotal_with_margin
_sr_total_price
_sr_price_total                   compatibility alias
_sr_currency / _sr_currency_symbol
_sr_quote_calculation_version
_sr_quote_snapshot
_sr_wc_order_id / _sr_wc_order_total
```

### `sr_service`

Defines predefined service content, pricing, variants, gallery/video, access, and generated WooCommerce product mapping.

## User metadata

```text
_srf_storage_used_bytes          current tracked request-upload usage
srf_used_bytes                   legacy usage key
srf_quota_bytes                  optional per-user quota override
seml_google_sub                  linked Google subject
```

WooCommerce billing profile keys are read but are not owned by this plugin.

## Important options

### Project/quote

```text
srf_project_access_mode          registered | public
srf_project_checkout_enabled
srf_quote_currency
srf_quote_currency_symbol
srf_quote_tax_rate
srf_quote_service_fee
srf_quote_setup_fee
srf_quote_profit_margin
srf_quote_max_upload_size
srf_quote_allowed_extensions
srf_quote_notify_admin_email
srf_quote_delete_data_on_uninstall
```

### Bambu

```text
srf_bambu_presets_enabled
srf_bambu_hourly_cost
srf_bambu_material_price_per_kg
srf_bambu_presets_version
```

### Availability/routing

```text
srf_coming_soon_service_enabled
srf_coming_soon_project_enabled
srf_service_form_page_id
srf_service_after_submit
srf_project_after_submit
srf_project_quote_product_id
srf_service_product_category_id
```

### Google/version markers

```text
srf_google_enabled
srf_google_client_id
srf_google_client_secret
srf_google_redirect_uri
srf_plugin_data_version
srf_rewrite_version
```

Legacy compatibility options remain supported where needed.

## WooCommerce integration

The hidden project product is identified by `_srf_project_quote_product = yes`. It is hidden, sold individually, physical, and non-taxable.

`add_project_request_to_cart()` validates:

- WooCommerce availability;
- project request type/status;
- positive server-stored quote total;
- formula version and request identity;
- hidden carrier product ownership.

Cart data carries a unique request key and display metadata. `apply_cart_item_prices()` reloads `_sr_total_price` by request ID rather than trusting session/customer price data.

Order line items store the request ID and quote metadata. `link_order_to_requests()` writes order linkage/contact/shipping and recognizes already-paid orders. Hooks map:

- processing/completed/payment complete → `paid`;
- on hold → `pending-payment`;
- failed → `payment-failed`;
- cancelled → `cancelled`;
- refunded → `refunded`.

`_sr_paid_notification_sent` prevents duplicate production emails.

## Notifications

`SR_Form_Handler::send_admin_new_request_email_public()` is the WooCommerce-safe public entry point. Email selection order is:

1. `srf_quote_notify_admin_email`;
2. legacy `srf_admin_email`;
3. WordPress `admin_email`.

A checkout-enabled project is not emailed as production-ready until payment. Request-only or checkout-failure projects send a quote notification immediately.

## Upload and quota behavior

Project uploads are normalized from `$_FILES`, checked for per-file and combined limits, filtered to allowed project extensions, structurally inspected, moved with `wp_handle_upload()`, and attached to the request.

Registered-user quota is checked before upload and incremented only after all files succeed. Any partial attachments are deleted on error. Project-creation failure removes attachments, reverses tracked bytes, and deletes the request post.

Marking a request `done` emits:

```php
do_action( 'srf_request_marked_done', $post_id, $user_id );
```

`SR_Form_Handler` registers its cleanup listener and permanently deletes `_sr_file_ids`, then subtracts actual file bytes from the user's usage.

## Security invariants

Maintain these properties in future changes:

- Never accept browser quote totals.
- Reload material/printer rows by ID and require `active` status.
- Enforce printer/material compatibility on the server.
- Resolve named profiles on the server.
- Reparse attached files before creating checkout data.
- Validate request ownership for account/download/export actions.
- Protect mutations with nonces and capabilities.
- Keep geometry/package/recursion limits.
- Delete partial uploads on all failure branches.
- Block direct purchase of generated service/project carrier products.
- Verify ownership metadata before deleting generated products.
- Preserve data on uninstall unless explicit destructive opt-in is set.

## Uninstall behavior

`uninstall.php` returns immediately unless `srf_quote_delete_data_on_uninstall` is true. In destructive mode it removes requests and their request attachments, services, owned generated products, quote tables, plugin options, plugin user metadata, Google state transients, and the Business User role. It deliberately retains reusable service media and refuses to delete a product whose ownership metadata does not match.

## Hooks and extension points

Important actions/filters include:

```php
srf_request_marked_done
srf_myaccount_requests_per_page
upload_mimes
woocommerce_* hooks registered in SRF_WooCommerce
```

The codebase also uses standard WordPress post-save, activation/deactivation, admin-post, shortcode, settings, rewrite, and mail APIs. Inspect the class source before relying on hook argument counts.

## Plugin language architecture

`includes/class-srf-language.php` applies a locale override only to the `service-requests-form` text domain through WordPress's `plugin_locale` filter. It reads separate options for frontend and administrator requests:

```text
srf_frontend_language
srf_admin_language
```

Allowed values are `site`, `en_US`, and `de_DE`. `site` follows WordPress's resolved locale. Frontend AJAX/REST requests are treated as frontend context so plugin-admin language does not leak into customer responses.

`SR_Settings` registers both options and renders the **Plugin language** section. German source and compiled catalogs are shipped as:

```text
languages/service-requests-form-de_DE.po
languages/service-requests-form-de_DE.mo
languages/service-requests-form.pot
```

PHP strings use the existing `service-requests-form` text domain. Dynamic JavaScript labels are passed through `wp_localize_script()` for `srfProject`, `srfFrontend`, and `srfAdmin`. Keep new customer/admin strings in PHP localization arrays rather than adding untranslated literals to JavaScript.

The project stepper uses a neutral `.srf-project-steps` `div` with `role="navigation"` and the release marker `data-srf-project-stepper="0.10.90"`. `SR_Form_Handler::register_assets()` registers `assets/css/project-stepper-0.10.90.css` after the shared frontend stylesheet, and the project shortcode enqueues it only for the custom-project form. The release-specific stylesheet defines one `flex-flow: row nowrap` container with three `.srf-project-step-slot` wrappers. Each slot uses `flex: 1 1 0`, `flex-basis: 0`, `width: 0`, and `min-width: 0`, while each button fills its slot. Matching inline `!important` declarations in `templates/project-form.php` protect the critical row, slot, and card-width properties when late theme/form-builder CSS changes wrapping, flex basis, width, minimum width, margin, float, or clear. The visual rules use a responsive 8–20 px gap; below 560 px only the secondary descriptions are hidden.

## Testing performed for 0.10.90

Release checks include:

- PHP syntax lint for every PHP file;
- Node syntax check for every JavaScript file;
- CSS brace-balance and release-stepper assertions;
- static asset-registration/dependency and translated-template assertions;
- Web Worker fixtures for ASCII/binary STL, coloured binary STL, OBJ vertex colours, malformed input, full preview retention below the ceiling, and reservoir capping above it;
- worker array-length, finite-normal, embedded-colour, bounds, volume, surface-area, and transfer-message assertions;
- a mocked DOM/WebGL lifecycle test covering renderer construction, model upload, scene options, colour/shading/wireframe/orientation state, draw calls, clear, and fallback-safe public interfaces;
- German PO/MO catalogue completeness, fuzzy-entry, and placeholder-preservation checks;
- version/reference, unwanted-file, package-root, and ZIP integrity checks.

The execution environment did not provide a functional EGL/SwiftShader WebGL context for a true headless GPU screenshot. A full WordPress/WooCommerce test in a real WebGL-capable desktop/mobile browser, plus payment-gateway staging tests, is still required for each deployment environment.

## Release checklist

1. Update both version declarations in `service-requests-form.php`.
2. Update READMEs and changelog.
3. Run PHP and JavaScript syntax checks.
4. Run geometry/worker tests.
5. Install on a clean WordPress staging site and verify activation/seeding.
6. Upgrade from the previous production version and verify `dbDelta()` columns.
7. Test registered and public project access.
8. Test STL, OBJ, 3MF, malformed, oversized, and non-fitting models.
9. Test cart, checkout, payment success, on-hold, failure, cancellation, refund, and duplicate payment hooks.
10. Test Done cleanup, quota reversal, My Account ownership, downloads, and uninstall in both preserve/delete modes.
11. Package a single top-level `service-requests-form/` directory with no tests or workspace files.

## Known limitations

- Formula 2.1 is a geometry heuristic, not Bambu Studio, PrusaSlicer, Cura, or G-code execution.
- Pricing does not model slicer-generated orientation/supports, acceleration, travel, purge, AMS changes, brims/rafts, tray packing, or printer-specific exclusion zones.
- Viewer orientation is visual only; the server independently evaluates axis-permutation fit.
- OBJ coordinates are assumed to be millimetres.
- Browser preview supports STL/OBJ only; 3MF is server-only.
- Embedded browser colours support OBJ vertex colours and common binary STL extensions, not OBJ MTL/textures or 3MF material resources.
- Extremely complex previews are sampled at 160,000 triangles. The original upload is still used for authoritative server pricing.
- The Canvas 2D compatibility fallback is capped at 20,000 displayed triangles.
- Direct Media Library URLs may be public depending on hosting configuration.
- 3MF requires PHP ZipArchive and DOM/XML.
- WooCommerce shipping/tax behavior must be reviewed for the site's legal/accounting configuration because the project carrier is physical and non-taxable while the plugin quote can contain its own tax.

## 0.10.90 implementation summary

Version 0.10.90 adds a custom, dependency-free WebGL 1 studio renderer and makes it the normal project preview. The worker now transfers positions, flat/smooth normals, and optional embedded colours; it retains complete previews through 160,000 triangles, uses bounded typed-array reservoir storage above the ceiling, and processes OBJ faces in one pass. The renderer provides studio lighting, display/filament/file colours, smooth/flat shading, lazy wireframe, standard views, automatic/manual axis orientation, printer build plate and cage geometry, fit guidance, and context-loss recovery without an idle animation loop.

The existing Canvas 2D viewer remains an automatic fallback and is capped at 20,000 displayed triangles. Common OBJ vertex colours and binary STL colour extensions are supported; MTL/textures and browser 3MF rendering are intentionally out of scope. All price, file, printer/material, fit, and checkout decisions remain server-authoritative.

The release-specific `assets/css/project-stepper-0.10.90.css` and `data-srf-project-stepper="0.10.90"` marker preserve the non-wrapping three-card step row. Independent English/German frontend and administration language loading, formula 2.1, large-OBJ server validation, quantity handling, Bambu profiles, WooCommerce payment lifecycle, paid notification, Done cleanup, and opt-in destructive uninstall behavior remain in place.
