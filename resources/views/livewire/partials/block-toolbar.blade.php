{{--
 * Visual Editor - Block Toolbar Partial
 *
 * A reusable Alpine.js-driven toolbar for rich text editing within blocks.
 * Include this partial inside an element with x-data="blockToolbar(...)".
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Livewire\Partials
 *
 * @since      1.5.0
 --}}

<div class="ve-block-toolbar mb-1 flex items-center gap-0.5 rounded border border-gray-200 bg-gray-50 px-1 py-0.5">
	{{-- Bold --}}
	<button
		type="button"
		@mousedown.prevent
		@click.prevent="format( 'bold' )"
		:class="isActive( 'bold' ) ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:bg-gray-200'"
		class="rounded p-1 text-xs font-bold"
		title="{{ __( 'Bold' ) }}"
	>B</button>

	{{-- Italic --}}
	<button
		type="button"
		@mousedown.prevent
		@click.prevent="format( 'italic' )"
		:class="isActive( 'italic' ) ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:bg-gray-200'"
		class="rounded p-1 text-xs italic"
		title="{{ __( 'Italic' ) }}"
	>I</button>

	{{-- Underline --}}
	<button
		type="button"
		@mousedown.prevent
		@click.prevent="format( 'underline' )"
		:class="isActive( 'underline' ) ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:bg-gray-200'"
		class="rounded p-1 text-xs underline"
		title="{{ __( 'Underline' ) }}"
	>U</button>

	{{-- Strikethrough --}}
	<button
		type="button"
		@mousedown.prevent
		@click.prevent="format( 'strikeThrough' )"
		:class="isActive( 'strikeThrough' ) ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:bg-gray-200'"
		class="rounded p-1 text-xs line-through"
		title="{{ __( 'Strikethrough' ) }}"
	>S</button>

	{{-- Separator --}}
	<div class="mx-0.5 h-4 w-px bg-gray-300"></div>

	{{-- Link --}}
	<button
		type="button"
		@mousedown.prevent
		@click.prevent="insertLink()"
		class="rounded p-1 text-gray-600 hover:bg-gray-200"
		title="{{ __( 'Link' ) }}"
	>
		<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
			<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
		</svg>
	</button>

	{{-- Separator --}}
	<div class="mx-0.5 h-4 w-px bg-gray-300"></div>

	{{-- Align Left --}}
	<button
		type="button"
		@mousedown.prevent
		@click.prevent="format( 'justifyLeft' )"
		:class="isActive( 'justifyLeft' ) ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:bg-gray-200'"
		class="rounded p-1"
		title="{{ __( 'Align Left' ) }}"
	>
		<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
			<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6h18M3 12h12M3 18h18" />
		</svg>
	</button>

	{{-- Align Center --}}
	<button
		type="button"
		@mousedown.prevent
		@click.prevent="format( 'justifyCenter' )"
		:class="isActive( 'justifyCenter' ) ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:bg-gray-200'"
		class="rounded p-1"
		title="{{ __( 'Align Center' ) }}"
	>
		<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
			<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6h18M6 12h12M3 18h18" />
		</svg>
	</button>

	{{-- Align Right --}}
	<button
		type="button"
		@mousedown.prevent
		@click.prevent="format( 'justifyRight' )"
		:class="isActive( 'justifyRight' ) ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:bg-gray-200'"
		class="rounded p-1"
		title="{{ __( 'Align Right' ) }}"
	>
		<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
			<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6h18M9 12h12M3 18h18" />
		</svg>
	</button>
</div>
