@php
	$placeholder    = $content['placeholder'] ?? __( 'visual-editor::ve.search_placeholder' );
	$buttonText     = $content['buttonText'] ?? __( 'visual-editor::ve.search' );
	$buttonPosition = $content['buttonPosition'] ?? 'outside';
	$showLabel      = $content['showLabel'] ?? true;
	$label          = $content['label'] ?? __( 'visual-editor::ve.search' );
	$displayStyle   = $content['displayStyle'] ?? 'inline';

	$isInline  = 'inline' === $displayStyle;
	$isInside  = 'inside' === $buttonPosition;
	$hasButton = 'none' !== $buttonPosition;
@endphp

<div class="ve-block ve-block-search ve-block-editing ve-block-dynamic-preview">
	<div role="search" aria-label="{{ $label }}" style="max-width: 600px;">
		@if ( $showLabel )
			<label style="display: block; font-weight: 500; margin-bottom: 0.5rem; font-size: 0.9em;">{{ $label }}</label>
		@endif

		<div style="{{ $isInline ? 'display: flex; align-items: stretch;' : 'display: flex; flex-direction: column; gap: 0.5rem;' }}">
			<div style="position: relative; flex: 1;">
				<input
					type="search"
					placeholder="{{ $placeholder }}"
					disabled
					style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid #d1d5db; border-radius: {{ $isInside && $hasButton ? '6px' : ( $isInline && $hasButton ? '6px 0 0 6px' : '6px' ) }}; font-size: 0.95em; background: #fff; color: #374151; outline: none; box-sizing: border-box;"
				/>
				@if ( $isInside && $hasButton )
					<button
						type="button"
						disabled
						style="position: absolute; right: 4px; top: 50%; transform: translateY(-50%); padding: 0.375rem 0.75rem; background: #2563eb; color: #fff; border: none; border-radius: 4px; font-size: 0.85em; cursor: default;"
					>{{ $buttonText }}</button>
				@endif
			</div>
			@if ( ! $isInside && $hasButton )
				<button
					type="button"
					disabled
					style="padding: 0.625rem 1.25rem; background: #2563eb; color: #fff; border: none; border-radius: {{ $isInline ? '0 6px 6px 0' : '6px' }}; font-size: 0.95em; cursor: default; white-space: nowrap;"
				>{{ $buttonText }}</button>
			@endif
		</div>
	</div>
</div>
