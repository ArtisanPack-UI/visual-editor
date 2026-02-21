{{--
 * Device Preview Component
 *
 * Wraps the editor canvas with a responsive preview container
 * that constrains width for tablet and mobile viewports,
 * with optional zoom controls.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div
	id="{{ $uuid }}"
	x-data="{
		zoom: {{ Js::from( $defaultZoom ) }},
		minZoom: {{ Js::from( $minZoom ) }},
		maxZoom: {{ Js::from( $maxZoom ) }},

		get device() {
			return Alpine.store( 'editor' ) ? Alpine.store( 'editor' ).devicePreview : {{ Js::from( $device ) }};
		},

		get containerWidth() {
			switch ( this.device ) {
				case 'tablet':
					return '{{ $tabletWidth }}px';
				case 'mobile':
					return '{{ $mobileWidth }}px';
				default:
					return '100%';
			}
		},

		get containerStyle() {
			const style = {
				width: this.containerWidth,
				maxWidth: '100%',
				margin: '0 auto',
				transformOrigin: 'top center',
			};

			if ( 100 !== this.zoom ) {
				style.transform = 'scale(' + ( this.zoom / 100 ) + ')';
			}

			return style;
		},

		zoomIn() {
			if ( this.zoom < this.maxZoom ) {
				this.zoom = Math.min( this.zoom + 10, this.maxZoom );
				this._announceZoom();
			}
		},

		zoomOut() {
			if ( this.zoom > this.minZoom ) {
				this.zoom = Math.max( this.zoom - 10, this.minZoom );
				this._announceZoom();
			}
		},

		resetZoom() {
			this.zoom = 100;
			this._announceZoom();
		},

		_announceZoom() {
			if ( Alpine.store( 'announcer' ) ) {
				Alpine.store( 'announcer' ).announce(
					{{ Js::from( __( 'visual-editor::ve.zoom_level', [ 'level' => '__LEVEL__' ] ) ) }}.replaceAll( '__LEVEL__', this.zoom )
				);
			}
		}
	}"
	{{ $attributes->merge( [ 'class' => 'relative flex flex-col items-center overflow-auto' ] ) }}
	role="region"
	aria-label="{{ __( 'visual-editor::ve.device_preview' ) }}"
>
	{{-- Preview container --}}
	<div
		x-ref="previewContainer"
		:style="containerStyle"
		class="transition-all duration-300 ease-in-out"
	>
		{{ $slot }}
	</div>

	{{-- Zoom controls --}}
	@if ( $showZoomControls )
		<div class="flex items-center gap-1 mt-2" role="group" aria-label="{{ __( 'visual-editor::ve.zoom_level', [ 'level' => '' ] ) }}">
			<button
				type="button"
				class="btn btn-ghost btn-xs btn-square"
				x-on:click="zoomOut()"
				:disabled="zoom <= minZoom"
				aria-label="{{ __( 'visual-editor::ve.zoom_out' ) }}"
			>
				<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
					<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12h-15" />
				</svg>
			</button>

			<button
				type="button"
				class="btn btn-ghost btn-xs text-xs min-w-[3rem]"
				x-on:click="resetZoom()"
				x-text="zoom + '%'"
				aria-label="{{ __( 'visual-editor::ve.zoom_reset' ) }}"
			></button>

			<button
				type="button"
				class="btn btn-ghost btn-xs btn-square"
				x-on:click="zoomIn()"
				:disabled="zoom >= maxZoom"
				aria-label="{{ __( 'visual-editor::ve.zoom_in' ) }}"
			>
				<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
					<path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
				</svg>
			</button>
		</div>
	@endif
</div>
