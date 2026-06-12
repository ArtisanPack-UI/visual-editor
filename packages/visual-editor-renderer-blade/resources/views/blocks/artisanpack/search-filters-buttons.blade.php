{{--
	artisanpack/search-filters-buttons — submit + reset pair (#502).
--}}
@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$searchLabel = (string) ( $attributes['searchLabel'] ?? __( 'Search' ) );
	$clearLabel  = (string) ( $attributes['clearLabel'] ?? __( 'Clear' ) );
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, [ 'ap-search-filters-buttons' ] ) !!}>
	<input
		type="submit"
		class="ap-search-filters-buttons__submit"
		value="{{ $searchLabel }}"
	/>
	<input
		type="reset"
		class="ap-search-filters-buttons__reset"
		value="{{ $clearLabel }}"
	/>
</div>
