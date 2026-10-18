@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\SearchForm;

	$label       = (string) ( $attributes['label'] ?? 'Search' );
	$showLabel   = ! isset( $attributes['showLabel'] ) || ! empty( $attributes['showLabel'] );
	$placeholder = (string) ( $attributes['placeholder'] ?? '' );
	$buttonText  = (string) ( $attributes['buttonText'] ?? 'Search' );
	$useIcon     = ! empty( $attributes['buttonUseIcon'] );
	$queryName   = (string) ( $attributes['query']['name'] ?? 's' );

	// Where the form submits and the term it shows (#961): see SearchForm.
	$action      = SearchForm::action( $attributes );
	$searchValue = SearchForm::searchValue( $attributes, $queryName );

	$ariaLabel = '' !== trim( $buttonText )
		? $buttonText
		: ( '' !== trim( $label ) ? $label : 'Search' );

	$inputId = sprintf( 'wp-block-search-input-%s', substr( md5( $blockName . serialize( $attributes ) ), 0, 8 ) );

	// Button position classes follow WordPress's render_block_core_search():
	// `wp-block-search__{button-outside|button-inside|no-button|button-only}`,
	// plus `__icon-button` / `__text-button` whenever a button renders.
	// WordPress also adds `__searchfield-hidden` to button-only and expands
	// the field with its interactivity script; no such script ships here, so
	// the field stays visible and the form works without JavaScript.
	$buttonPosition = SearchForm::buttonPosition( $attributes );
	$hasButton      = SearchForm::BUTTON_NONE !== $buttonPosition;

	$baseClasses = [ 'wp-block-search', 'wp-block-search__' . $buttonPosition ];

	if ( $hasButton ) {
		$baseClasses[] = $useIcon ? 'wp-block-search__icon-button' : 'wp-block-search__text-button';
	}

	// A hidden label stays in the markup for screen readers: it is the
	// input's only accessible name.
	$labelClasses = $showLabel ? 'wp-block-search__label' : 'wp-block-search__label screen-reader-text';

	$buttonClasses = [ 'wp-block-search__button' ];

	if ( $useIcon ) {
		$buttonClasses[] = 'has-icon';
	}

	$inputPlaceholder = '' !== $placeholder
		? sprintf( ' placeholder="%s"', e( $placeholder ) )
		: '';
@endphp
<form role="search" method="get" action="{{ $action }}"{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses ) !!}>
	<label class="{{ $labelClasses }}" for="{{ $inputId }}">{{ $label }}</label>
	<div class="wp-block-search__inside-wrapper">
		<input id="{{ $inputId }}" type="search" class="wp-block-search__input" name="{{ $queryName }}" value="{{ $searchValue }}"{!! $inputPlaceholder !!}/>
		@if ( ! $hasButton )
			{{-- no-button: Enter submits the form. --}}
		@elseif ( $useIcon )
<button type="submit" class="{{ implode( ' ', $buttonClasses ) }}" aria-label="{{ $ariaLabel }}"><svg class="wp-block-search__button-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="M13.5 6C10.5 6 8 8.5 8 11.5c0 1.1.3 2.1.9 3l-3.4 3 1 1.1 3.4-3c1 .9 2.2 1.4 3.6 1.4 3 0 5.5-2.5 5.5-5.5C19 8.5 16.5 6 13.5 6zm0 9.5c-2.2 0-4-1.8-4-4s1.8-4 4-4 4 1.8 4 4-1.8 4-4 4z"></path></svg></button>
		@else
			<button type="submit" class="{{ implode( ' ', $buttonClasses ) }}">{{ $buttonText }}</button>
		@endif
	</div>
</form>
