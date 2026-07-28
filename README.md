# Service Requests Form

**Version: 0.10.60**  
**User and administrator guide**

Service Requests Form is a WordPress plugin for receiving two kinds of customer work:

1. **Predefined services** configured by the site administrator and displayed with `[service_request_form]`.
2. **Custom 3D-print projects** submitted through `[project_request_form]`, including model upload, model preview, printer/material/process selection, a server-verified price, and optional WooCommerce payment.

The custom-project workflow was substantially upgraded in version 0.10.60. It now has a real three-step interface, faster background STL/OBJ analysis, built-in Bambu Lab starter printers and process profiles, server-authoritative pricing, build-volume checks, and a WooCommerce payment lifecycle.

Technical implementation details are in [`README-DEVELOPERS.md`](README-DEVELOPERS.md).

## Requirements

- WordPress 6.0 or newer.
- PHP 7.4 or newer.
- HTTPS is strongly recommended for customer forms and checkout.
- WooCommerce is optional for request-only operation and required when **Project payment** is enabled.
- PHP `ZipArchive`, `DOMDocument`, and `DOMXPath` are required to analyse 3MF files. STL and OBJ pricing do not depend on those extensions.
- The server's PHP and web-server upload limits must be large enough for the configured project upload limit.

Before installing on a production site, back up the database and `wp-content/uploads`, then test the update on a staging copy.

## Installation or update

1. In WordPress, open **Plugins → Add New → Upload Plugin**.
2. Upload the version 0.10.60 ZIP.
3. When updating an existing installation, approve replacing the current plugin.
4. Activate the plugin.
5. Open **Service and Subscription → Settings** and save the settings once.
6. Review **Materials** and **Printers** before accepting paid orders.
7. Clear page/cache/CDN caches so the new JavaScript and CSS are served.

Activation and the first administrator request after an update run idempotent database upgrades, seed missing enabled Bambu starter data, prepare the hidden WooCommerce project product when WooCommerce is available, and refresh My Account rewrite rules.

## Quick setup for custom 3D-print orders

1. Create a WordPress page named, for example, **Custom 3D Print**.
2. Add this shortcode:

   ```text
   [project_request_form]
   ```

3. Open **Service and Subscription → Settings**.
4. Under **Form availability**, choose who may order:
   - **Registered website users only**; or
   - **Everyone, including guests**.
5. Enable or disable **Project payment**.
6. Under **Bambu Lab starter profiles**, leave presets enabled and select **Install missing Bambu material and printers**.
7. Open **Materials** and set the real price, density, wastage, stock/status, and available colours for each material.
8. Open **Printers** and calibrate build volume, throughput, hourly cost, setup/warm-up/post-processing time, minimum job price, and supported materials.
9. Configure currency, service fee, setup fee, margin, tax, upload size, and the production-notification email.
10. Submit and pay for a small known model on staging. Compare the result with your workshop's normal slicer and update the printer/material values as needed.

The starter values are examples and must be calibrated before live sales.

## Shortcodes

### Predefined service request

```text
[service_request_form]
```

This workflow uses services created under the `sr_service` post type. It supports service content, variants, quantities, uploads, account/profile checks, service-specific pricing, and WooCommerce routing where configured.

### Custom 3D-print project

```text
[project_request_form]
```

This workflow is independent of predefined services. It collects project details, printable models, printer/material/process choices, and a calculated quote.

## Custom-project customer journey

### Step 1 — Project details

The customer supplies a project name and description. Registered customers use account profile data. In public mode, guests enter their name and email, with optional company and phone fields.

### Step 2 — Upload and preview

The customer uploads one or more STL, OBJ, or 3MF files.

- STL and OBJ files are analysed in a Web Worker so parsing does not block the page's main interface.
- The preview renders complete solid meshes up to 160,000 triangles and samples only exceptionally large meshes.
- The built-in lightweight canvas viewer supports drag rotation, zoom, and standard views.
- 3MF files are deliberately analysed on the server. The form explains that an instant browser preview is not available for 3MF.
- Files larger than the browser-preview safety threshold are also deferred to the server.

The browser result is a convenience only. It is never trusted as the checkout price.

### Step 3 — Configure, price, and pay

The customer selects an active printer and compatible active material. Bambu Lab printers offer the built-in process list. Other printers use custom layer and infill settings.

The browser displays an immediate geometry estimate when enough local information is available. When the form is submitted, PHP reads the uploaded files again, validates the geometry, checks printer fit, recalculates every price component, stores a quote snapshot, and only then creates the checkout item.

## Bambu Lab process profiles

Version 0.10.60 includes these Bambu-style process choices:

