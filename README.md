# Service Requests Form

**Service Requests Form** is a WordPress plugin for collecting, pricing, tracking, and managing customer service requests from the frontend. It is designed for service businesses that need a customer request form, file uploads, WooCommerce account integration, an admin workflow, and optional 3D-print/project quote features.

- **Plugin version:** 0.10.11
- **Text domain:** `service-requests-form`
- **Main plugin file:** `service-requests-form.php`
- **Author:** Ali Khajavi
- **Website:** https://semlingerpro.de

## Main Features

### Frontend service request form

Use the `[service_request_form]` shortcode to display a customer service request form. The form allows logged-in business users or administrators to:

- Select a service from the configured service catalogue.
- View service-specific information beside the form.
- Select service variants/options, such as size, height, finish, or any custom option group configured by the admin.
- Enter name, company, email, phone, shipping address, and request description.
- Upload one or more files.
- Submit requests after accepting the configured terms.
- Submit without a file when the “no file” option is selected.

The form uses a responsive layout. On larger screens, the request form appears next to the service information panel. On smaller screens, the layout stacks into a single column.

### Dynamic service information panel

Admins can create and manage services using the `sr_service` post type. Each service can include:

- Title and main content.
- Featured image/thumbnail.
- Image gallery/slider.
- Variant groups with custom keys and values.
- Video URL.
- Video title.
- Video description.

When a customer selects a service in the frontend form, the information panel updates automatically for that service.

Video URLs support:

- oEmbed providers such as YouTube and Vimeo.
- Direct video files such as `.mp4`, `.webm`, and `.ogg`.

### Project request and 3D quote form

Use the `[project_request_form]` shortcode to display a project-style request flow. This form is intended for 3D print/project submissions and includes:

- Project title and description.
- Local login/register step.
- Optional Google login/register buttons.
- Required terms acceptance.
- Multiple file upload.
- 3D viewer area for supported model files.
- Material selection.
- Printer selection.
- Layer height, infill, shell mode, scale, quantity, and notes.
- Live quote summary based on material, printer, model metrics, and pricing settings.

Supported pricing/model logic is handled by the project pricing class and frontend calculator scripts.

### Admin request management

The plugin creates a `service_request` custom post type for submitted requests. Admins can review requests in WordPress Admin and manage:

- Customer details.
- Selected service.
- Selected variants/options.
- Request description.
- Uploaded files.
- Shipping address.
- Request status.
- Quote/project settings where applicable.

Requests are created with status `new` by default.

### Request statuses

The admin status metabox supports the request workflow:

- `new`
- `in_progress`
- `done`

When requests are completed, the plugin includes cleanup/storage handling hooks that can be used to reduce stored upload usage.

### WooCommerce My Account integration

When WooCommerce is active, customers get a **Service Requests** tab inside **My Account**.

Customers can:

- View their own submitted service requests.
- Open individual request details.
- See the current request status.
- Download their uploaded files through secure download links.
- Edit the request description while the request status is still `new`.

The plugin registers the My Account endpoint:

```text
/my-account/service-requests/
```

### Secure file uploads and downloads

The plugin includes upload validation, storage tracking, and secure download handling.

Upload-related features include:

- File size validation.
- Allowed extension validation.
- WordPress media attachment creation.
- Per-user storage usage tracking.
- Different quota behavior for business users and normal users.
- Secure customer downloads with nonce checks and request ownership checks.

The plugin allows additional upload MIME types for logged-in users who can upload files, including common CAD, 3D, archive, image, and PDF formats:

- `obj`, `stl`, `ply`, `step`, `stp`, `igs`, `iges`
- `zip`, `rar`, `7z`
- `png`, `jpg`, `jpeg`, `webp`
- `pdf`

### Business user role

On activation, the plugin creates a `business_user` role with:

- `read`
- `upload_files`

The service request form is gated for business users and administrators. Assign customers the **Business User** role when they should be allowed to submit service requests and upload files.

### Admin dashboard menu

The plugin adds a WordPress Admin menu named **Service and Subscription**. From this menu, admins can access:

- Dashboard overview.
- Service Requests.
- Services.
- Quote Orders/current request management area.
- Materials.
- Printers.
- Storage.
- Settings.

The dashboard shows request counts, recent requests, storage usage, material count, and printer count.

### Materials catalogue

The materials admin page lets admins create, edit, and delete quote materials. Material fields include:

- Name and slug.
- Description.
- Price per gram.
- Price per cm³.
- Density.
- Machine time factor.
- Surface quality factor.
- Wastage factor.
- Available colors.
- Supported finishes.
- Supported support materials.
- Default support material.
- Supported color modes/shades.
- Support material mapping JSON.
- Active/inactive status.

