# Service Requests Form

**Current plugin version: 0.10.40**  
**Documentation reviewed against the complete plugin source: July 27, 2026**

Service Requests Form is a WordPress plugin for collecting, pricing, purchasing, tracking, and administering customer service requests. It combines a configurable service catalogue, customer file uploads, WooCommerce account and checkout integration, a 3D project quotation workflow, materials and printer administration, Google sign-in, and an internal request dashboard.

This file is the **site-owner and administrator guide**. Technical architecture, data structures, hooks, and development notes are documented in [`README-DEVELOPERS.md`](README-DEVELOPERS.md).

## Contents

- [What the plugin provides](#what-the-plugin-provides)
- [Important requirements](#important-requirements)
- [Installation](#installation)
- [Quick setup](#quick-setup)
- [Shortcodes](#shortcodes)
- [Configure services](#configure-services)
- [Configure project quotations](#configure-project-quotations)
- [Customer workflows](#customer-workflows)
- [WooCommerce behavior](#woocommerce-behavior)
- [Requests and statuses](#requests-and-statuses)
- [Uploads and storage](#uploads-and-storage)
- [Google sign-in](#google-sign-in)
- [Settings reference](#settings-reference)
- [Exports and downloads](#exports-and-downloads)
- [Data removal and uninstall warning](#data-removal-and-uninstall-warning)
- [Troubleshooting](#troubleshooting)
- [Current-version notes](#current-version-notes)

## What the plugin provides

### Configured service requests

The `[service_request_form]` shortcode displays a responsive service-order form. A signed-in customer can:

- Select a published service.
- See the service image, gallery, description, video, base price, and available options.
- Choose required or optional service variations.
- See option surcharges and a calculated quantity total.
- Enter a project description.
- Upload one or more files, or indicate that no file is currently needed.
- Accept the terms and submit the request.
- Continue to the WooCommerce cart or checkout when a priced service is linked to WooCommerce.

Customer contact and shipping details are not typed into this form. They are read from the signed-in user and WooCommerce customer profile.

### Open 3D project requests

The `[project_request_form]` shortcode displays a multi-step project workflow for 3D-print and manufacturing requests. It includes:

- Project title and description.
- Local WordPress sign-in.
- Optional Google sign-in or registration.
- Terms acceptance.
- Multiple file upload.
- Browser preview for supported model files.
- Material and printer selection.
- Optional service-profile selection.
- Layer height, infill, shell mode, scale, quantity, and print notes.
- A live browser estimate.
- A server-calculated final quote stored with the request.

For the final server-side quotation in version 0.10.40, the printable model must be **STL, OBJ, or 3MF**. See [Project file compatibility](#project-file-compatibility).

### Customer request dashboard

When WooCommerce is active, the plugin adds **Service Requests** to **My Account**. Customers can:

- View requests belonging to their account.
- Open request details.
- Review status, price, customer data, options, and project settings.
- Download uploaded files through ownership-checked links.
- Export a request as HTML or open an email-style HTML view.
- Edit the request description while its status is `new`.

### Administration

The plugin adds a **Service and Subscription** menu containing:

- Dashboard.
- Service Requests and Add New Request.
- Services and Add New Service.
- Materials.
- Printers.
- Orders.
- Storage.
- Settings.

The dashboard summarizes request counts, recent requests, upload storage, materials, and printers.

## Important requirements

### WordPress and PHP

The current plugin header does not declare `Requires at least` or `Requires PHP` values. Test version 0.10.40 on a staging site before production deployment.

The source uses modern PHP syntax and WordPress APIs. A maintained PHP 7.4+ or PHP 8.x environment is recommended, together with a current WordPress release.

### WooCommerce

WooCommerce is strongly recommended and is functionally required for the complete workflow:

- The service form reads the shipping address through `WC_Customer`.
- The customer dashboard uses WooCommerce My Account endpoints.
- Priced service requests are added to the WooCommerce cart.
- Service products are synchronized as WooCommerce products.

Without WooCommerce, the service request form cannot obtain the required shipping address and therefore cannot complete normal validation.

### User accounts

Both frontend forms require a signed-in WordPress user in version 0.10.40.

For a configured service request, the account should contain:

- Name or display name.
- Billing company.
- Valid email address.
- Billing phone.
- Complete WooCommerce shipping address.

The plugin creates a `business_user` role on activation. This role is not required to submit a request; its main built-in benefit is the higher upload quota.

### Permalinks

The plugin registers the WooCommerce endpoint:

```text
/my-account/service-requests/
```

Activation and version changes flush rewrite rules automatically. If the My Account tab returns a 404, save **Settings > Permalinks** once without changing anything.

## Installation

1. Back up the WordPress database and uploads directory.
2. In WordPress Admin, open **Plugins > Add New Plugin > Upload Plugin**.
3. Upload the plugin ZIP.
4. Activate **Service Requests Form**.
5. Confirm that the **Service and Subscription** menu appears.
6. Confirm that WooCommerce is active and its My Account, Cart, and Checkout pages are configured.
7. Complete the setup steps below.

Activation performs the following operations:

- Creates the `business_user` role if it does not already exist.
- Creates or updates the quote materials and printers database tables.
- Registers the My Account endpoint before flushing rewrite rules.

## Quick setup

### 1. Create the service request page

Create a normal WordPress page and insert:

```text
[service_request_form]
```

Publish the page, then select it under:

**Service and Subscription > Settings > Service request form page**

This page is used by linked WooCommerce service products when a customer must submit a request before purchasing.

### 2. Create the project request page

Create another page and insert:

```text
[project_request_form]
```

Publish the page.

### 3. Create services

Open **Service and Subscription > Add New Service** and configure at least one published service. See [Configure services](#configure-services).

### 4. Configure customer accounts

Make sure customers can register through WooCommerce My Account. Ask them to complete billing company, billing phone, and shipping details before using the service form.

Assign the **Business User** role to accounts that should receive the built-in 10 GB quota instead of the standard 1 GB quota.

### 5. Configure materials and printers

The project form only displays active materials and active printers. Add at least one of each under:

- **Service and Subscription > Materials**
- **Service and Subscription > Printers**

### 6. Review plugin settings

Open **Service and Subscription > Settings** and configure currency, taxes, fees, margins, file settings, WooCommerce redirect behavior, and optional Google authentication.

### 7. Test end to end

Use a non-administrator test account to verify:

1. Registration and profile completion.
2. Service selection and variations.
3. Upload validation.
4. Cart or checkout redirect.
5. My Account request visibility.
6. Admin request details and status changes.
7. File download and export access.

## Shortcodes

### Service request form

```text
[service_request_form]
```

Behavior:

- Shows a coming-soon panel when the service-form coming-soon option is enabled.
- Shows a local/Google login gate to guests.
- Requires a complete customer profile for signed-in users.
- Loads all published `sr_service` records.
- Defaults to the first service, unless a valid service is supplied in the URL.

To preselect a service, link to the form page with:

```text
?srf_service=123
```

Replace `123` with the service post ID.

### Project request form

```text
[project_request_form]
```

Behavior:

- Shows a separate coming-soon panel when project coming-soon mode is enabled.
- Includes a sign-in step for guests.
- Requires a project title, description, terms acceptance, material, printer, and at least one file.
- Validates printer/material and optional printer/service-profile compatibility.
- Enforces the selected printer's minimum and maximum layer height when configured.
- Calculates and stores a final quote after files have uploaded successfully.

## Configure services

Services are stored as the private admin-only custom post type `sr_service`.

### Basic content

Each service supports:

- Title.
- Main editor content.
- Featured image.
- Published or unpublished status.

Only published services appear in frontend selectors.

The service content is formatted for the information panel. Shortcodes inside service content are stripped before display to prevent accidental nested forms.

### Gallery

Use **Service Gallery / Slider** to select one or more Media Library images. The frontend displays these images in a slider and lightbox-style viewer.

Deleting a service does not automatically delete its reusable gallery images from the Media Library.

### Base price and linked WooCommerce product

Use **Service Pricing / WooCommerce Product** to set:

- Base price.
- Whether the service may be purchased directly from the WooCommerce shop.

When WooCommerce is active, saving a service creates or updates a linked simple virtual product. The plugin synchronizes:

- Product title and content.
- Base price.
- Featured image.
- Product category named **Services**.
- Direct-purchase behavior.

When direct purchase is disabled, shop buttons lead customers to the configured service request form instead of allowing a normal add-to-cart action.

### Service variations and option prices

A service can contain multiple variation groups. Each group has:

- A key, such as `Height`, `Finish`, or `Urgency`.
- Comma-separated values.
- Optional per-value surcharge.
- Required or optional selection behavior.

Use the following format to add prices:

```text
Standard|0, Express|50, Premium|120
```

A value without `|price` has a zero surcharge.

For a configured service request, the price is calculated as:

```text
(base price + selected option surcharges) × requested quantity
```

### Service video

A service may include:

- Video URL.
- Video title.
- Video description.

The frontend attempts WordPress oEmbed first. YouTube and Vimeo normally work through oEmbed. Direct `.mp4`, `.webm`, and `.ogg` URLs are rendered with an HTML video player.

## Configure project quotations

### Materials

Material records are stored in the plugin quote database. Important fields include:

- Name, slug, and description.
- Price per gram.
- Price per cm³.
- Density.
- Machine-time factor.
- Surface-quality factor.
- Wastage factor.
- Available colors.
- Supported finishes.
- Support-material choices and mappings.
- Color modes.
- Active or inactive status.

Only active materials appear in the project form.

### Printers

Printer profiles support a large range of machine and pricing fields, including:

- Name, brand, family, model, description, and technology.
- Build volume.
- Resolution, nozzle, feature-size, and weight limits.
- Default speed and speed unit.
- Hourly cost and efficiency factors.
- Setup, warmup, and post-processing time.
- Layer-height range.
- Supported/default materials.
- Supported/default service profiles.
- Finishes, supports, color modes, and application profiles.
- Minimum pricing and margin overrides.
- Feature toggles for infill, supports, structure, scale, quantity, and advanced settings.
- Technology-specific FDM, resin, and PolyJet parameters.
- Multi-material, full-color, biocompatible, transparent, and flexible-material capabilities.
- Allowed file formats and job limits.
- Active or inactive status.

Only active printers appear in the project form.

The admin interface includes brand-specific extension data for **Formlabs** and **Stratasys**.

### Service profiles

A printer may allow one or more published services to act as service profiles. When a profile is selected in the project form, its variation groups can be displayed as project options.

### How final project pricing works

Version 0.10.40 calculates a model volume and applies:

- Scale.
- Solid or hollow factor.
- Infill factor.
- Material wastage, density, and surface factors.
- Material cost based on the greater of volume pricing and weight pricing.
- Printer speed, hourly cost, machine-time factor, and layer-height factor.
- Quantity.
- Service fee.
- Setup fee.
- Profit margin.
- Tax.

The resulting quote details and a JSON quote snapshot are stored with the request.

The browser estimate is a convenience preview. The stored server-side quote is the authoritative calculation for the submitted request.

### Project file compatibility

The upload interface presents a broad set of design and reference formats. However, final automatic pricing in version 0.10.40 parses only:

- STL, including ASCII and binary STL.
- OBJ.
- 3MF.

For the most reliable project submission, upload an STL, OBJ, or 3MF model.

Current limitations:

- STEP, STP, IGES, and IGS are recognized as model formats but are rejected by the final automatic-pricing engine.
- The browser 3D preview parses STL and OBJ; a 3MF file can be priced by the server but may not preview in the browser.
- PDFs, images, and ZIPs may be useful as supporting files but do not provide printable volume data.
- Actual acceptance also depends on WordPress MIME validation and the site's server/PHP upload limits.

## Customer workflows

### Configured service workflow

1. The customer opens the page containing `[service_request_form]`.
2. A guest signs in or registers.
3. The plugin checks the customer's profile.
4. The customer selects a service, options, and quantity.
5. The customer enters a project description.
6. The customer uploads files or selects the no-file option.
7. The customer accepts the terms and submits.
8. A `service_request` post is created.
9. Files are stored as WordPress Media Library attachments linked to the request.
10. The plugin sends an admin notification attempt.
11. When WooCommerce can create a linked service product, the request is added to the cart and its status becomes `pending-payment`.
12. The customer is redirected to cart or checkout according to settings.

### Open project workflow

1. The customer enters a project title and description.
2. A guest signs in locally or with Google.
3. The customer accepts the terms.
4. The customer uploads one or more files.
5. The customer chooses material, printer, optional service profile, and print settings.
6. The plugin validates compatibility and layer height.
7. Files are stored as attachments.
8. The server parses supported 3D model files and calculates the final quote.
9. The request and quote snapshot are saved.
10. The plugin sends an admin notification attempt.
11. The customer sees a confirmation screen and can open the request dashboard.

### My Account workflow

The My Account table includes:

- Date.
- Service or project type.
- Status.
- Upload count and size.
- Price when available.
- Request ID.
- View action.

A customer can edit only the description, and only while the request status is `new`. File replacement and model-setting changes are not available from My Account.

## WooCommerce behavior

### Product synchronization

Every saved service may be synchronized to a simple virtual WooCommerce product. The service post stores the linked product ID.

### Request-first purchasing

When direct purchasing is disabled for a service:

- Normal add-to-cart attempts are blocked.
- Shop/archive buttons become **Request service** links.
- The single-product page shows a **Submit service request** button.
- The customer must submit the plugin form before the product can be added.

### Cart behavior

When a configured service request is successfully added:

- The plugin empties the current WooCommerce cart.
- It adds one linked service product line.
- The plugin stores the requested quantity as request/cart metadata.
- The WooCommerce line price is set to the complete calculated request total.

This means unrelated products already in the customer's cart are removed. Test this behavior against your store requirements.

### Order linkage and paid status

At checkout, the request ID and selected options are copied to the order line item. The request stores the WooCommerce order ID.

WooCommerce order status changes work as follows:

- Checkout creation keeps the request at `pending-payment`.
- Order status `processing` or `completed` changes the request to `paid`.

## Requests and statuses

Requests are stored as the private admin-only custom post type `service_request`.

### Status values

Version 0.10.40 uses these values:

| Status | Meaning |
|---|---|
| `new` | Newly submitted and still description-editable by the customer. |
| `pending-payment` | Added to a WooCommerce purchase flow but not marked paid. |
| `paid` | Linked order reached WooCommerce processing or completed status. |
| `in_progress` | Work has started. |
| `done` | Work is complete. |

The admin status selector offers `new`, `pending-payment`, `in_progress`, and `done`. The `paid` value is normally assigned by WooCommerce.

### Admin request screen

The request edit screen provides:

- Request summary.
- Customer and shipping data.
- Selected service and variants.
- Current status selector.
- Uploaded-file links and sizes.
- HTML and email-style export actions.
- Project and quote metadata in the request record.

## Uploads and storage

### Quotas

The built-in total storage quotas are:

| Account | Default total quota |
|---|---:|
| Normal signed-in user | 1 GB |
| `business_user` | 10 GB |
| Administrator | 10 GB |

A developer can set a per-user byte override with the `srf_quota_bytes` user-meta field.

The plugin tracks used bytes in `_srf_storage_used_bytes` and also writes the legacy `srf_used_bytes` key for compatibility.

### Upload storage

Uploaded request files are stored as normal WordPress attachments and are associated with the request post. Consequently:

- The files exist in the WordPress uploads directory.
- They appear in Media Library data.
- Server, PHP, web-server, proxy, and WordPress upload limits still apply.
- Backup and privacy policies should include these files.

### Allowed files

The plugin registers MIME support for several CAD, archive, image, and PDF types. The submission handler also applies its own extension and WordPress file-type checks.

For current default form behavior, the safest formats are:

```text
stl, obj, 3mf, step, stp, iges, igs, pdf, jpg, jpeg, png, zip
```

Project final pricing has the narrower STL/OBJ/3MF requirement explained above.

### Storage administration

**Service and Subscription > Storage** lists users with tracked usage. The **Clear storage** action:

- Finds all service requests owned by that user.
- Permanently deletes their linked request attachments.
- Clears each request's file list.
- Resets the tracked storage value.

This action is destructive and cannot be undone without a backup.

### Done-status cleanup behavior

The `done` status emits the developer action `srf_request_marked_done`. In the supplied version 0.10.40 source, no bundled listener attaches automatic file cleanup to that action.

Therefore, do not assume that changing a request to Done removes files. Use the Storage screen or add a developer integration if automatic cleanup is required.

## Google sign-in

Google sign-in can be enabled under **Service and Subscription > Settings**.

Required values:

- Enable Google Login.
- Google Client ID.
- Google Client Secret.
- Redirect URI, or leave blank to use the displayed default callback.

The Google OAuth client must allow the exact redirect URI shown in plugin settings.

The plugin requests the scopes:

```text
openid email profile
```

On successful authentication:

- An existing account is matched by Google subject ID or email.
- Otherwise a new WooCommerce `customer` is created when WooCommerce exists, or a WordPress `subscriber` is created without it.
- The Google subject identifier is stored in user meta.
- The user is signed in and returned to the originating local page.

New Google-created accounts still need billing company, billing phone, and shipping details before the configured service form can pass profile validation.

## Settings reference

Open **Service and Subscription > Settings**.

### Google Login

- Enable Google Login.
- Google Client ID.
- Google Client Secret.
- Redirect URI.

### Form availability

- Service form coming soon.
- Project form coming soon.

Each option replaces its shortcode form with a branded coming-soon panel.

### 3D quote general settings

- Currency code. Default: `EUR`.
- Currency symbol. Default: `€`.
- Tax rate. Default: `0`.
- Service fee. Default: `5`.
- Setup fee. Default: `0`.
- Profit margin. Default: `20` percent.
- Maximum upload size setting. Default: `500` MB.
- Allowed extensions setting. Default: `stl,obj,3mf`.
- Guest ordering toggle.
- Delete data on uninstall toggle.
- Admin notification email.

Several of these settings have implementation caveats in version 0.10.40; see [Current-version notes](#current-version-notes).

### WooCommerce service workflow

- Service request form page.
- After submit: go directly to checkout or go to cart.

## Exports and downloads

### Customer downloads

Customer file downloads require:

- A signed-in user.
- A valid nonce.
- Ownership of the request.
- The attachment ID to be listed on that request.

The handler streams the file after authorization.

### Customer exports

A customer can open an HTML or email-style export only for a request they own and with a valid nonce.

### Administrator exports

Users who can edit the request can export from the request list or request edit screen. Admin exports also require a request-specific nonce.

### Admin file links

The admin file metabox uses normal Media Library attachment URLs. Access control for those direct URLs depends on the site's media and web-server configuration.

## Data removal and uninstall warning

**Back up the site before uninstalling version 0.10.40.**

The supplied `uninstall.php` currently performs unconditional cleanup and does not consult the visible **Delete data on uninstall** setting.

Uninstalling the plugin permanently deletes:

- All `service_request` posts.
- All files referenced by request `_sr_file_ids` metadata.
- All `sr_service` posts.
- A small set of legacy options: `srf_admin_email`, `srf_allowed_file_types`, `srf_max_file_size_mb`, and `srf_terms_url`.

Uninstalling does **not** deliberately delete service gallery images from the Media Library.

The current uninstall routine does not remove all modern plugin settings, user meta, linked WooCommerce products, or the custom quote tables. Plan and test your own retention/removal procedure if complete erasure is required.

## Troubleshooting

### The service form says profile information is missing

Confirm the signed-in customer's WooCommerce profile contains:

- Billing company.
- Billing phone.
- Shipping first/last name and address.
- Valid account email.

The service form uses stored profile data and does not provide editable contact fields.

### The service form cannot find a shipping address

Confirm WooCommerce is active. The current code builds the address through `WC_Customer`; without that class, the address is always empty.

### My Account Service Requests returns 404

1. Confirm WooCommerce My Account is configured.
2. Open **Settings > Permalinks**.
3. Click **Save Changes**.
4. Clear page/cache/CDN caches.

### No services appear

Confirm at least one `sr_service` post is published. Draft and private services are not included.

### No materials or printers appear

Confirm the records exist and their status is **active**.

### A printer disappears after selecting a material

The frontend filters printers by supported material IDs. Edit the printer and add that material, or remove the restriction.

### A project file uploads but final pricing fails

Use a valid closed STL, OBJ, or 3MF model with measurable volume. STEP/STP/IGES/IGS are not supported by the final parser in version 0.10.40.

### 3MF does not preview

The browser viewer currently handles STL and OBJ. The server can still parse supported 3MF content for final pricing.

### Upload rejected despite a high plugin quota

Check all lower-level limits:

- PHP `upload_max_filesize`.
- PHP `post_max_size`.
- Web server or reverse proxy body limit.
- WordPress multisite upload limits.
- Security plugin or hosting MIME restrictions.

### Admin notification goes to the wrong address

In version 0.10.40, the mail sender reads the legacy `srf_admin_email` option and otherwise falls back to the WordPress `admin_email`. The visible quote notification setting uses a different option key and is not consumed by that sender.

### Done status did not delete files

That is the current bundled behavior. Use **Service and Subscription > Storage > Clear storage** for the user, or implement a listener for `srf_request_marked_done`.

### A submitted service removed other cart products

The plugin intentionally empties the WooCommerce cart before adding the request-linked service product.

## Current-version notes

The following are important source-level realities of version 0.10.40:

1. The version is defined consistently as `0.10.40` in the plugin header and runtime property.
2. Both forms require login, even though a guest-ordering setting exists.
3. The service form effectively depends on WooCommerce for its shipping-address validation.
4. The admin notification sender and the visible notification setting use different option keys.
5. The visible quote maximum-upload and allowed-extension settings are not the only upload controls used by the form handler.
6. The upload MIME registry, shared extension list, project display list, and final pricing parser do not have identical format lists.
7. The Done status fires a hook but has no bundled automatic-cleanup listener.
8. The uninstall routine ignores the visible delete-data toggle and deletes requests, request files, and services unconditionally.
9. The uninstall routine does not drop the materials/printers tables or delete every current option.
10. The WooCommerce request flow empties the customer's existing cart.
11. The standalone `calculator.js`, `uploader.js`, and `viewer.js` files are present but are not enqueued by the current shortcode handler; equivalent behavior is largely contained in `frontend.js`.
12. The legacy `templates/frontend-list.php` remains a placeholder; the functional customer list is `templates/myaccount/service-requests.php`.

These notes are documented so administrators can deploy the current code safely and developers can prioritize future fixes.

## License

The package includes an Apache License 2.0 file. See [`LICENSE`](LICENSE).

## Technical documentation

For architecture, classes, database schema, post meta, options, hooks, security checks, pricing formulas, extension points, and release procedures, see:

[`README-DEVELOPERS.md`](README-DEVELOPERS.md)
