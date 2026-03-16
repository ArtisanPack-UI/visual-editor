@php
	$anchor    = $content['anchor'] ?? null;
	$htmlId    = $content['htmlId'] ?? null;
	$className = $content['className'] ?? '';

	$elementId = veSanitizeHtmlId( $htmlId ?: $anchor );

	$classes = 've-block ve-block-form';
	if ( $className ) {
		$classes .= " {$className}";
	}
@endphp

<div
	class="{{ $classes }}"
	@if ( $elementId ) id="{{ $elementId }}" @endif
>
	<livewire:visual-editor.blocks.form-block-component
		:form-id="$content['formId'] ?? null"
		:display-style="$content['displayStyle'] ?? 'embedded'"
		:submit-button-text="$content['submitButtonText'] ?? ''"
		:submit-button-color="$content['submitButtonColor'] ?? 'primary'"
		:submit-button-size="$content['submitButtonSize'] ?? 'md'"
		:success-message="$content['successMessage'] ?? ''"
		:redirect-url="$content['redirectUrl'] ?? ''"
		:show-labels="$content['showLabels'] ?? true"
		:layout="$content['layout'] ?? 'stacked'"
		:columns="$content['columns'] ?? 2"
		:enable-honeypot="$content['enableHoneypot'] ?? true"
		:prefill-via-url="$content['prefillViaUrl'] ?? false"
		:field-spacing="$styles['fieldSpacing'] ?? '1rem'"
		:custom-class="$content['customClass'] ?? ''"
	/>
</div>
