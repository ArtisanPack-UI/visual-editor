@php
	$blockId          = $blockId ?? 'accordion-' . uniqid();
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
	$innerBlocks      = $innerBlocks ?? [];

	$classes = 've-block ve-block-accordion ve-block-editing';
	$classes .= " ve-accordion-{$accordionStyle}";
	$classes .= " ve-accordion-icon-{$iconStyle}";
	$classes .= " ve-accordion-icon-{$iconPosition}";
	if ( $bordered ) {
		$classes .= ' ve-accordion-bordered';
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
@endphp

<div
	class="{{ $classes }}"
	@if ( $cssVars ) style="{{ $cssVars }}" @endif
	data-allow-multiple="{{ $allowMultiple ? 'true' : 'false' }}"
	data-icon-style="{{ $iconStyle }}"
	data-accordion-style="{{ $accordionStyle }}"
	x-data="{ open: [0] }"
>
	@foreach ( $innerBlocks as $index => $innerBlock )
		@php
			$section        = $sections[ $index ] ?? [];
			$sectionTitle   = $section['title'] ?? __( 'visual-editor::ve.accordion_section_title_placeholder' ) . ' ' . ( $index + 1 );
			$headingLevel   = $section['headingLevel'] ?? 'h3';
			$headingTag     = in_array( $headingLevel, [ 'h2', 'h3', 'h4', 'h5', 'h6' ], true ) ? $headingLevel : 'h3';
			$sectionClasses = 've-accordion-section collapse';
		@endphp
		<div
			class="{{ $sectionClasses }}"
			:class="open.includes( {{ $index }} ) ? 'collapse-open' : 'collapse-close'"
		>
			<{{ $headingTag }}
				class="ve-accordion-header collapse-title"
				role="button"
				tabindex="0"
				id="ve-accordion-header-{{ $index }}"
				aria-controls="ve-accordion-content-{{ $index }}"
				:aria-expanded="open.includes( {{ $index }} ).toString()"
				x-on:click="open.includes( {{ $index }} ) ? open = open.filter( i => i !== {{ $index }} ) : {{ $allowMultiple ? "open = [ ...open, {$index} ]" : "open = [ {$index} ]" }}"
				x-on:keydown.enter.prevent="open.includes( {{ $index }} ) ? open = open.filter( i => i !== {{ $index }} ) : {{ $allowMultiple ? "open = [ ...open, {$index} ]" : "open = [ {$index} ]" }}"
				x-on:keydown.space.prevent="open.includes( {{ $index }} ) ? open = open.filter( i => i !== {{ $index }} ) : {{ $allowMultiple ? "open = [ ...open, {$index} ]" : "open = [ {$index} ]" }}"
			>
				<span
				contenteditable="true"
				class="ve-accordion-title-editable"
				x-on:blur="$dispatch( 've-field-change', { blockId: {{ Js::from( $blockId ) }}, field: 'sections.{{ $index }}.title', value: $event.target.textContent.trim() } )"
			>{{ $sectionTitle }}</span>
			</{{ $headingTag }}>
			<div
				class="ve-accordion-content collapse-content"
				role="region"
				id="ve-accordion-content-{{ $index }}"
				aria-labelledby="ve-accordion-header-{{ $index }}"
				data-placeholder="{{ __( 'visual-editor::ve.accordion_section_placeholder' ) }}"
				x-show="open.includes( {{ $index }} )"
			>
				{!! $innerBlock !!}
			</div>
		</div>
	@endforeach
</div>
