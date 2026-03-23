{{--
 * Override Indicator Partial
 *
 * Displays a visual indicator showing whether a style value is inherited
 * from global styles or overridden at the template level. Includes a
 * reset button for overridden values.
 *
 * Expected Alpine scope variables:
 *   - overridden: boolean expression indicating override status
 *   - name: string display name for the item
 *   - onReset: function to call when reset is clicked
 *
 * Usage in x-data context:
 *   @include('visual-editor::components._override-indicator', [
 *       'overriddenExpr' => 'isOverridden(index)',
 *       'nameExpr'       => 'entry.name',
 *       'resetExpr'      => 'resetToBase(index)',
 *   ])
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<span class="inline-flex items-center gap-1 shrink-0">
	{{-- Override dot --}}
	<span
		class="w-2 h-2 rounded-full"
		role="img"
		:class="{{ $overriddenExpr }} ? 'bg-warning' : 'bg-success/40'"
		:title="{{ $overriddenExpr }} ? '{{ __( 'visual-editor::ve.overridden' ) }}' : '{{ __( 'visual-editor::ve.inherited_from_global' ) }}'"
		:aria-label="{{ $nameExpr }} + ': ' + ( {{ $overriddenExpr }} ? '{{ __( 'visual-editor::ve.overridden' ) }}' : '{{ __( 'visual-editor::ve.inherited_from_global' ) }}' )"
	></span>

	{{-- Reset button (only shown when overridden) --}}
	<button
		type="button"
		x-show="{{ $overriddenExpr }}"
		x-on:click="{{ $resetExpr }}"
		class="text-base-content/30 hover:text-warning focus:text-warning transition-colors cursor-pointer focus:outline-none"
		:aria-label="'{{ __( 'visual-editor::ve.reset_to_global' ) }}: ' + {{ $nameExpr }}"
		title="{{ __( 'visual-editor::ve.reset_to_global' ) }}"
	>
		<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
			<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
		</svg>
	</button>
</span>
