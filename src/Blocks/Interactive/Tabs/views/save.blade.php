@php
	$tabs              = $content['tabs'] ?? [];
	$tabPosition       = $content['tabPosition'] ?? 'top';
	$tabStyle          = $styles['tabStyle'] ?? 'default';
	$tabSize           = $styles['tabSize'] ?? 'md';
	$fullWidth         = $styles['fullWidth'] ?? false;
	$fadeTransition    = $styles['fadeTransition'] ?? true;
	$rememberActive    = $content['rememberActive'] ?? false;
	$tabTextColor      = veSanitizeCssColor( $styles['tabTextColor'] ?? null );
	$activeTabColor    = veSanitizeCssColor( $styles['activeTabColor'] ?? null );
	$contentBackground = veSanitizeCssColor( $styles['contentBackground'] ?? null );
	$anchor            = $content['anchor'] ?? null;
	$htmlId            = $content['htmlId'] ?? null;
	$className         = $content['className'] ?? '';
	$innerBlocks       = $innerBlocks ?? [];

	$elementId = veSanitizeHtmlId( $htmlId ?: $anchor ) ?: 've-tabs-' . substr( md5( serialize( $tabs ) ), 0, 8 );

	$isVertical = in_array( $tabPosition, [ 'left', 'right' ], true );

	$classes = 've-block ve-block-tabs';
	if ( $isVertical ) {
		$classes .= ' ve-tabs-vertical ve-tabs-' . $tabPosition;
	}
	if ( $className ) {
		$classes .= " {$className}";
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

	$storageKey   = $rememberActive && $elementId ? $elementId : '';
	$panelCount   = count( $innerBlocks );
	$lastTabIndex = max( $panelCount - 1, 0 );
@endphp

<div
	class="{{ $classes }}"
	@if ( $elementId ) id="{{ $elementId }}" @endif
	@if ( $wrapperStyles ) style="{{ $wrapperStyles }}" @endif
	x-data="{
		activeTab: 0,
		storageKey: '{{ $storageKey }}',
		init() {
			if ( this.storageKey ) {
				const saved = localStorage.getItem( 've-tabs-' + this.storageKey );
				if ( saved !== null ) {
					this.activeTab = parseInt( saved, 10 );
				}
			}
		},
		setTab( index ) {
			this.activeTab = index;
			if ( this.storageKey ) {
				localStorage.setItem( 've-tabs-' + this.storageKey, index );
			}
		}
	}"
	x-on:keydown.arrow-right.prevent="setTab( Math.min( activeTab + 1, {{ $lastTabIndex }} ) )"
	x-on:keydown.arrow-left.prevent="setTab( Math.max( activeTab - 1, 0 ) )"
	x-on:keydown.home.prevent="setTab( 0 )"
	x-on:keydown.end.prevent="setTab( {{ $lastTabIndex }} )"
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
				$tabIcon  = $tab['icon'] ?? '';
				$tabId    = $tab['id'] ?? $index;
			@endphp
			<button
				type="button"
				class="tab"
				:class="activeTab === {{ $index }} ? 'tab-active' : ''"
				role="tab"
				:aria-selected="( activeTab === {{ $index }} ).toString()"
				aria-controls="ve-tabpanel-{{ $elementId }}-{{ $tabId }}"
				id="ve-tab-{{ $elementId }}-{{ $tabId }}"
				:tabindex="activeTab === {{ $index }} ? '0' : '-1'"
				x-on:click="setTab( {{ $index }} )"
			>
				@if ( $tabIcon )
					<span class="ve-tab-icon">{{ $tabIcon }}</span>
				@endif
				{{ $tabLabel }}
			</button>
		@endforeach
	</div>

	<div class="ve-tabs-content" @if ( $contentStyles ) style="{{ $contentStyles }}" @endif>
		@foreach ( $innerBlocks as $index => $innerBlock )
			@php
				$tabId = $tabs[ $index ]['id'] ?? $index;
			@endphp
			<div
				role="tabpanel"
				id="ve-tabpanel-{{ $elementId }}-{{ $tabId }}"
				aria-labelledby="ve-tab-{{ $elementId }}-{{ $tabId }}"
				x-show="activeTab === {{ $index }}"
				@if ( $fadeTransition ) x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" @endif
				tabindex="0"
			>
				{!! $innerBlock !!}
			</div>
		@endforeach
	</div>
</div>
