@php
	$formId       = $content['formId'] ?? null;
	$displayStyle = $content['displayStyle'] ?? 'embedded';
	$showLabels   = $content['showLabels'] ?? true;
	$layout       = $content['layout'] ?? 'stacked';
	$columns      = max( 1, min( 4, (int) ( $content['columns'] ?? 2 ) ) );
	$fieldSpacing = veSanitizeCssDimension( $styles['fieldSpacing'] ?? '1rem', '1rem' );

	$formsInstalled = class_exists( \ArtisanPackUI\Forms\Models\Form::class );
	$form           = null;
	$fields         = [];
	$availableForms = [];

	if ( $formsInstalled ) {
		try {
			$availableForms = \ArtisanPackUI\Forms\Models\Form::query()
				->orderBy( 'name' )
				->get();
		} catch ( \Throwable $e ) {
			$availableForms = collect();
		}

		if ( $formId ) {
			try {
				$form = \ArtisanPackUI\Forms\Models\Form::find( (int) $formId );
				if ( $form ) {
					$fields = $form->fields()->orderBy( 'sort_order' )->get();
				}
			} catch ( \Throwable $e ) {
				$form   = null;
				$fields = [];
			}
		}
	}

	$isGrid    = 'grid' === $layout;
	$isInline  = 'inline' === $layout;
	$isModal   = 'modal' === $displayStyle;
	$isSlide   = 'slide-over' === $displayStyle;

	$submitText = $content['submitButtonText'] ?? '';
	if ( ! $submitText && $form ) {
		$submitText = $form->submit_button_text ?: __( 'visual-editor::ve.form_submit_default' );
	} elseif ( ! $submitText ) {
		$submitText = __( 'visual-editor::ve.form_submit_default' );
	}

	$buttonColor = $content['submitButtonColor'] ?? 'primary';
	$buttonSize  = $content['submitButtonSize'] ?? 'md';

	$sizeMap = [
		'sm' => 'padding: 0.375rem 0.75rem; font-size: 0.85em;',
		'md' => 'padding: 0.625rem 1.25rem; font-size: 0.95em;',
		'lg' => 'padding: 0.75rem 1.5rem; font-size: 1.05em;',
	];
	$colorMap = [
		'primary'   => 'background: #2563eb; color: #fff;',
		'secondary' => 'background: #6b7280; color: #fff;',
		'accent'    => 'background: #8b5cf6; color: #fff;',
		'success'   => 'background: #16a34a; color: #fff;',
		'warning'   => 'background: #d97706; color: #fff;',
		'error'     => 'background: #dc2626; color: #fff;',
		'info'      => 'background: #0891b2; color: #fff;',
	];
	$buttonStyle = ( $colorMap[ $buttonColor ] ?? $colorMap['primary'] ) . ( $sizeMap[ $buttonSize ] ?? $sizeMap['md'] ) . ' border: none; border-radius: 6px; cursor: default; font-weight: 500;';
@endphp

