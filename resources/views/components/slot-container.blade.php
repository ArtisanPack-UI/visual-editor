{{--
 * Slot Container Component
 *
 * A named slot that accepts content from Fill components.
 * Uses an Alpine.js store for cross-component communication.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div
	id="{{ $uuid }}"
	x-data
	x-init="
		if ( ! Alpine.store( 'slotFills' ) ) {
			Alpine.store( 'slotFills', {
				fills: {},

				register( slotName, fillId, priority ) {
					if ( ! this.fills[ slotName ] ) {
						this.fills[ slotName ] = [];
					}
					const existing = this.fills[ slotName ].findIndex( ( f ) => f.id === fillId );
					if ( existing >= 0 ) {
						this.fills[ slotName ][ existing ].priority = priority;
					} else {
						this.fills[ slotName ].push( { id: fillId, priority: priority || 10 } );
					}
					this.fills[ slotName ].sort( ( a, b ) => a.priority - b.priority );
				},

				unregister( slotName, fillId ) {
					if ( ! this.fills[ slotName ] ) return;
					this.fills[ slotName ] = this.fills[ slotName ].filter( ( f ) => f.id !== fillId );
				},

				getFills( slotName ) {
					return this.fills[ slotName ] || [];
				},

				hasFills( slotName ) {
					return ( this.fills[ slotName ] || [] ).length > 0;
				}
			} );
		}
	"
	data-ve-slot="{{ $name }}"
	{{ $attributes->merge( [ 'class' => '' ] ) }}
>
	{{-- Teleport target for fills --}}
	<div data-ve-slot-target="{{ $name }}">
		{{-- Fills teleport their content here --}}
	</div>

	{{-- Default content shown when no fills are registered --}}
	<template x-if="! $store.slotFills?.hasFills( '{{ $name }}' )">
		<div>
			{{ $slot }}
		</div>
	</template>
</div>
