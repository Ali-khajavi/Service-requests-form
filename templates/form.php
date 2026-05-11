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

// old selected variants (map: Variant Key => chosen Value) for reload after validation errors
$old_variants = $old( 'variants', array() );
if ( ! is_array( $old_variants ) ) {
	$old_variants = array();
}
?>

<form class="srf-form" method="post" enctype="multipart/form-data">

	<div class="srf-service-picker">
		<h2 class="srf-service-picker__title"><?php esc_html_e( 'Semlinger Dental Services', 'service-requests-form' ); ?></h2>
	</div>

	<div class="srf-form__field">
		<label for="srf-service">
			<?php esc_html_e( 'Service', 'service-requests-form' ); ?> <span class="srf-required">*</span>
		</label>

		<?php if ( ! empty( $services ) ) : ?>
			<?php
			$selected_service_title = __( 'Please choose a service', 'service-requests-form' );
			$selected_service_thumb = '';
			foreach ( $services as $service_item ) {
				$service_item_id = isset( $service_item['id'] ) ? (int) $service_item['id'] : 0;
				if ( $service_item_id === (int) $selected_service_id ) {
					$selected_service_title = isset( $service_item['title'] ) ? (string) $service_item['title'] : $selected_service_title;
					$selected_service_thumb = isset( $service_item['thumb'] ) ? (string) $service_item['thumb'] : '';
					break;
				}
			}
			?>
			<div class="srf-service-dropdown" data-srf-service-dropdown>
				<button
					type="button"
					class="srf-service-dropdown__trigger"
					data-srf-service-trigger
					aria-haspopup="listbox"
					aria-expanded="false"
				>
					<span class="srf-service-dropdown__trigger-media" data-srf-service-trigger-media>
						<?php if ( ! empty( $selected_service_thumb ) ) : ?>
							<img src="<?php echo esc_url( $selected_service_thumb ); ?>" alt="" loading="lazy" />
						<?php else : ?>
							<span class="srf-service-dropdown__trigger-placeholder"></span>
						<?php endif; ?>
					</span>
					<span class="srf-service-dropdown__trigger-text" data-srf-service-trigger-text><?php echo esc_html( $selected_service_title ); ?></span>
					<span class="srf-service-dropdown__chevron" aria-hidden="true">&#9662;</span>
				</button>

				<div class="srf-service-dropdown__menu" data-srf-service-menu role="listbox" tabindex="-1" aria-label="<?php echo esc_attr__( 'Services', 'service-requests-form' ); ?>" hidden>
					<?php foreach ( $services as $service ) : ?>
						<?php
						$service_id    = isset( $service['id'] ) ? (int) $service['id'] : 0;
						$service_title = isset( $service['title'] ) ? (string) $service['title'] : '';
						$service_thumb = isset( $service['thumb'] ) ? (string) $service['thumb'] : '';
						$is_active     = ( (int) $selected_service_id === (int) $service_id );
						?>
						<button
							type="button"
							class="srf-service-dropdown__option<?php echo $is_active ? ' is-active' : ''; ?>"
							data-srf-service-option
							data-service-id="<?php echo esc_attr( $service_id ); ?>"
							data-service-title="<?php echo esc_attr( $service_title ); ?>"
							data-service-thumb="<?php echo esc_url( $service_thumb ); ?>"
							role="option"
							aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
						>
							<span class="srf-service-dropdown__option-media" aria-hidden="true">
								<?php if ( ! empty( $service_thumb ) ) : ?>
									<img src="<?php echo esc_url( $service_thumb ); ?>" alt="" loading="lazy" />
								<?php else : ?>
									<span class="srf-service-dropdown__option-placeholder"></span>
								<?php endif; ?>
							</span>
							<span class="srf-service-dropdown__option-title"><?php echo esc_html( $service_title ); ?></span>
						</button>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>

		<select id="srf-service" name="srf_service" class="srf-service-select srf-service-select--native" required>
			<option value=""><?php esc_html_e( 'Please choose a service', 'service-requests-form' ); ?></option>

			<?php foreach ( $services as $service ) : ?>
				<?php
				$service_id    = isset( $service['id'] ) ? (int) $service['id'] : 0;
				$service_title = isset( $service['title'] ) ? (string) $service['title'] : '';

				/**
				 * Variant Groups: Key + Values[] (stored in _sr_service_variations)
				 * Example: [ ['key'=>'Height','values'=>['2m','3m']] ]
				 */
				$variant_groups = array();

				if ( $service_id > 0 ) {
					if ( class_exists( 'SR_Services_CPT' ) && method_exists( 'SR_Services_CPT', 'get_variations' ) ) {
						$variant_groups = SR_Services_CPT::get_variations( $service_id );
					} else {
						$variant_groups = get_post_meta( $service_id, '_sr_service_variations', true );
					}
				}

				if ( ! is_array( $variant_groups ) ) {
					$variant_groups = array();
				}

				// Normalize + sanitize to: [ ['key'=>string,'values'=>[string...]] ... ]
				$clean_groups = array();
				foreach ( $variant_groups as $row ) {

					// New format
					if ( isset( $row['key'] ) && isset( $row['values'] ) && is_array( $row['values'] ) ) {
						$key  = trim( sanitize_text_field( $row['key'] ) );
						$vals = array();

						foreach ( $row['values'] as $v ) {
							$v = trim( sanitize_text_field( $v ) );
							if ( $v !== '' ) {
								$vals[] = $v;
							}
						}

						if ( $key !== '' && ! empty( $vals ) ) {
							$unique_vals = array_values( array_unique( $vals ) );
							$raw_prices = isset( $row['prices'] ) && is_array( $row['prices'] ) ? $row['prices'] : array();
							$prices = array();
							foreach ( $unique_vals as $uv ) {
								$prices[ $uv ] = isset( $raw_prices[ $uv ] ) ? max( 0, (float) $raw_prices[ $uv ] ) : 0;
							}
							$clean_groups[] = array(
								'key'    => $key,
								'values' => $unique_vals,
								'prices' => $prices,
							);
						}
						continue;
					}

					// Back-compat: old rows label/value -> treated as a single Variant group
					if ( isset( $row['label'] ) ) {
						$lbl = trim( sanitize_text_field( $row['label'] ) );
						if ( $lbl !== '' ) {
							$clean_groups[] = array(
								'key'    => __( 'Variant', 'service-requests-form' ),
								'values' => array( $lbl ),
							);
						}
					}
				}

				$variants_json = ! empty( $clean_groups ) ? wp_json_encode( $clean_groups ) : '[]';
				$base_price = ( class_exists( 'SRF_WooCommerce' ) ) ? SRF_WooCommerce::get_base_price( $service_id ) : 0;
				?>
				<option
					value="<?php echo esc_attr( $service_id ); ?>"
					<?php selected( $selected_service_id, $service_id ); ?>
					data-variants="<?php echo esc_attr( $variants_json ); ?>"
					data-base-price="<?php echo esc_attr( $base_price ); ?>"
				>
					<?php echo esc_html( $service_title ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</div>

	<!-- Variant groups (dynamic; shown only if the selected service has variants) -->
	<div class="srf-form__field srf-form__field--variants" id="srf-variants-field" style="display:none;">
		<div id="srf-variants-wrap"></div>
		<small class="srf-field__help">
			<?php esc_html_e( 'Choose the option(s) required for this service.', 'service-requests-form' ); ?>
		</small>
	</div>

	<div class="srf-price-summary" id="srf-price-summary" style="display:none;">
		<strong><?php esc_html_e( 'Price summary', 'service-requests-form' ); ?></strong>
		<div id="srf-price-lines"></div>
		<div><strong><?php esc_html_e( 'Total', 'service-requests-form' ); ?>: <span id="srf-price-total"></span></strong></div>
	</div>

	<?php
	// Customer contact and shipping data are intentionally not shown here.
	// They are loaded server-side from the logged-in WooCommerce user profile.
	?>

	<div class="srf-form__field">
		<label for="srf-description">
			<?php esc_html_e( 'Project description', 'service-requests-form' ); ?> <span class="srf-required">*</span>
		</label>
		<textarea id="srf-description" name="srf_description" rows="6" required><?php echo esc_textarea( $old( 'description' ) ); ?></textarea>
	</div>

	<div class="srf-form__field">
		<label for="srf-files">
			<?php esc_html_e( 'Upload file(s)', 'service-requests-form' ); ?>
		</label>
		<input type="file" id="srf-files" name="srf_files[]" multiple />
		<small class="srf-field__help">
			<?php esc_html_e( 'You can upload CAD/3D/scan files here. File type and size limits apply.', 'service-requests-form' ); ?>
		</small>
	</div>

	<div class="srf-form__field srf-form__field--checkbox">
		<label>
			<input type="checkbox" name="srf_no_file" value="1" <?php checked( $old( 'no_file' ), '1' ); ?> />
			<?php esc_html_e( 'I don’t have a file yet / not needed', 'service-requests-form' ); ?>
		</label>
	</div>

	<div class="srf-form__field srf-form__field--checkbox">
		<label>
			<input type="checkbox" name="srf_terms" value="1" <?php checked( $old( 'terms' ), '1' ); ?> required />
			<?php
			$terms_url = (string) get_option( 'srf_terms_url', '' );
			if ( $terms_url ) {
				printf(
					wp_kses_post( __( 'I accept the <a href="%s" target="_blank" rel="noopener">Terms & Conditions</a>.', 'service-requests-form' ) ),
					esc_url( $terms_url )
				);
			} else {
				esc_html_e( 'I accept the Terms & Conditions.', 'service-requests-form' );
			}
			?>
		</label>
	</div>

	<input type="hidden" name="srf_form_submitted" value="1" />
	<?php wp_nonce_field( 'srf_submit_request', 'srf_nonce' ); ?>

	<div class="srf-form__actions">
		<button type="submit" class="srf-button">
			<?php esc_html_e( 'Submit', 'service-requests-form' ); ?>
		</button>
	</div>

</form>

<script>
(function(){
	var serviceSelect = document.getElementById('srf-service');
	var variantsField = document.getElementById('srf-variants-field');
	var wrap          = document.getElementById('srf-variants-wrap');
	var priceBox      = document.getElementById('srf-price-summary');
	var priceLines    = document.getElementById('srf-price-lines');
	var priceTotal    = document.getElementById('srf-price-total');

	if (!serviceSelect || !variantsField || !wrap) return;

	var oldSelections = <?php echo wp_json_encode( $old_variants ); ?> || {};

	function clearWrap(){
		while (wrap.firstChild) wrap.removeChild(wrap.firstChild);
	}

	function buildGroupRow(group, idx){
		if (!group) return;

		var key = group.key ? String(group.key) : '';
		var values = Array.isArray(group.values) ? group.values : [];

		if (!key || !values.length) return;

		var row = document.createElement('div');
		row.className = 'srf-form__field';
		row.style.marginBottom = '1rem';

		var label = document.createElement('label');
		label.textContent = key + ' *';
		row.appendChild(label);

		// Hidden key field
		var hiddenKey = document.createElement('input');
		hiddenKey.type = 'hidden';
		hiddenKey.name = 'srf_variants[' + idx + '][key]';
		hiddenKey.value = key;
		row.appendChild(hiddenKey);

		// Select
		var select = document.createElement('select');
		select.name = 'srf_variants[' + idx + '][value]';
		select.required = true;

		var placeholder = document.createElement('option');
		placeholder.value = '';
		placeholder.textContent = '<?php echo esc_js( __( 'Please choose', 'service-requests-form' ) ); ?>';
		select.appendChild(placeholder);

		values.forEach(function(v){
			v = String(v);
			var o = document.createElement('option');
			o.value = v;
			var extra = group.prices && group.prices[v] ? parseFloat(group.prices[v]) : 0;
			o.textContent = extra > 0 ? v + ' (+' + formatMoney(extra) + ')' : v;
			select.appendChild(o);
		});

		// Restore previous selection if available (key => value)
		if (oldSelections && typeof oldSelections === 'object' && oldSelections[key]) {
			select.value = String(oldSelections[key]);
		}

		select.addEventListener('change', updatePriceSummary);
		row.appendChild(select);
		wrap.appendChild(row);
	}

	function formatMoney(amount){
		amount = parseFloat(amount || 0);
		try { return new Intl.NumberFormat(undefined, { style:'currency', currency:'EUR' }).format(amount); } catch(e) { return amount.toFixed(2); }
	}

	function updatePriceSummary(){
		if (!priceBox || !priceLines || !priceTotal) return;
		var opt = serviceSelect.options[serviceSelect.selectedIndex];
		if (!opt) { priceBox.style.display = 'none'; return; }
		var base = parseFloat(opt.getAttribute('data-base-price') || '0') || 0;
		var groups = [];
		try { groups = JSON.parse(opt.getAttribute('data-variants') || '[]'); } catch(e) { groups = []; }
		var total = base;
		priceLines.innerHTML = '';
		var baseLine = document.createElement('div');
		baseLine.textContent = '<?php echo esc_js( __( 'Base price', 'service-requests-form' ) ); ?>: ' + formatMoney(base);
		priceLines.appendChild(baseLine);
		var selects = wrap.querySelectorAll('select');
		selects.forEach(function(sel, idx){
			var chosen = sel.value;
			var g = groups[idx] || {};
			var extra = chosen && g.prices && g.prices[chosen] ? parseFloat(g.prices[chosen]) : 0;
			if (extra > 0) {
				total += extra;
				var line = document.createElement('div');
				line.textContent = (g.key || '<?php echo esc_js( __( 'Option', 'service-requests-form' ) ); ?>') + ': ' + chosen + ' +' + formatMoney(extra);
				priceLines.appendChild(line);
			}
		});
		priceTotal.textContent = formatMoney(total);
		priceBox.style.display = '';
	}

	function rebuild(){
		clearWrap();

		var opt  = serviceSelect.options[serviceSelect.selectedIndex];
		var json = opt ? opt.getAttribute('data-variants') : '[]';

		var groups = [];
		try { groups = JSON.parse(json || '[]'); } catch(e) { groups = []; }

		if (!groups || !groups.length) {
			variantsField.style.display = 'none';
			updatePriceSummary();
			return;
		}

		variantsField.style.display = '';
		groups.forEach(function(g, i){
			buildGroupRow(g, i);
		});
		updatePriceSummary();
	}

	serviceSelect.addEventListener('change', function(){
		oldSelections = {};
		rebuild();
	});

	rebuild();
})();
</script>
