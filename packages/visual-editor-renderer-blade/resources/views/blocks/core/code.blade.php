@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\CodeLanguage;

	$content       = (string) ( $attributes['content'] ?? '' );
	$languageClass = CodeLanguage::className( $attributes['language'] ?? null );
	$codeAttribute = '' === $languageClass ? '' : ' class="' . e( $languageClass ) . '"';
@endphp
<pre{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-code' ] ) !!}><code{!! $codeAttribute !!}>{!! $content !!}</code></pre>
