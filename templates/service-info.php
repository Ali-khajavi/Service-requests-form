<?php
/**
 * Dynamic service information block.
 *
 * Variables expected:
 * - $selected_service_data array|null:
 *   - id
 *   - title
 *   - content (HTML)
 *   - images (array of [id, url, alt])
 *   - variations (array of [label, value]) OPTIONAL
 */

if ( empty( $selected_service_data ) || ! is_array( $selected_service_data ) ) {
	?>
	<div class="srf-service-info">
		<h2 class="srf-service-info__title">
			<?php esc_html_e( 'Please select a service to see details.', 'service-requests-form' ); ?>
		</h2>
	</div>
	<?php
	return;
}

$title   = isset( $selected_service_data['title'] ) ? (string) $selected_service_data['title'] : '';
$content = isset( $selected_service_data['content'] ) ? (string) $selected_service_data['content'] : '';

$images = ( isset( $selected_service_data['images'] ) && is_array( $selected_service_data['images'] ) )
	? $selected_service_data['images']
	: array();

// ✅ NEW: variations support
$variations = ( isset( $selected_service_data['variations'] ) && is_array( $selected_service_data['variations'] ) )
	? $selected_service_data['variations']
	: array();

// sanitize variations (avoid broken arrays)
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
?>

<div class="srf-service-info" data-service-id="<?php echo esc_attr( $selected_service_data['id'] ); ?>">
	<h2 class="srf-service-info__title"><?php echo esc_html( $title ); ?></h2>

	<div class="srf-service-info__text is-collapsed" data-srf-collapsible="text">
		<?php echo wp_kses_post( $content ); ?>
	</div>

	<button type="button" class="srf-service-info__toggle" data-srf-toggle="text">
		<?php esc_html_e( 'Show more', 'service-requests-form' ); ?>
	</button>

	<?php if ( ! empty( $clean_variations ) ) : ?>
		<div class="srf-service-info__variants">
			<h3 class="srf-service-info__subtitle"><?php esc_html_e( 'Variants', 'service-requests-form' ); ?></h3>
			<ul class="srf-service-info__variants-list">
				<?php foreach ( $clean_variations as $v ) : ?>
					<li><?php echo esc_html( $v['label'] ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $images ) ) :
		$first = $images[0];
		$json  = wp_json_encode( $images );
		?>
		<div class="srf-service-slider" data-srf-slider="switcher" data-images="<?php echo esc_attr( $json ); ?>">
			<div class="srf-service-slider__viewport">
				<img
					src="<?php echo esc_url( $first['url'] ); ?>"
					alt="<?php echo esc_attr( $first['alt'] ); ?>"
					class="srf-service-slider__image"
					loading="lazy"
				/>
			</div>

			<div class="srf-service-slider__nav">
				<button type="button" class="srf-service-slider__prev" aria-label="<?php esc_attr_e( 'Previous image', 'service-requests-form' ); ?>">&#10094;</button>
				<button type="button" class="srf-service-slider__next" aria-label="<?php esc_attr_e( 'Next image', 'service-requests-form' ); ?>">&#10095;</button>
			</div>
		</div>
	<?php endif; ?>
</div>