Only active materials are shown in the project request quote form.

### Printers catalogue

The printers admin page lets admins create, edit, and delete printer profiles. Printer fields include:

- Name, brand, family, and model.
- Build volume and machine capabilities.
- Supported materials.
- Default material.
- Supported service profiles.
- Default service profile.
- Hourly cost.
- Default speed and speed unit.
- Efficiency, setup, warmup, and post-process time.
- Pricing model.
- Minimum job price.
- Minimum material charge.
- Margin override.
- Layer height limits.
- Wall thickness and quantity limits.
- Allowed file formats.
- Multi-material and special material capability flags.
- Active/inactive status.

The plugin includes brand registry files for Stratasys and Formlabs printer data extension.

### 3D quote settings

The settings screen includes general quote options such as:

- Currency.
- Currency symbol.
- Tax rate.
- Service fee.
- Setup fee.
- Profit margin.
- Maximum upload size.
- Allowed file extensions.
- Guest ordering setting.
- Delete data on uninstall setting.
- Admin notification email.

These settings are used by the project request form and quote calculation flow.

### Google login support

The project request form can show Google login/register buttons when Google login is enabled. The settings screen includes:

- Enable Google Login.
- Google Client ID.
- Google Client Secret.
- Google Redirect URI.

The redirect URI defaults to a callback URL on the site homepage if no custom value is configured.

### Admin notification emails

When a request is submitted, the plugin sends an admin notification email. The recipient is taken from plugin settings where available, otherwise the WordPress admin email is used.

The request stores email metadata, including:

- Recipient.
- Subject.
- Whether the email was sent.
- Sent timestamp.

### Storage dashboard

The storage admin screen shows user storage usage and includes tools to clear/reset tracked storage usage for users.

### Uninstall behavior

The plugin includes an `uninstall.php` file. Quote data removal can be controlled by the **Delete data on uninstall** setting.

## Requirements

Recommended environment:

- WordPress 6.x or newer.
- PHP 8.0 or newer.
- WooCommerce for My Account integration.
- A theme/page builder that supports WordPress shortcodes.

WooCommerce is recommended because the plugin uses WooCommerce account endpoints for the customer request dashboard.

## Installation

1. In WordPress Admin, go to **Plugins > Add New > Upload Plugin**.
2. Upload the plugin ZIP file.
3. Click **Install Now**.
4. Activate **Service Requests Form**.
5. After activation, the plugin creates the `business_user` role and flushes rewrite rules.
6. If My Account links do not work immediately, go to **Settings > Permalinks** and click **Save Changes** once.

## Basic Setup

### 1. Configure plugin settings

Go to:

```text
Service and Subscription > Settings
```

Configure:

- Quote currency and pricing settings.
- Upload size and allowed extensions.
- Admin notification email.
- Google login settings, if needed.
- Delete-on-uninstall preference.

### 2. Create services

Go to:

```text
Service and Subscription > Services
```

Create one or more services. For each service, add:

- Service title and description.
- Featured image.
- Gallery images.
- Optional variant groups.
- Optional video URL, title, and description.

Example variant group:

```text
Key: Height
Values: 2m, 3m, 7.5m
```

### 3. Add the service request form to a page

Create or edit a WordPress page and add:

```text
[service_request_form]
```

Publish the page. Optionally save this page URL in the plugin settings or related option used for the request form URL so the My Account area can link customers back to the form.

### 4. Assign customers the Business User role

Go to:

```text
Users > All Users
```

Edit the customer user and assign the **Business User** role. Users without the correct role may not be allowed to submit service requests.

### 5. Add the project request form, if needed

Create another page and add:

```text
[project_request_form]
```

Use this page for 3D print/project quote submissions.

### 6. Configure materials and printers for project quotes

Go to:

```text
Service and Subscription > Materials
Service and Subscription > Printers
```

Create active materials and printers. The project request form only displays active materials and printers.

## How Customers Use It

### Service request flow

1. Customer logs in.
2. Customer opens the page containing `[service_request_form]`.
3. Customer selects a service.
4. The service information panel updates automatically.
5. Customer selects service options/variants if available.
6. Customer enters contact and request details.
7. Customer uploads files or selects the no-file option.
8. Customer accepts the terms.
9. Customer submits the request.
10. Admin receives a notification email.
11. Customer tracks the request in **My Account > Service Requests**.

### Project/3D quote flow