<div class="ve-block ve-block-form ve-block-editing ve-block-dynamic-preview">
	@if ( ! $formsInstalled )
		<div style="padding: 2rem; text-align: center; background: #fef3c7; border: 1px dashed #d97706; border-radius: 8px; color: #92400e;">
			<svg style="width: 2rem; height: 2rem; margin: 0 auto 0.5rem;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
				<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
			</svg>
			<p style="font-weight: 600; margin: 0 0 0.25rem;">{{ __( 'visual-editor::ve.form_package_required' ) }}</p>
			<p style="font-size: 0.85em; margin: 0;">{{ __( 'visual-editor::ve.form_package_required_hint' ) }}</p>
		</div>
	@elseif ( ! $form )
		<div
			style="padding: 2rem; text-align: center; background: #f3f4f6; border: 1px dashed #9ca3af; border-radius: 8px; color: #6b7280;"
			x-data="{
				selectedFormId: '',
				selectForm() {
					if ( this.selectedFormId ) {
						document.dispatchEvent( new CustomEvent( 've-field-change', {
							detail: { blockId: 'dynamic', field: 'formId', value: this.selectedFormId },
							bubbles: true
						} ) );
					}
				}
			}"
		>
			<svg style="width: 2rem; height: 2rem; margin: 0 auto 0.5rem;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
				<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
			</svg>
			<p style="font-weight: 600; margin: 0 0 0.75rem;">{{ __( 'visual-editor::ve.form_select_form' ) }}</p>

			@if ( $availableForms->count() > 0 )
				<div style="display: flex; gap: 0.5rem; justify-content: center; align-items: stretch; max-width: 400px; margin: 0 auto;">
					<select
						x-model="selectedFormId"
						style="flex: 1; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.9em; background: #fff; color: #374151; box-sizing: border-box;"
					>
						<option value="">{{ __( 'visual-editor::ve.form_select_a_form' ) }}</option>
						@foreach ( $availableForms as $availableForm )
							<option value="{{ $availableForm->id }}">{{ $availableForm->name }}{{ ! $availableForm->is_active ? ' (' . __( 'visual-editor::ve.form_inactive_label' ) . ')' : '' }}</option>
						@endforeach
					</select>
					<button
						type="button"
						x-on:click="selectForm()"
						:disabled="! selectedFormId"
						style="padding: 0.5rem 1rem; background: #2563eb; color: #fff; border: none; border-radius: 6px; font-size: 0.9em; font-weight: 500; cursor: pointer; white-space: nowrap;"
						:style="! selectedFormId ? 'opacity: 0.5; cursor: not-allowed;' : ''"
					>{{ __( 'visual-editor::ve.form_select_button' ) }}</button>
				</div>
			@else
				<p style="font-size: 0.85em; margin: 0;">{{ __( 'visual-editor::ve.form_no_forms_available' ) }}</p>
			@endif
		</div>
	@else
		@if ( $isModal || $isSlide )
			<div style="text-align: center; padding: 1.5rem;">
				<p style="font-size: 0.85em; color: #6b7280; margin: 0 0 0.75rem;">
					{{ $isModal ? __( 'visual-editor::ve.form_modal_trigger_preview' ) : __( 'visual-editor::ve.form_slide_over_trigger_preview' ) }}
				</p>
				<button type="button" disabled style="{{ $buttonStyle }}">
					{{ $submitText }}
				</button>
			</div>
		@endif

		<div style="{{ ( $isModal || $isSlide ) ? 'border: 1px dashed #d1d5db; border-radius: 8px; padding: 1rem; margin-top: 0.5rem;' : '' }}">
			@if ( $isModal || $isSlide )
				<p style="font-size: 0.75em; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em; margin: 0 0 0.75rem;">
					{{ $isModal ? __( 'visual-editor::ve.form_modal_content_label' ) : __( 'visual-editor::ve.form_slide_over_content_label' ) }}
				</p>
			@endif

			<div style="{{ $isGrid ? "display: grid; grid-template-columns: repeat({$columns}, 1fr); gap: {$fieldSpacing};" : ( $isInline ? "display: flex; flex-wrap: wrap; gap: {$fieldSpacing}; align-items: flex-end;" : "display: flex; flex-direction: column; gap: {$fieldSpacing};" ) }}">
				@foreach ( $fields as $field )
					@if ( $field->isLayoutField() )
						@if ( 'heading' === $field->type )
							<div style="{{ $isGrid ? 'grid-column: 1 / -1;' : ( $isInline ? 'flex-basis: 100%;' : '' ) }}">
								<h3 style="font-weight: 600; font-size: 1.1em; margin: 0;">{{ $field->label }}</h3>
							</div>
						@elseif ( 'divider' === $field->type )
							<div style="{{ $isGrid ? 'grid-column: 1 / -1;' : ( $isInline ? 'flex-basis: 100%;' : '' ) }}">
								<hr style="border: none; border-top: 1px solid #e5e7eb; margin: 0.5rem 0;" />
							</div>
						@elseif ( 'paragraph' === $field->type )
							<div style="{{ $isGrid ? 'grid-column: 1 / -1;' : ( $isInline ? 'flex-basis: 100%;' : '' ) }}">
								<p style="font-size: 0.9em; color: #6b7280; margin: 0;">{{ $field->help_text ?: $field->label }}</p>
							</div>
						@endif
					@else
						@php
							$widthStyle = '';
							if ( $isGrid ) {
								$widthStyle = match ( $field->width ) {
									'half'       => 'grid-column: span ' . max( 1, (int) round( $columns * 0.5 ) ) . ';',
									'third'      => 'grid-column: span ' . max( 1, (int) round( $columns / 3 ) ) . ';',
									'two-thirds' => 'grid-column: span ' . max( 1, (int) round( $columns * 2 / 3 ) ) . ';',
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
							$fieldId = 've-form-field-' . $field->uuid;
						@endphp
						<div style="{{ $widthStyle }}">
							@if ( $showLabels && $field->label )
								<label for="{{ $fieldId }}" style="display: block; font-weight: 500; margin-bottom: 0.375rem; font-size: 0.9em;">
									{{ $field->label }}
									@if ( $field->is_required )
										<span style="color: #dc2626;">*</span>
									@endif
								</label>
							@endif

							@php $inputType = 'phone' === $field->type ? 'tel' : $field->type; @endphp
							@if ( in_array( $field->type, [ 'text', 'email', 'phone', 'number', 'url', 'date', 'time', 'hidden' ], true ) )
								<input
									type="{{ $inputType }}"
									id="{{ $fieldId }}"
									placeholder="{{ $field->placeholder }}"
									disabled
									style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.95em; background: #fff; color: #374151; box-sizing: border-box;"
								/>
							@elseif ( 'textarea' === $field->type )
								<textarea
									id="{{ $fieldId }}"
									placeholder="{{ $field->placeholder }}"
									rows="3"
									disabled
									style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.95em; background: #fff; color: #374151; resize: vertical; box-sizing: border-box;"
								></textarea>
							@elseif ( in_array( $field->type, [ 'select', 'select_multiple' ], true ) )
								<select
									id="{{ $fieldId }}"
									disabled
									@if ( 'select_multiple' === $field->type ) multiple @endif
									style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.95em; background: #fff; color: #374151; box-sizing: border-box;"
								>
									<option>{{ $field->placeholder ?: __( 'visual-editor::ve.form_select_option' ) }}</option>
									@foreach ( $field->options ?? [] as $option )
										<option>{{ $option['label'] ?? $option }}</option>
									@endforeach
								</select>
							@elseif ( 'checkbox' === $field->type )
								<div style="display: flex; align-items: center; gap: 0.5rem;">
									<input type="checkbox" id="{{ $fieldId }}" disabled style="width: 1rem; height: 1rem;" />
									@if ( $field->help_text )
										<span style="font-size: 0.9em; color: #374151;">{{ $field->help_text }}</span>
									@endif
								</div>
							@elseif ( 'checkbox_group' === $field->type )
								<div style="display: flex; flex-direction: column; gap: 0.375rem;">
									@foreach ( $field->options ?? [] as $option )
										<div style="display: flex; align-items: center; gap: 0.5rem;">
											<input type="checkbox" disabled style="width: 1rem; height: 1rem;" />
											<span style="font-size: 0.9em;">{{ $option['label'] ?? $option }}</span>
										</div>
									@endforeach
								</div>
							@elseif ( 'radio' === $field->type )
								<div style="display: flex; flex-direction: column; gap: 0.375rem;">
									@foreach ( $field->options ?? [] as $option )
										<div style="display: flex; align-items: center; gap: 0.5rem;">
											<input type="radio" name="{{ $fieldId }}" disabled style="width: 1rem; height: 1rem;" />
											<span style="font-size: 0.9em;">{{ $option['label'] ?? $option }}</span>
										</div>
									@endforeach
								</div>
							@elseif ( 'toggle' === $field->type )
								<div style="display: flex; align-items: center; gap: 0.5rem;">
									<input type="checkbox" id="{{ $fieldId }}" disabled style="width: 1rem; height: 1rem;" />
									@if ( $field->help_text )
										<span style="font-size: 0.9em; color: #374151;">{{ $field->help_text }}</span>
									@endif
								</div>
							@elseif ( 'file' === $field->type )
								<div style="padding: 1rem; border: 1px dashed #d1d5db; border-radius: 6px; text-align: center; color: #9ca3af; font-size: 0.9em;">
									{{ __( 'visual-editor::ve.form_file_upload_placeholder' ) }}
								</div>
							@else
								<input
									type="text"
									id="{{ $fieldId }}"
									placeholder="{{ $field->placeholder }}"
									disabled
									style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.95em; background: #fff; color: #374151; box-sizing: border-box;"
								/>
							@endif

							@if ( $field->help_text && ! in_array( $field->type, [ 'checkbox', 'toggle' ], true ) )
								<p style="font-size: 0.8em; color: #9ca3af; margin: 0.25rem 0 0;">{{ $field->help_text }}</p>
							@endif
						</div>
					@endif
				@endforeach
			</div>

			<div style="margin-top: {{ $fieldSpacing }};">
				<button type="button" disabled style="{{ $buttonStyle }}">
					{{ $submitText }}
				</button>
			</div>
		</div>
	@endif
</div>
