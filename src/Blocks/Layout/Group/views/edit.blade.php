@php
	$tag            = $content['tag'] ?? 'div';
	$flexDirection  = $content['flexDirection'] ?? 'column';
	$flexWrap       = $content['flexWrap'] ?? 'nowrap';
	$justifyContent = $content['justifyContent'] ?? 'flex-start';
	$textColor      = $styles['textColor'] ?? null;
	$bgColor        = $styles['backgroundColor'] ?? null;
	$padding        = $styles['padding'] ?? null;
	$margin         = $styles['margin'] ?? null;
	$border         = $styles['border'] ?? [];
	$minHeight      = $styles['minHeight'] ?? '';
	$verticalAlign  = $styles['verticalAlignment'] ?? 'top';
	$gap            = $styles['gap'] ?? null;
	$useFlexbox     = $styles['useFlexbox'] ?? false;
	$fillHeight     = $styles['fillHeight'] ?? false;
	$innerSpacing   = $styles['innerSpacing'] ?? 'normal';
	$innerBlocks    = $innerBlocks ?? [];

	$allowedTags = [ 'div', 'section', 'article', 'aside', 'main', 'header', 'footer' ];
	$tag         = in_array( $tag, $allowedTags ) ? $tag : 'div';

	$allowedDirections = [ 'column', 'row' ];
	$flexDirection     = in_array( $flexDirection, $allowedDirections ) ? $flexDirection : 'column';

	$allowedWraps = [ 'nowrap', 'wrap' ];
	$flexWrap     = in_array( $flexWrap, $allowedWraps ) ? $flexWrap : 'nowrap';

	$allowedJustify = [ 'flex-start', 'center', 'flex-end', 'space-between' ];
	$justifyContent = in_array( $justifyContent, $allowedJustify ) ? $justifyContent : 'flex-start';

	$alignMap = [
		'top'     => 'flex-start',
		'center'  => 'center',
		'bottom'  => 'flex-end',
		'stretch' => 'stretch',
	];

	$inlineStyles = "display: flex; flex-direction: {$flexDirection}; flex-wrap: {$flexWrap};";

	if ( 'row' === $flexDirection ) {
		$inlineStyles .= " justify-content: {$justifyContent};";
		$inlineStyles .= " align-items: " . ( $alignMap[ $verticalAlign ] ?? 'flex-start' ) . ";";
	} else {
		$inlineStyles .= " align-items: " . ( $alignMap[ $verticalAlign ] ?? 'flex-start' ) . ";";
	}

	if ( $textColor ) {
		$inlineStyles .= " color: {$textColor};";
	}

	if ( $bgColor ) {
		$inlineStyles .= " background-color: {$bgColor};";
	}

	if ( $gap ) {
		$inlineStyles .= " gap: {$gap};";
	}

	if ( $useFlexbox ) {
		$spacingMap = [
			'none'   => '0',
			'small'  => '0.5rem',
			'normal' => '1rem',
			'medium' => '1.5rem',
			'large'  => '2rem',
		];
		$gapValue    = $spacingMap[ $innerSpacing ] ?? '1rem';
		$inlineStyles .= " gap: {$gapValue};";
	}

	if ( $fillHeight ) {
		$inlineStyles .= " height: 100%;";
	}

	if ( is_array( $padding ) ) {
		$top    = $padding['top'] ?? '0';
		$right  = $padding['right'] ?? '0';
		$bottom = $padding['bottom'] ?? '0';
		$left   = $padding['left'] ?? '0';
		$inlineStyles .= " padding: {$top} {$right} {$bottom} {$left};";
	}

	if ( is_array( $margin ) ) {
		$top    = $margin['top'] ?? '0';
		$bottom = $margin['bottom'] ?? '0';
		$inlineStyles .= " margin-top: {$top}; margin-bottom: {$bottom};";
	}

	if ( is_array( $border ) && 'none' !== ( $border['style'] ?? 'none' ) ) {
		$bWidth = ( $border['width'] ?? '0' ) . ( $border['widthUnit'] ?? 'px' );
		$bStyle = $border['style'] ?? 'solid';
		$bColor = $border['color'] ?? 'currentColor';
		$inlineStyles .= " border: {$bWidth} {$bStyle} {$bColor};";

		$bRadius = $border['radius'] ?? '0';
		if ( $bRadius && '0' !== $bRadius ) {
			$bRadiusUnit = $border['radiusUnit'] ?? 'px';
			$inlineStyles .= " border-radius: {$bRadius}{$bRadiusUnit};";
		}
	}

	if ( $minHeight ) {
		$inlineStyles .= " min-height: {$minHeight};";
	}

	$hasExplicitVariation = $bgColor || $textColor || 'column' !== $flexDirection;
	$showVariationPicker  = empty( $innerBlocks ) && ! $hasExplicitVariation;
	$orientation          = 'row' === $flexDirection ? 'horizontal' : 'vertical';