1. Customer opens the page containing `[project_request_form]`.
2. Customer logs in or uses Google login if enabled.
3. Customer enters the project name and description.
4. Customer accepts the terms.
5. Customer uploads project/model files.
6. Customer selects material and printer.
7. Customer sets layer height, infill, shell mode, scale, quantity, and notes.
8. The quote summary updates based on the selected settings.
9. Customer submits the project request.
10. Admin reviews the request in WordPress Admin.

## How Admins Use It

### Manage incoming requests

Go to:

```text
Service and Subscription > Service Requests
```

Open a request to review the submitted details, uploaded files, selected service/options, and customer information.

### Update request status

In the request edit screen, use the request status metabox to move the request through:

```text
New -> In Progress -> Done
```

### Manage customer-visible services

Go to:

```text
Service and Subscription > Services
```

Use services to control what customers can select on the frontend form and what content appears in the dynamic information panel.

### Manage 3D quote resources

Go to:

```text
Service and Subscription > Materials
Service and Subscription > Printers
```

Create active materials and printers before using the project request form.

### Monitor storage

Go to:

```text
Service and Subscription > Storage
```

Review tracked user storage and clear storage usage when needed.

## Shortcodes

### `[service_request_form]`

Displays the main service request form with service selection, dynamic service information, customer fields, variants, uploads, terms acceptance, and submission handling.

### `[project_request_form]`

Displays the project/3D request form with login step, file upload, 3D viewer area, material/printer selection, print settings, and quote summary.

## Custom Post Types

### `service_request`

Stores submitted customer requests.

### `sr_service`

Stores customer-selectable services and frontend service information.

## Database Tables

The plugin creates custom quote database tables for materials and printers:

- `wp_srf_quote_materials`
- `wp_srf_quote_printers`

The actual table prefix depends on the WordPress database prefix.

## File and Folder Overview

```text
service-requests-form/
├── service-requests-form.php          Main plugin bootstrap
├── uninstall.php                      Uninstall handler
├── includes/
│   ├── class-sr-cpt.php               Service request CPT
│   ├── class-sr-services-cpt.php      Services CPT and service metadata
│   ├── class-sr-form-handler.php      Shortcodes, validation, submissions, uploads
│   ├── class-sr-myaccount.php         WooCommerce My Account integration
│   ├── class-sr-settings.php          Settings screen
│   ├── class-sr-service-data.php      Service data helpers
│   ├── class-srf-admin-menu.php       Admin dashboard/menu
│   ├── class-srf-admin-status.php     Request status metabox/workflow
│   ├── class-srf-admin-storage.php    Storage dashboard/tools
│   ├── class-srf-admin-materials.php  Materials CRUD admin
│   ├── class-srf-admin-printers.php   Printers CRUD admin
│   ├── class-srf-google-auth.php      Google login integration
│   ├── class-srf-project-pricing.php  Project/3D pricing logic
│   └── class-srf-quote-db.php         Materials/printers database layer
├── templates/
│   ├── form.php                       Service request form template
│   ├── project-form.php               Project/3D request form template
│   ├── service-info.php               Dynamic service info template
│   ├── frontend-list.php              Frontend list template
│   └── myaccount/service-requests.php WooCommerce My Account template
└── assets/
    ├── css/                           Frontend/admin styles
    └── js/                            Frontend, uploader, viewer, calculator scripts
```

## Troubleshooting

### My Account endpoint returns 404

Go to:

```text
Settings > Permalinks
```

Click **Save Changes** once to refresh rewrite rules.

### Customer cannot submit a service request

Check that:

- The customer is logged in.
- The customer has the **Business User** role or is an administrator.
- The form page contains `[service_request_form]`.
- Required fields are completed.
- Terms are accepted.
- Uploaded files match the allowed extensions and size limit.

### Project form does not show materials or printers

Check that:

- Materials exist and are marked active.
- Printers exist and are marked active.
- Printers support the selected material.
- Quote database tables were created on plugin activation.

### Google login does not appear

Check that:

- Google Login is enabled in settings.
- Client ID and Client Secret are saved.
- Redirect URI matches the Google Cloud OAuth configuration.
- The project form page uses `[project_request_form]`.

### Uploads fail

Check that:

- The file extension is allowed in plugin settings.
- The file size is below the configured maximum.
- The user has permission to upload files.
- The server/PHP upload limits are high enough.

## Security Notes

The plugin uses WordPress security practices including:

- Nonce validation for forms and secure downloads.
- Server-side validation and sanitization.
- Request ownership checks in My Account.
- File type and file size validation.
- Role-based submission gating.
- Escaping output in templates and admin screens.

## License

Commercial License. All rights reserved.

## Author

Ali Khajavi  
https://semlingerpro.de
