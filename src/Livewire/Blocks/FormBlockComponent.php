<?php

/**
 * Form Block Livewire Component.
 *
 * Server-side rendering and submission component for the Form
 * dynamic block. Loads form configuration from artisanpack-ui/forms,
 * renders fields, handles AJAX submission with validation, and
 * provides spam protection via honeypot and rate limiting.
 *
 * Gracefully degrades if artisanpack-ui/forms is not installed.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Livewire\Blocks
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Livewire\Blocks;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

/**
 * Livewire component for the Form dynamic block.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Livewire\Blocks
 *
 * @since      1.0.0
 */
class FormBlockComponent extends Component
{
	use WithFileUploads;

	/**
	 * The form ID from artisanpack-ui/forms.
	 *
	 * @since 1.0.0
	 *
	 * @var int|null
	 */
	public ?int $formId = null;

	/**
	 * Display style (embedded, modal, slide-over).
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $displayStyle = 'embedded';

	/**
	 * Submit button text override.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $submitButtonText = '';

	/**
	 * Submit button color.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $submitButtonColor = 'primary';

	/**
	 * Submit button size.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $submitButtonSize = 'md';

	/**
	 * Success message after submission.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $successMessage = '';

	/**
	 * Redirect URL after successful submission.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $redirectUrl = '';

	/**
	 * Whether to show field labels.
	 *
	 * @since 1.0.0
	 *
	 * @var bool
	 */
	public bool $showLabels = true;

	/**
	 * Form layout (stacked, inline, grid).
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $layout = 'stacked';

	/**
	 * Number of grid columns.
	 *
	 * @since 1.0.0
	 *
	 * @var int
	 */
	public int $columns = 2;

	/**
	 * Whether honeypot spam protection is enabled.
	 *
	 * @since 1.0.0
	 *
	 * @var bool
	 */
	public bool $enableHoneypot = true;

	/**
	 * Spacing between fields.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $fieldSpacing = '1rem';

	/**
	 * Custom CSS class.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $customClass = '';

	/**
	 * Whether to pre-fill fields from URL query parameters.
	 *
	 * @since 1.0.0
	 *
	 * @var bool
	 */
	public bool $prefillViaUrl = false;

	/**
	 * Whether this is being rendered in the editor.
	 *
	 * @since 1.0.0
	 *
	 * @var bool
	 */
	public bool $isEditor = false;

	/**
	 * Form field values keyed by field name.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, mixed>
	 */
	public array $formData = [];

	/**
	 * Honeypot field value.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $honeypot = '';

	/**
	 * Whether the form was successfully submitted.
	 *
	 * @since 1.0.0
	 *
	 * @var bool
	 */
	public bool $submitted = false;

	/**
	 * Whether the modal/slide-over is open.
	 *
	 * @since 1.0.0
	 *
	 * @var bool
	 */
	public bool $showOverlay = false;

	/**
	 * Unique form element ID.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $formElementId = '';

	/**
	 * Resolved form fields.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, object>
	 */
	public array $resolvedFields = [];

