<?php
/**
 * Front-end service request form template.
 *
 * Variables expected:
 * - $services            array of [id, title]
 * - $selected_service_id int|null
 * - $errors              array (not used yet)
 * - $old_data            array (not used yet)
 */

if ( ! empty( $success ) ) : ?>
	<div class="srf-form__success">
		<?php esc_html_e( 'Your request has been sent successfully.', 'service-requests-form' ); ?>
	</div>
<?php endif; ?>

<?php if ( ! empty( $errors ) && is_array( $errors ) ) : ?>
	<div class="srf-form__errors">
		<ul>
			<?php foreach ( $errors as $err ) : ?>
				<li><?php echo esc_html( $err ); ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
<?php endif; ?>

<?php


if ( ! isset( $services ) || ! is_array( $services ) ) {
    $services = array();
}

if ( ! isset( $selected_service_id ) ) {
    $selected_service_id = null;
}

if ( ! isset( $old_data ) || ! is_array( $old_data ) ) {
    $old_data = array();
}

// Simple helpers for old values (later populated by validation)
$old = function( $key, $default = '' ) use ( $old_data ) {
    return isset( $old_data[ $key ] ) ? $old_data[ $key ] : $default;
};
?>

<form class="srf-form" method="post" enctype="multipart/form-data">
    
    <!-- Service quick picker (clickable items) -->
    <div class="srf-service-picker">
        <h2 class="srf-service-picker__title"><?php esc_html_e( 'Semlinger Dental Services', 'service-requests-form' ); ?></h2>
    </div>

