@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$info = ( isset( $attributes['_resolvedBusinessInfo'] ) && is_array( $attributes['_resolvedBusinessInfo'] ) )
		? $attributes['_resolvedBusinessInfo']
		: [];

	$showSpecialHours = ! array_key_exists( 'showSpecialHours', $attributes ) || false !== $attributes['showSpecialHours'];

	$hours = ( isset( $info['hours'] ) && is_array( $info['hours'] ) ) ? $info['hours'] : [];

	$specialHours = ( isset( $info['specialHours'] ) && is_array( $info['specialHours'] ) ) ? $info['specialHours'] : [];

	$dayOrder = [ 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' ];
	$dayLabels = [
		'monday'    => __( 'Monday' ),
		'tuesday'   => __( 'Tuesday' ),
		'wednesday' => __( 'Wednesday' ),
		'thursday'  => __( 'Thursday' ),
		'friday'    => __( 'Friday' ),
		'saturday'  => __( 'Saturday' ),
		'sunday'    => __( 'Sunday' ),
	];

	$formatHours = function ( $entry ) {
		if ( ! is_array( $entry ) ) {
			return '';
		}

		if ( ! empty( $entry['closed'] ) ) {
			return __( 'Closed' );
		}

		// Support split shifts — an entry may be a list of ranges rather
		// than a single { open, close } pair.
		if ( isset( $entry[0] ) && is_array( $entry[0] ) ) {
			$parts = [];
			foreach ( $entry as $range ) {
				if ( is_array( $range ) && isset( $range['open'], $range['close'] ) ) {
					$parts[] = sprintf( '%s – %s', (string) $range['open'], (string) $range['close'] );
				}
			}
			return implode( ', ', $parts );
		}

		if ( isset( $entry['open'], $entry['close'] ) ) {
			return sprintf( '%s – %s', (string) $entry['open'], (string) $entry['close'] );
		}

		return '';
	};

	$baseClasses = [ 'ap-business-hours' ];
	$ariaLabel   = (string) ( $attributes['ariaLabel'] ?? __( 'Business hours' ) );
@endphp
<section{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses ) !!} aria-label="{{ $ariaLabel }}">
	@if ( empty( $hours ) && ( ! $showSpecialHours || empty( $specialHours ) ) )
		{{-- SSR-safe: render an empty wrapper rather than nothing so themes can style the empty state. --}}
	@else
		<table class="ap-business-hours__table">
			<tbody>
				@foreach ( $dayOrder as $day )
					@if ( array_key_exists( $day, $hours ) )
						<tr class="ap-business-hours__row">
							<th scope="row" class="ap-business-hours__day">{{ $dayLabels[ $day ] }}</th>
							<td class="ap-business-hours__time">{{ $formatHours( $hours[ $day ] ) }}</td>
						</tr>
					@endif
				@endforeach
				@if ( $showSpecialHours && ! empty( $specialHours ) )
					@foreach ( $specialHours as $special )
						@php
							if ( ! is_array( $special ) ) {
								continue;
							}
							$label  = isset( $special['label'] ) && is_string( $special['label'] ) ? $special['label'] : (string) ( $special['date'] ?? '' );
							$closed = ! empty( $special['closed'] );
							$hoursLabel = $closed
								? __( 'Closed' )
								: ( isset( $special['open'], $special['close'] )
									? sprintf( '%s – %s', (string) $special['open'], (string) $special['close'] )
									: '' );
						@endphp
						<tr class="ap-business-hours__row ap-business-hours__row--special">
							<th scope="row" class="ap-business-hours__day">{{ $label }}</th>
							<td class="ap-business-hours__time">{{ $hoursLabel }}</td>
						</tr>
					@endforeach
				@endif
			</tbody>
		</table>
	@endif
</section>
