<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\InspectorField;

test( 'inspector field can be instantiated', function (): void {
	$component = new InspectorField(
		name: 'textColor',
		schema: [ 'type' => 'color', 'label' => 'Text Color', 'default' => null ],
		value: '#ff0000',
		blockId: 'block-123',
	);

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->name )->toBe( 'textColor' );
	expect( $component->value )->toBe( '#ff0000' );
	expect( $component->blockId )->toBe( 'block-123' );
} );

test( 'inspector field returns correct field type', function (): void {
	$component = new InspectorField(
		name: 'textColor',
		schema: [ 'type' => 'color', 'label' => 'Text Color' ],
	);

	expect( $component->fieldType() )->toBe( 'color' );
} );

test( 'inspector field returns text as default field type', function (): void {
	$component = new InspectorField(
		name: 'anchor',
		schema: [ 'label' => 'Anchor' ],
	);

	expect( $component->fieldType() )->toBe( 'text' );
} );

test( 'inspector field returns correct label', function (): void {
	$component = new InspectorField(
		name: 'fontSize',
		schema: [ 'type' => 'select', 'label' => 'Font Size' ],
	);

	expect( $component->fieldLabel() )->toBe( 'Font Size' );
} );

test( 'inspector field returns field name as fallback label', function (): void {
	$component = new InspectorField(
		name: 'fontSize',
		schema: [ 'type' => 'select' ],
	);

	expect( $component->fieldLabel() )->toBe( 'fontSize' );
} );

test( 'inspector field returns options from schema', function (): void {
	$component = new InspectorField(
		name: 'level',
		schema: [
			'type'    => 'select',
			'label'   => 'Level',
			'options' => [ 'h1' => 'H1', 'h2' => 'H2' ],
		],
	);

	expect( $component->fieldOptions() )->toBe( [ 'h1' => 'H1', 'h2' => 'H2' ] );
} );

test( 'inspector field renders for color type', function (): void {
	$view = $this->blade(
		'<x-ve-inspector-field name="textColor" :schema="$schema" :value="$value" />',
		[
			'schema' => [ 'type' => 'color', 'label' => 'Text Color', 'default' => null ],
			'value'  => '#3b82f6',
		],
	);

	expect( $view )->not->toBeNull();
} );

test( 'inspector field renders for select type', function (): void {
	$view = $this->blade(
		'<x-ve-inspector-field name="fontSize" :schema="$schema" :value="$value" />',
		[
			'schema' => [
				'type'    => 'select',
				'label'   => 'Font Size',
				'options' => [ 'small' => 'Small', 'base' => 'Normal', 'large' => 'Large' ],
			],
			'value' => 'base',
		],
	);

	expect( $view )->not->toBeNull();
} );

test( 'inspector field renders for toggle type', function (): void {
	$view = $this->blade(
		'<x-ve-inspector-field name="dropCap" :schema="$schema" :value="$value" />',
		[
			'schema' => [ 'type' => 'toggle', 'label' => 'Drop Cap', 'default' => false ],
			'value'  => true,
		],
	);

	expect( $view )->not->toBeNull();
} );

test( 'inspector field renders for media_picker type', function (): void {
	$view = $this->blade(
		'<x-ve-inspector-field name="backgroundImage" :schema="$schema" :value="$value" />',
		[
			'schema' => [ 'type' => 'media_picker', 'label' => 'Background Image' ],
			'value'  => '',
		],
	);

	expect( $view )->not->toBeNull();
} );

test( 'inspector field renders media_picker with existing value', function (): void {
	$view = $this->blade(
		'<x-ve-inspector-field name="backgroundImage" :schema="$schema" :value="$value" />',
		[
			'schema' => [ 'type' => 'media_picker', 'label' => 'Background Image' ],
			'value'  => 'https://example.com/image.jpg',
		],
	);

	expect( $view )->not->toBeNull();
} );

test( 'inspector field renders for text type', function (): void {
	$view = $this->blade(
		'<x-ve-inspector-field name="anchor" :schema="$schema" :value="$value" />',
		[
			'schema' => [ 'type' => 'text', 'label' => 'Anchor', 'placeholder' => 'Enter anchor...' ],
			'value'  => '',
		],
	);

	expect( $view )->not->toBeNull();
} );
