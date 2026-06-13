{{--
	artisanpack/search-field — search input shell (#502).

	Reads `_resolvedSearchValue` (the current `?s=` value) when stamped by
	the host pipeline, otherwise falls back to the live request. The
	`name="s"` keeps the form posting back through the host's existing
	search route.
--}}
@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$label       = (string) ( $attributes['label'] ?? 'Search' );
	$placeholder = (string) ( $attributes['placeholder'] ?? 'Search …' );

	$searchValue = '';
	if ( array_key_exists( '_resolvedSearchValue', $attributes ) ) {
		$searchValue = is_scalar( $attributes['_resolvedSearchValue'] )
			? (string) $attributes['_resolvedSearchValue']
			: '';
	} elseif ( function_exists( 'request' ) ) {
		$rawValue    = request()->query( 's', '' );
		$searchValue = is_scalar( $rawValue ) ? (string) $rawValue : '';
	}

	// Hash via json_encode rather than serialize: `serialize()` throws
	// on objects (closures, resources) that a host might smuggle into
	// the attributes envelope. `json_encode` drops what it can't encode
	// and still yields a stable suffix for the id.
	//
	// `$renderIndex` is the per-tree visit counter handed in by
	// {@see BlockRenderer::renderStatic} — it disambiguates two
	// search-field instances that carry byte-identical attributes
	// (the actual a11y concern, when label `for=` references would
	// otherwise collide), while the hash keeps the id stable for the
	// common single-instance case.
	$attrHash = substr( md5( (string) json_encode( $attributes ) ), 0, 8 );
	$inputId  = sprintf( 'ap-search-field-%d-%s', $renderIndex ?? 0, $attrHash );
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, [ 'ap-search-field' ] ) !!}>
	<label class="ap-search-field__label" for="{{ $inputId }}">{{ $label }}</label>
	<input
		type="search"
		class="ap-search-field__input"
		id="{{ $inputId }}"
		name="s"
		value="{{ $searchValue }}"
		placeholder="{{ $placeholder }}"
	/>
</div>
