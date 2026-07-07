<?php

declare( strict_types=1 );

use ArtisanPackUI\Ai\Contracts\FeatureRegistry;
use ArtisanPackUI\VisualEditor\Ai\Agents\ContentBlockSuggestionAgent;
use ArtisanPackUI\VisualEditor\Ai\Agents\HeadingHierarchyAgent;
use ArtisanPackUI\VisualEditor\Ai\Agents\LayoutSuggestionAgent;
use ArtisanPackUI\VisualEditor\VisualEditorServiceProvider;

it( 'declares three visual_editor.* features from the service provider', function (): void {
	$provider = new VisualEditorServiceProvider( $this->app );
	$features = $provider->aiFeatures();

	expect( $features )->toHaveKeys( [
		'visual_editor.suggest_next_block',
		'visual_editor.suggest_layout',
		'visual_editor.heading_hierarchy',
	] );

	expect( $features['visual_editor.suggest_next_block']['agent'] )->toBe( ContentBlockSuggestionAgent::class );
	expect( $features['visual_editor.suggest_layout']['agent'] )->toBe( LayoutSuggestionAgent::class );
	expect( $features['visual_editor.heading_hierarchy']['agent'] )->toBe( HeadingHierarchyAgent::class );

	foreach ( $features as $definition ) {
		expect( $definition['package'] )->toBe( 'artisanpack-ui/visual-editor' );
	}
} );

it( 'registers those three features with the ai FeatureRegistry at boot', function (): void {
	/** @var FeatureRegistry $registry */
	$registry = $this->app->make( FeatureRegistry::class );

	expect( $registry->get( 'visual_editor.suggest_next_block' ) )->not->toBeNull();
	expect( $registry->get( 'visual_editor.suggest_layout' ) )->not->toBeNull();
	expect( $registry->get( 'visual_editor.heading_hierarchy' ) )->not->toBeNull();
} );
