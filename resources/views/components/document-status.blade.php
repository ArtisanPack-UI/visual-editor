{{--
 * Document Status Component
 *
 * Status selector for the Document tab with a conditional
 * date/time picker when "Scheduled" is selected.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

@php
	$statuses = [
		'draft'     => __( 'visual-editor::ve.status_draft' ),
		'published' => __( 'visual-editor::ve.status_published' ),
		'scheduled' => __( 'visual-editor::ve.status_scheduled' ),
		'pending'   => __( 'visual-editor::ve.status_pending' ),
	];

	$statuses = function_exists( 'applyFilters' )
		? applyFilters( 'ap.visualEditor.document.statuses', $statuses )
		: $statuses;
@endphp

<div
	id="{{ $uuid }}"
	x-data="{
		status: {{ Js::from( $status ) }},
		scheduledDate: {{ Js::from( $scheduledDate ) }},

		setStatus( newStatus ) {
			this.status = newStatus;
			if ( Alpine.store( 'editor' ) ) {
				Alpine.store( 'editor' ).setDocumentStatus( newStatus );
			}
		},

		setScheduledDate( date ) {
			this.scheduledDate = date;
			if ( Alpine.store( 'editor' ) ) {
				Alpine.store( 'editor' ).setScheduledDate( date );
			}
		},
	}"
	{{ $attributes->merge( [ 'class' => 'space-y-3' ] ) }}
>
	{{-- Status select --}}
	<div>
		<label class="text-xs font-medium text-base-content/60">
			{{ __( 'visual-editor::ve.document_status' ) }}
		</label>
		<select
			class="select select-sm w-full"
			x-model="status"
			x-on:change="setStatus( $event.target.value )"
		>
			@foreach ( $statuses as $value => $label )
				<option value="{{ $value }}">{{ $label }}</option>
			@endforeach
		</select>
	</div>

	{{-- Conditional date/time picker for scheduled status --}}
	<div x-show="'scheduled' === status" x-transition>
		<label class="text-xs font-medium text-base-content/60">
			{{ __( 'visual-editor::ve.schedule_date' ) }}
		</label>
		<input
			type="datetime-local"
			class="input input-sm w-full"
			x-model="scheduledDate"
			x-on:change="setScheduledDate( $event.target.value )"
		/>
		<p class="text-xs text-base-content/40 mt-1">
			{{ __( 'visual-editor::ve.schedule_date_hint' ) }}
		</p>
	</div>
</div>
