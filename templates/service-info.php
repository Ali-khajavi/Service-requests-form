<?php
/**
 * Service info template (right panel)
 *
 * Expects:
 * - $selected_service_data (array|null) with keys:
 *   - title
 *   - content (HTML)
 *   - images (array of [url, alt] or [id,url,alt])
 *   - variants (array of ['key'=>string,'values'=>string[]]) OPTIONAL
 *   - variations (legacy) OPTIONAL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data = ( isset( $selected_service_data ) && is_array( $selected_service_data ) ) ? $selected_service_data : null;

if ( ! $data ) : ?>
	<div class="srf-service-info">
		<h2 class="srf-service-info__title"><?php esc_html_e( 'Please select a service', 'service-requests-form' ); ?></h2>
	</div>
	<?php return; ?>
<?php endif; ?>

<?php
$title   = isset( $data['title'] ) ? (string) $data['title'] : '';
$content = isset( $data['content'] ) ? (string) $data['content'] : '';
$images  = ( isset( $data['images'] ) && is_array( $data['images'] ) ) ? $data['images'] : array();
$video   = ( isset( $data['video'] ) && is_array( $data['video'] ) ) ? $data['video'] : array();

// Variant groups: prefer 'variants' (new) but accept 'variations' (legacy) as fallback.
$raw_variants = array();
if ( isset( $data['variants'] ) && is_array( $data['variants'] ) ) {
	$raw_variants = $data['variants'];
} elseif ( isset( $data['variations'] ) && is_array( $data['variations'] ) ) {
	$raw_variants = $data['variations'];
}

// Normalize variants into groups: [ ['key'=>..., 'values'=>[...]], ... ]
$variant_groups = array();

foreach ( $raw_variants as $row ) {

	// New format: ['key' => 'Height', 'values' => ['2m','3m']]
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
			$variant_groups[] = array(
				'key'    => $key,
				'values' => array_values( array_unique( $vals ) ),
			);
		}
		continue;
	}

	// Legacy: ['label'=>'Upper jaw','value'=>'upper_jaw'] or sometimes only label
	if ( isset( $row['label'] ) ) {
		$lbl = trim( sanitize_text_field( $row['label'] ) );
		if ( $lbl !== '' ) {
			$variant_groups[] = array(
				'key'    => __( 'Variant', 'service-requests-form' ),
				'values' => array( $lbl ),
			);
		}
	}
}

// Slider JSON for frontend.js switcher init (expects [{url,alt}, ...])
$slider_items = array();
foreach ( $images as $img ) {
	if ( is_array( $img ) ) {
		$url = isset( $img['url'] ) ? (string) $img['url'] : '';
		$alt = isset( $img['alt'] ) ? (string) $img['alt'] : '';
		if ( $url !== '' ) {
			$slider_items[] = array(
				'url' => esc_url_raw( $url ),
				'alt' => sanitize_text_field( $alt ),
			);
		}
	}
}
$slider_json = ! empty( $slider_items ) ? wp_json_encode( $slider_items ) : '[]';

$video_title = isset( $video['title'] ) ? (string) $video['title'] : '';
$video_desc  = isset( $video['description'] ) ? (string) $video['description'] : '';
$video_embed = isset( $video['embed'] ) ? (string) $video['embed'] : '';
?>

<div class="srf-service-info">
	<?php if ( $video_embed !== '' ) : ?>
		<div class="srf-service-video">
			<div class="srf-service-video__frame">
				<?php echo wp_kses_post( $video_embed ); ?>
			</div>
			<?php if ( $video_title !== '' ) : ?>
				<h3 class="srf-service-video__title"><?php echo esc_html( $video_title ); ?></h3>
			<?php endif; ?>
			<?php if ( $video_desc !== '' ) : ?>
				<p class="srf-service-video__desc"><?php echo esc_html( $video_desc ); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $slider_items ) ) : ?>
		<div class="srf-service-slider"
		     data-srf-slider="switcher"
		     data-images="<?php echo esc_attr( $slider_json ); ?>">
			<div class="srf-service-slider__viewport">
				<img class="srf-service-slider__image"
				     src="<?php echo esc_url( $slider_items[0]['url'] ); ?>"
				     alt="<?php echo esc_attr( $slider_items[0]['alt'] ); ?>"
				     loading="lazy" />
			</div>
			<div class="srf-service-slider__nav">
				<button type="button" class="srf-service-slider__prev">&#10094;</button>
				<button type="button" class="srf-service-slider__next">&#10095;</button>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( $title !== '' ) : ?>
		<h2 class="srf-service-info__title"><?php echo esc_html( $title ); ?></h2>
	<?php else : ?>
		<h2 class="srf-service-info__title"><?php esc_html_e( 'Service', 'service-requests-form' ); ?></h2>
	<?php endif; ?>

	<?php if ( $content !== '' ) : ?>
		<div class="srf-service-info__text is-collapsed" data-srf-collapsible="text">
			<?php echo wp_kses_post( $content ); ?>
		</div>
		<button type="button" class="srf-service-info__toggle" data-srf-toggle="text"><?php esc_html_e( 'Show more', 'service-requests-form' ); ?></button>
	<?php endif; ?>

	<?php if ( ! empty( $variant_groups ) ) : ?>
		<div class="srf-service-info__variants">
			<h3 class="srf-service-info__subtitle"><?php esc_html_e( 'Variants', 'service-requests-form' ); ?></h3>
			<ul class="srf-service-info__variants-list">
				<?php foreach ( $variant_groups as $g ) : ?>
					<li>
						<strong><?php echo esc_html( $g['key'] ); ?>:</strong>
						<?php echo esc_html( implode( ', ', (array) $g['values'] ) ); ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>
</div>
