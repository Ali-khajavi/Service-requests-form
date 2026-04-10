# Service Requests Form

WordPress plugin for collecting and managing service requests with a frontend form, per-service dynamic content, and an admin dashboard workflow.

## What This Plugin Does

- Adds a service request UI shortcode for customers.
- Adds a project request UI shortcode for project-style submissions.
- Lets admins manage requests, statuses, uploads, and service content from WP Admin.
- Integrates with WooCommerce account/shipping data.

## Shortcodes

- `[service_request_form]`  
  Main service form with dynamic service info panel.

- `[project_request_form]`  
  Project submission flow without service selection.

## Frontend Highlights (`[service_request_form]`)

- Two-column layout on desktop:
  - Form area: 30%
  - Service information area: 70%
- Service information updates instantly when the selected service changes.
- Media-first information panel order:
  1. Service video (if configured)
  2. Service image gallery/slider
  3. Service title, description, and variants
- Responsive fallback to single-column layout on smaller screens.

## Service Content Managed in WP Dashboard

Each `Service` (`sr_service` post type) can now configure:

- Main content (title + editor content)
- Gallery/slider images
- Variant groups (key + values)
- Video URL
- Video title
- Video description

Video behavior:

- If URL is an oEmbed source (for example YouTube/Vimeo), the embed is rendered automatically.
- If URL is a direct media file (`.mp4`, `.webm`, `.ogg`), an HTML5 video player is rendered.
- Video switches automatically when the user selects another service.

## Request Workflow

1. User chooses a service.
2. Related service information and media update instantly.
3. User submits request details and optional files.
4. Admin receives a notification email.
5. Admin manages the request in the dashboard.
6. User can review requests in My Account.

## Security and Validation

- Nonce checks and server-side validation.
- File type and size validation.
- Per-user storage tracking/quota handling.
- Business-role submission gating for service requests.

## Compatibility

- WordPress 6.x+
- WooCommerce (recommended)
- PHP 8.0+

## License

Commercial License. All rights reserved.

## Author

Ali Khajavi  
https://semlingerpro.de
