@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	/*
	 * Carousel (#1127) — WAI-ARIA APG carousel pattern. The region and each
	 * slide are labelled here; `interactivity.js` builds the dots (one per
	 * position, which depends on the slides per view at the current width),
	 * reveals the controls and drives the track. Without the script the
	 * track stays a scroll-snap strip and the controls stay hidden.
	 */

	// The same rules as the React / Vue `attrBoolean()`: booleans as they
	// are, `''` / `'false'` / `'0'` and zero are false.
	$flag = static function ( $value, bool $fallback ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( null === $value ) {
			return $fallback;
		}

		if ( is_string( $value ) ) {
			return ! in_array( strtolower( trim( $value ) ), [ '', 'false', '0' ], true );
		}

		return (bool) $value;
	};

	$clamp = static function ( $value, int $min, int $max ): ?int {
		if ( ! is_numeric( $value ) ) {
			return null;
		}

		return max( $min, min( $max, (int) round( (float) $value ) ) );
	};

	$perViewBase = $clamp( $attributes['slidesPerView'] ?? null, 1, 6 ) ?? 3;
	$overrides   = $attributes['responsive']['slidesPerView'] ?? [];
	$overrides   = is_array( $overrides ) ? $overrides : [];
	$perViewTab  = $clamp( $overrides['tablet'] ?? null, 1, 6 ) ?? min( $perViewBase, 2 );
	$perViewMob  = $clamp( $overrides['mobile'] ?? null, 1, 6 ) ?? 1;

	$placement = (string) ( $attributes['controlsPlacement'] ?? 'below' );

	if ( ! in_array( $placement, [ 'below', 'split', 'overlay' ], true ) ) {
		$placement = 'below';
	}

	$showArrows  = $flag( $attributes['showArrows'] ?? null, true );
	$showDots    = $flag( $attributes['showDots'] ?? null, true );
	$loop        = $flag( $attributes['loop'] ?? null, false );
	$autoplay    = $flag( $attributes['autoplay'] ?? null, false );
	$interval    = $clamp( $attributes['autoplayInterval'] ?? null, 2, 30 ) ?? 5;
	$dimInactive = $flag( $attributes['dimInactive'] ?? null, false );

	$styles = [
		'--ap-carousel-per-view: ' . $perViewBase,
		'--ap-carousel-per-view-tablet: ' . $perViewTab,
		'--ap-carousel-per-view-mobile: ' . $perViewMob,
	];

	if ( $dimInactive ) {
		$opacity  = $clamp( $attributes['inactiveOpacity'] ?? null, 0, 100 ) ?? 35;
		$styles[] = '--ap-carousel-inactive-opacity: ' . BlockSupports::cssNumber( $opacity / 100 );
	}

	foreach ( [
		'arrowColor'           => '--ap-carousel-arrow-color',
		'arrowBackgroundColor' => '--ap-carousel-arrow-background',
		'dotColor'             => '--ap-carousel-dot-color',
		'dotActiveColor'       => '--ap-carousel-dot-active-color',
	] as $colorAttribute => $property ) {
		$color = BlockSupports::safeCssValue( (string) ( $attributes[ $colorAttribute ] ?? '' ) );

		if ( null !== $color ) {
			$styles[] = $property . ': ' . $color;
		}
	}

	// BlockRenderer rendered the slides through `carousel-slide.blade.php`
	// (#1127): only slide children, each labelled with a placeholder token,
	// and none that Block Visibility hid. Number them now that the count of
	// slides on the page is known.
	$slidesHtml = $innerBlocksHtml;
	$nonce      = (string) ( $attributes['_veCarouselSlides'] ?? '' );

	if ( 1 === preg_match( '/^[a-f0-9]+$/', $nonce ) ) {
		// The token shape `BlockRenderer::carouselSlideToken()` mints. Every
		// slide carries it in its marker; unlabelled ones also as their
		// aria-label.
		$token     = 'ap-carousel-slide-' . $nonce . '-(\d+)';
		$positions = [];

		$slidesHtml = (string) preg_replace_callback(
			'/ data-ap-carousel-slide="' . $token . '"/',
			static function ( array $match ) use ( &$positions ): string {
				$positions[ $match[1] ] = count( $positions ) + 1;

				return '';
			},
			$slidesHtml,
		);

		$total      = count( $positions );
		$slidesHtml = (string) preg_replace_callback(
			'/' . $token . '/',
			static fn ( array $match ): string => e( __( ':current of :total', [
				'current' => $positions[ $match[1] ] ?? 0,
				'total'   => $total,
			] ) ),
			$slidesHtml,
		);
	}

	$ariaLabel = trim( (string) ( $attributes['ariaLabel'] ?? '' ) );

	if ( '' === $ariaLabel ) {
		$ariaLabel = __( 'Carousel' );
	}

	$baseClasses = [ 'wp-block-artisanpack-carousel', 'ap-carousel', 'is-controls-' . $placement ];

	if ( $dimInactive ) {
		$baseClasses[] = 'has-dimmed-inactive';
	}

	$hasControls = $showArrows || $showDots || $autoplay;
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses, $styles ) !!} role="region" aria-roledescription="{{ __( 'carousel' ) }}" aria-label="{{ $ariaLabel }}" data-ap-carousel=""@if ( $loop ) data-loop="true"@endif @if ( $autoplay ) data-autoplay="{{ $interval }}"@endif>
	<div class="wp-block-artisanpack-carousel__viewport">
		<div class="wp-block-artisanpack-carousel__track" aria-live="polite">
			{!! $slidesHtml !!}
		</div>
	</div>
	@if ( $hasControls )
		<div class="wp-block-artisanpack-carousel__controls" hidden>
			@if ( $autoplay )
				<button type="button" class="wp-block-artisanpack-carousel__autoplay" data-label-pause="{{ __( 'Pause' ) }}" data-label-play="{{ __( 'Play' ) }}">{{ __( 'Pause' ) }}</button>
			@endif
			@if ( $showArrows )
				<button type="button" class="wp-block-artisanpack-carousel__arrow wp-block-artisanpack-carousel__arrow--previous" aria-label="{{ __( 'Previous slide' ) }}"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="M15 6l-6 6 6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
			@endif
			@if ( $showDots )
				<div class="wp-block-artisanpack-carousel__dots" role="group" aria-label="{{ __( 'Choose a slide' ) }}" data-dot-label="{{ __( 'Go to slide :index' ) }}"></div>
			@endif
			@if ( $showArrows )
				<button type="button" class="wp-block-artisanpack-carousel__arrow wp-block-artisanpack-carousel__arrow--next" aria-label="{{ __( 'Next slide' ) }}"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="M9 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
			@endif
		</div>
	@endif
</div>
