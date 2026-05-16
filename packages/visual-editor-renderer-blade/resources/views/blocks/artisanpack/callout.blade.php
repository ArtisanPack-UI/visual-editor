@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$validSeverities = [ 'info', 'success', 'warning', 'error' ];
	$validIcons      = [ 'info', 'check', 'warning', 'error', 'lightbulb' ];

	$severity = (string) ( $attributes['severity'] ?? 'info' );

	if ( ! in_array( $severity, $validSeverities, true ) ) {
		$severity = 'info';
	}

	$icon = (string) ( $attributes['icon'] ?? 'info' );

	if ( ! in_array( $icon, $validIcons, true ) ) {
		$icon = 'info';
	}

	$content = (string) ( $attributes['content'] ?? '' );

	$iconPaths = [
		'info'      => 'M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm1 15h-2v-6h2Zm0-8h-2V7h2Z',
		'check'     => 'M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm-1.5 14.5-4-4 1.4-1.4 2.6 2.6 6.6-6.6L17.5 8.5Z',
		'warning'   => 'M12 2 1 21h22Zm1 15h-2v-2h2Zm0-4h-2V9h2Z',
		'error'     => 'M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm5 13.6L15.6 17 12 13.4 8.4 17 7 15.6 10.6 12 7 8.4 8.4 7 12 10.6 15.6 7 17 8.4 13.4 12Z',
		'lightbulb' => 'M9 21h6v-1H9Zm3-19a7 7 0 0 0-4 12.74V17h8v-2.26A7 7 0 0 0 12 2Zm1 12h-2v-2h2Z',
	];

	$baseClasses = [
		'ap-callout',
		'ap-callout--' . $severity,
	];
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses ) !!} data-severity="{{ $severity }}">
	<span class="ap-callout__icon" aria-hidden="true">
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
			<path d="{{ $iconPaths[ $icon ] }}"></path>
		</svg>
	</span>
	<div class="ap-callout__body">{!! $content !!}</div>
</div>
