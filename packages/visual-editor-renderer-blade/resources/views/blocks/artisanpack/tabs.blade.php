@php
	use ArtisanPackUI\VisualEditorRendererBlade\Services\TabIdStamper;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$renderer = app( \ArtisanPackUI\VisualEditorRendererBlade\BlockRenderer::class );

	$validAlign   = [ 'horizontal', 'vertical' ];
	$validSpacing = [ 'start', 'end', 'center', 'equal' ];

	$tabsAlign = (string) ( $attributes['tabsAlign'] ?? 'horizontal' );

	if ( ! in_array( $tabsAlign, $validAlign, true ) ) {
		$tabsAlign = 'horizontal';
	}

	$tabsSpacing = (string) ( $attributes['tabsSpacing'] ?? 'start' );

	if ( ! in_array( $tabsSpacing, $validSpacing, true ) ) {
		$tabsSpacing = 'start';
	}

	// Tab triggers AND panels are derived together from the inner
	// blocks so the trigger anchor / aria-controls always lines up
	// with the panel id, even when the section's persisted tabId is
	// empty. BlockRenderer::render() has already given each section a
	// page-unique id (#1057); a block rendered on its own hasn't been
	// through that pass, so it gets ids unique within this block.
	$tabs = [];
	$sectionIndex = 0;

	foreach ( ( new TabIdStamper() )->stampSections( $innerBlocks ) as $child ) {
		if ( ! is_array( $child ) || ( $child['name'] ?? '' ) !== 'artisanpack/tab-section' ) {
			continue;
		}

		$sectionIndex++;
		$childAttrs = isset( $child['attributes'] ) && is_array( $child['attributes'] ) ? $child['attributes'] : [];
		$label = (string) ( $childAttrs['label'] ?? '' );
		$tabId = $childAttrs[ TabIdStamper::ATTRIBUTE ];

		if ( '' === $label ) {
			$label = 'Tab ' . $sectionIndex;
		}

		$childInner = isset( $child['innerBlocks'] ) && is_array( $child['innerBlocks'] )
			? $renderer->render( $child['innerBlocks'] )
			: '';

		// The panel carries the section's own supports (#922): class,
		// block style, colour, spacing, border and shadow. `anchor` is
		// not a tab-section support, and the panel id is fixed below.
		$panelAttrs = $childAttrs;
		unset( $panelAttrs['anchor'] );

		$tabs[] = [
			'label'     => $label,
			'tabId'     => $tabId,
			'content'   => $childInner,
			'wrapper'   => BlockSupports::wrapperAttrs( $panelAttrs, [ 'wp-block-artisanpack-tab-section', 'ap-tab-section' ] ),
			'ariaLabel' => trim( (string) ( $childAttrs['ariaLabel'] ?? '' ) ),
		];
	}

	$baseClasses = [ 'wp-block-artisanpack-tabs', 'ap-tabs', 'align-tabs-' . $tabsAlign, 'space-tabs-' . $tabsSpacing ];
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses ) !!} data-ap-tabs>
	<div class="ap-tabs__list">
		<ul role="tablist">
			@foreach ( $tabs as $index => $trigger )
				@php
					$isFirst = 0 === $index;
				@endphp
				<li>
					<a
						href="#tabs-panel-{{ $trigger['tabId'] }}"
						id="tabs-tab-{{ $trigger['tabId'] }}"
						role="tab"
						aria-controls="tabs-panel-{{ $trigger['tabId'] }}"
						aria-selected="{{ $isFirst ? 'true' : 'false' }}"
						tabindex="{{ $isFirst ? '0' : '-1' }}"
					>{{ $trigger['label'] }}</a>
				</li>
			@endforeach
		</ul>
	</div>
	<div class="ap-tabs__container">
		@foreach ( $tabs as $tab )
			{{-- An author's aria-label replaces aria-labelledby, which would otherwise win. --}}
			<div{!! $tab['wrapper'] !!}
				role="tabpanel"
				id="tabs-panel-{{ $tab['tabId'] }}"
				@if ( '' !== $tab['ariaLabel'] ) aria-label="{{ $tab['ariaLabel'] }}" @else aria-labelledby="tabs-tab-{{ $tab['tabId'] }}" @endif
			>{!! $tab['content'] !!}</div>
		@endforeach
	</div>
</div>
