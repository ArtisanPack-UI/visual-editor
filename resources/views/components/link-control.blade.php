{{--
 * Link Control Component
 *
 * A URL input with expandable panel for link text, open-in-new-tab, and nofollow.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div
	id="{{ $uuid }}"
	x-data="{
		url: '{{ $url ?? '' }}',
		text: '{{ $text ?? '' }}',
		newTab: {{ $newTab ? 'true' : 'false' }},
		nofollow: {{ $nofollow ? 'true' : 'false' }},
		expanded: {{ $expanded ? 'true' : 'false' }},
		dispatch() {
			$dispatch( 've-link-change', {
				url: this.url,
				text: this.text,
				newTab: this.newTab,
				nofollow: this.nofollow
			} )
		}
	}"
	{{ $attributes->merge( [ 'class' => 'flex flex-col gap-2' ] ) }}
>
	@if ( $label )
		<label class="text-xs font-medium text-gray-600">
			{{ $label }}
		</label>
	@endif

	{{-- URL Input Row --}}
	<div class="flex items-center gap-1">
		<div class="flex-1">
			<x-artisanpack-input
				type="url"
				x-model="url"
				x-on:input="dispatch()"
				:placeholder="__( 'https://example.com' )"
				size="sm"
				:aria-label="__( 'URL' )"
			/>
		</div>

		<x-artisanpack-button
			x-on:click="expanded = !expanded"
			size="sm"
			color="ghost"
			:aria-expanded="'expanded'"
			aria-controls="{{ $uuid }}-options"
			:title="__( 'Link options' )"
		>
			<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
			</svg>
		</x-artisanpack-button>
	</div>

	{{-- Expandable Options --}}
	<div
		id="{{ $uuid }}-options"
		x-show="expanded"
		x-collapse
		class="flex flex-col gap-2 rounded-md border border-gray-200 p-2"
	>
		<x-artisanpack-input
			x-model="text"
			x-on:input="dispatch()"
			:label="__( 'Link text' )"
			:placeholder="__( 'Enter link text...' )"
			size="sm"
		/>

		<x-artisanpack-toggle
			x-model="newTab"
			x-on:change="dispatch()"
			:label="__( 'Open in new tab' )"
		/>

		<x-artisanpack-toggle
			x-model="nofollow"
			x-on:change="dispatch()"
			:label="__( 'Add nofollow' )"
		/>
	</div>

	@if ( $hint )
		<div class="{{ $hintClass }}">{{ $hint }}</div>
	@endif
</div>
