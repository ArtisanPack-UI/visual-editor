@php
	$tabs              = $content['tabs'] ?? [];
	$tabPosition       = $content['tabPosition'] ?? 'top';
	$tabStyle          = $styles['tabStyle'] ?? 'default';
	$tabSize           = $styles['tabSize'] ?? 'md';
	$fullWidth         = $styles['fullWidth'] ?? false;
	$tabTextColor      = veSanitizeCssColor( $styles['tabTextColor'] ?? null );
	$activeTabColor    = veSanitizeCssColor( $styles['activeTabColor'] ?? null );
	$contentBackground = veSanitizeCssColor( $styles['contentBackground'] ?? null );
	$innerBlocks       = $innerBlocks ?? [];

	$isVertical = in_array( $tabPosition, [ 'left', 'right' ], true );

	$classes = 've-block ve-block-tabs ve-block-editing';
	if ( $isVertical ) {
		$classes .= ' ve-tabs-vertical ve-tabs-' . $tabPosition;
	}

	$tabStyleClass = 'default' !== $tabStyle ? "tabs-{$tabStyle}" : '';
	$tabSizeClass  = 'md' !== $tabSize ? "tabs-{$tabSize}" : '';
	$tabsClasses   = trim( "tabs {$tabStyleClass} {$tabSizeClass}" );
	if ( $fullWidth ) {
		$tabsClasses .= ' w-full';
	}

	$wrapperStyles = '';
	if ( $isVertical ) {
		$wrapperStyles .= 'display: flex;';
		if ( 'right' === $tabPosition ) {
			$wrapperStyles .= ' flex-direction: row-reverse;';
		}
	}

	$tabListStyles = '';
	if ( $tabTextColor ) {
		$tabListStyles .= "color: {$tabTextColor};";
	}

	$contentStyles = '';
	if ( $contentBackground ) {
		$contentStyles .= "background-color: {$contentBackground};";
	}

	$activeStyles = '';
	if ( $activeTabColor ) {
		$activeStyles = "--ve-tabs-active-color: {$activeTabColor};";
	}
@endphp

<div
	class="{{ $classes }}"
	@if ( $wrapperStyles ) style="{{ $wrapperStyles }}" @endif
	data-tab-position="{{ $tabPosition }}"
	data-tab-style="{{ $tabStyle }}"
	x-data="{ activeTab: 0 }"
>
	<div
		class="{{ $tabsClasses }}"
		role="tablist"
		aria-label="{{ __( 'visual-editor::ve.block_tabs_name' ) }}"
		@if ( $tabListStyles || $activeStyles ) style="{{ $tabListStyles }}{{ $activeStyles }}" @endif
	>
		@foreach ( $tabs as $index => $tab )
			@php
				$tabLabel = $tab['label'] ?? __( 'visual-editor::ve.tabs_tab_label_placeholder' ) . ' ' . ( $index + 1 );
			@endphp
			<button
				type="button"
				class="tab"
				:class="activeTab === {{ $index }} ? 'tab-active' : ''"
				role="tab"
				:aria-selected="( activeTab === {{ $index }} ).toString()"
				x-on:click="activeTab = {{ $index }}"
			>
				<span contenteditable="true" class="ve-tab-label-editable">{{ $tabLabel }}</span>
			</button>
		@endforeach
	</div>

	<div class="ve-tabs-content" @if ( $contentStyles ) style="{{ $contentStyles }}" @endif>
		@foreach ( $innerBlocks as $index => $innerBlock )
			<div
				role="tabpanel"
				x-show="activeTab === {{ $index }}"
			>
				{!! $innerBlock !!}
			</div>
		@endforeach
	</div>
</div>
