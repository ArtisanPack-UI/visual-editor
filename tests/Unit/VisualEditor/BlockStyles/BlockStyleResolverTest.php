<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\BlockStyles\BlockStyleResolver;
use ArtisanPackUI\VisualEditor\Facades\VisualEditor;
use ArtisanPackUI\VisualEditor\VisualEditorServiceProvider;

/**
 * @param  array<string, mixed>  $styles
 */
function useGlobalStylesForBlockStyleTest( array $styles ): void
{
	config()->set( 'artisanpack.visual-editor.site-editor.global-styles', [ 'theme' => 'test-theme', 'settings' => [], 'styles' => $styles ] );

	( new VisualEditorServiceProvider( app() ) )->registerSiteEditorResolvers();
	app()->forgetScopedInstances();
}

afterEach( function (): void {
	removeAllFilters( 'ap.visualEditor.blockStyles' );
} );

it( 'resolves variations registered in code', function (): void {
	VisualEditor::registerBlockStyle( 'core/group', 'card', 'Card', [ 'border' => [ 'radius' => '12px' ] ] );

	$resolver = app( BlockStyleResolver::class );

	expect( $resolver->editorPayload() )->toContain( [ 'name' => 'core/group', 'slug' => 'card', 'label' => 'Card', 'isDefault' => false ] )
		->and( $resolver->css() )->toContain( ':root :where(.wp-block-group.is-style-card)' );

	VisualEditor::unregisterBlockStyle( 'core/group', 'card' );

	expect( app( BlockStyleResolver::class )->editorPayload() )->not->toContain( [ 'name' => 'core/group', 'slug' => 'card', 'label' => 'Card', 'isDefault' => false ] );
} );

it( 'rejects a malformed block name', function (): void {
	VisualEditor::registerBlockStyle( 'group', 'card', 'Card' );
} )->throws( InvalidArgumentException::class );

it( 'reads partials from configured directories', function (): void {
	$directory = sys_get_temp_dir() . '/ve-block-styles-' . uniqid();
	mkdir( $directory );
	file_put_contents( $directory . '/glow.json', json_encode( [ 'title' => 'Glow', 'blockTypes' => [ 'core/button' ], 'styles' => [ 'shadow' => '0 0 24px #22d3ee' ] ] ) );

	config()->set( 'artisanpack.visual-editor.block_styles.directories', [ $directory ] );
	app()->forgetScopedInstances();

	$css = app( BlockStyleResolver::class )->css();

	unlink( $directory . '/glow.json' );
	rmdir( $directory );

	expect( $css )->toContain( ':root :where(.wp-block-button.is-style-glow .wp-block-button__link) {' )
		->toContain( 'box-shadow: 0 0 24px #22d3ee;' );
} );

it( 'reads theme.json variations from the resolved global styles', function (): void {
	useGlobalStylesForBlockStyleTest( [
		'blocks' => [ 'core/quote' => [ 'variations' => [ 'neon' => [ 'color' => [ 'text' => '#22d3ee' ] ] ] ] ],
	] );

	$resolver = app( BlockStyleResolver::class );

	expect( $resolver->editorPayload() )->toBe( [ [ 'name' => 'core/quote', 'slug' => 'neon', 'label' => 'Neon', 'isDefault' => false ] ] )
		->and( $resolver->css() )->toContain( ':root :where(.wp-block-quote.is-style-neon) {' );
} );

it( 'keeps the theme style when code only relabels a variation', function (): void {
	useGlobalStylesForBlockStyleTest( [
		'blocks' => [ 'core/quote' => [ 'variations' => [ 'neon' => [ 'color' => [ 'text' => '#22d3ee' ] ] ] ] ],
	] );

	VisualEditor::registerBlockStyle( 'core/quote', 'neon', 'Neon glow' );

	$resolver = app( BlockStyleResolver::class );

	expect( $resolver->editorPayload()[0]['label'] )->toBe( 'Neon glow' )
		->and( $resolver->css() )->toContain( 'color: #22d3ee;' );
} );

it( 'lets a code registration replace a theme variation style', function (): void {
	useGlobalStylesForBlockStyleTest( [
		'blocks' => [ 'core/quote' => [ 'variations' => [ 'neon' => [ 'color' => [ 'text' => '#22d3ee' ] ] ] ] ],
	] );

	VisualEditor::registerBlockStyle( 'core/quote', 'neon', 'Neon', [ 'color' => [ 'text' => '#e879f9' ] ] );

	expect( app( BlockStyleResolver::class )->css() )->toContain( 'color: #e879f9;' )
		->not->toContain( '#22d3ee' );
} );

it( 'sanitises entries a filter returns', function (): void {
	addFilter( 'ap.visualEditor.blockStyles', fn (): array => [
		'not-an-entry',
		[ 'block' => 'bad', 'slug' => 'x' ],
		[ 'block' => 'core/button', 'slug' => 'Outline Thick', 'label' => '', 'isDefault' => 'yes' ],
	] );

	expect( app( BlockStyleResolver::class )->editorPayload() )->toBe( [
		[ 'name' => 'core/button', 'slug' => 'outline-thick', 'label' => 'Outline thick', 'isDefault' => false ],
	] );
} );
