{{--
 * Drop Zone Component
 *
 * Handles drag-and-drop of blocks and files with visual feedback.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div
	id="{{ $uuid }}"
	x-data="{
		isDragging: false,
		isValid: true,
		insertPosition: null,
		dragCounter: 0,

		handleDragEnter( event ) {
			if ( {{ Js::from( $disabled ) }} ) return;
			event.preventDefault();
			this.dragCounter++;
			this.isDragging = true;
			this.validateDrop( event );
		},

		handleDragOver( event ) {
			if ( {{ Js::from( $disabled ) }} ) return;
			event.preventDefault();
			event.dataTransfer.dropEffect = this.isValid ? 'move' : 'none';

			@if ( $showInsertionLine )
				const rect = this.$el.getBoundingClientRect();
				const midY = rect.top + ( rect.height / 2 );
				this.insertPosition = event.clientY < midY ? 'above' : 'below';
			@endif
		},

		handleDragLeave( event ) {
			event.preventDefault();
			this.dragCounter--;
			if ( this.dragCounter <= 0 ) {
				this.dragCounter = 0;
				this.isDragging = false;
				this.insertPosition = null;
			}
		},

		handleDrop( event ) {
			if ( {{ Js::from( $disabled ) }} ) return;
			event.preventDefault();
			event.stopPropagation();
			this.isDragging = false;
			this.dragCounter = 0;

			if ( ! this.isValid ) {
				this.insertPosition = null;
				return;
			}

			const data = {};

			{{-- Handle file drops --}}
			@if ( $allowFiles )
				if ( event.dataTransfer.files && event.dataTransfer.files.length > 0 ) {
					const files = Array.from( event.dataTransfer.files );
					const acceptTypes = {{ Js::from( $acceptTypes ) }};
					const maxSize = {{ Js::from( $maxFileSize ) }};

					const validFiles = files.filter( ( file ) => {
						if ( acceptTypes.length > 0 ) {
							const typeMatch = acceptTypes.some( ( type ) => {
								if ( type.endsWith( '/*' ) ) {
									return file.type.startsWith( type.replace( '/*', '' ) );
								}
								return file.type === type;
							} );
							if ( ! typeMatch ) return false;
						}
						if ( maxSize && file.size > maxSize * 1024 ) return false;
						return true;
					} );

					if ( validFiles.length > 0 ) {
						this.$dispatch( 've-drop-zone-drop', {
							type: 'file',
							files: validFiles,
							position: this.insertPosition,
							zoneId: '{{ $uuid }}',
						} );

						if ( Alpine.store( 'announcer' ) ) {
							const msg = validFiles.length === 1
								? {!! Js::from( trans_choice( 'visual-editor::ve.files_dropped', 1 ) ) !!}
								: {!! Js::from( trans_choice( 'visual-editor::ve.files_dropped', 2, [ 'count' => '__COUNT__' ] ) ) !!}.replace( '__COUNT__', validFiles.length );
							Alpine.store( 'announcer' ).announce( msg );
						}
					}

					this.insertPosition = null;
					return;
				}
			@endif

			{{-- Handle block drops --}}
			@if ( $allowBlocks )
				const blockData = event.dataTransfer.getData( 'application/ve-block' );
				if ( blockData ) {
					try {
						const block = JSON.parse( blockData );
						this.$dispatch( 've-drop-zone-drop', {
							type: 'block',
							data: block,
							position: this.insertPosition,
							zoneId: '{{ $uuid }}',
						} );

						if ( Alpine.store( 'announcer' ) ) {
							Alpine.store( 'announcer' ).announce( {!! Js::from( __( 'visual-editor::ve.block_dropped' ) ) !!} );
						}
					} catch ( e ) {
						console.error( 'Invalid block data', e );
					}

					this.insertPosition = null;
					return;
				}
			@endif

			{{-- Handle HTML drops --}}
			@if ( $allowHtml )
				const html = event.dataTransfer.getData( 'text/html' );
				if ( html ) {
					this.$dispatch( 've-drop-zone-drop', {
						type: 'html',
						data: html,
						position: this.insertPosition,
						zoneId: '{{ $uuid }}',
					} );

					this.insertPosition = null;
					return;
				}
			@endif

			this.insertPosition = null;
		},

		validateDrop( event ) {
			const types = event.dataTransfer.types;
			this.isValid = false;

			if ( types.includes( 'Files' ) && {{ Js::from( $allowFiles ) }} ) {
				this.isValid = true;
			}
			if ( types.includes( 'application/ve-block' ) && {{ Js::from( $allowBlocks ) }} ) {
				this.isValid = true;
			}
			if ( types.includes( 'text/html' ) && {{ Js::from( $allowHtml ) }} ) {
				this.isValid = true;
			}
		}
	}"
	x-on:dragenter="handleDragEnter( $event )"
	x-on:dragover="handleDragOver( $event )"
	x-on:dragleave="handleDragLeave( $event )"
	x-on:drop="handleDrop( $event )"
	{{ $attributes->merge( [ 'class' => 'relative rounded-lg border-2 border-dashed border-base-300 transition-colors' ] ) }}
	:class="{
		'border-primary bg-primary/5': isDragging && isValid,
		'border-error bg-error/5': isDragging && ! isValid,
		'opacity-50 cursor-not-allowed': {{ Js::from( $disabled ) }},
	}"
	@if ( $label )
		aria-label="{{ $label }}"
		role="region"
	@else
		role="group"
	@endif
>
	{{-- Insertion line indicator --}}
	@if ( $showInsertionLine )
		<div
			x-show="isDragging && isValid && insertPosition === 'above'"
			class="absolute top-0 left-2 right-2 h-0.5 bg-primary rounded -translate-y-px"
			aria-hidden="true"
		></div>
		<div
			x-show="isDragging && isValid && insertPosition === 'below'"
			class="absolute bottom-0 left-2 right-2 h-0.5 bg-primary rounded translate-y-px"
			aria-hidden="true"
		></div>
	@endif

	{{-- Content --}}
	<div class="p-4">
		@if ( $emptyMessage && $slot->isEmpty() )
			<div class="flex flex-col items-center justify-center py-8 text-base-content/40">
				<svg class="w-8 h-8 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
					<path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
				</svg>
				<p class="text-sm">{{ $emptyMessage }}</p>
			</div>
		@else
			{{ $slot }}
		@endif
	</div>
</div>
