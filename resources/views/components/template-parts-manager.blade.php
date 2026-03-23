{{--
 * Template Parts Manager Component
 *
 * Sidebar panel for managing template part assignments per area.
 * Supports assigning existing parts via dropdown, creating new parts,
 * and clearing assignments.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

@php
	$initialAssignments = $assignments;
	$initialPartsByArea = $partsByArea;
	$initialAreaLabels  = $areaLabels;
@endphp

<div
	id="{{ $uuid }}"
	x-data="{
		assignments: {{ Js::from( $initialAssignments ) }},
		partsByArea: {{ Js::from( $initialPartsByArea ) }},
		areaLabels: {{ Js::from( $initialAreaLabels ) }},
		areas: [ 'header', 'footer', 'sidebar', 'custom' ],
		clearLabel: {{ Js::from( __( 'visual-editor::ve.template_part_clear' ) ) }},
		creating: null,
		newPartName: '',

		_cleanupListener: null,

		init() {
			this._cleanupListener = Livewire.on( 've-template-part-created', ( data ) => {
				const payload = Array.isArray( data ) ? data[0] : data;
				const area = payload.area;
				const part = payload.part;
				if ( ! area || ! part ) return;

				if ( ! this.partsByArea[ area ] ) {
					this.partsByArea[ area ] = [];
				}
				this.partsByArea[ area ].push( part );
				this.assignments[ area ] = part.id;
				this._dispatchChange();
			} );
		},

		destroy() {
			if ( typeof this._cleanupListener === 'function' ) {
				this._cleanupListener();
			}
		},

		getAssignedPartName( area ) {
			const partId = this.assignments[ area ];
			if ( ! partId ) return null;
			const parts = this.partsByArea[ area ] || [];
			const part  = parts.find( p => p.id === partId );
			return part ? part.name : null;
		},

		assignPart( area, partId ) {
			this.assignments[ area ] = partId ? parseInt( partId, 10 ) : null;
			this._dispatchChange();
		},

		clearAssignment( area ) {
			this.assignments[ area ] = null;
			this._dispatchChange();
		},

		startCreate( area ) {
			this.creating    = area;
			this.newPartName = '';
		},

		cancelCreate() {
			this.creating    = null;
			this.newPartName = '';
		},

		confirmCreate() {
			if ( ! this.creating || ! this.newPartName.trim() ) return;
			const area = this.creating;
			const name = this.newPartName.trim();

			{{-- Dispatch event for Livewire to handle --}}
			this.$dispatch( 've-template-part-create', {
				area: area,
				name: name,
			} );

			this.creating    = null;
			this.newPartName = '';
		},

		_getStore() {
			return Alpine.store( 'editor' ) || Alpine.store( 'globalStyles' ) || null;
		},

		_dispatchChange() {
			const store = this._getStore();
			if ( store ) {
				if ( typeof store.markDirty === 'function' ) store.markDirty();
				if ( typeof store._dispatchChange === 'function' ) store._dispatchChange();
			}

			document.dispatchEvent( new CustomEvent( 've-template-parts-change', {
				detail: { assignments: JSON.parse( JSON.stringify( this.assignments ) ) },
				bubbles: true,
			} ) );
		},
	}"
	{{ $attributes->merge( [ 'class' => 'flex flex-col gap-4' ] ) }}
>
	<h3 class="text-sm font-semibold text-base-content">
		{{ __( 'visual-editor::ve.template_parts_title' ) }}
	</h3>

	<template x-for="area in areas" :key="area">
		<div class="flex flex-col gap-2 rounded-lg border border-base-300 p-3">
			{{-- Area label --}}
			<div class="text-xs font-medium text-base-content/60" x-text="areaLabels[ area ]"></div>

			{{-- Current assignment or empty state --}}
			<div x-show="creating !== area">
				<div
					class="flex items-center gap-2"
					x-data="{ selectedPart: String( assignments[ area ] || '' ) }"
					x-effect="assignments[ area ] = selectedPart ? parseInt( selectedPart, 10 ) : null"
				>
					<select
						class="select select-sm select-bordered flex-1"
						x-model="selectedPart"
						x-on:change="$nextTick( () => _dispatchChange() )"
					>
						<option value="">{{ __( 'visual-editor::ve.template_part_select_placeholder' ) }}</option>
						<template x-for="part in ( partsByArea[ area ] || [] )" :key="part.id">
							<option :value="String( part.id )" x-text="part.name"></option>
						</template>
					</select>

					{{-- Clear button --}}
					<button
						type="button"
						x-show="assignments[ area ]"
						x-on:click="clearAssignment( area )"
						class="btn btn-ghost btn-xs btn-square text-base-content/40 hover:text-error"
						:aria-label="clearLabel"
					>
						<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
						</svg>
					</button>
				</div>

				{{-- Action buttons --}}
				<div class="flex items-center gap-2 mt-2">
					<button
						type="button"
						x-on:click="startCreate( area )"
						class="btn btn-ghost btn-xs text-base-content/50 hover:text-base-content/80"
					>
						<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
						</svg>
						{{ __( 'visual-editor::ve.template_part_create_new' ) }}
					</button>

					<template x-if="assignments[ area ]">
						<button
							type="button"
							class="btn btn-ghost btn-xs text-base-content/50 hover:text-base-content/80"
							x-on:click="$dispatch( 've-template-part-edit', { area: area, partId: assignments[ area ] } )"
						>
							{{ __( 'visual-editor::ve.template_part_edit' ) }}
						</button>
					</template>
				</div>
			</div>

			{{-- Create new part inline --}}
			<div x-show="creating === area" x-cloak class="flex flex-col gap-2">
				<input
					type="text"
					x-model="newPartName"
					class="input input-sm input-bordered w-full"
					placeholder="{{ __( 'visual-editor::ve.template_part_name_placeholder' ) }}"
					x-on:keydown.enter.prevent="confirmCreate()"
					x-on:keydown.escape.prevent="cancelCreate()"
				/>
				<div class="flex justify-end gap-2">
					<button
						type="button"
						x-on:click="cancelCreate()"
						class="btn btn-ghost btn-xs"
					>
						{{ __( 'visual-editor::ve.cancel' ) }}
					</button>
					<button
						type="button"
						x-on:click="confirmCreate()"
						class="btn btn-primary btn-xs"
					>
						{{ __( 'visual-editor::ve.save' ) }}
					</button>
				</div>
			</div>
		</div>
	</template>
</div>