@endphp

<{{ $tag }}
	class="ve-block ve-block-group ve-block-editing"
	style="{{ $inlineStyles }}"
	data-placeholder="{{ __( 'visual-editor::ve.block_group_placeholder' ) }}"
>
	@if ( $showVariationPicker )
		<div class="ve-group-variation-picker flex flex-col items-center justify-center gap-4 py-8 px-4 w-full">
			<p class="text-sm text-base-content/60">{{ __( 'visual-editor::ve.variation_picker_instruction' ) }}</p>
			<div class="flex gap-3">
				<button
					type="button"
					class="ve-variation-btn flex flex-col items-center gap-1 rounded-lg border border-base-300 px-4 py-3 hover:border-primary hover:bg-primary/5 transition-colors"
					data-ve-set-variation="group"
				>
					<svg class="w-8 h-8 text-base-content/70" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
						<rect x="3" y="3" width="18" height="18" rx="2" />
						<line x1="3" y1="9" x2="21" y2="9" />
						<line x1="3" y1="15" x2="21" y2="15" />
					</svg>
					<span class="text-xs font-medium">{{ __( 'visual-editor::ve.variation_group' ) }}</span>
				</button>
				<button
					type="button"
					class="ve-variation-btn flex flex-col items-center gap-1 rounded-lg border border-base-300 px-4 py-3 hover:border-primary hover:bg-primary/5 transition-colors"
					data-ve-set-variation="row"
				>
					<svg class="w-8 h-8 text-base-content/70" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
						<rect x="3" y="3" width="18" height="18" rx="2" />
						<line x1="9" y1="3" x2="9" y2="21" />
						<line x1="15" y1="3" x2="15" y2="21" />
					</svg>
					<span class="text-xs font-medium">{{ __( 'visual-editor::ve.variation_row' ) }}</span>
				</button>
				<button
					type="button"
					class="ve-variation-btn flex flex-col items-center gap-1 rounded-lg border border-base-300 px-4 py-3 hover:border-primary hover:bg-primary/5 transition-colors"
					data-ve-set-variation="stack"
				>
					<svg class="w-8 h-8 text-base-content/70" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
						<rect x="3" y="3" width="18" height="18" rx="2" />
						<line x1="3" y1="8" x2="21" y2="8" />
						<line x1="3" y1="13" x2="21" y2="13" />
						<line x1="3" y1="18" x2="21" y2="18" />
					</svg>
					<span class="text-xs font-medium">{{ __( 'visual-editor::ve.variation_stack' ) }}</span>
				</button>
			</div>
		</div>
	@else
		<x-ve-inner-blocks
			:inner-blocks="$innerBlocks"
			:parent-id="$context['blockId'] ?? null"
			:placeholder="__( 'visual-editor::ve.block_group_placeholder' )"
			:orientation="$orientation"
			:editing="true"
		/>
	@endif
</{{ $tag }}>
