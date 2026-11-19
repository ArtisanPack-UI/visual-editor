@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	/*
	 * Carousel (#1127) — WAI-ARIA APG carousel pattern. The region and each
	 * slide are labelled here; `interactivity.js` builds the dots (one per
	 * position, which depends on the slides per view at the current width),
	 * reveals the controls and drives the track. Without the script the
	 * track stays a scroll-snap strip and the controls stay hidden.
	 */
	$renderer = app( \ArtisanPackUI\VisualEditorRendererBlade\BlockRenderer::class );

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

	$showArrows  = (bool) ( $attributes['showArrows'] ?? true );
	$showDots    = (bool) ( $attributes['showDots'] ?? true );
	$loop        = (bool) ( $attributes['loop'] ?? false );
	$autoplay    = (bool) ( $attributes['autoplay'] ?? false );
	$interval    = $clamp( $attributes['autoplayInterval'] ?? null, 2, 30 ) ?? 5;
	$dimInactive = (bool) ( $attributes['dimInactive'] ?? false );

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

	$slides = [];

	foreach ( $innerBlocks as $child ) {
		if ( ! is_array( $child ) || ( $child['name'] ?? '' ) !== 'artisanpack/carousel-slide' ) {
			continue;
		}

		$childAttrs = isset( $child['attributes'] ) && is_array( $child['attributes'] ) ? $child['attributes'] : [];

		// The slide carries its own supports; `anchor` is not one of them.
		$slideAttrs = $childAttrs;
		unset( $slideAttrs['anchor'] );

		$slides[] = [
			'wrapper'   => BlockSupports::wrapperAttrs( $slideAttrs, [ 'wp-block-artisanpack-carousel-slide', 'ap-carousel-slide' ] ),
			'ariaLabel' => trim( (string) ( $childAttrs['ariaLabel'] ?? '' ) ),
			'content'   => isset( $child['innerBlocks'] ) && is_array( $child['innerBlocks'] )
				? $renderer->render( $child['innerBlocks'] )
				: '',
		];
	}

	$total     = count( $slides );
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
			@foreach ( $slides as $index => $slide )
				<div{!! $slide['wrapper'] !!} role="group" aria-roledescription="{{ __( 'slide' ) }}" aria-label="{{ '' !== $slide['ariaLabel'] ? $slide['ariaLabel'] : __( ':current of :total', [ 'current' => $index + 1, 'total' => $total ] ) }}">{!! $slide['content'] !!}</div>
			@endforeach
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
