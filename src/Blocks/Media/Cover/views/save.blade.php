@php
	$mediaType        = $content['mediaType'] ?? 'image';
	$mediaUrl         = $content['mediaUrl'] ?? '';
	$alt              = $content['alt'] ?? '';
	$focalPoint       = $content['focalPoint'] ?? [ 'x' => 0.5, 'y' => 0.5 ];
	$hasParallax      = $content['hasParallax'] ?? false;
	$isRepeated       = $content['isRepeated'] ?? false;
	$overlayColor     = $styles['overlayColor'] ?? '#000000';
	$overlayOpacity   = $styles['overlayOpacity'] ?? 50;
	$useContentWidth  = $content['useContentWidth'] ?? false;
	$contentMaxWidth  = $content['contentMaxWidth'] ?? '';
	$minHeight        = $styles['minHeight'] ?? '430px';
	$minHeightUnit    = $styles['minHeightUnit'] ?? 'px';
	$contentAlignment = $styles['contentAlignment'] ?? 'center';
	$fullWidth        = $styles['fullWidth'] ?? false;
	$textColor        = $styles['textColor'] ?? null;
	$padding          = $styles['padding'] ?? null;
	$anchor           = $content['anchor'] ?? null;
	$htmlId           = $content['htmlId'] ?? null;
	$className        = $content['className'] ?? '';

	$elementId   = veSanitizeHtmlId( $htmlId ?: $anchor );
	$innerBlocks = $innerBlocks ?? [];

	$focalX = is_array( $focalPoint ) ? ( $focalPoint['x'] ?? 0.5 ) : 0.5;
	$focalY = is_array( $focalPoint ) ? ( $focalPoint['y'] ?? 0.5 ) : 0.5;
	$focalX = max( 0, min( 1, (float) $focalX ) );
	$focalY = max( 0, min( 1, (float) $focalY ) );
	$objectPosition = round( $focalX * 100 ) . '% ' . round( $focalY * 100 ) . '%';

	$overlayOpacity = max( 0, min( 100, (int) $overlayOpacity ) );
	$overlayColor   = $overlayColor ? veSanitizeCssColor( $overlayColor ) : '#000000';
	$textColor      = $textColor ? veSanitizeCssColor( $textColor ) : null;

	$allowedUnits = [ 'px', 'vh', 'vw' ];
	$safeUnit     = in_array( $minHeightUnit, $allowedUnits, true ) ? $minHeightUnit : 'px';

	$minHeightValue = veSanitizeCssDimension( $minHeight, '430px' );

	$alignmentMap = [
		'top-left'      => [ 'flex-start', 'flex-start' ],
		'top-center'    => [ 'flex-start', 'center' ],
		'top-right'     => [ 'flex-start', 'flex-end' ],
		'center-left'   => [ 'center', 'flex-start' ],
		'center'        => [ 'center', 'center' ],
		'center-right'  => [ 'center', 'flex-end' ],
		'bottom-left'   => [ 'flex-end', 'flex-start' ],
		'bottom-center' => [ 'flex-end', 'center' ],
		'bottom-right'  => [ 'flex-end', 'flex-end' ],
	];
	$alignment      = $alignmentMap[ $contentAlignment ] ?? [ 'center', 'center' ];
	$justifyContent = $alignment[0];
	$alignItems     = $alignment[1];

	$containerStyles = "position: relative; display: flex; flex-direction: column; justify-content: {$justifyContent}; align-items: {$alignItems}; min-height: {$minHeightValue}; overflow: hidden;";
	if ( $fullWidth ) {
		$containerStyles .= ' width: 100%;';
	}
	if ( $textColor ) {
		$containerStyles .= " color: {$textColor};";
	}

	$bgStyles = 'position: absolute; inset: 0;';
	if ( 'image' === $mediaType && $mediaUrl ) {
		$safeBgUrl = str_replace( [ "'", '"', '\\', '(', ')', ';' ], '', $mediaUrl );
		if ( $hasParallax ) {
			$bgStyles .= " background-image: url('" . $safeBgUrl . "'); background-position: {$objectPosition}; background-attachment: fixed; background-size: cover;";
			if ( $isRepeated ) {
				$bgStyles .= ' background-repeat: repeat; background-size: auto;';
			} else {
				$bgStyles .= ' background-repeat: no-repeat;';
			}
		}
	}

	$overlayStyles = "position: absolute; inset: 0; background-color: {$overlayColor}; opacity: " . ( $overlayOpacity / 100 ) . ";";

	$contentStyles = 'position: relative; z-index: 1;';
	if ( is_array( $padding ) ) {
		$pTop    = veSanitizeCssDimension( $padding['top'] ?? '0' );
		$pRight  = veSanitizeCssDimension( $padding['right'] ?? '0' );
		$pBottom = veSanitizeCssDimension( $padding['bottom'] ?? '0' );
		$pLeft   = veSanitizeCssDimension( $padding['left'] ?? '0' );
		$contentStyles .= " padding: {$pTop} {$pRight} {$pBottom} {$pLeft};";
	}
	if ( $useContentWidth && $contentMaxWidth ) {
		$contentStyles .= ' max-width: ' . veSanitizeCssDimension( $contentMaxWidth ) . '; margin-left: auto; margin-right: auto;';
	}

	$classes = 've-block ve-block-cover';
	if ( $className ) {
		$classes .= " {$className}";
	}
@endphp

<div
	class="{{ $classes }}"
	style="{{ $containerStyles }}"
	@if ( $elementId ) id="{{ $elementId }}" @endif
>
	{{-- Background layer --}}
	@if ( 'image' === $mediaType && $mediaUrl && ! $hasParallax && $isRepeated )
		<div
			class="ve-block-cover__background"
			style="position: absolute; inset: 0; background-image: url('{{ $safeBgUrl }}'); background-repeat: repeat; background-size: auto;"
			aria-hidden="true"
		></div>
	@elseif ( 'image' === $mediaType && $mediaUrl && ! $hasParallax )
		<img
			class="ve-block-cover__image"
			src="{{ $mediaUrl }}"
			alt="{{ $alt }}"
			style="position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; object-position: {{ $objectPosition }};"
			@if ( ! $alt ) aria-hidden="true" @endif
		/>
	@elseif ( 'image' === $mediaType && $mediaUrl && $hasParallax )
		<div class="ve-block-cover__background" style="{{ $bgStyles }}" aria-hidden="true"></div>
	@elseif ( 'video' === $mediaType && $mediaUrl )
		<video
			class="ve-block-cover__video"
			src="{{ $mediaUrl }}"
			autoplay
			muted
			loop
			playsinline
			style="position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; object-position: {{ $objectPosition }};"
			aria-hidden="true"
		></video>
	@elseif ( 'color' === $mediaType )
		<div class="ve-block-cover__background" style="{{ $bgStyles }}" aria-hidden="true"></div>
	@endif

	{{-- Overlay --}}
	<div class="ve-block-cover__overlay" style="{{ $overlayStyles }}" aria-hidden="true"></div>

	{{-- Content --}}
	<div class="ve-block-cover__content" style="{{ $contentStyles }}">
		@foreach ( $innerBlocks as $innerBlock )
			{!! $innerBlock !!}
		@endforeach
	</div>
</div>
