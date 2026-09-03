<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Resources\TaxonomyRegistry;

it( 'falls back to the category/post_tag defaults when the config is empty', function () {
	config()->set( 'artisanpack.visual-editor.taxonomies', [] );

	expect( TaxonomyRegistry::fromConfig() )->toBe( [
		[ 'slug' => 'category', 'label' => 'Category', 'plural' => 'Categories' ],
		[ 'slug' => 'post_tag', 'label' => 'Tag', 'plural' => 'Tags' ],
	] );
} );

it( 'normalises string-label entries and derives the plural', function () {
	config()->set( 'artisanpack.visual-editor.taxonomies', [
		'category' => 'Category',
		'genre'    => 'Genre',
	] );

	expect( TaxonomyRegistry::fromConfig() )->toBe( [
		[ 'slug' => 'category', 'label' => 'Category', 'plural' => 'Categories' ],
		[ 'slug' => 'genre', 'label' => 'Genre', 'plural' => 'Genres' ],
	] );
} );

it( 'honours an explicit label and plural from an array entry', function () {
	config()->set( 'artisanpack.visual-editor.taxonomies', [
		'topic' => [ 'label' => 'Topic', 'plural' => 'Topical Areas' ],
	] );

	expect( TaxonomyRegistry::fromConfig() )->toBe( [
		[ 'slug' => 'topic', 'label' => 'Topic', 'plural' => 'Topical Areas' ],
	] );
} );

it( 'derives a title-cased label from the slug when none is given', function () {
	config()->set( 'artisanpack.visual-editor.taxonomies', [
		'product_line' => [ 'plural' => 'Product Lines' ],
	] );

	expect( TaxonomyRegistry::fromConfig() )->toBe( [
		[ 'slug' => 'product_line', 'label' => 'Product Line', 'plural' => 'Product Lines' ],
	] );
} );

it( 'deduplicates entries whose keys collapse to the same normalised slug', function () {
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

it( 'drops entries whose slug is outside the safe identifier set', function () {
	config()->set( 'artisanpack.visual-editor.taxonomies', [
		'bad slug' => 'Bad',
		'genre'    => 'Genre',
	] );

	expect( TaxonomyRegistry::fromConfig() )->toBe( [
		[ 'slug' => 'genre', 'label' => 'Genre', 'plural' => 'Genres' ],
	] );
} );
