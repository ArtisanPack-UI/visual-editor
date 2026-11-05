<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Resources\TaxonomyRegistry;

it( 'falls back to the category/post_tag defaults when the config is empty', function (): void {
	config()->set( 'artisanpack.visual-editor.taxonomies', [] );

	expect( TaxonomyRegistry::fromConfig() )->toBe( [
		[ 'slug' => 'category', 'label' => 'Category', 'plural' => 'Categories' ],
		[ 'slug' => 'post_tag', 'label' => 'Tag', 'plural' => 'Tags' ],
	] );
} );

it( 'normalises string-label entries and derives the plural', function (): void {
	config()->set( 'artisanpack.visual-editor.taxonomies', [
		'category' => 'Category',
		'genre'    => 'Genre',
	] );

	expect( TaxonomyRegistry::fromConfig() )->toBe( [
		[ 'slug' => 'category', 'label' => 'Category', 'plural' => 'Categories' ],
		[ 'slug' => 'genre', 'label' => 'Genre', 'plural' => 'Genres' ],
	] );
} );

it( 'honours an explicit label and plural from an array entry', function (): void {
	config()->set( 'artisanpack.visual-editor.taxonomies', [
		'topic' => [ 'label' => 'Topic', 'plural' => 'Topical Areas' ],
	] );

	expect( TaxonomyRegistry::fromConfig() )->toBe( [
		[ 'slug' => 'topic', 'label' => 'Topic', 'plural' => 'Topical Areas' ],
	] );
} );

it( 'derives a title-cased label from the slug when none is given', function (): void {
	config()->set( 'artisanpack.visual-editor.taxonomies', [
		'product_line' => [ 'plural' => 'Product Lines' ],
	] );

	expect( TaxonomyRegistry::fromConfig() )->toBe( [
		[ 'slug' => 'product_line', 'label' => 'Product Line', 'plural' => 'Product Lines' ],
	] );
} );

it( 'deduplicates entries whose keys collapse to the same normalised slug', function (): void {
	config()->set( 'artisanpack.visual-editor.taxonomies', [
		'Genre'   => 'Genre',
		' genre ' => 'Padded',
		'genre'   => 'Duplicate',
		'topic'   => 'Topic',
	] );

	expect( TaxonomyRegistry::fromConfig() )->toBe( [
		[ 'slug' => 'genre', 'label' => 'Genre', 'plural' => 'Genres' ],
		[ 'slug' => 'topic', 'label' => 'Topic', 'plural' => 'Topics' ],
	] );
} );

it( 'drops entries whose slug is outside the safe identifier set', function (): void {
	config()->set( 'artisanpack.visual-editor.taxonomies', [
		'bad slug' => 'Bad',
		'genre'    => 'Genre',
	] );

	expect( TaxonomyRegistry::fromConfig() )->toBe( [
		[ 'slug' => 'genre', 'label' => 'Genre', 'plural' => 'Genres' ],
	] );
} );

it( 'keeps a declared postTypes list', function (): void {
	config()->set( 'artisanpack.visual-editor.taxonomies', [
		'package' => [ 'label' => 'Package', 'postTypes' => [ 'post', 'case-study' ] ],
	] );

	expect( TaxonomyRegistry::fromConfig() )->toBe( [
		[ 'slug' => 'package', 'label' => 'Package', 'plural' => 'Packages', 'postTypes' => [ 'post', 'case-study' ] ],
	] );
} );

it( 'drops unsafe and duplicate postTypes values', function (): void {
	config()->set( 'artisanpack.visual-editor.taxonomies', [
		'package' => [ 'label' => 'Package', 'postTypes' => [ ' post ', 'post', 'bad type', 7 ] ],
	] );

	expect( TaxonomyRegistry::fromConfig()[0]['postTypes'] )->toBe( [ 'post' ] );
} );

it( 'omits postTypes when none survive', function (): void {
	config()->set( 'artisanpack.visual-editor.taxonomies', [
		'package' => [ 'label' => 'Package', 'postTypes' => [ 'bad type' ] ],
		'genre'   => [ 'label' => 'Genre', 'postTypes' => 'post' ],
	] );

	expect( TaxonomyRegistry::fromConfig() )->toBe( [
		[ 'slug' => 'package', 'label' => 'Package', 'plural' => 'Packages' ],
		[ 'slug' => 'genre', 'label' => 'Genre', 'plural' => 'Genres' ],
	] );
} );