- 0.08mm Extra Fine
- 0.08mm High Quality
- 0.12mm Fine
- 0.12mm High Quality
- 0.16mm Optimal
- 0.16mm High Quality
- 0.20mm Standard
- 0.20mm Strength
- 0.24mm Draft
- 0.28mm Extra Draft

Labels include the selected printer, for example `0.16mm High Quality @BBL X1C`.

Named Bambu profiles lock their cost-driving layer, infill, wall, top/bottom, pattern, time-factor, and material-factor values on the server. A customer cannot lower the checkout price by altering hidden browser fields.

These profiles approximate process differences for this plugin's quote engine. They are not copies of Bambu Studio configuration files and do not run Bambu Studio.

## Bambu starter resources

When enabled, the installer can add missing rows without overwriting existing rows:

- Bambu PLA Basic
- Bambu Lab X1 Carbon
- Bambu Lab P1S
- Bambu Lab P1P
- Bambu Lab A1
- Bambu Lab A1 mini

Duplicate detection normalizes brand/model identity so common `Bambu Lab` and `bambulab` spelling differences do not create another starter printer.

Changing the starter hourly or material price later does not overwrite a printer or material that has already been created. Edit live rows under **Materials** and **Printers**.

## How project pricing works

Version 0.10.60 uses quote formula `2.1`. It is a geometry-based commercial estimator, not a slicer.

The server calculates:

1. Closed-mesh volume, surface area, triangle count, and model dimensions.
2. Scale in three dimensions.
3. Approximate shell volume from surface area, nozzle/line width, wall loops, layer height, and top/bottom layers.
4. Interior infill, hollow mode, supports, material wastage, and process material factors.
5. Estimated material weight using material density.
6. Material cost using the greater configured result of price per gram or price per cubic centimetre, plus minimum material charge.
7. Machine time from printed volume, printer throughput, layer/wall/cap factors, material machine factor, printer efficiency, process time factor, quantity, and fixed setup/warm-up/post-processing time.
8. Printer cost from calculated machine time and hourly cost.
9. Service fee, setup fee, profit margin or printer-specific margin override, minimum job price, and configured tax.

The quote stored on the request includes the calculation version and a complete JSON snapshot. WooCommerce receives the server-stored request total, not a posted browser price.

### Build-volume checking

Each uploaded model is checked independently against the selected printer's X/Y/Z build volume at the selected scale. The check tries axis permutations, allowing a model to fit after rotation. It does not perform nesting or multi-part tray packing.

### Calibration advice

Use several known jobs covering small/large parts, low/high infill, supports, and different layer heights. Compare the plugin estimate with your slicer and actual workshop time. Adjust:

- material price, density, wastage, and quality/time factors;
- printer throughput and hourly cost;
- setup, warm-up, and post-processing minutes;
- support factor and line width;
- minimum material and minimum job charges;
- service fee, margin, and tax.

## File support and validation

Automatic project pricing accepts:

- `.stl` — ASCII or binary, including binary files with harmless trailing bytes;
- `.obj` — vertices and polygon faces, triangulated by the parser;
- `.3mf` — model resources, components, build items, units, and affine transforms.

The server rejects empty files, malformed structures, unreadable geometry, unsupported extensions, models with no measurable closed volume, excessive geometry, and models that do not fit the selected printer.

Current safety limits include approximately four million priced triangles, two million OBJ/3MF vertices, 20,000 3MF package entries, and 128 MB for an uncompressed 3MF model XML definition. The normal upload setting and server limits can be lower.

For reliable pricing, upload repaired, watertight meshes with consistent face orientation and millimetre-based dimensions. OBJ has no mandatory unit standard; the plugin interprets OBJ coordinates as millimetres.

## Access and availability settings

Under **Service and Subscription → Settings → Form availability**:

- Either form can be replaced by a coming-soon screen independently.
- The custom project form can be restricted to registered users or opened to everyone.
- Project payment can be required or disabled.

When payment is enabled but WooCommerce is unavailable, the plugin preserves the request as **Quote ready**, notifies the administrator, and informs the customer that checkout could not be started.

## WooCommerce project payment

When project payment is enabled:

1. The server validates and prices the request.
2. The plugin creates or reuses a hidden, non-taxable, physical WooCommerce carrier product.
3. One cart line is created for the request. Requested print quantity remains inside the verified quote, while WooCommerce cart quantity stays one to prevent multiplying the quote twice.
4. The request becomes **Pending payment**.
5. Billing and shipping details are copied from the order when available.
6. Processing, completed, or a WooCommerce payment-complete event marks the request **Paid / ready for production**.
7. The production email is sent once after payment.
8. Failed, on-hold, cancelled, and refunded order states update the request lifecycle.

The hidden product cannot be added directly without valid request data. Its cart price is restored from `_sr_total_price` on every cart calculation.

