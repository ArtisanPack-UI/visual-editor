{{--
 * Visual Editor - Global Block Toolbar
 *
 * A toolbar that appears above any selected block, containing universal tools
 * (block type indicator, drag handle, move up/down) and block-specific tools
 * (alignment, rich text formatting, heading level) based on the block's
 * toolbar configuration.
 *
 * Required variables:
 * @var string      $blockId       The block ID.
 * @var string      $blockType     The block type identifier.
 * @var array|null  $blockConfig   The block configuration from BlockRegistry.
 * @var array       $block         The block data array (content, settings, etc.).
 * @var int         $blockIndex    The block's position in the blocks array (0-based).
 * @var int         $totalBlocks   The total number of blocks.
 * @var bool        $isEditing     Whether the block is in inline edit mode.
 * @var bool        $isRichText    Whether the block's text field is richtext type.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Livewire\Partials
 *
 * @since      1.7.0
 --}}

@php
	$toolbarTools = $blockConfig['toolbar'] ?? [];
	$blockName    = $blockConfig['name'] ?? ucfirst( $blockType );
	$hasAlign     = in_array( 'align', $toolbarTools, true );
	$hasRichText  = in_array( 'richtext', $toolbarTools, true ) && $isEditing && $isRichText;
	$hasHeadingLevel     = in_array( 'heading_level', $toolbarTools, true );
	$hasListStyle        = in_array( 'list_style', $toolbarTools, true );
	$hasConstrainedWidth = in_array( 'constrained_width', $toolbarTools, true );
	$hasAlignItems       = in_array( 'align_items', $toolbarTools, true );
	$hasJustifyContent   = in_array( 'justify_content', $toolbarTools, true );
	$currentLevel    = $block['content']['level'] ?? 'h2';
	$currentStyle    = $block['content']['style'] ?? 'bullet';
	$isConstrained   = $block['settings']['constrained'] ?? false;
	$currentAlignItems     = $block['settings']['align_items'] ?? 'stretch';
	$currentJustifyContent = $block['settings']['justify_content'] ?? 'start';
@endphp

<div
	class="ve-global-toolbar absolute bottom-full left-0 z-20 mb-1 flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-1.5 py-1 shadow-sm"
	x-data="{ openDropdown: null, ...globalBlockToolbar() }"
	@mousedown.prevent.self
	@click.stop
