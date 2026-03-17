@php
	$sections         = $content['sections'] ?? [];
	$allowMultiple    = $content['allowMultiple'] ?? false;
	$iconStyle        = $styles['iconStyle'] ?? 'chevron';
	$iconPosition     = $styles['iconPosition'] ?? 'right';
	$bordered         = $styles['bordered'] ?? true;
	$accordionStyle   = $styles['accordionStyle'] ?? 'default';
	$headerBackground = veSanitizeCssColor( $styles['headerBackground'] ?? null );
	$contentBg        = veSanitizeCssColor( $styles['contentBackground'] ?? null );
	$borderColor      = veSanitizeCssColor( $styles['borderColor'] ?? null );
	$activeHeaderClr  = veSanitizeCssColor( $styles['activeHeaderColor'] ?? null );
	$anchor           = $content['anchor'] ?? null;
	$htmlId           = $content['htmlId'] ?? null;
	$className        = $content['className'] ?? '';
	$innerBlocks      = $innerBlocks ?? [];

	$elementId = veSanitizeHtmlId( $htmlId ?: $anchor ) ?: 've-accordion-' . substr( md5( serialize( $sections ) ), 0, 8 );

	$classes = 've-block ve-block-accordion';
	$classes .= " ve-accordion-{$accordionStyle}";
	$classes .= " ve-accordion-icon-{$iconStyle}";
	$classes .= " ve-accordion-icon-{$iconPosition}";
	if ( $bordered ) {
		$classes .= ' ve-accordion-bordered';
	}
	if ( $className ) {
		$classes .= " {$className}";
	}

	$cssVars = '';
	if ( $headerBackground ) {
		$cssVars .= "--ve-accordion-header-bg: {$headerBackground};";
	}
	if ( $contentBg ) {
		$cssVars .= "--ve-accordion-content-bg: {$contentBg};";
	}
	if ( $borderColor ) {
		$cssVars .= "--ve-accordion-border-color: {$borderColor};";
	}
	if ( $activeHeaderClr ) {
		$cssVars .= "--ve-accordion-active-header-color: {$activeHeaderClr};";
	}

	$defaultOpen = [];
	foreach ( $sections as $index => $section ) {
		if ( ! empty( $section['isOpen'] ) ) {
			$defaultOpen[] = $index;
		}
	}
@endphp

<div
	class="{{ $classes }}"
	@if ( $elementId ) id="{{ $elementId }}" @endif
	@if ( $cssVars ) style="{{ $cssVars }}" @endif
	x-data="{
		allowMultiple: {{ $allowMultiple ? 'true' : 'false' }},
		open: {{ json_encode( $defaultOpen ) }},
		toggle( index ) {
			if ( this.open.includes( index ) ) {
				this.open = this.open.filter( i => i !== index );
			} else {
				if ( ! this.allowMultiple ) {
					this.open = [ index ];
				} else {
					this.open.push( index );
				}
			}
		},
		isOpen( index ) {
			return this.open.includes( index );
		}
	}"
>
	@foreach ( $innerBlocks as $index => $innerBlock )
		@php
			$section      = $sections[ $index ] ?? [];
			$sectionTitle = $section['title'] ?? __( 'visual-editor::ve.accordion_section_title_placeholder' ) . ' ' . ( $index + 1 );
			$headingLevel = $section['headingLevel'] ?? 'h3';
			$headingTag   = in_array( $headingLevel, [ 'h2', 'h3', 'h4', 'h5', 'h6' ], true ) ? $headingLevel : 'h3';
			$sectionId    = $section['id'] ?? $index;
		@endphp
		<div
			class="ve-accordion-section"
			:class="isOpen( {{ $index }} ) ? 've-accordion-section-open' : 've-accordion-section-closed'"
		>
			<{{ $headingTag }}
				class="ve-accordion-header"
				style="cursor: pointer;"
				role="button"
				tabindex="0"
				id="ve-accordion-header-{{ $elementId }}-{{ $sectionId }}"
				aria-controls="ve-accordion-content-{{ $elementId }}-{{ $sectionId }}"
				:aria-expanded="isOpen( {{ $index }} ).toString()"
				x-on:click="toggle( {{ $index }} )"
				x-on:keydown.enter.prevent="toggle( {{ $index }} )"
				x-on:keydown.space.prevent="toggle( {{ $index }} )"
			>
				{{ $sectionTitle }}
			</{{ $headingTag }}>
			<div
				class="ve-accordion-content"
				role="region"
				id="ve-accordion-content-{{ $elementId }}-{{ $sectionId }}"
				aria-labelledby="ve-accordion-header-{{ $elementId }}-{{ $sectionId }}"
				x-show="isOpen( {{ $index }} )"
				x-collapse
			>
				{!! $innerBlock !!}
			</div>
		</div>
	@endforeach
</div>