The carrier product is physical so WooCommerce can collect shipping. It is marked non-taxable because the plugin quote already includes the tax configured in Service Requests settings. Do not also add WooCommerce product tax to this item unless you deliberately redesign the tax model.

## Request statuses

The administrator can use:

- New
- Quote ready
- Pending payment
- Paid / ready for production
- In progress
- Done
- Payment failed
- Cancelled
- Refunded

Marking a request **Done** permanently deletes its uploaded request files and releases the registered user's tracked storage usage. Keep external production archives before using Done when files must be retained.

## Customer accounts and My Account

The plugin adds Service Requests views to WooCommerce My Account when WooCommerce account features are available. Request ownership is validated before account views, edits, exports, or protected download handlers are served.

A guest project remains owned by user ID `0` until WooCommerce links an authenticated customer account to the order. Billing contact details from the order are copied to the request.

## Administration

The **Service and Subscription** menu provides access to settings, requests, services, quote materials, printers, and storage tools. Administrators should review:

- active/inactive material and printer status;
- printer/material compatibility;
- per-printer build volume and layer range;
- throughput unit and hourly cost;
- notification email delivery;
- customer upload storage;
- payment and request statuses.

## Security notes

Version 0.10.60 uses nonces, capability checks, post ownership checks, server-side option resolution, extension/structure validation, upload limits, geometry limits, and server-side checkout pricing. The project form also contains a honeypot field.

Uploaded WordPress Media Library files can still be reachable by their direct upload URL on many WordPress hosts. The plugin protects its own My Account download route, but it does not turn the entire uploads directory into private storage. Sites handling confidential models should use private object storage or a protected uploads architecture.

No browser calculation, hidden field, cart price, or customer-submitted printer/material object is accepted as authoritative.

## Uninstall and data retention

By default, uninstalling the plugin **preserves all plugin data**.

When **Delete plugin data on uninstall** is explicitly enabled before uninstalling, the uninstall routine permanently removes:

- service requests and their uploaded request attachments;
- predefined services;
- only WooCommerce products proven to have been generated by this plugin;
- the hidden project carrier product when ownership metadata matches;
- quote material and printer tables;
- plugin settings and version markers;
- plugin storage/quota and Google-link user metadata;
- the Business User role.

Service gallery and featured media are not deleted because those files may be reused elsewhere. This cleanup cannot be undone without a backup.

## Troubleshooting

### The preview is slow or unavailable

Confirm that JavaScript workers are allowed by the site's Content Security Policy and that cache/minification tools are serving `assets/js/model-worker.js` and `assets/js/project.js` from version 0.10.60. Large files and all 3MF files intentionally use server analysis.

### A 3MF file is rejected

Enable PHP ZipArchive and DOM/XML extensions. Confirm the 3MF package contains at least one `.model` entry and a valid 3MF core model/build structure.

### The server price differs from the browser estimate

The server result is intentionally authoritative. A difference can occur when the browser deferred analysis, when the server normalized a named profile, or when exact server geometry/fit validation found something the preview could not know.

### Checkout does not start

Verify WooCommerce is active, checkout/cart pages are configured, the project-payment setting is enabled, and at least one payment method is available. The request should remain **Quote ready** if the cart item cannot be created.

### No printer or material appears

Install starter data or create active rows manually. Ensure the printer is active and its supported-material list includes at least one active material.

### Uploads fail below the plugin limit

Check PHP `upload_max_filesize`, `post_max_size`, web-server/proxy limits, security plugins, and hosting request limits. The smallest effective limit wins.

### Production email is missing

Check the configured production-notification email, WordPress administration email, SMTP/mail logs, spam filtering, and the request's email-result metadata. Paid-project email is intentionally delayed until WooCommerce reports payment.

## Version 0.10.60 change summary

- Fixed false OBJ validation failures when valid face records appear after the first 1 MB of a large file.
- Replaced the fixed-size OBJ sample with a constant-memory line-by-line structure scan.
- Added support for UTF-8 BOM-prefixed OBJ files during preliminary validation.
- Added explicit registered-only/public access for `[project_request_form]`.
- Rebuilt the custom-project form as three functional steps.
- Added background STL/OBJ analysis and a sampled lightweight preview.
- Added secure server-side STL, OBJ, and 3MF geometry pricing.
- Added server build-volume checks with axis rotation.
- Added ten Bambu process profiles and five starter Bambu printers.
- Added starter Bambu PLA Basic material installation.
- Added server-locked named process options and quote formula `2.1`.
- Added optional WooCommerce project checkout and payment-state synchronization.
- Added paid-only production notifications for checkout projects.
- Added project details and lifecycle information to request/account views.
- Added automatic upload cleanup when a request is marked Done.
- Changed uninstall to preserve data by default and perform complete cleanup only after explicit opt-in.
