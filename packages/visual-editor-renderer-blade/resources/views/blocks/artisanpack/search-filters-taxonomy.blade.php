{{--
	artisanpack/search-filters-taxonomy — labelled `<select>` (#502).

	Reads `_resolvedTerms` (a list of `{ slug, name }` records) stamped
	by the host pipeline and pre-selects the term named in the current
	request's `?{taxonomy}=` parameter via `_resolvedSelectedTerm`. When
	the host has not stamped a term list we fall back to a direct
	cms-framework lookup so the dropdown still populates against
	`category` + `post_tag` (the two taxonomies cms-framework ships).
	Unknown taxonomies render with just the placeholder option so the
	form stays usable.
--}}
@php
	use ArtisanPackUI\CMSFramework\Modules\Blog\Models\PostCategory;
	use ArtisanPackUI\CMSFramework\Modules\Blog\Models\PostTag;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$label        = (string) ( $attributes['label'] ?? 'Choose' );
	$taxonomy     = strtolower( (string) ( $attributes['taxonomy'] ?? 'category' ) );
	$taxonomy     = preg_replace( '/[^a-z0-9_-]/', '', $taxonomy );
	$taxonomyName = (string) ( $attributes['taxonomyName'] ?? 'Category' );

	$termsRaw = $attributes['_resolvedTerms'] ?? null;
	$terms    = [];

	if ( is_array( $termsRaw ) ) {
		foreach ( $termsRaw as $term ) {
			if ( ! is_array( $term ) ) {
				continue;
			}
			$slug = isset( $term['slug'] ) ? (string) $term['slug'] : '';
			$name = isset( $term['name'] ) ? (string) $term['name'] : '';
			if ( '' === $slug || '' === $name ) {
				continue;
			}
			$terms[] = [ 'slug' => $slug, 'name' => $name ];
		}
	} elseif ( '' !== $taxonomy ) {
		// Fallback: pull terms directly from cms-framework when the host
		// has not stamped a `_resolvedTerms` envelope. Quiet about errors
		// so a host that has not installed cms-framework still gets the
		// placeholder shell instead of a 500.
		$taxonomyModel = match ( $taxonomy ) {
			'category', 'categories', 'post_category' => PostCategory::class,
			'tag', 'tags', 'post_tag'                 => PostTag::class,
			default                                   => null,
		};

		if ( null !== $taxonomyModel && class_exists( $taxonomyModel ) ) {
			try {
				$terms = $taxonomyModel::query()
					->orderBy( 'name' )
					->get( [ 'slug', 'name' ] )
					->map( static fn ( $row ): array => [
						'slug' => (string) $row->slug,
						'name' => (string) $row->name,
					] )
					->filter( static fn ( array $t ): bool => '' !== $t['slug'] && '' !== $t['name'] )
					->values()
					->all();
			} catch ( \Throwable $e ) {
				report( $e );
				$terms = [];
			}
		}
	}

	$selectedTerm = '';
	if ( array_key_exists( '_resolvedSelectedTerm', $attributes ) ) {
		$selectedTerm = is_scalar( $attributes['_resolvedSelectedTerm'] )
			? (string) $attributes['_resolvedSelectedTerm']
			: '';
	} elseif ( '' !== $taxonomy && function_exists( 'request' ) ) {
		$raw          = request()->query( $taxonomy, '' );
		$selectedTerm = is_scalar( $raw ) ? (string) $raw : '';
	}

	$selectId = 'ap-search-filters-taxonomy-' . $taxonomy;
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, [ 'ap-search-filters-taxonomy' ] ) !!}>
	<label class="ap-search-filters-taxonomy__label" for="{{ $selectId }}">{{ $label }}</label>
	<select
		class="ap-search-filters-taxonomy__select"
		id="{{ $selectId }}"
		name="{{ $taxonomy }}"
	>
		<option value="">{{ __( 'Select a' ) }} {{ $taxonomyName }}</option>
		@foreach ( $terms as $term )
			@php $isSelected = $term['slug'] === $selectedTerm; @endphp
			<option value="{{ $term['slug'] }}"{!! $isSelected ? ' selected' : '' !!}>{{ $term['name'] }}</option>
		@endforeach
	</select>
</div>