<div class="srf-form__field">
        <label for="srf-service">
            <?php esc_html_e( 'Service', 'service-requests-form' ); ?> <span class="srf-required">*</span>
        </label>
        <select id="srf-service" name="srf_service" required>
            <option value=""><?php esc_html_e( 'Please choose a service', 'service-requests-form' ); ?></option>
            <?php foreach ( $services as $service ) : ?>
                <option
                    value="<?php echo esc_attr( $service['id'] ); ?>"
                    <?php selected( $selected_service_id, $service['id'] ); ?>
                >
                    <?php echo esc_html( $service['title'] ); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="srf-form__field">
        <label for="srf-name">
            <?php esc_html_e( 'Name', 'service-requests-form' ); ?> <span class="srf-required">*</span>
        </label>
        <input
            type="text"
            id="srf-name"
            name="srf_name"
            value="<?php echo esc_attr( $old( 'name' ) ); ?>"
            required
        />
    </div>

    <div class="srf-form__field">
        <label for="srf-company">
            <?php esc_html_e( 'Company', 'service-requests-form' ); ?>
        </label>
        <input
            type="text"
            id="srf-company"
            name="srf_company"
            value="<?php echo esc_attr( $old( 'company' ) ); ?>"
            required
        />
    </div>

    <div class="srf-form__field">
        <label for="srf-email">
            <?php esc_html_e( 'Email', 'service-requests-form' ); ?> <span class="srf-required">*</span>
        </label>
        <input
            type="email"
            id="srf-email"
            name="srf_email"
            value="<?php echo esc_attr( $old( 'email' ) ); ?>"
            required
        />
    </div>

    <div class="srf-form__field">
        <label for="srf-phone">
            <?php esc_html_e( 'Phone', 'service-requests-form' ); ?>
        </label>
        <input
            type="text"
            id="srf-phone"
            name="srf_phone"
            value="<?php echo esc_attr( $old( 'phone' ) ); ?>"
            required
        />
    </div>



    <div class="srf-form__field">
        <label>
            <?php esc_html_e( 'Shipping address', 'service-requests-form' ); ?>
            <span class="srf-required">*</span>
        </label>

        <?php
        $shipping_display     = '';
        $shipping_single_line = '';
        $shipping_ok          = false;

        // My Account URL (WooCommerce if available)
        $my_account_url = site_url( '/my-account/' );
        if ( function_exists( 'wc_get_page_permalink' ) ) {
            $my_account_url = wc_get_page_permalink( 'myaccount' );
        }

        // Shipping edit URL (WooCommerce endpoint if available)
        $shipping_edit_url = $my_account_url;
        if ( function_exists( 'wc_get_endpoint_url' ) && function_exists( 'wc_get_page_permalink' ) ) {
            $shipping_edit_url = wc_get_endpoint_url( 'edit-address', 'shipping', wc_get_page_permalink( 'myaccount' ) );
        }

        if ( is_user_logged_in() && function_exists( 'WC' ) && WC() && WC()->customer ) {

            $address = array(
            'first_name' => WC()->customer->get_shipping_first_name(),
            'last_name'  => WC()->customer->get_shipping_last_name(),
            'company'    => WC()->customer->get_shipping_company(),
            'address_1'  => WC()->customer->get_shipping_address_1(),
            'address_2'  => WC()->customer->get_shipping_address_2(),
            'city'       => WC()->customer->get_shipping_city(),
            'state'      => WC()->customer->get_shipping_state(),
            'postcode'   => WC()->customer->get_shipping_postcode(),
            'country'    => WC()->customer->get_shipping_country(),
            );

            // Minimum needed
            if ( ! empty( $address['address_1'] ) && ! empty( $address['city'] ) && ! empty( $address['postcode'] ) ) {
            $shipping_ok = true;

            // Try Woo formatter first
            if ( isset( WC()->countries ) && is_object( WC()->countries ) ) {
                $shipping_display = WC()->countries->get_formatted_address( $address );
            }

            // Fallback formatter
            if ( empty( $shipping_display ) ) {
                $shipping_display = implode( "\n", array_filter( array(
                trim( $address['first_name'] . ' ' . $address['last_name'] ),
                $address['company'],
                $address['address_1'],
                $address['address_2'],
                trim( $address['postcode'] . ' ' . $address['city'] ),
                $address['state'],
                $address['country'],
                ) ) );
            }

            // Make a clean single-line version (no <br>, no newlines)
            $shipping_single_line = (string) $shipping_display;
            $shipping_single_line = str_replace( array( '<br>', '<br/>', '<br />' ), ', ', $shipping_single_line );
            $shipping_single_line = str_replace( array( "\r\n", "\n", "\r" ), ', ', $shipping_single_line );
            $shipping_single_line = wp_strip_all_tags( $shipping_single_line );
            $shipping_single_line = preg_replace( '/\s+/', ' ', $shipping_single_line );
            $shipping_single_line = preg_replace( '/\s*,\s*/', ', ', $shipping_single_line );
            $shipping_single_line = trim( $shipping_single_line, " ,\t\n\r\0\x0B" );
            }
        }
        ?>

        <?php if ( $shipping_ok ) : ?>
            <div class="srf-shipping-box">
            <?php echo esc_html( $shipping_single_line ); ?>
            </div>

            <!-- Hidden field so your handler saves it -->
            <input type="hidden" name="srf_shipping_address" value="<?php echo esc_attr( $shipping_single_line ); ?>" />

            <p class="srf-field__help">
            <a href="<?php echo esc_url( $shipping_edit_url ); ?>">
                <?php esc_html_e( 'Edit shipping address', 'service-requests-form' ); ?>
            </a>
            </p>
        <?php else : ?>
            <div class="srf-shipping-missing">
            <?php esc_html_e( 'Please first set up your shipping address in your account before submitting a request.', 'service-requests-form' ); ?>
            <br>
            <a href="<?php echo esc_url( $shipping_edit_url ); ?>">
                <?php esc_html_e( 'Go to My Account', 'service-requests-form' ); ?>
            </a>
            </div>

            <input type="hidden" name="srf_shipping_address" value="" />
        <?php endif; ?>
    </div>





    <div class="srf-form__field">
        <label for="srf-description">
            <?php esc_html_e( 'Project description', 'service-requests-form' ); ?> <span class="srf-required">*</span>
        </label>
        <textarea
            id="srf-description"
            name="srf_description"
            rows="5"
            required
        ><?php echo esc_textarea( $old( 'description' ) ); ?></textarea>
    </div>

    <div class="srf-form__field">
        <label for="srf-file">
            <?php esc_html_e( 'Upload file(s)', 'service-requests-form' ); ?>
        </label>
        <input
            type="file"
            id="srf-file"
            name="srf_files[]"
            multiple="multiple"
        />
        <small class="srf-field__help">
            <?php esc_html_e( 'You can upload CAD/3D/scan files here. File type and size limits will apply in a later phase.', 'service-requests-form' ); ?>
        </small>
    </div>

    <div class="srf-form__field srf-form__field--checkbox">
        <label>
            <input
                type="checkbox"
                name="srf_no_file"
                value="1"
                <?php checked( $old( 'no_file' ), '1' ); ?>
            />
            <?php esc_html_e( 'I don’t have a file yet / not needed for this service', 'service-requests-form' ); ?>
        </label>
    </div>

    <div class="srf-form__field srf-form__field--checkbox">
        <label>
            <input
                type="checkbox"
                name="srf_terms"
                value="1"
                <?php checked( $old( 'terms' ), '1' ); ?>
                required
            />
            <?php
            // Terms URL will be dynamic from settings in a later phase.
            esc_html_e( 'I accept the Terms & Conditions', 'service-requests-form' );
            ?>
            <span class="srf-required">*</span>
        </label>
    </div>

    <?php
    // Hidden fields for later processing (validation/saving).
    ?>
    <input type="hidden" name="srf_form_submitted" value="1" />
    <?php wp_nonce_field( 'srf_submit_request', 'srf_nonce' ); ?>

    <div class="srf-form__actions">
        <button type="submit" class="srf-button">
            <?php esc_html_e( 'Send request', 'service-requests-form' ); ?>
        </button>
    </div>
</form>
