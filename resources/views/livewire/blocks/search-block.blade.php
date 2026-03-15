<div>
	<form
		role="search"
		class="ve-block ve-block-search ve-block-search-{{ $displayStyle }}"
		@if ( ! $isEditor )
			action="{{ $actionUrl }}"
			method="GET"
		@endif
		aria-label="{{ $label ?: $placeholder ?: __( 'visual-editor::ve.search' ) }}"
	>
		@if ( $showLabel )
			<label for="{{ $inputId }}" class="ve-block-search-label">
				{{ $label }}
			</label>
		@endif

		<div class="ve-block-search-field-wrapper ve-block-search-field-{{ $buttonPosition }}">
			<input
				type="search"
				id="{{ $inputId }}"
				name="q"
				class="ve-block-search-input"
				placeholder="{{ $placeholder }}"
				aria-label="{{ $label ?: $placeholder ?: __( 'visual-editor::ve.search' ) }}"
				@if ( $isEditor )
					disabled
				@endif
			/>

			@if ( 'none' !== $buttonPosition )
				<button
					type="submit"
					class="ve-block-search-button ve-block-search-button-{{ $buttonPosition }}"
					@if ( $isEditor )
						disabled
					@endif
				>
					<span class="ve-block-search-button-text">{{ $buttonText }}</span>
				</button>
			@endif
		</div>
	</form>
</div>
