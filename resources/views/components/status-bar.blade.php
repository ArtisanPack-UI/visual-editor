{{--
 * Status Bar Component
 *
 * Bottom bar displaying block count, word count, save status,
 * and last saved time for the editor.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

@php
	use ArtisanPackUI\VisualEditor\View\Components\EditorState;

	$saveStatusConstants = array_combine(
		array_map( 'strtoupper', EditorState::SAVE_STATUSES ),
		EditorState::SAVE_STATUSES,
	);
@endphp

<div
	id="{{ $uuid }}"
	x-data="{
		SAVE_STATUS: Object.freeze( {{ Js::from( $saveStatusConstants ) }} ),

		get blockCount() {
			return Alpine.store( 'editor' ) ? Alpine.store( 'editor' ).getBlockCount() : 0;
		},

		get wordCount() {
			return Alpine.store( 'editor' ) ? Alpine.store( 'editor' ).getWordCount() : 0;
		},

		get saveStatus() {
			return Alpine.store( 'editor' ) ? Alpine.store( 'editor' ).saveStatus : this.SAVE_STATUS.SAVED;
		},

		get lastSavedAt() {
			return Alpine.store( 'editor' ) ? Alpine.store( 'editor' ).lastSavedAt : null;
		},

		get lastSavedLabel() {
			if ( ! this.lastSavedAt ) return '';
			const formatted = this.lastSavedAt.toLocaleTimeString( navigator.language || 'en', { hour: '2-digit', minute: '2-digit' } );
			return {{ Js::from( __( 'visual-editor::ve.last_saved', [ 'time' => '__TIME__' ] ) ) }}.replace( '__TIME__', formatted );
		},

		get saveStatusLabel() {
			const labels = {
				[this.SAVE_STATUS.SAVED]: {{ Js::from( __( 'visual-editor::ve.saved' ) ) }},
				[this.SAVE_STATUS.UNSAVED]: {{ Js::from( __( 'visual-editor::ve.unsaved_changes' ) ) }},
				[this.SAVE_STATUS.SAVING]: {{ Js::from( __( 'visual-editor::ve.saving' ) ) }},
				[this.SAVE_STATUS.ERROR]: {{ Js::from( __( 'visual-editor::ve.save_error' ) ) }},
			};
			return labels[ this.saveStatus ] || '';
		},

		get blockCountLabel() {
			return 1 === this.blockCount
				? {{ Js::from( trans_choice( 'visual-editor::ve.block_count', 1 ) ) }}
				: {{ Js::from( trans_choice( 'visual-editor::ve.block_count', 2, [ 'count' => '__COUNT__' ] ) ) }}.replaceAll( '__COUNT__', this.blockCount );
		},

		get wordCountLabel() {
			return 1 === this.wordCount
				? {{ Js::from( trans_choice( 'visual-editor::ve.word_count', 1 ) ) }}
				: {{ Js::from( trans_choice( 'visual-editor::ve.word_count', 2, [ 'count' => '__COUNT__' ] ) ) }}.replaceAll( '__COUNT__', this.wordCount );
		},
	}"
	{{ $attributes->merge( [ 'class' => 'flex items-center justify-between px-4 py-1.5 border-t border-base-300 bg-base-100 text-xs text-base-content/60' ] ) }}
	role="region"
	aria-label="{{ __( 'visual-editor::ve.status_bar' ) }}"
>
	{{-- Left: counts --}}
	<div class="flex items-center gap-3">
		@if ( $showBlockCount )
			<span x-text="blockCountLabel"></span>
		@endif

		@if ( $showWordCount )
			<span x-text="wordCountLabel"></span>
		@endif
	</div>

	{{-- Right: save status --}}
	<div class="flex items-center gap-3">
		@if ( $showLastSaved )
			<span x-show="lastSavedAt" x-text="lastSavedLabel"></span>
		@endif

		@if ( $showSaveStatus )
			<span
				role="status"
				:class="{
					'text-success': SAVE_STATUS.SAVED === saveStatus,
					'text-warning': SAVE_STATUS.UNSAVED === saveStatus,
					'text-info': SAVE_STATUS.SAVING === saveStatus,
					'text-error': SAVE_STATUS.ERROR === saveStatus,
				}"
				x-text="saveStatusLabel"
			></span>
		@endif
	</div>
</div>
