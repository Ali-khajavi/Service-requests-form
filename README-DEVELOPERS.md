# Service Requests Form — Developer Reference

**Plugin version: 0.10.40**  
**Text domain: `service-requests-form`**  
**Main file: `service-requests-form.php`**  
**Source audit date: July 27, 2026**

This document describes the implementation shipped in version 0.10.40. It is intended for maintainers, reviewers, integrators, and developers extending the plugin.

The administrator and customer guide is [`README.md`](README.md).

## Contents

- [Version source of truth](#version-source-of-truth)
- [Bootstrap and lifecycle](#bootstrap-and-lifecycle)
- [Repository map](#repository-map)
- [Major classes](#major-classes)
- [Frontend entry points](#frontend-entry-points)
- [Request lifecycle](#request-lifecycle)
- [Custom post types](#custom-post-types)
- [Post meta model](#post-meta-model)
- [User meta](#user-meta)
- [Options](#options)
- [Custom database tables](#custom-database-tables)
- [Pricing engine](#pricing-engine)
- [WooCommerce integration](#woocommerce-integration)
- [My Account routing](#my-account-routing)
- [Google OAuth integration](#google-oauth-integration)
- [Uploads and file security](#uploads-and-file-security)
- [Admin actions and permissions](#admin-actions-and-permissions)
- [Hooks and extension points](#hooks-and-extension-points)
- [Assets and frontend JavaScript](#assets-and-frontend-javascript)
- [Activation, updates, deactivation, and uninstall](#activation-updates-deactivation-and-uninstall)
- [Internationalization](#internationalization)
- [Development workflow](#development-workflow)
- [Release checklist](#release-checklist)
- [Known implementation caveats in 0.10.40](#known-implementation-caveats-in-01040)

## Version source of truth

Version 0.10.40 is declared in two places in `service-requests-form.php`:

```php
Version:     0.10.40
```

```php
public $version = '0.10.40';
```

The runtime property is exposed as `SRF_VERSION` and is used for asset cache busting and the one-time rewrite flush.

When releasing a new version, update both declarations together and update both README files.

## Bootstrap and lifecycle

`service-requests-form.php` defines the final singleton `Service_Requests_Form`.

Bootstrap sequence:

1. `SRF()` returns `Service_Requests_Form::instance()`.
2. `setup()` defines constants.
3. `includes()` loads all class files.
4. `init_hooks()` connects CPTs, admin screens, forms, OAuth, settings, WooCommerce, My Account, translations, and rewrite maintenance.

Defined constants:

| Constant | Value |
|---|---|
| `SRF_VERSION` | Runtime plugin version. |
| `SRF_PLUGIN_FILE` | Absolute main plugin file path. |
| `SRF_PLUGIN_BASENAME` | WordPress plugin basename. |
| `SRF_PLUGIN_DIR` | Absolute plugin directory with trailing slash. |
| `SRF_PLUGIN_URL` | Plugin URL with trailing slash. |

### Loaded source files

The main loader requires:

```text
includes/class-sr-cpt.php
includes/class-sr-services-cpt.php
includes/class-sr-service-data.php
includes/class-srf-quote-db.php
includes/class-srf-admin-menu.php
includes/class-srf-admin-status.php
includes/class-srf-admin-storage.php
includes/class-srf-admin-materials.php
includes/class-srf-admin-printers.php
includes/class-srf-project-pricing.php
includes/class-sr-form-handler.php
includes/class-sr-myaccount.php
includes/class-srf-google-auth.php
includes/class-sr-settings.php
includes/class-srf-woocommerce.php
```

## Repository map

```text
service-requests-form/
├── service-requests-form.php
│   Main plugin header, singleton bootstrap, constants, MIME filter,
│   debug helper, role creation, activation/deactivation hooks.
├── uninstall.php
│   Unconditional CPT/request-file cleanup plus limited legacy option removal.
├── README.md
│   Administrator and customer guide.
├── README-DEVELOPERS.md
│   This developer reference.
├── Structure.txt
│   Legacy high-level tree; not authoritative for the current source.
├── LICENSE
│   Apache License 2.0 text.
├── includes/
│   ├── class-sr-cpt.php
│   │   service_request CPT, admin columns, request summary, exports.
│   ├── class-sr-services-cpt.php
│   │   sr_service CPT, gallery, pricing, variation, and video meta.
│   ├── class-sr-service-data.php
│   │   Published service query and frontend service-data normalization.
│   ├── class-sr-form-handler.php
│   │   Shortcodes, forms, validation, profile loading, uploads, quota,
│   │   notification mail, request creation, project quote orchestration.
│   ├── class-sr-myaccount.php
│   │   WooCommerce My Account endpoint, list/detail actions,
│   │   secure file download, customer export, description editing.
│   ├── class-sr-settings.php
│   │   Settings registration, sanitizers, and settings UI.
│   ├── class-srf-admin-menu.php
│   │   Parent admin menu, dashboard, Orders view, asset loading.
│   ├── class-srf-admin-status.php
│   │   Request status and file metaboxes; Done action emission.
│   ├── class-srf-admin-storage.php
│   │   Storage usage table and destructive per-user clear action.
│   ├── class-srf-admin-materials.php
│   │   Material CRUD UI and admin-post handlers.
│   ├── class-srf-admin-printers.php
│   │   Printer CRUD UI, capability fields, brand panels, handlers.
│   ├── class-srf-google-auth.php
│   │   Google OAuth authorization, callback, account resolution.
│   ├── class-srf-project-pricing.php
│   │   STL/OBJ/3MF parsing, geometry metrics, final quote calculation.
│   ├── class-srf-quote-db.php
│   │   Materials/printers schema installation and CRUD data access.
│   ├── class-srf-woocommerce.php
│   │   Service-product sync, request-first cart workflow, order linkage.
│   └── printers/
│       ├── class-srf-printer-brand-registry.php
│       │   Brand/family definitions and brand-settings sanitization/UI.
│       └── brands/
│           ├── formlabs.php
│           └── stratasys.php
├── templates/
│   ├── form.php
│   │   Configured service request form and inline variation/price JS.
│   ├── project-form.php
│   │   Multi-step project form, auth, upload, viewer, quote controls.
│   ├── service-info.php
│   │   Service price, video, gallery, content, and variation panel.
│   ├── frontend-list.php
│   │   Legacy placeholder; not the functional My Account list.
│   └── myaccount/
│       └── service-requests.php
│           Functional request table, modal details, export/download links,
│           and description edit form.
└── assets/
    ├── css/
    │   ├── frontend.css
    │   └── admin.css
    └── js/
        ├── frontend.js
        │   Enqueued frontend implementation: service panel, dropdown,
        │   slider/lightbox, project steps, viewer, filtering, live estimate.
        ├── admin.js
        │   Service gallery Media Library integration.
        ├── calculator.js
        ├── uploader.js
        └── viewer.js
            Standalone project modules present in the package but not enqueued
            by the current shortcode asset methods.
```

## Major classes

### `Service_Requests_Form`

Responsibilities:

- Singleton lifecycle.
- Constant definitions.
- Class loading.
- Global hook initialization.
- Translation loading.
- My Account initialization.
- One-time rewrite flush keyed by `srf_rewrite_version`.

### `SR_CPT`

Responsibilities:

- Registers `service_request`.
- Adds request list columns.
- Adds request summary metabox.
- Adds row actions for HTML/email-style exports.
- Authorizes and renders admin exports.

### `SR_Services_CPT`

Responsibilities:

- Registers `sr_service`.
- Adds gallery, pricing, variations, and video metaboxes.
- Sanitizes and stores service metadata.
- Loads Media Library admin JavaScript.
- Adds service-list columns.
- Normalizes legacy variation records.

### `SR_Service_Data`

Responsibilities:

- Queries published services ordered by `menu_order title`.
- Returns dropdown records.
- Builds frontend service data.
- Strips shortcodes from service content.
- Resolves gallery images, prices, variations, and video embed markup.

### `SR_Form_Handler`

Responsibilities:

- Registers `[service_request_form]` and `[project_request_form]`.
- Registers/enqueues frontend assets.
- Renders coming-soon and login gates.
- Loads account profile/shipping data.
- Validates service/project submissions and nonces.
- Validates uploads, quotas, extensions, and WordPress file types.
- Creates Media Library attachments and service requests.
- Calculates service prices and invokes project final pricing.
- Sends admin notification attempts.
- Exposes public wrappers for quota and cleanup operations.

### `SRF_MyAccount`

Responsibilities:

- Registers `service-requests` endpoint with `EP_PAGES`.
- Registers public and WooCommerce query vars.
- Inserts the My Account menu item.
- Loads customer-owned request records.
- Handles customer description edits.
- Streams authorized downloads.
- Generates authorized exports.
- Works around legacy root endpoint redirects.

### `SRF_Settings`

Responsibilities:

- Registers plugin options.
- Sanitizes settings values.
- Provides quote settings helper data.
- Renders the settings page.

### `SRF_Quote_DB`

Responsibilities:

- Installs custom tables with `dbDelta()`.
- Provides material and printer CRUD.
- Bootstraps missing tables on access.
- Normalizes timestamps.
- Returns active choices for frontend forms.

### `SRF_Admin_Materials`

Responsibilities:

- Material list/edit UI.
- Material save/delete admin-post handlers.
- JSON and list-field sanitization.
- Capability and nonce enforcement.

### `SRF_Admin_Printers`

Responsibilities:

- Printer list/edit UI.
- Printer save/delete handlers.
- Printer capability and technology configuration.
- Material/service-profile relationship sanitization.
- FDM, resin, and PolyJet parameter fields.
- Brand/family panel synchronization.

### `SRF_Printer_Brand_Registry`

Responsibilities:

- Loads Formlabs and Stratasys brand definitions.
- Returns brand/family choices.
- Decodes and sanitizes brand settings JSON.
- Renders brand-specific panels.

### `SRF_Project_Pricing`

Responsibilities:

- Identifies supported model extensions.
- Parses ASCII/binary STL, OBJ, and 3MF.
- Applies 3MF units, object components, and transforms.
- Computes signed-triangle volume, triangle count, and bounds.
- Calculates material, machine, fees, margin, tax, and total.

### `SRF_WooCommerce`

Responsibilities:

- Synchronizes services to simple virtual WooCommerce products.
- Creates/uses the `Services` product category.
- Controls direct purchase versus request-first behavior.
- Calculates configured service prices.
- Empties the cart and adds the request-linked service line.
- Sets line price and displays request metadata.
- Copies request data to order items.
- Links orders to requests and changes paid statuses.

### `SRF_Google_Auth`

Responsibilities:

- Generates Google authorization URLs.
- Stores state in 15-minute transients.
- Exchanges authorization code for an access token.
- Fetches OpenID Connect user info.
- Matches/creates a local user and signs the user in.
- Prevents cross-host redirects.

### `SRF_Admin_Status`

Responsibilities:

- Adds request status and request files metaboxes.
- Saves status after autosave/revision/nonce/capability checks.
- Emits `srf_request_marked_done` when status becomes `done`.

### `SRF_Admin_Storage`

Responsibilities:

- Lists users with tracked request-file storage.
- Permanently deletes all request files for a selected user.
- Resets tracked storage.

### `SRF_Admin_Menu`

Responsibilities:

- Registers the **Service and Subscription** parent menu.
- Renders dashboard cards and recent requests.
- Routes materials/printers pages.
- Renders the request-focused Orders page.
- Loads admin CSS for plugin screens.

## Frontend entry points

### Shortcodes

```text
[service_request_form]
[project_request_form]
```

No shortcode attributes are currently parsed.

### Query parameters

| Parameter | Context | Purpose |
|---|---|---|
| `srf_service` | Service form | Preselect a service ID. |
| `srf_submitted=1` | Service form/My Account | Display post-redirect success state. |
| `srf_project_submitted=1` | Project form | Display project confirmation state. |
| `srf_google_callback=1` | Site callback | Trigger Google OAuth callback. |
| `srf_google_error` | Auth forms | Display mapped Google error. |
| `srf_view` | My Account | Open a request detail modal. |
| `srf_download` | My Account | Attachment ID to stream. |
| `srf_request` | My Account | Parent request ID for download. |
| `srf_export` | My Account | Request ID to export. |
| `format` | Export | `html` or `email`. |
| `srf_nonce` | Download/export | Request-specific nonce. |
| `srf_debug` | Logged-in requests | Enables `srf_log()` output when WP debug logging is active. |

## Request lifecycle

### Configured service request

1. Shortcode enqueues `frontend.css` and `frontend.js`.
2. Coming-soon setting is checked.
3. Guest receives login gate.
4. Signed-in profile and WooCommerce shipping address are loaded.
5. Published services are queried.
6. POST data is sanitized.
7. Nonce `srf_submit_request` is verified.
8. Service, quantity, profile, description, variations, terms, shipping, and file/no-file rule are validated.
9. A published `service_request` post is inserted.
10. Request meta and calculated service price are saved.
11. Files are validated and inserted as attachments.
12. Notification mail is attempted.
13. WooCommerce tries to add the linked product.
14. If cart insertion succeeds, status changes to `pending-payment`.
15. Customer is redirected to cart/checkout or My Account.

### Project request

1. Shortcode enqueues the same base frontend assets.
2. Coming-soon setting is checked.
3. Multi-step form renders; guests can sign in within step one.
4. POST data is sanitized.
5. Nonce `srf_submit_project_request` is verified.
6. Authentication, title, description, terms, material, printer, profile compatibility, layer height, and file presence are validated.
7. A published `service_request` post is inserted with `_sr_request_type = project`.
8. Files are uploaded.
9. `SRF_Project_Pricing::calculate_final_quote()` parses the model files.
10. Quote metrics and snapshot are stored.
11. On parser/calculation error, attachments, quota, and the request are rolled back.
12. Notification mail is attempted.
13. Customer is redirected to a confirmation state.

## Custom post types

### `service_request`

Registration:

```php
'public'       => false,
'show_ui'      => true,
'show_in_menu' => 'srf-main',
'supports'     => array( 'title', 'editor' ),
'has_archive'  => false,
'rewrite'      => false,
'show_in_rest' => false,
```

### `sr_service`

Registration:

```php
'public'       => false,
'show_ui'      => true,
'show_in_menu' => 'srf-main',
'supports'     => array( 'title', 'editor', 'thumbnail' ),
'has_archive'  => false,
'rewrite'      => false,
'show_in_rest' => false,
```

Both CPTs use the standard `post` capability type.

## Post meta model

### Service meta

| Key | Type | Purpose |
|---|---|---|
| `_sr_service_gallery_ids` | `int[]` | Gallery attachment IDs. |
| `_sr_service_variations` | `array[]` | Variation groups with `key`, `values`, `prices`, `required`. |
| `_sr_service_video_url` | string | oEmbed or direct video URL. |
| `_sr_service_video_title` | string | Video heading. |
| `_sr_service_video_description` | string | Video description. |
| `_sr_service_base_price` | float | Base service price. |
| `_sr_service_direct_purchasable` | `yes`/empty | Allow normal WooCommerce purchase. |
| `_sr_wc_product_id` | int | Linked WooCommerce product ID. |

The linked product stores `_sr_service_id` and `_sr_service_direct_purchasable`.

### Common request meta

| Key | Purpose |
|---|---|
| `_sr_service_id` | Selected service ID for configured requests. |
| `_sr_service_title` | Stored service/project label. |
| `_sr_name` | Customer name snapshot. |
| `_sr_company` | Customer company snapshot. |
| `_sr_email` | Customer email snapshot. |
| `_sr_phone` | Customer phone snapshot. |
| `_sr_shipping_address` | Customer shipping snapshot. |
| `_sr_description` | Request description. |
| `_sr_no_file` | No-file indicator. |
| `_sr_terms_accepted` | Terms acceptance indicator. |
| `_sr_user_id` | Owner user ID. |
| `_sr_status` | Request workflow state. |
| `_sr_file_ids` | Attachment ID array. |
| `_sr_quantity` | Requested quantity. |
| `_sr_request_type` | `project` for open project requests; otherwise absent. |
| `_sr_wc_order_id` | Linked WooCommerce order ID. |
| `_sr_admin_email_to` | Notification recipient used. |
| `_sr_admin_email_subject` | Notification subject. |
| `_sr_admin_email_sent` | `1` or `0`. |
| `_sr_admin_email_sent_at` | WordPress local MySQL timestamp. |

### Configured-service pricing meta

| Key | Purpose |
|---|---|
| `_sr_variants` | Selected variation map. |
| `_sr_price_base` | Base price snapshot. |
| `_sr_price_extras` | Selected option surcharge details. |
| `_sr_price_total` | Total configured service price. |

### Project-selection meta

| Key | Purpose |
|---|---|
| `_sr_project_title` | Project title. |
| `_sr_material_id` | Material table ID. |
| `_sr_material_name` | Material name snapshot. |
| `_sr_printer_id` | Printer table ID. |
| `_sr_printer_name` | Printer name snapshot. |
| `_sr_service_profile_id` | Optional `sr_service` profile ID. |
| `_sr_service_profile_title` | Profile title snapshot. |
| `_sr_profile_variations` | Selected profile variations. |
| `_sr_layer_height` | Layer height. |
| `_sr_infill` | Infill percentage. |
| `_sr_shell_mode` | `solid` or `hollow`. |
| `_sr_scale` | Scale percentage, clamped to 10–500. |
| `_sr_quote_notes` | Customer print notes. |

### Project quote and geometry meta

| Key | Purpose |
|---|---|
| `_sr_model_count` | Number of parsed models. |
| `_sr_model_formats` | Comma-separated unique formats. |
| `_sr_model_triangles` | Total triangle count. |
| `_sr_model_bounds_mm` | Maximum dimensions across parsed models. |
| `_sr_model_volume_cm3` | Raw model volume. |
| `_sr_effective_volume_cm3` | Volume after scale/shell/infill. |
| `_sr_adjusted_volume_cm3` | Effective volume after wastage. |
| `_sr_estimated_weight_g` | Density-derived weight. |
| `_sr_unit_material_cost` | Per-unit material cost. |
| `_sr_unit_printer_cost` | Per-unit machine cost. |
| `_sr_material_cost` | Quantity material total. |
| `_sr_printer_cost` | Quantity machine total. |
| `_sr_service_fee` | Service fee snapshot. |
| `_sr_setup_fee` | Setup fee snapshot. |
| `_sr_profit_margin_percent` | Margin percentage. |
| `_sr_profit_margin_amount` | Margin amount. |
| `_sr_tax_rate` | Tax percentage. |
| `_sr_tax_amount` | Tax amount. |
| `_sr_subtotal_before_margin` | Items plus fees. |
| `_sr_subtotal_with_margin` | Pre-tax subtotal after margin. |
| `_sr_total_price` | Final quote total. |
| `_sr_currency` | Currency code snapshot. |
| `_sr_currency_symbol` | Currency symbol snapshot. |
| `_sr_quote_snapshot` | JSON-encoded quote result. |

### Attachment meta

| Key | Purpose |
|---|---|
| `_srf_file_bytes` | Optional recorded attachment size used by request summaries/cleanup. |

Cleanup also falls back to `filesize()` when attachment byte meta is absent.

## User meta

| Key | Purpose |
|---|---|
| `_srf_storage_used_bytes` | Canonical tracked request-upload usage. |
| `srf_used_bytes` | Legacy mirrored usage key. |
| `srf_quota_bytes` | Optional per-user quota override in bytes. |
| `seml_google_sub` | Google OpenID Connect subject ID. |

Customer profile data is read from:

```text
billing_first_name
billing_last_name
billing_company
billing_phone
```

Shipping is read through `WC_Customer` getters.

## Options

### Runtime/core options

| Option | Default/use |
|---|---|
| `srf_rewrite_version` | Version last used to flush endpoint rewrites. |
| `srf_admin_email` | Legacy notification recipient actually read by mail sender. |
| `srf_allowed_file_types` | Legacy shared upload allowlist actually read by handler. |
| `srf_max_file_size_mb` | Legacy default per-file max helper. |
| `srf_terms_url` | Terms link used by configured service form when present. |
| `srf_coming_soon_enabled` | Legacy shared coming-soon fallback. |

### Google options

| Constant | Option key |
|---|---|
| `SRF_Google_Auth::OPTION_ENABLED` | `srf_google_enabled` |
| `OPTION_CLIENT_ID` | `srf_google_client_id` |
| `OPTION_CLIENT_SECRET` | `srf_google_client_secret` |
| `OPTION_REDIRECT_URI` | `srf_google_redirect_uri` |

### Quote/settings options

| Constant | Option key | Default |
|---|---|---|
| `OPTION_CURRENCY` | `srf_quote_currency` | `EUR` |
| `OPTION_CURRENCY_SYMBOL` | `srf_quote_currency_symbol` | `€` |
| `OPTION_TAX_RATE` | `srf_quote_tax_rate` | `0` |
| `OPTION_SERVICE_FEE` | `srf_quote_service_fee` | `5` |
| `OPTION_SETUP_FEE` | `srf_quote_setup_fee` | `0` |
| `OPTION_PROFIT_MARGIN` | `srf_quote_profit_margin` | `20` |
| `OPTION_MAX_UPLOAD_SIZE` | `srf_quote_max_upload_size` | `500` |
| `OPTION_ALLOWED_EXTENSIONS` | `srf_quote_allowed_extensions` | `stl,obj,3mf` |
| `OPTION_GUEST_ORDERING` | `srf_quote_guest_ordering` | true |
| `OPTION_DELETE_ON_UNINSTALL` | `srf_quote_delete_data_on_uninstall` | false |
| `OPTION_NOTIFY_ADMIN_EMAIL` | `srf_quote_notify_admin_email` | empty |
| `OPTION_COMING_SOON` | `srf_coming_soon_enabled` | false |
| `OPTION_COMING_SOON_SERVICE` | `srf_coming_soon_service_enabled` | false/fallback |
| `OPTION_COMING_SOON_PROJECT` | `srf_coming_soon_project_enabled` | false/fallback |

### WooCommerce options

| Constant | Option key | Purpose |
|---|---|---|
| `OPTION_FORM_PAGE_ID` | `srf_service_form_page_id` | Request form page. |
| `OPTION_AFTER_SUBMIT` | `srf_service_after_submit` | `checkout` or `cart`. |
| `OPTION_CATEGORY_ID` | `srf_service_product_category_id` | Cached Services category ID. |

## Custom database tables

Tables use the active WordPress prefix.

### `{prefix}srf_quote_materials`

Columns:

```text
id
name
slug
description
price_per_gram
price_per_cm3
density
machine_time_factor
surface_quality_factor
wastage_factor
color_availability
supported_finishes
supported_support_materials
default_support_material
supported_color_modes
support_material_map
status
created_at
updated_at
```

Indexes:

- Primary key on `id`.
- Unique key on `slug`.
- Keys on `status` and `name`.

### `{prefix}srf_quote_printers`

Core columns:

```text
id
name
brand
printer_family
brand_settings_json
model
description
technology
build_volume_x
build_volume_y
build_volume_z
xy_resolution
nozzle_size
min_feature_size
max_part_weight
default_speed
speed_unit
hourly_cost
machine_efficiency_factor
setup_time_minutes
warmup_time_minutes
postprocess_time_minutes
min_layer_height
max_layer_height
supported_materials
default_material_id
supported_service_profile_ids
default_service_profile_id
supported_application_profiles
supported_finishes
supported_support_materials
default_support_material
support_material_map
supported_color_modes
pricing_model
minimum_job_price
minimum_material_charge
margin_override
```

Feature toggles and general limits:

```text
enable_infill
enable_supports
enable_structure
enable_application_profile
enable_finish_selection
enable_color_selection
enable_scale
enable_quantity
enable_advanced_settings
multi_material_enabled
color_printing_enabled
supports_hollow_models
supports_full_color_workflow
supports_biocompatible_workflow
supports_transparent_materials
supports_flexible_materials
max_materials_per_job
min_wall_thickness
max_quantity_per_job
allowed_file_formats
status
created_at
updated_at
```

FDM columns:

```text
fdm_infill_min
fdm_infill_max
fdm_support_factor
fdm_default_line_width
fdm_default_print_speed
fdm_default_travel_speed
fdm_max_print_speed
fdm_default_wall_count
fdm_default_top_layers
fdm_default_bottom_layers
fdm_default_infill_pattern
fdm_supported_infill_patterns
fdm_support_overhang_angle
fdm_support_interface_factor
fdm_cooling_factor
fdm_retraction_factor
fdm_bed_adhesion_type
fdm_bridge_optimization
```

Resin columns:

```text
resin_curing_factor
resin_shrinkage_percent
resin_default_wall_thickness
resin_support_density_factor
resin_support_removal_factor
resin_default_exposure_time
resin_bottom_exposure_time
resin_lift_speed
resin_lift_distance
resin_orientation_factor
resin_support_touchpoint_factor
resin_support_tip_size
resin_hollow_factor
resin_drain_hole_factor
resin_drain_hole_min_diameter
resin_shrinkage_compensation
resin_cure_compensation_factor
resin_default_shell_thickness
resin_post_cure_factor
resin_cleaning_difficulty_factor
```

PolyJet columns:

```text
polyjet_profile_cost_factor
polyjet_profile_time_factor
polyjet_finish_cost_factor
polyjet_finish_time_factor
polyjet_support_material_factor
polyjet_tray_packing_factor
polyjet_surface_quality_factor
polyjet_postprocess_factor
polyjet_failure_factor
polyjet_color_mixing_factor
polyjet_material_switching_factor
polyjet_cleaning_factor
polyjet_application_profile_override_factor
polyjet_support_cleanup_difficulty_factor
polyjet_layer_resolution_microns
polyjet_build_style
polyjet_voxel_control_factor
```

The printer schema is created and evolved with `dbDelta()`.

## Pricing engine

### Supported server parsers

`SRF_Project_Pricing::$supported_extensions`:

```text
stl, obj, 3mf
```

`$model_extensions` additionally identifies:

```text
step, stp, iges, igs
```

Those additional model formats cause an explicit unsupported-final-pricing exception instead of being silently ignored.

### Geometry metrics

The engine combines all parsed model files and returns:

- Total signed-triangle volume, converted to absolute volume.
- Total triangle count.
- Model count.
- Unique uppercase format list.
- Maximum X/Y/Z dimensions across models.

STL parsing supports binary and ASCII input. OBJ parsing triangulates polygon faces. 3MF parsing processes XML model resources, build items, units, components, and transforms.

### Effective volume

Let:

- `V` be raw volume in cm³.
- `S` be scale percentage.
- `H` be `0.55` for hollow or `1` for solid.
- `I` be infill percentage.

The code computes:

```text
scale_factor  = (S / 100)^3
infill_factor = 0.2 + (I / 100) × 0.8
effective_cm3 = V × scale_factor × H × infill_factor
adjusted_cm3  = effective_cm3 × material.wastage_factor
```

### Material cost

```text
estimated_g          = adjusted_cm3 × density
material_from_volume = adjusted_cm3 × price_per_cm3
material_from_weight = estimated_g × price_per_gram
unit_material_cost   = max(material_from_volume, material_from_weight)
                       × surface_quality_factor
```

### Machine cost

```text
unit_hours = (adjusted_cm3 / printer.default_speed)
             × material.machine_time_factor
             × (0.2 / layer_height)

unit_printer_cost = unit_hours × printer.hourly_cost
```

The current final-pricing formula does not incorporate most of the extended printer technology fields, minimum-job pricing, setup/warmup/post-process time, or printer margin override.

### Order total

```text
items_material_total   = unit_material_cost × quantity
items_printer_total    = unit_printer_cost × quantity
items_subtotal         = items_material_total + items_printer_total
order_fees             = service_fee + setup_fee
subtotal_before_margin = items_subtotal + order_fees
margin_amount          = subtotal_before_margin × profit_margin / 100
subtotal_with_margin   = subtotal_before_margin + margin_amount
tax_amount             = subtotal_with_margin × tax_rate / 100
final_total            = subtotal_with_margin + tax_amount
```

Returned money values are rounded to two decimals. Geometry metrics retain up to five decimals where applicable.

## WooCommerce integration

`SRF_WooCommerce::init()` is deferred to `plugins_loaded` priority 30.

### Availability

```php
class_exists( 'WooCommerce' ) && function_exists( 'WC' )
```

### Service product synchronization

On `save_post_sr_service` priority 30:

- A linked `product` post is created when absent.
- Product title/content mirror the service.
- Product type is `simple`.
- Product is virtual, in stock, unmanaged stock, and visible.
- Base price is copied to `_regular_price` and `_price`.
- Featured image is synchronized.
- A `Services` category is created/reused.

### Direct purchase control

A service product is request-first unless `_sr_service_direct_purchasable === 'yes'`.

Request-first enforcement occurs through:

- `woocommerce_add_to_cart_validation`.
- Single-product request button.
- Loop add-to-cart link replacement.

### Cart insertion

`add_request_to_cart()`:

1. Resolves or creates the linked product.
2. Calculates service price from base, variation surcharges, and requested quantity.
3. Calls `WC()->cart->empty_cart()`.
4. Adds one product unit with `srf_*` cart item data.
5. Uses the complete line total as the product price.

The requested quantity is metadata; WooCommerce cart quantity remains one.

### Order linkage

`woocommerce_checkout_create_order_line_item` writes translated display meta including Service Request ID, Quantity, and variations.

`woocommerce_checkout_order_processed` reads the request ID from the order item, stores `_sr_wc_order_id`, and sets `pending-payment`.

`woocommerce_order_status_processing` and `woocommerce_order_status_completed` set `paid`.

## My Account routing

Endpoint constant:

```php
const ENDPOINT_LIST = 'service-requests';
```

Registration:

```php
add_rewrite_endpoint( 'service-requests', EP_PAGES );
```

The class registers public query vars because secure download and export handlers run during `template_redirect`.

### Customer query

The list page queries published `service_request` posts with:

```text
meta_key   = _sr_user_id
meta_value = current user ID
```

Pagination size is controlled by:

```php
apply_filters( 'srf_myaccount_requests_per_page', 15 )
```

### Description editing

Customer edits require:

- Login.
- `srf_edit_request` nonce.
- Request post type.
- `_sr_user_id` ownership.
- Status exactly `new`, case-insensitive.

The new description is passed through `sanitize_textarea_field()` on input and `wp_strip_all_tags()` before storage.

### Secure file download

Download requires:

- Login.
- Request ID and attachment ID.
- Nonce action `srf_download_{request_id}_{attachment_id}`.
- Request ownership.
- Attachment ID present in `_sr_file_ids`.
- Existing readable attached file.

The handler sends download headers and streams the file.

### Customer export

Export requires login, owner match, and `srf_export_{request_id}` nonce. Rendering reuses `SR_CPT::build_export_html()`.

## Google OAuth integration

### Authorization

`auth_url()`:

- Generates a 32-character state token.
- Stores local redirect, intent, and creation time in a transient for 15 minutes.
- Requests `openid email profile`.
- Uses Google's OAuth 2.0 authorization endpoint.
- Uses `prompt=select_account`.

### Callback

`maybe_handle_callback()`:

1. Verifies plugin enablement and required code/state.
2. Loads and deletes the state transient.
3. Exchanges code at Google's token endpoint.
4. Fetches OpenID Connect user info.
5. Requires verified email and nonempty subject ID.
6. Resolves a user by `seml_google_sub`, then email, then creates a new account.
7. Stores `seml_google_sub`.
8. Sets current user and persistent auth cookie.
9. Redirects to the sanitized local target.

### Account creation

New accounts use:

- WooCommerce role `customer` when WooCommerce exists.
- WordPress role `subscriber` otherwise.
- Generated unique username based on email local part.
- Random 24-character password.

No Google token is stored after the callback.

## Uploads and file security

### MIME registration

The global filter in `service-requests-form.php` adds MIME types only for logged-in users with `upload_files` capability:

```text
obj, stl, ply, step, stp, igs, iges,
zip, rar, 7z,
png, jpg, jpeg, webp,
pdf
```

`SR_Form_Handler::allow_project_upload_mimes()` adds project MIME mappings through a second filter.

### Project display allowlist

`get_project_allowed_extensions()` returns:

```text
stl, 3mf, obj, step, stp, iges, igs, dxf,
pdf, jpg, jpeg, png, zip
```

### Shared handler allowlist

`get_allowed_extensions()` reads legacy option `srf_allowed_file_types`; its default is:

```text
stl, obj, mtl, ply, zip, rar, 7z,
step, stp, igs, iges,
png, jpg, jpeg, pdf, 3mf
```

### Effective validation

Every request upload passes both:

1. Shared `extension_is_allowed()`.
2. `validate_project_uploaded_file()`, which uses the project allowlist and `wp_check_filetype_and_ext()`.

Therefore the default effective extension set is the intersection of both lists and WordPress MIME recognition. The lists are currently inconsistent.

### Quota

Default totals:

```text
normal user          1,073,741,824 bytes
business/admin      10,737,418,240 bytes
```

`user_meta('srf_quota_bytes')` overrides these values when positive.

Usage is incremented only after all uploads succeed. Rollback deletes attachments and subtracts uploaded bytes when later request processing fails.

### Per-file maximum

`handle_request_uploads()` accepts a custom max. Current callers pass the user's role quota/project quota as that max, so the legacy `srf_max_file_size_mb` helper is bypassed in normal shortcode submission paths. Infrastructure limits usually become the practical file maximum.

### Attachment ownership model

Attachments are parented to the request post and their IDs are stored in `_sr_file_ids`.

Customer downloads enforce request ownership and membership in that array. The admin request-files metabox uses direct attachment URLs without the customer download handler.

## Admin actions and permissions

### Parent menu capabilities

| Screen | Capability |
|---|---|
| Dashboard | `edit_posts` |
| Add New Request | `edit_posts` |
| Add New Service | `edit_posts` |
| Orders | `edit_posts` |
| Materials | `manage_options` |
| Printers | `manage_options` |
| Storage | `manage_options` |
| Settings | `manage_options` |

### Material actions

Handlers:

```text
admin_post_srf_save_material
admin_post_srf_delete_material
```

Both require `manage_options` and request-specific nonces.

### Printer actions

Handlers:

```text
admin_post_srf_save_printer
admin_post_srf_delete_printer
```

Both require `manage_options` and request-specific nonces.

### Storage action

Handler:

```text
admin_post_srf_clear_user_storage
```

Requires `manage_options`, user-specific nonce, and valid user ID.

### Admin request export

Handler:

```text
admin_post_srf_export_request
```

Requires request-specific nonce and `edit_post` for the request.

### Status save

`save_post_service_request` checks:

- Not autosave.
- Not revision.
- Correct post type.
- Status nonce.
- `edit_post` capability.

The status value is sanitized but not restricted to the four values displayed by the metabox.

## Hooks and extension points

### Plugin-defined action

```php
do_action( 'srf_request_marked_done', $post_id, $user_id );
```

Triggered when the admin status metabox saves `done`.

Example cleanup integration:

```php
add_action( 'srf_request_marked_done', function ( $post_id, $user_id ) {
    if ( class_exists( 'SR_Form_Handler' ) ) {
        SR_Form_Handler::cleanup_request_files_public( $post_id, $user_id );
    }
}, 10, 2 );
```

The package does not register this listener itself in 0.10.40.

### Plugin-defined filter

```php
apply_filters( 'srf_myaccount_requests_per_page', 15 );
```

Example:

```php
add_filter( 'srf_myaccount_requests_per_page', function () {
    return 25;
} );
```

### Important WordPress/WooCommerce hooks used

```text
plugins_loaded
init
admin_init
admin_menu
admin_enqueue_scripts
wp_enqueue_scripts
template_redirect
wp
upload_mimes
query_vars
save_post_sr_service
save_post_service_request
add_meta_boxes
manage_*_posts_columns
manage_*_posts_custom_column
post_row_actions
woocommerce_get_query_vars
woocommerce_account_menu_items
woocommerce_account_service-requests_endpoint
woocommerce_add_to_cart_validation
woocommerce_single_product_summary
woocommerce_loop_add_to_cart_link
woocommerce_before_calculate_totals
woocommerce_get_item_data
woocommerce_checkout_create_order_line_item
woocommerce_checkout_order_processed
woocommerce_order_status_processing
woocommerce_order_status_completed
```

## Assets and frontend JavaScript

### Enqueued assets

Both shortcodes enqueue:

```text
assets/css/frontend.css
assets/js/frontend.js
```

Version is `SRF_VERSION`.

The service editor enqueues:

```text
assets/js/admin.js
```

with jQuery and WordPress Media Library.

Admin plugin pages enqueue:

```text
assets/css/admin.css
```

### Localized data

`frontend.js` receives `srfFrontend`:

```text
can_submit
popup_title
popup_message
popup_button
```

All published service data is injected into `window.srfServiceData` before the script.

### `frontend.js` responsibilities

- Service image slider and lightbox.
- Dynamic service information HTML.
- Service thumbnail dropdown.
- Variation controls and service profile variations.
- Multi-step project form state.
- STL/OBJ browser parsing and WebGL/canvas-style model display.
- Model bounds and volume calculation.
- Material-based printer filtering.
- Printer-based service profile filtering.
- Live estimate rendering.
- Submission-state transitions.

### Standalone JavaScript files

`calculator.js`, `uploader.js`, and `viewer.js` are not registered or enqueued by `SR_Form_Handler` in version 0.10.40. Avoid assuming they execute unless another integration explicitly loads them.

## Activation, updates, deactivation, and uninstall

### Activation

`srf_activate_plugin()`:

- Creates `business_user` with `read` and `upload_files`.
- Loads My Account class if needed.
- Registers endpoint.
- Calls `SRF_Quote_DB::install()`.
- Flushes rewrite rules.

The `$network_wide` argument is accepted but no multisite iteration is implemented.

### Update rewrite maintenance

On `admin_init`, `maybe_flush_rewrite_rules()` compares `srf_rewrite_version` with the runtime version. When different, it registers the endpoint, flushes once, and stores the current version.

### Deactivation

Only flushes rewrite rules. Data and roles remain.

### Uninstall

`uninstall.php` currently runs unconditional deletion without reading `srf_quote_delete_data_on_uninstall`.

It deletes:

- All `service_request` posts.
- Every attachment ID stored in each request's `_sr_file_ids`.
- All `sr_service` posts.
- Legacy options `srf_admin_email`, `srf_allowed_file_types`, `srf_max_file_size_mb`, and `srf_terms_url`.

It intentionally does not delete service gallery attachments.

It does not currently:

- Drop `{prefix}srf_quote_materials`.
- Drop `{prefix}srf_quote_printers`.
- Delete all current settings options.
- Delete storage or Google user meta.
- Remove the `business_user` role.
- Delete linked WooCommerce products.
- Remove `srf_rewrite_version`.

Any release changing uninstall behavior must be tested with real data and a backup.

## Internationalization

Text domain:

```text
service-requests-form
```

Domain path:

```text
/languages
```

`load_plugin_textdomain()` runs on `plugins_loaded` priority 0.

The package currently contains no `languages/` files. Generate POT/PO/MO files as part of a localization release.

Several hard-coded strings are not fully generic, including the frontend heading **Semlinger Dental Services**, the project response-hours copy, and some unlocalized exceptions. Review these before distributing the plugin to other organizations.

## Development workflow

### Local environment

Use a disposable or backed-up WordPress environment with:

- WooCommerce.
- Pretty permalinks.
- Mail capture.
- At least one normal customer and one business user.
- Test services, materials, and printers.
- Small valid STL, OBJ, and 3MF fixtures.
- Invalid, oversized, and unsupported upload fixtures.

### Static checks

Recommended checks:

```bash
php -l service-requests-form.php
find includes templates -name '*.php' -print0 | xargs -0 -n1 php -l
```

Use WordPress Coding Standards/PHPCS where available. The current code is not fully normalized to one formatting style, so introduce formatting-only changes separately from behavioral changes.

### Functional test matrix

Test at minimum:

1. Activation on a clean database.
2. Upgrade with existing endpoint rewrites.
3. Service creation and product synchronization.
4. Required/optional variation validation and surcharge totals.
5. Login gate and Google callback errors.
6. Missing customer profile fields.
7. Service submission with file and no-file paths.
8. Cart emptying, line pricing, order linkage, and paid status.
9. Project material/printer filtering.
10. STL ASCII/binary, OBJ, and 3MF final pricing.
11. Unsupported STEP/STP/IGES/IGS rollback.
12. Quota rejection and partial-upload rollback.
13. Customer request ownership isolation.
14. Description edit only in `new` status.
15. Authorized/unauthorized download and export.
16. Storage clear and byte accounting.
17. Uninstall behavior on a backed-up test site.

### Debug logging

`srf_log()` writes only when all conditions are true:

- `WP_DEBUG` is true.
- `WP_DEBUG_LOG` is true.
- User is signed in.
- URL contains a nonempty `srf_debug` parameter.

Log prefix:

```text
[SRF]
```

Do not expose debug query parameters or logs containing customer data in production.

## Release checklist

1. Update plugin header version.
2. Update `Service_Requests_Form::$version`.
3. Update version in `README.md` and `README-DEVELOPERS.md`.
4. Run PHP syntax checks for every PHP file.
5. Run coding-standard checks where configured.
6. Test activation and quote table migration.
7. Test rewrite flush and My Account endpoint.
8. Test both shortcodes as guest, customer, business user, and administrator.
9. Test WooCommerce product sync, cart, checkout, and paid status.
10. Test every supported model parser.
11. Test upload failure rollback and quota accounting.
12. Test notification recipient behavior.
13. Test Done status behavior and any cleanup listener.
14. Test Storage clear.
15. Review uninstall behavior and back up before executing it.
16. Rebuild translations if strings changed.
17. Remove development files and secrets from the distribution ZIP.
18. Package with one top-level `service-requests-form/` directory.
19. Install the final ZIP on a clean staging site.

## Known implementation caveats in 0.10.40

These items are derived directly from the current source and should be treated as maintenance priorities rather than assumptions.

### 1. Minimum platform versions are undeclared

The plugin header has no `Requires at least`, `Requires PHP`, or `WC requires at least` fields.

### 2. WooCommerce is effectively required for configured service submissions

`get_current_user_shipping_address()` returns an empty string when `WC_Customer` is unavailable, while the service form requires a nonempty shipping address.

### 3. Guest-ordering setting is not honored by form authorization

`current_user_can_submit()` returns only `is_user_logged_in()`, and the project handler also rejects guests. `srf_quote_guest_ordering` does not alter this behavior.

### 4. Notification option mismatch

Settings register and display:

```text
srf_quote_notify_admin_email
```

The sender reads:

```text
srf_admin_email
```

then falls back to WordPress `admin_email`.

### 5. Upload settings mismatch

The visible quote settings are:

```text
srf_quote_max_upload_size
srf_quote_allowed_extensions
```

The shared upload handler reads legacy options:

```text
srf_max_file_size_mb
srf_allowed_file_types
```

Normal shortcode callers also pass role quota as the custom per-file maximum, bypassing the helper that reads `srf_max_file_size_mb`.

### 6. File-format lists are inconsistent

Global MIME registration, project UI allowlist, legacy shared allowlist, browser preview parser, and server final-pricing parser each differ.

### 7. Done cleanup is not connected

`SRF_Admin_Status` emits `srf_request_marked_done`, but no bundled `add_action()` connects it to `cleanup_request_files_public()`.

### 8. Uninstall setting is ignored

`uninstall.php` does not check `srf_quote_delete_data_on_uninstall`. Requests, request attachments, and services are deleted whenever the uninstall file runs.

### 9. Uninstall is incomplete for modern data

Quote tables, most current options, user meta, role, and linked products remain.

### 10. WooCommerce cart is emptied

`add_request_to_cart()` calls `WC()->cart->empty_cart()` before adding the service request line.

### 11. Extended printer fields are mostly not used by final pricing

The schema/UI stores extensive technology parameters, but `calculate_final_quote()` currently uses a limited subset: default speed, hourly cost, layer height, and material factors.

### 12. Admin status selector does not include `paid`

WooCommerce can store `paid`, and My Account can label it, but the admin dropdown offers only `new`, `pending-payment`, `in_progress`, and `done`.

### 13. Standalone project JavaScript modules are dormant

`calculator.js`, `uploader.js`, and `viewer.js` are not enqueued by the current form handler.

### 14. Legacy frontend list is a placeholder

`templates/frontend-list.php` states that a future update will display requests. The working list is the WooCommerce My Account template.

### 15. No REST API exposure

Both CPTs set `show_in_rest` to false, and the plugin registers no custom REST routes.

### 16. Direct admin media links may bypass application authorization

Customer downloads are ownership-protected. The admin file metabox outputs ordinary attachment URLs; media privacy depends on server/site configuration.

### 17. Service-form presentation contains organization-specific copy

`templates/form.php` hard-codes **Semlinger Dental Services**. The project form also contains hard-coded working hours and response-time statements.

### 18. Network activation is not implemented across sites

Activation accepts `$network_wide` but does not loop through multisite blogs.

### 19. Product sync does not delete stale linked products

Deleting a service does not include a linked-product cleanup routine.

### 20. Some pricing/admin concepts are not connected end to end

Printer minimum job price, minimum material charge, margin override, setup/warmup/post-process time, and many advanced technology fields are stored but not applied by the final quote formula.

## License

See [`LICENSE`](LICENSE), Apache License 2.0.
