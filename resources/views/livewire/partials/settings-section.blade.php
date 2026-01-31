{{--
 * Visual Editor - Collapsible Settings Section
 *
 * A reusable collapsible section for the block settings panel.
 * Uses Alpine.js collapse plugin for smooth open/close transitions.
 *
 * Required variables:
 * @var string $title      The section heading text.
 * @var string $sectionKey The section identifier (e.g., 'sizing', 'typography').
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Livewire\Partials
 *
 * @since      1.8.0
 --}}

<div x-data="{ open: true }" class="border-b border-gray-200">
	<button
		type="button"
		@click="open = !open"
		class="flex w-full items-center justify-between px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 hover:bg-gray-50"
	>
		{{ $title }}
		<svg
			:class="open && 'rotate-180'"
			class="h-3.5 w-3.5 shrink-0 transition-transform duration-200"
			fill="none"
			stroke="currentColor"
			viewBox="0 0 24 24"
		>
			<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
		</svg>
	</button>
	<div x-show="open" x-collapse class="px-3 pb-3">
		{{ $slot }}
	</div>
</div>
