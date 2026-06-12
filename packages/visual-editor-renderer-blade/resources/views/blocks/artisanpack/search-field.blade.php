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
	$inputId = 'ap-search-field-' . substr( md5( (string) json_encode( $attributes ) ), 0, 8 );
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
