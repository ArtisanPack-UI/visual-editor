{{--
 * Link Popover Component
 *
 * A WordPress-style link popover with URL input, expandable Advanced
 * section (open in new tab, nofollow, sponsored), Cancel and Apply buttons.
 *
 * Expects the parent x-data scope to provide:
 *   - linkPopoverOpen (bool)     – controls visibility
 *   - linkPopoverUrl (string)    – the URL value
 *   - linkPopoverNewTab (bool)   – open in new tab
 *   - linkPopoverNofollow (bool) – mark as nofollow
 *   - linkPopoverSponsored (bool) – mark as sponsored
 *
 * Dispatches the configured event with { url, newTab, nofollow, sponsored }.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div
	id="{{ $uuid }}"
	x-show="linkPopoverOpen"
	x-on:click.outside="linkPopoverOpen = false"
	x-on:keydown.escape.window="linkPopoverOpen = false"
	x-transition
	{{ $attributes->merge( [ 'class' => 'absolute left-0 top-full mt-1 w-72 rounded-lg border border-base-300 bg-base-100 shadow-lg p-3 z-50' ] ) }}
>
	<div class="flex flex-col gap-3">
		{{-- URL input --}}
		<div>
			<input
				type="url"
				class="input input-bordered input-sm w-full"
				placeholder="{{ __( 'visual-editor::ve.url_placeholder' ) }}"
				x-model="linkPopoverUrl"
				x-on:keydown.enter.prevent="
					$dispatch( {{ Js::from( $eventName ) }}, {
						url: linkPopoverUrl,
						newTab: linkPopoverNewTab,
						nofollow: linkPopoverNofollow,
						sponsored: linkPopoverSponsored,
					} );
					linkPopoverOpen = false;
				"
			/>
		</div>

		{{-- Advanced collapsible section --}}
		<div x-data="{ advancedOpen: false }">
			<button
				type="button"
				class="flex items-center gap-1 text-xs font-medium text-base-content/70 hover:text-base-content transition-colors"
				x-on:click="advancedOpen = ! advancedOpen"
				:aria-expanded="advancedOpen ? 'true' : 'false'"
			>
				<svg
					class="w-3 h-3 transition-transform"
					:class="advancedOpen ? 'rotate-0' : '-rotate-90'"
					fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
					aria-hidden="true" focusable="false"
				>
					<path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
				</svg>
				{{ __( 'visual-editor::ve.advanced' ) }}
			</button>

			<div
				x-show="advancedOpen"
				x-collapse
				class="mt-2 flex flex-col gap-2.5"
			>
				{{-- Open in new tab --}}
				<label class="flex items-center gap-2 cursor-pointer">
					<input
						type="checkbox"
						class="checkbox checkbox-sm"
						x-model="linkPopoverNewTab"
					/>
					<span class="text-xs text-base-content/80">
						{{ __( 'visual-editor::ve.open_in_new_tab' ) }}
					</span>
				</label>

				{{-- Nofollow --}}
				<label class="flex items-start gap-2 cursor-pointer">
					<input
						type="checkbox"
						class="checkbox checkbox-sm mt-0.5"
						x-model="linkPopoverNofollow"
					/>
					<span class="text-xs text-base-content/80">
						{!! __( 'visual-editor::ve.search_engines_nofollow', [ 'tag' => '<code class="text-[0.65rem] bg-base-200 px-1 py-0.5 rounded">nofollow</code>' ] ) !!}
					</span>
				</label>

				{{-- Sponsored --}}
				<label class="flex items-start gap-2 cursor-pointer">
					<input
						type="checkbox"
						class="checkbox checkbox-sm mt-0.5"
						x-model="linkPopoverSponsored"
					/>
					<span class="text-xs text-base-content/80">
						{!! __( 'visual-editor::ve.sponsored_link', [ 'tag' => '<code class="text-[0.65rem] bg-base-200 px-1 py-0.5 rounded">sponsored</code>' ] ) !!}
					</span>
				</label>
			</div>
		</div>

		{{-- Action buttons --}}
		<div class="flex justify-end gap-2">
			<button
				type="button"
				class="btn btn-sm btn-ghost"
				x-on:click="linkPopoverOpen = false"
			>
				{{ __( 'visual-editor::ve.cancel' ) }}
			</button>
			<button
				type="button"
				class="btn btn-sm btn-primary"
				x-on:click="
					$dispatch( {{ Js::from( $eventName ) }}, {
						url: linkPopoverUrl,
						newTab: linkPopoverNewTab,
						nofollow: linkPopoverNofollow,
						sponsored: linkPopoverSponsored,
					} );
					linkPopoverOpen = false;
				"
			>
				{{ __( 'visual-editor::ve.apply' ) }}
			</button>
		</div>
	</div>
</div>