	/**
	 * Initialize the component.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function mount(): void
	{
		$this->formElementId = 've-form-' . (string) Str::uuid();
		$this->columns       = max( 1, min( 4, $this->columns ) );

		$form = $this->getForm();

		if ( $form ) {
			$fields = $form->fields()->orderBy( 'sort_order' )->get();

			foreach ( $fields as $field ) {
				if ( ! $field->isLayoutField() ) {
					$this->formData[ $field->name ] = $field->default_value ?? '';
				}
			}

			$this->resolvedFields = $fields->all();

			if ( $this->prefillViaUrl ) {
				$query = request()->query();
				foreach ( $fields as $field ) {
					if ( ! $field->isLayoutField() && isset( $query[ $field->name ] ) ) {
						$this->formData[ $field->name ] = sanitizeText( (string) $query[ $field->name ] );
					}
				}
			}
		}
	}

	/**
	 * Submit the form.
	 *
	 * @since 1.0.0
	 *
	 * @return mixed
	 */
	public function submitForm(): mixed
	{
		if ( $this->enableHoneypot && '' !== $this->honeypot ) {
			return null;
		}

		$rateLimitKey = 've-form-submit:' . request()->ip() . ':' . $this->formId;
		if ( RateLimiter::tooManyAttempts( $rateLimitKey, 5 ) ) {
			$this->addError( 'formData', __( 'visual-editor::ve.form_rate_limited' ) );

			return null;
		}
		RateLimiter::hit( $rateLimitKey, 60 );

		$form = $this->getForm();

		if ( ! $form ) {
			return null;
		}

		$fields = $this->getEffectiveFields();

		$rules    = [];
		$messages = [];

		foreach ( $fields as $field ) {
			if ( $field->isLayoutField() ) {
				continue;
			}

			$fieldRules = $field->buildValidationRules();
			if ( ! empty( $fieldRules ) ) {
				$rules[ "formData.{$field->name}" ] = $fieldRules;
			}
		}

		$validated = $this->validate( $rules, $messages );
		$formData  = $validated['formData'] ?? $this->formData;

		if ( function_exists( 'sanitizeText' ) ) {
			foreach ( $formData as $key => $value ) {
				if ( is_string( $value ) ) {
					$formData[ $key ] = sanitizeText( $value );
				}
			}
		}

		try {
			$submissionService = null;
			if ( class_exists( \ArtisanPackUI\Forms\Services\SubmissionService::class ) ) {
				$submissionService = app( \ArtisanPackUI\Forms\Services\SubmissionService::class );
			}

			if ( $submissionService ) {
				$submissionService->create( $form, $formData );
			}
		} catch ( Throwable $e ) {
			logger()->error( 'Form block submission failed: ' . $e->getMessage(), [
				'form_id' => $this->formId,
			] );

			$this->addError( 'formData', __( 'visual-editor::ve.form_submission_error' ) );

			return null;
		}

		veDoAction( 've.form-block.submitted', $form, $formData );

		$this->submitted = true;

		$successMsg = $this->successMessage ?: $form->success_message ?: __( 'visual-editor::ve.form_submission_success' );
		$this->dispatch( 've-form-submitted', formId: $this->formId, message: $successMsg );

		if ( $this->redirectUrl ) {
			$parsedHost = parse_url( $this->redirectUrl, PHP_URL_HOST );
			$appHost    = parse_url( config( 'app.url' ), PHP_URL_HOST );
			$isRelative = str_starts_with( $this->redirectUrl, '/' ) && ! str_starts_with( $this->redirectUrl, '//' );

			if ( $isRelative || $parsedHost === $appHost ) {
				return $this->redirect( $this->redirectUrl );
			}
		}

		return null;
	}

	/**
	 * Open the overlay (modal or slide-over).
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function openOverlay(): void
	{
		$this->showOverlay = true;
	}

	/**
	 * Close the overlay (modal or slide-over).
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function closeOverlay(): void
	{
		$this->showOverlay = false;
	}

	/**
	 * Get the form model.
	 *
	 * Supports customization via the ve.form-block.available-forms filter hook.
	 *
	 * @since 1.0.0
	 *
	 * @return object|null
	 */
	public function getForm(): ?object
	{
		if ( ! class_exists( \ArtisanPackUI\Forms\Models\Form::class ) ) {
			return null;
		}

		if ( ! $this->formId ) {
			return null;
		}

		try {
			return \ArtisanPackUI\Forms\Models\Form::find( $this->formId );
		} catch ( Throwable $e ) {
			return null;
		}
	}

	/**
	 * Get the resolved submit button text.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function getSubmitText(): string
	{
		if ( $this->submitButtonText ) {
			return $this->submitButtonText;
		}

		$form = $this->getForm();

		if ( $form && $form->submit_button_text ) {
			return $form->submit_button_text;
		}

		return __( 'visual-editor::ve.form_submit_default' );
	}

	/**
	 * Render the component.
	 *
	 * @since 1.0.0
	 *
	 * @return View
	 */
	public function render(): View
	{
		$form   = $this->getForm();
		$fields = $this->getEffectiveFields();

		$formsInstalled = class_exists( \ArtisanPackUI\Forms\Models\Form::class );

		return view( 'visual-editor::livewire.blocks.form-block', [
			'form'            => $form,
			'fields'          => $fields,
			'formsInstalled'  => $formsInstalled,
			'submitText'      => $this->getSubmitText(),
		] );
	}

	/**
	 * Get the effective fields after applying filters.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	protected function getEffectiveFields(): array
	{
		$fields = collect( $this->resolvedFields );

		$filtered = veApplyFilters( 've.form-block.fields', $fields );
		if ( is_iterable( $filtered ) ) {
			$fields = collect( $filtered );
		}

		return $fields->all();
	}
}
