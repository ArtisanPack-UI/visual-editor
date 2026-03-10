<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\InspectorField;

test( 'inspector field with textarea type returns textarea field type', function (): void {
	$component = new InspectorField(
		name: 'alt',
		schema: [ 'type' => 'textarea', 'label' => 'Alt text', 'default' => '' ],
	);

	expect( $component->fieldType() )->toBe( 'textarea' );
} );

test( 'inspector field textarea renders textarea element', function (): void {
	$this->blade( '<x-ve-inspector-field name="alt" :schema="[\'type\' => \'textarea\', \'label\' => \'Alt text\', \'default\' => \'\']" value="Test alt" block-id="block-1" />' )
		->assertSee( 'Alt text' )
		->assertSee( 'Test alt' );
} );

test( 'inspector field textarea renders hint when provided', function (): void {
	$this->blade( '<x-ve-inspector-field name="alt" :schema="[\'type\' => \'textarea\', \'label\' => \'Alt text\', \'hint\' => \'Describe the image\', \'default\' => \'\']" value="" block-id="block-1" />' )
		->assertSee( 'Describe the image' );
} );
