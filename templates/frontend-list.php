<?php
/**
 * Front-end user request list (placeholder for now).
 *
 * In a later phase, this will show previous requests for logged-in users.
 */

if ( ! is_user_logged_in() ) {
    return;
}
?>
<div class="srf-user-requests">
    <h3 class="srf-user-requests__title">
        <?php esc_html_e( 'Your previous service requests', 'service-requests-form' ); ?>
    </h3>
    <p class="srf-user-requests__placeholder">
        <?php esc_html_e( 'In a future update, this area will show a list of your submitted requests.', 'service-requests-form' ); ?>
    </p>
</div>
