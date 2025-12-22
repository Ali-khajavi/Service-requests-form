<?php
/**
 * Front-end service request form template.
 *
 * Variables expected:
 * - $services            array of [id, title]
 * - $selected_service_id int|null
 * - $errors              array
 * - $old_data            array
 * - $success             bool
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

$old = function( $key, $default = '' ) use ( $old_data ) {
	return isset( $old_data[ $key ] ) ? $old_data[ $key ] : $default;
};

// old selected variant (for reload after validation errors)
$old_variant = (string) $old( 'variant', '' );
?>

<form class="srf-form" method="post" enctype="multipart/form-data">

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
				<?php
				$service_id    = isset( $service['id'] ) ? (int) $service['id'] : 0;
				$service_title = isset( $service['title'] ) ? (string) $service['title'] : '';

				/**
				 * ✅ IMPORTANT FIX:
				 * Always load variations from DB, not from $services array.
				 * Because your $services currently does not include variations.
				 */
				$variations = array();

				if ( $service_id > 0 ) {
					if ( class_exists( 'SR_Services_CPT' ) && method_exists( 'SR_Services_CPT', 'get_variations' ) ) {
						$variations = SR_Services_CPT::get_variations( $service_id );
					} else {
						// fallback meta key (must match what you save in CPT)
						$variations = get_post_meta( $service_id, '_sr_service_variations', true );
					}
				}

				if ( ! is_array( $variations ) ) {
					$variations = array();
				}

				// sanitize variations
				$clean_variations = array();
				foreach ( $variations as $v ) {
					$label = isset( $v['label'] ) ? sanitize_text_field( $v['label'] ) : '';
					$value = isset( $v['value'] ) ? sanitize_key( $v['value'] ) : '';
					if ( $label !== '' && $value !== '' ) {
						$clean_variations[] = array(
							'label' => $label,
							'value' => $value,
						);
					}
				}

				$variations_json = ! empty( $clean_variations ) ? wp_json_encode( $clean_variations ) : '[]';
				?>
				<option
					value="<?php echo esc_attr( $service_id ); ?>"
					<?php selected( $selected_service_id, $service_id ); ?>
					data-variations="<?php echo esc_attr( $variations_json ); ?>"
				>
					<?php echo esc_html( $service_title ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</div>

	<!-- Variant dropdown (hidden by default; shown if service has variations) -->
	<div class="srf-form__field srf-form__field--variant" id="srf-variant-field" style="display:none;">
		<label for="srf-variant">
			<?php esc_html_e( 'Variant', 'service-requests-form' ); ?>
			<span class="srf-required">*</span>
		</label>
		<select id="srf-variant" name="srf_variant">
			<option value=""><?php esc_html_e( 'Please choose a variant', 'service-requests-form' ); ?></option>
		</select>
		<small class="srf-field__help">
			<?php esc_html_e( 'Select the variation for this service.', 'service-requests-form' ); ?>
		</small>
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
			<?php esc_html_e( 'Company', 'service-requests-form' ); ?> <span class="srf-required">*</span>
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
			<?php esc_html_e( 'Phone', 'service-requests-form' ); ?> <span class="srf-required">*</span>
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

		$my_account_url = site_url( '/my-account/' );
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$my_account_url = wc_get_page_permalink( 'myaccount' );
		}

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

			if ( ! empty( $address['address_1'] ) && ! empty( $address['city'] ) && ! empty( $address['postcode'] ) ) {
				$shipping_ok = true;

				if ( isset( WC()->countries ) && is_object( WC()->countries ) ) {
					$shipping_display = WC()->countries->get_formatted_address( $address );
				}

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
		<input type="file" id="srf-file" name="srf_files[]" multiple="multiple" />
		<small class="srf-field__help">
			<?php esc_html_e( 'You can upload CAD/3D/scan files here. File type and size limits will apply in a later phase.', 'service-requests-form' ); ?>
		</small>
	</div>

	<div class="srf-form__field srf-form__field--checkbox">
		<label>
			<input type="checkbox" name="srf_no_file" value="1" <?php checked( $old( 'no_file' ), '1' ); ?> />
			<?php esc_html_e( 'I don’t have a file yet / not needed for this service', 'service-requests-form' ); ?>
		</label>
	</div>

	<div class="srf-form__field srf-form__field--checkbox">
		<label>
			<input type="checkbox" name="srf_terms" value="1" <?php checked( $old( 'terms' ), '1' ); ?> required />
			<?php esc_html_e( 'I accept the Terms & Conditions', 'service-requests-form' ); ?>
			<span class="srf-required">*</span>
		</label>
	</div>

	<input type="hidden" name="srf_form_submitted" value="1" />
	<?php wp_nonce_field( 'srf_submit_request', 'srf_nonce' ); ?>

	<div class="srf-form__actions">
		<button type="submit" class="srf-button">
			<?php esc_html_e( 'Send request', 'service-requests-form' ); ?>
		</button>
	</div>

</form>

<script>
(function(){
	var serviceSelect  = document.getElementById('srf-service');
	var variantField   = document.getElementById('srf-variant-field');
	var variantSelect  = document.getElementById('srf-variant');

	if (!serviceSelect || !variantField || !variantSelect) return;

	var oldVariant = <?php echo wp_json_encode( $old_variant ); ?>;

	function setRequired(isRequired){
		if (isRequired) {
			variantSelect.setAttribute('required', 'required');
		} else {
			variantSelect.removeAttribute('required');
		}
	}

	function rebuildVariants(){
		var opt = serviceSelect.options[serviceSelect.selectedIndex];
		var json = opt ? opt.getAttribute('data-variations') : '[]';
		var variations = [];

		try { variations = JSON.parse(json || '[]'); } catch(e) { variations = []; }

		variantSelect.innerHTML = '';
		var placeholder = document.createElement('option');
		placeholder.value = '';
		placeholder.textContent = '<?php echo esc_js( __( 'Please choose a variant', 'service-requests-form' ) ); ?>';
		variantSelect.appendChild(placeholder);

		if (!variations || !variations.length) {
			variantField.style.display = 'none';
			variantSelect.value = '';
			setRequired(false);
			return;
		}

		variations.forEach(function(v){
			if (!v || !v.value || !v.label) return;
			var o = document.createElement('option');
			o.value = v.value;
			o.textContent = v.label;
			variantSelect.appendChild(o);
		});

		variantField.style.display = '';
		setRequired(true);

		if (oldVariant) {
			variantSelect.value = oldVariant;
		}
	}

	serviceSelect.addEventListener('change', function(){
		oldVariant = '';
		rebuildVariants();
	});

	rebuildVariants();
})();
</script>
