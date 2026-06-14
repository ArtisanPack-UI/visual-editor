@php
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

	/**
	 * Normalize a tab id so it's safe to interpolate into HTML id /
	 * aria-controls / fragment href values. Mirrors the React + Vue
	 * sanitizeTabId helpers; positional fallback when the persisted
	 * slug is empty.
	 */
	$sanitizeTabId = static function ( string $raw, int $sectionIndex ): string {
		$slug = strtolower( $raw );
		$slug = preg_replace( '/[^a-z0-9-]+/', '-', $slug ) ?? '';
		$slug = trim( $slug, '-' );
		return '' === $slug ? 'tab-' . $sectionIndex : $slug;
	};

	/**
	 * Ensure a tab id is unique within the current tabs block — if the
	 * author duplicated a tab-section (copy/paste, template clone) two
	 * sections can land on the same slug, which would collapse the
	 * trigger / panel ARIA wiring. Append a numeric suffix in that
	 * case.
	 */
	$uniqueTabId = static function ( string $base, array &$used ): string {
		if ( ! isset( $used[ $base ] ) ) {
			$used[ $base ] = true;
			return $base;
		}
		$suffix    = 2;
		$candidate = $base . '-' . $suffix;
		while ( isset( $used[ $candidate ] ) ) {
			$suffix++;
			$candidate = $base . '-' . $suffix;
		}
		$used[ $candidate ] = true;
		return $candidate;
	};

	// Tab triggers AND panels are derived together from the inner
	// blocks so the trigger anchor / aria-controls always lines up
	// with the panel id, even when the section's persisted tabId is
	// empty.
	$tabs = [];
	$sectionIndex = 0;
	$usedIds = [];

	foreach ( $innerBlocks as $child ) {
		if ( ! is_array( $child ) || ( $child['name'] ?? '' ) !== 'artisanpack/tab-section' ) {
			continue;
		}

		$sectionIndex++;
		$childAttrs = isset( $child['attributes'] ) && is_array( $child['attributes'] ) ? $child['attributes'] : [];
		$label  = (string) ( $childAttrs['label'] ?? '' );
		$baseId = $sanitizeTabId( (string) ( $childAttrs['tabId'] ?? '' ), $sectionIndex );
		$tabId  = $uniqueTabId( $baseId, $usedIds );

		if ( '' === $label ) {
			$label = 'Tab ' . $sectionIndex;
		}

		$childInner = isset( $child['innerBlocks'] ) && is_array( $child['innerBlocks'] )
			? $renderer->render( $child['innerBlocks'] )
			: '';

		$tabs[] = [
			'label'   => $label,
			'tabId'   => $tabId,
			'content' => $childInner,
		];
	}

	$baseClasses = [ 'ap-tabs', 'align-tabs-' . $tabsAlign, 'space-tabs-' . $tabsSpacing ];
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
			<div
				class="ap-tab-section"
				role="tabpanel"
				id="tabs-panel-{{ $tab['tabId'] }}"
				aria-labelledby="tabs-tab-{{ $tab['tabId'] }}"
			>{!! $tab['content'] !!}</div>
		@endforeach
	</div>
</div>
