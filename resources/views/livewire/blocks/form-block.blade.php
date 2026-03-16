<div>
	@if ( ! $formsInstalled )
		<div class="ve-block-form-notice ve-block-form-notice-warning" role="alert">
			<p>{{ __( 'visual-editor::ve.form_package_required' ) }}</p>
			<p>{{ __( 'visual-editor::ve.form_package_required_hint' ) }}</p>
		</div>
	@elseif ( ! $form )
		<div class="ve-block-form-notice ve-block-form-notice-info" role="alert">
			<p>{{ __( 'visual-editor::ve.form_select_form' ) }}</p>
		</div>
	@elseif ( $submitted && ! $redirectUrl )
		<div
			class="ve-block-form-success"
			role="status"
			aria-live="polite"
		>
			<p>{{ $successMessage ?: $form->success_message ?: __( 'visual-editor::ve.form_submission_success' ) }}</p>
		</div>
	@else
		@php
			$isGrid   = 'grid' === $layout;
			$isInline = 'inline' === $layout;
			$isModal  = 'modal' === $displayStyle;
			$isSlide  = 'slide-over' === $displayStyle;
		@endphp

		@if ( $isModal || $isSlide )
			<button
				type="button"
				wire:click="openOverlay"
				class="ve-block-form-trigger btn btn-{{ $submitButtonColor }} btn-{{ $submitButtonSize }}"
			>
				{{ $submitText }}
			</button>
		@endif

		<div
			@if ( $isModal || $isSlide )
				x-data="{ open: @entangle('showOverlay') }"
				x-show="open"
				x-cloak
				x-trap.noscroll="open"
				role="dialog"
				aria-modal="true"
				aria-label="{{ $form->name }}"
				class="ve-block-form-overlay ve-block-form-overlay-{{ $displayStyle }}"
				@keydown.escape.window="open = false"
			@endif
		>
			@if ( $isModal || $isSlide )
				<div
					class="ve-block-form-overlay-backdrop"
					x-on:click="open = false"
				></div>
				<div class="ve-block-form-overlay-content ve-block-form-overlay-content-{{ $displayStyle }}">
					<div class="ve-block-form-overlay-header">
						<h2>{{ $form->name }}</h2>
						<button
							type="button"
							wire:click="closeOverlay"
							aria-label="{{ __( 'visual-editor::ve.close' ) }}"
							class="ve-block-form-overlay-close"
						>&times;</button>
					</div>
			@endif

			<form
				wire:submit{{ $useAjax ? '.prevent' : '' }}="submitForm"
				id="{{ $formElementId }}"
				class="ve-block-form-form {{ $customClass }}"
				aria-label="{{ $form->name }}"
				novalidate
			>
				@csrf

				@if ( $errors->has( 'formData' ) )
					<div class="ve-block-form-error-banner" role="alert">
						<p>{{ $errors->first( 'formData' ) }}</p>
					</div>
				@endif

				@if ( $enableHoneypot )
					<div aria-hidden="true" style="position: absolute; left: -9999px; opacity: 0; height: 0; overflow: hidden;">
						<label for="{{ $formElementId }}-hp">{{ __( 'visual-editor::ve.form_honeypot_label' ) }}</label>
						<input type="text" id="{{ $formElementId }}-hp" wire:model="honeypot" tabindex="-1" autocomplete="off" />
					</div>
				@endif

				<div
					class="ve-block-form-fields"
					style="
						@if ( $isGrid )
							display: grid; grid-template-columns: repeat({{ $columns }}, 1fr); gap: {{ $fieldSpacing }};
						@elseif ( $isInline )
							display: flex; flex-wrap: wrap; gap: {{ $fieldSpacing }}; align-items: flex-end;
						@else
							display: flex; flex-direction: column; gap: {{ $fieldSpacing }};
						@endif
					"
				>
					@foreach ( $fields as $field )
						@if ( $field->isLayoutField() )
							@if ( 'heading' === $field->type )
								<div class="ve-block-form-layout-field" style="{{ $isGrid ? 'grid-column: 1 / -1;' : ( $isInline ? 'flex-basis: 100%;' : '' ) }}">
									<h3>{{ $field->label }}</h3>
								</div>
							@elseif ( 'divider' === $field->type )
								<div class="ve-block-form-layout-field" style="{{ $isGrid ? 'grid-column: 1 / -1;' : ( $isInline ? 'flex-basis: 100%;' : '' ) }}">
									<hr />
								</div>
							@elseif ( 'paragraph' === $field->type )
								<div class="ve-block-form-layout-field" style="{{ $isGrid ? 'grid-column: 1 / -1;' : ( $isInline ? 'flex-basis: 100%;' : '' ) }}">
									<p>{{ $field->help_text ?: $field->label }}</p>
								</div>
							@endif
						@else
							@php
								$widthStyle = '';
								if ( $isGrid ) {
									$widthStyle = match ( $field->width ) {
										'half'       => 'grid-column: span 1;',
										'third'      => 'grid-column: span 1;',
										'two-thirds' => 'grid-column: span 2;',
										default      => 'grid-column: 1 / -1;',
									};
								} elseif ( $isInline ) {
									$widthStyle = match ( $field->width ) {
										'half'       => 'flex: 0 0 calc(50% - ' . $fieldSpacing . ');',
										'third'      => 'flex: 0 0 calc(33.333% - ' . $fieldSpacing . ');',
										'two-thirds' => 'flex: 0 0 calc(66.666% - ' . $fieldSpacing . ');',
										default      => 'flex: 1 1 100%;',
									};
								}
								$fieldId   = $formElementId . '-' . $field->name;
								$errorKey  = "formData.{$field->name}";
								$hasError  = $errors->has( $errorKey );
								$errorMsg  = $errors->first( $errorKey );
							@endphp
							<div
								class="ve-block-form-field {{ $hasError ? 've-block-form-field-error' : '' }}"
								style="{{ $widthStyle }}"
							>
								@if ( $showLabels && $field->label )
									<label for="{{ $fieldId }}" class="ve-block-form-label">
										{{ $field->label }}
										@if ( $field->is_required )
											<span class="ve-block-form-required" aria-hidden="true">*</span>
										@endif
									</label>
								@endif

								@if ( in_array( $field->type, [ 'text', 'email', 'phone', 'number', 'url', 'date', 'time' ], true ) )
									<input
										type="{{ $field->type }}"
										id="{{ $fieldId }}"
										name="{{ $field->name }}"
										wire:model="formData.{{ $field->name }}"
										placeholder="{{ $field->placeholder }}"
										class="ve-block-form-input {{ $hasError ? 've-block-form-input-error' : '' }}"
										@if ( $field->is_required ) required aria-required="true" @endif
										@if ( $hasError ) aria-invalid="true" aria-describedby="{{ $fieldId }}-error" @endif
										@if ( $field->help_text && ! $hasError ) aria-describedby="{{ $fieldId }}-help" @endif
									/>
								@elseif ( 'textarea' === $field->type )
									<textarea
										id="{{ $fieldId }}"
										name="{{ $field->name }}"
										wire:model="formData.{{ $field->name }}"
										placeholder="{{ $field->placeholder }}"
										rows="{{ $field->getConfig( 'rows', 3 ) }}"
										class="ve-block-form-textarea {{ $hasError ? 've-block-form-input-error' : '' }}"
										@if ( $field->is_required ) required aria-required="true" @endif
										@if ( $hasError ) aria-invalid="true" aria-describedby="{{ $fieldId }}-error" @endif
										@if ( $field->help_text && ! $hasError ) aria-describedby="{{ $fieldId }}-help" @endif
									></textarea>
								@elseif ( in_array( $field->type, [ 'select', 'select_multiple' ], true ) )
									<select
										id="{{ $fieldId }}"
										name="{{ $field->name }}"
										wire:model="formData.{{ $field->name }}"
										class="ve-block-form-select {{ $hasError ? 've-block-form-input-error' : '' }}"
										@if ( 'select_multiple' === $field->type ) multiple @endif
										@if ( $field->is_required ) required aria-required="true" @endif
										@if ( $hasError ) aria-invalid="true" aria-describedby="{{ $fieldId }}-error" @endif
									>
										@if ( $field->placeholder )
											<option value="">{{ $field->placeholder }}</option>
										@endif
										@foreach ( $field->options ?? [] as $option )
											<option value="{{ $option['value'] ?? $option }}">{{ $option['label'] ?? $option }}</option>
										@endforeach
									</select>
								@elseif ( 'checkbox' === $field->type )
									<div class="ve-block-form-checkbox-wrapper">
										<input
											type="checkbox"
											id="{{ $fieldId }}"
											name="{{ $field->name }}"
											wire:model="formData.{{ $field->name }}"
											class="ve-block-form-checkbox"
											@if ( $field->is_required ) required aria-required="true" @endif
											@if ( $hasError ) aria-invalid="true" aria-describedby="{{ $fieldId }}-error" @endif
										/>
										@if ( $field->help_text )
											<span class="ve-block-form-checkbox-text">{{ $field->help_text }}</span>
										@endif
									</div>
								@elseif ( 'checkbox_group' === $field->type )
									<fieldset
										class="ve-block-form-fieldset"
										@if ( $hasError ) aria-describedby="{{ $fieldId }}-error" @endif
									>
										@foreach ( $field->options ?? [] as $index => $option )
											<div class="ve-block-form-checkbox-wrapper">
												<input
													type="checkbox"
													id="{{ $fieldId }}-{{ $index }}"
													wire:model="formData.{{ $field->name }}"
													value="{{ $option['value'] ?? $option }}"
													class="ve-block-form-checkbox"
												/>
												<label for="{{ $fieldId }}-{{ $index }}">{{ $option['label'] ?? $option }}</label>
											</div>
										@endforeach
									</fieldset>
								@elseif ( 'radio' === $field->type )
									<fieldset
										class="ve-block-form-fieldset"
										@if ( $hasError ) aria-describedby="{{ $fieldId }}-error" @endif
									>
										@foreach ( $field->options ?? [] as $index => $option )
											<div class="ve-block-form-radio-wrapper">
												<input
													type="radio"
													id="{{ $fieldId }}-{{ $index }}"
													name="{{ $field->name }}"
													wire:model="formData.{{ $field->name }}"
													value="{{ $option['value'] ?? $option }}"
													class="ve-block-form-radio"
													@if ( $field->is_required ) required @endif
												/>
												<label for="{{ $fieldId }}-{{ $index }}">{{ $option['label'] ?? $option }}</label>
											</div>
										@endforeach
									</fieldset>
								@elseif ( 'toggle' === $field->type )
									<div class="ve-block-form-toggle-wrapper">
										<input
											type="checkbox"
											id="{{ $fieldId }}"
											name="{{ $field->name }}"
											wire:model="formData.{{ $field->name }}"
											class="ve-block-form-toggle"
											@if ( $hasError ) aria-invalid="true" aria-describedby="{{ $fieldId }}-error" @endif
										/>
										@if ( $field->help_text )
											<span class="ve-block-form-toggle-text">{{ $field->help_text }}</span>
										@endif
									</div>
								@elseif ( 'file' === $field->type )
									<input
										type="file"
										id="{{ $fieldId }}"
										name="{{ $field->name }}"
										wire:model="formData.{{ $field->name }}"
										class="ve-block-form-file"
										@if ( $field->is_required ) required aria-required="true" @endif
										@if ( $hasError ) aria-invalid="true" aria-describedby="{{ $fieldId }}-error" @endif
									/>
								@elseif ( 'hidden' === $field->type )
									<input
										type="hidden"
										name="{{ $field->name }}"
										wire:model="formData.{{ $field->name }}"
									/>
								@else
									<input
										type="text"
										id="{{ $fieldId }}"
										name="{{ $field->name }}"
										wire:model="formData.{{ $field->name }}"
										placeholder="{{ $field->placeholder }}"
										class="ve-block-form-input {{ $hasError ? 've-block-form-input-error' : '' }}"
										@if ( $field->is_required ) required aria-required="true" @endif
										@if ( $hasError ) aria-invalid="true" aria-describedby="{{ $fieldId }}-error" @endif
									/>
								@endif

								@if ( $hasError )
									<p id="{{ $fieldId }}-error" class="ve-block-form-error" role="alert">{{ $errorMsg }}</p>
								@elseif ( $field->help_text && ! in_array( $field->type, [ 'checkbox', 'toggle' ], true ) )
									<p id="{{ $fieldId }}-help" class="ve-block-form-help">{{ $field->help_text }}</p>
								@endif
							</div>
						@endif
					@endforeach
				</div>

				<div class="ve-block-form-actions" style="margin-top: {{ $fieldSpacing }};">
					<button
						type="submit"
						class="ve-block-form-submit btn btn-{{ $submitButtonColor }} btn-{{ $submitButtonSize }}"
						wire:loading.attr="disabled"
					>
						<span wire:loading.remove wire:target="submitForm">{{ $submitText }}</span>
						<span wire:loading wire:target="submitForm">{{ __( 'visual-editor::ve.form_submitting' ) }}</span>
					</button>
				</div>
			</form>

			@if ( $isModal || $isSlide )
				</div>
			@endif
		</div>
	@endif
</div>
