@php
	use ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$validInnerLayouts = [ 'normal', 'equal', 'center', 'bottom', 'last-bottom' ];

	$clampSpan = static function ( $value, int $fallback = 1 ): int {
		$int = is_numeric( $value ) ? (int) $value : $fallback;
		if ( $int < 1 ) {
			return 1;
		}
		if ( $int > 12 ) {
			return 12;
		}
		return $int;
	};

	$innerLayoutRaw = is_string( $attributes['innerLayout'] ?? null )
		? $attributes['innerLayout']
		: 'normal';
	$innerLayout = in_array( $innerLayoutRaw, $validInnerLayouts, true )
		? $innerLayoutRaw
		: 'normal';

	$baseColumnSpan = $clampSpan( $attributes['gridColumnSpan'] ?? null, 1 );
	$baseRowSpan    = $clampSpan( $attributes['gridRowSpan'] ?? null, 1 );

	$spanClasses = [
		'ap-grid-item-span-' . $baseColumnSpan . '-base-columns',
		'ap-grid-item-span-' . $baseRowSpan . '-base-row',
	];

	$registry = app( BreakpointRegistry::class );

	$collectOverrides = static function ( $overrides, string $suffix, int $base, callable $clamp ) use ( $registry ): array {
		$classes = [];

		if ( ! is_array( $overrides ) ) {
			return $classes;
		}

		foreach ( $overrides as $bp => $value ) {
			if ( BreakpointRegistry::BASE_KEY === $bp ) {
				continue;
			}
			if ( ! is_numeric( $value ) ) {
				continue;
			}
			if ( null === $registry->get( (string) $bp ) ) {
				continue;
			}

			$classes[] = 'ap-grid-item-span-' . $clamp( $value, $base ) . '-' . $bp . '-' . $suffix;
		}

		return $classes;
	};

	$spanClasses = array_merge(
		$spanClasses,
		$collectOverrides( $attributes['responsive']['gridColumnSpan'] ?? null, 'columns', $baseColumnSpan, $clampSpan ),
		$collectOverrides( $attributes['responsive']['gridRowSpan'] ?? null, 'row', $baseRowSpan, $clampSpan ),
	);

	$baseClasses = array_merge(
		[
			'ap-grid-item',
			'ap-grid-item-layout-' . $innerLayout,
		],
		$spanClasses,
	);
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses ) !!}>
	{!! $innerBlocksHtml !!}
</div>