>
	{{-- Group 1: Universal Tools --}}
	{{-- Block Type Indicator --}}
	<span
		class="flex items-center gap-1 rounded px-1.5 py-1 text-sm font-medium text-gray-500"
		title="{{ $blockName }}"
	>
		<svg class="h-4.5 w-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
			<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
		</svg>
	</span>

	{{-- Drag Handle --}}
	<span
		draggable="true"
		class="cursor-grab rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
		title="{{ __( 'Drag to reorder' ) }}"
		aria-label="{{ __( 'Drag to reorder block' ) }}"
	>
		<svg class="h-4.5 w-4.5" fill="currentColor" viewBox="0 0 24 24">
			<circle cx="9" cy="5" r="1.5" />
			<circle cx="15" cy="5" r="1.5" />
			<circle cx="9" cy="12" r="1.5" />
			<circle cx="15" cy="12" r="1.5" />
			<circle cx="9" cy="19" r="1.5" />
			<circle cx="15" cy="19" r="1.5" />
		</svg>
	</span>

	{{-- Move Up / Move Down --}}
	<div class="flex flex-col">
		<button
			type="button"
			wire:click.stop="moveBlockUp( '{{ $blockId }}' )"
			class="rounded p-0.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 disabled:opacity-30 disabled:hover:bg-transparent"
			title="{{ __( 'Move up' ) }}"
			@if ( 0 === $blockIndex ) disabled @endif
		>
			<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
			</svg>
		</button>
		<button
			type="button"
			wire:click.stop="moveBlockDown( '{{ $blockId }}' )"
			class="rounded p-0.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 disabled:opacity-30 disabled:hover:bg-transparent"
			title="{{ __( 'Move down' ) }}"
			@if ( $blockIndex >= $totalBlocks - 1 ) disabled @endif
		>
			<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
			</svg>
		</button>
	</div>

	{{-- Separator --}}
	<div class="mx-0.5 h-6 w-px bg-gray-200"></div>

	{{-- Group 2: Block-Specific Tools --}}

	{{-- Heading Level Selector --}}
	@if ( $hasHeadingLevel )
		<div class="relative">
			<button
				type="button"
				@click="openDropdown = (openDropdown === 'level' ? null : 'level')"
				class="flex items-center gap-1 rounded px-2 py-1 text-sm font-semibold text-gray-600 hover:bg-gray-100"
				title="{{ __( 'Change heading level' ) }}"
			>
				{{ strtoupper( $currentLevel ) }}
				<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
				</svg>
			</button>
			<div
				x-show="openDropdown === 'level'"
				@click.outside="openDropdown = null"
				x-transition
				class="absolute left-0 top-full z-30 mt-1 rounded border border-gray-200 bg-white py-1 shadow-lg"
			>
				@foreach ( [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ] as $level )
					<button
						type="button"
						wire:click.stop="changeHeadingLevel( '{{ $blockId }}', '{{ $level }}' )"
						@click="openDropdown = null"
						class="flex w-full items-center px-3 py-1.5 text-left text-sm hover:bg-blue-50
							{{ $currentLevel === $level ? 'bg-blue-50 font-semibold text-blue-700' : 'text-gray-700' }}"
					>
						{{ strtoupper( $level ) }}
					</button>
				@endforeach
			</div>
		</div>

		{{-- Separator after heading level --}}
		@if ( $hasConstrainedWidth || $hasAlignItems || $hasJustifyContent || $hasListStyle || $hasAlign || $hasRichText )
			<div class="mx-0.5 h-6 w-px bg-gray-200"></div>
		@endif
	@endif

	{{-- Constrained Width Toggle --}}
	@if ( $hasConstrainedWidth )
		<button
			type="button"
			@mousedown.prevent
			wire:click.stop="updateToolbarBlockSetting( '{{ $blockId }}', 'constrained', {{ $isConstrained ? 'false' : 'true' }} )"
			class="rounded p-1.5 {{ $isConstrained ? 'bg-blue-100 text-blue-700' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700' }}"
			title="{{ $isConstrained ? __( 'Remove Constrained Width' ) : __( 'Apply Constrained Width' ) }}"
		>
			<svg class="h-4.5 w-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 4v16M15 4v16" opacity="{{ $isConstrained ? '1' : '0.3' }}" />
			</svg>
		</button>

		{{-- Separator after constrained width --}}
		@if ( $hasAlignItems || $hasJustifyContent || $hasListStyle || $hasAlign || $hasRichText )
			<div class="mx-0.5 h-6 w-px bg-gray-200"></div>
		@endif
	@endif

	{{-- Align Items Selector --}}
	@if ( $hasAlignItems )
		<div class="relative">
			<button
				type="button"
				@click="openDropdown = (openDropdown === 'alignItems' ? null : 'alignItems')"
				class="flex items-center gap-1 rounded px-2 py-1 text-sm font-medium text-gray-600 hover:bg-gray-100"
				title="{{ __( 'Change align items' ) }}"
			>
				<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
					@if ( 'stretch' === $currentAlignItems )
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4h16M4 20h16M8 8h8v8H8z" />
					@elseif ( 'start' === $currentAlignItems )
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4h16M8 8h8v4H8z" />
					@elseif ( 'center' === $currentAlignItems )
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h8v4H8z" />
					@elseif ( 'end' === $currentAlignItems )
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 20h16M8 12h8v4H8z" />
					@endif
				</svg>
				<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
				</svg>
			</button>
			<div
				x-show="openDropdown === 'alignItems'"
				@click.outside="openDropdown = null"
				x-transition
				class="absolute left-0 top-full z-30 mt-1 w-44 rounded border border-gray-200 bg-white py-1 shadow-lg"
			>
				@foreach ( [ 'stretch' => __( 'Stretch' ), 'start' => __( 'Align Start' ), 'center' => __( 'Align Center' ), 'end' => __( 'Align End' ) ] as $value => $label )
					<button
						type="button"
						wire:click.stop="updateToolbarBlockSetting( '{{ $blockId }}', 'align_items', '{{ $value }}' )"
						@click="openDropdown = null"
						class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm hover:bg-blue-50
							{{ $currentAlignItems === $value ? 'bg-blue-50 font-semibold text-blue-700' : 'text-gray-700' }}"
					>
						<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							@if ( 'stretch' === $value )
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4h16M4 20h16M8 8h8v8H8z" />
							@elseif ( 'start' === $value )
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4h16M8 8h8v4H8z" />
							@elseif ( 'center' === $value )
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h8v4H8z" />
							@elseif ( 'end' === $value )
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 20h16M8 12h8v4H8z" />
							@endif
						</svg>
						{{ $label }}
					</button>
				@endforeach
			</div>
		</div>

		{{-- Separator after align items --}}
		@if ( $hasJustifyContent || $hasListStyle || $hasAlign || $hasRichText )
			<div class="mx-0.5 h-6 w-px bg-gray-200"></div>
		@endif
	@endif

	{{-- Justify Content Selector --}}
	@if ( $hasJustifyContent )
		<div class="relative">
			<button
				type="button"
				@click="openDropdown = (openDropdown === 'justifyContent' ? null : 'justifyContent')"
				class="flex items-center gap-1 rounded px-2 py-1 text-sm font-medium text-gray-600 hover:bg-gray-100"
				title="{{ __( 'Change justify content' ) }}"
			>
				<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
					@if ( 'start' === $currentJustifyContent )
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h4m0 0v12m0-12h4v12H8z" />
					@elseif ( 'center' === $currentJustifyContent )
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 6h4m0 0v12m0-12h4v12h-4z" />
					@elseif ( 'end' === $currentJustifyContent )
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6h4m0 0v12m0-12h4v12h-4z" />
					@elseif ( 'between' === $currentJustifyContent )
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h4v12H4zM16 6h4v12h-4z" />
					@elseif ( 'around' === $currentJustifyContent )
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6h4v12H3zM10 6h4v12h-4zM17 6h4v12h-4z" />
					@elseif ( 'evenly' === $currentJustifyContent )
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2 6h4v12H2zM10 6h4v12h-4zM18 6h4v12h-4z" />
					@endif
				</svg>
				<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
				</svg>
			</button>
			<div
				x-show="openDropdown === 'justifyContent'"
				@click.outside="openDropdown = null"
				x-transition
				class="absolute left-0 top-full z-30 mt-1 w-48 rounded border border-gray-200 bg-white py-1 shadow-lg"
			>
				@foreach ( [ 'start' => __( 'Justify Start' ), 'center' => __( 'Justify Center' ), 'end' => __( 'Justify End' ), 'between' => __( 'Space Between' ), 'around' => __( 'Space Around' ), 'evenly' => __( 'Space Evenly' ) ] as $value => $label )
					<button
						type="button"
						wire:click.stop="updateToolbarBlockSetting( '{{ $blockId }}', 'justify_content', '{{ $value }}' )"
						@click="openDropdown = null"
						class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm hover:bg-blue-50
							{{ $currentJustifyContent === $value ? 'bg-blue-50 font-semibold text-blue-700' : 'text-gray-700' }}"
					>
						<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							@if ( 'start' === $value )
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h4m0 0v12m0-12h4v12H8z" />
							@elseif ( 'center' === $value )
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 6h4m0 0v12m0-12h4v12h-4z" />
							@elseif ( 'end' === $value )
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6h4m0 0v12m0-12h4v12h-4z" />
							@elseif ( 'between' === $value )
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h4v12H4zM16 6h4v12h-4z" />
							@elseif ( 'around' === $value )
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6h4v12H3zM10 6h4v12h-4zM17 6h4v12h-4z" />
							@elseif ( 'evenly' === $value )
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2 6h4v12H2zM10 6h4v12h-4zM18 6h4v12h-4z" />
							@endif
						</svg>
						{{ $label }}
					</button>
				@endforeach
			</div>
		</div>

		{{-- Separator after justify content --}}
		@if ( $hasListStyle || $hasAlign || $hasRichText )
			<div class="mx-0.5 h-6 w-px bg-gray-200"></div>
		@endif
	@endif

	{{-- List Style Toggle --}}
	@if ( $hasListStyle )
		<button
			type="button"
			@mousedown.prevent
			@click.prevent="$wire.$parent.changeListStyle( '{{ $blockId }}', 'bullet' )"
			class="rounded p-1.5 {{ 'bullet' === $currentStyle ? 'bg-blue-100 text-blue-700' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700' }}"
			title="{{ __( 'Unordered List' ) }}"
		>
			<svg class="h-4.5 w-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" />
			</svg>
		</button>
		<button
			type="button"
			@mousedown.prevent
			@click.prevent="$wire.$parent.changeListStyle( '{{ $blockId }}', 'number' )"
			class="rounded p-1.5 {{ 'number' === $currentStyle ? 'bg-blue-100 text-blue-700' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700' }}"
			title="{{ __( 'Ordered List' ) }}"
		>
			<svg class="h-4.5 w-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 6h14M7 12h14M7 18h14" />
				<text x="1" y="7" font-size="6" fill="currentColor" stroke="none" font-weight="bold">1</text>
				<text x="1" y="13" font-size="6" fill="currentColor" stroke="none" font-weight="bold">2</text>
				<text x="1" y="19" font-size="6" fill="currentColor" stroke="none" font-weight="bold">3</text>
			</svg>
		</button>

		{{-- Separator after list style --}}
		@if ( $hasAlign || $hasRichText )
			<div class="mx-0.5 h-6 w-px bg-gray-200"></div>
		@endif
	@endif

	{{-- Alignment Tools --}}
	@if ( $hasAlign )
		<button
			type="button"
			@mousedown.prevent
			@click.prevent="$wire.$parent.updateBlockSetting( 'alignment', 'left' )"
			class="rounded p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700"
			title="{{ __( 'Align Left' ) }}"
		>
			<svg class="h-4.5 w-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6h18M3 12h12M3 18h18" />
			</svg>
		</button>
		<button
			type="button"
			@mousedown.prevent
			@click.prevent="$wire.$parent.updateBlockSetting( 'alignment', 'center' )"
			class="rounded p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700"
			title="{{ __( 'Align Center' ) }}"
		>
			<svg class="h-4.5 w-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6h18M6 12h12M3 18h18" />
			</svg>
		</button>
		<button
			type="button"
			@mousedown.prevent
			@click.prevent="$wire.$parent.updateBlockSetting( 'alignment', 'right' )"
			class="rounded p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700"
			title="{{ __( 'Align Right' ) }}"
		>
			<svg class="h-4.5 w-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6h18M9 12h12M3 18h18" />
			</svg>
		</button>

		{{-- Separator after alignment --}}
		@if ( $hasRichText )
			<div class="mx-0.5 h-6 w-px bg-gray-200"></div>
		@endif
	@endif

	{{-- Rich Text Formatting Tools --}}
	@if ( $hasRichText )
		<button
			type="button"
			@mousedown.prevent
			@click.prevent="format( 'bold' )"
			:class="isActive( 'bold' ) ? 'bg-blue-100 text-blue-700' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700'"
			class="rounded p-1.5 text-sm font-bold"
			title="{{ __( 'Bold' ) }}"
		>B</button>

		<button
			type="button"
			@mousedown.prevent
			@click.prevent="format( 'italic' )"
			:class="isActive( 'italic' ) ? 'bg-blue-100 text-blue-700' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700'"
			class="rounded p-1.5 text-sm italic"
			title="{{ __( 'Italic' ) }}"
		>I</button>

		<button
			type="button"
			@mousedown.prevent
			@click.prevent="insertLink()"
			class="rounded p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700"
			title="{{ __( 'Link' ) }}"
		>
			<svg class="h-4.5 w-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
			</svg>
		</button>
	@endif

	{{-- Separator before More Options --}}
	<div class="mx-0.5 h-6 w-px bg-gray-200"></div>

	{{-- Group 3: More Options --}}
	<div class="relative">
		<button
			type="button"
			@click="openDropdown = (openDropdown === 'more' ? null : 'more')"
			class="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
			title="{{ __( 'More options' ) }}"
		>
			<svg class="h-4.5 w-4.5" fill="currentColor" viewBox="0 0 24 24">
				<circle cx="12" cy="5" r="2" />
				<circle cx="12" cy="12" r="2" />
				<circle cx="12" cy="19" r="2" />
			</svg>
		</button>
		<div
			x-show="openDropdown === 'more'"
			@click.outside="openDropdown = null"
			x-transition
			class="absolute right-0 top-full z-30 mt-1 w-44 rounded border border-gray-200 bg-white py-1 shadow-lg"
		>
			<button
				type="button"
				wire:click.stop="deleteBlock( '{{ $blockId }}' )"
				@click="openDropdown = null"
				class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50"
			>
				<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
				</svg>
				{{ __( 'Delete block' ) }}
			</button>
		</div>
	</div>
</div>
