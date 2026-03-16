<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Livewire\Blocks\FormBlockComponent;

test( 'form component has default property values', function (): void {
	$component = new FormBlockComponent();

	expect( $component->formId )->toBeNull()
		->and( $component->displayStyle )->toBe( 'embedded' )
		->and( $component->submitButtonText )->toBe( '' )
		->and( $component->submitButtonColor )->toBe( 'primary' )
		->and( $component->submitButtonSize )->toBe( 'md' )
		->and( $component->successMessage )->toBe( '' )
		->and( $component->redirectUrl )->toBe( '' )
		->and( $component->showLabels )->toBeTrue()
		->and( $component->layout )->toBe( 'stacked' )
		->and( $component->columns )->toBe( 2 )
		->and( $component->useAjax )->toBeTrue()
		->and( $component->enableHoneypot )->toBeTrue()
		->and( $component->fieldSpacing )->toBe( '1rem' )
		->and( $component->customClass )->toBe( '' )
		->and( $component->isEditor )->toBeFalse()
		->and( $component->submitted )->toBeFalse()
		->and( $component->showOverlay )->toBeFalse()
		->and( $component->honeypot )->toBe( '' );
} );

test( 'form component get form returns null for nonexistent form id', function (): void {
	$component = new FormBlockComponent();

	$component->formId = 999;

	expect( $component->getForm() )->toBeNull();
} );

test( 'form component get form returns null when no form id', function (): void {
	$component = new FormBlockComponent();

	$component->formId = null;

	expect( $component->getForm() )->toBeNull();
} );

test( 'form component get submit text returns default when no override', function (): void {
	$component = new FormBlockComponent();

	$component->submitButtonText = '';
	$component->formId           = null;

	$text = $component->getSubmitText();

	expect( $text )->not->toBeEmpty();
} );

test( 'form component get submit text uses override when provided', function (): void {
	$component = new FormBlockComponent();

	$component->submitButtonText = 'Send Message';

	expect( $component->getSubmitText() )->toBe( 'Send Message' );
} );

test( 'form component submit form blocks honeypot submissions', function (): void {
	$component = new FormBlockComponent();

	$component->enableHoneypot = true;
	$component->honeypot       = 'spam bot filled this';

	$result = $component->submitForm();

	expect( $result )->toBeNull()
		->and( $component->submitted )->toBeFalse();
} );

test( 'form component submit form returns null when no form', function (): void {
	$component = new FormBlockComponent();

	$component->formId = null;

	$result = $component->submitForm();

	expect( $result )->toBeNull();
} );

test( 'form component overlay methods toggle state', function (): void {
	$component = new FormBlockComponent();

	expect( $component->showOverlay )->toBeFalse();

	$component->openOverlay();
	expect( $component->showOverlay )->toBeTrue();

	$component->closeOverlay();
	expect( $component->showOverlay )->toBeFalse();
} );
