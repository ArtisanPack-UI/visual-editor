<?php

/**
 * #896 — the editor mounts stamp the Jed locale data for the
 * `artisanpack-visual-editor` text domain from
 * `lang/vendor/visual-editor/{locale}.json`.
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Support\EditorLocaleData;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Tests\Fixtures\TestBlockContentModel;

beforeEach( function (): void {
	$this->langPath = sys_get_temp_dir() . '/ve-lang-' . uniqid();
	File::ensureDirectoryExists( $this->langPath . '/vendor/visual-editor' );
	app()->useLangPath( $this->langPath );

	$this->writeLocale = function ( string $locale, mixed $payload ): void {
		File::put( $this->langPath . '/vendor/visual-editor/' . $locale . '.json', is_string( $payload ) ? $payload : json_encode( $payload ) );
	};

	config()->set( 'artisanpack.visual-editor.resources', [ 'posts' => TestBlockContentModel::class ] );
} );

afterEach( function (): void {
	File::deleteDirectory( $this->langPath );
	removeAllFilters( EditorLocaleData::FILTER );
	removeAllFilters( 'ap.visualEditor.editorConfig' );
	app()->setLocale( 'en' );
} );

function frenchMakeJson(): array
{
	return [
		'translation-revision-date' => '2026-10-16 00:00+0000',
		'domain'                    => 'messages',
		'locale_data'               => [
			'messages' => [
				''      => [ 'domain' => 'messages', 'lang' => 'fr', 'plural-forms' => 'nplurals=2; plural=(n > 1);' ],
				'Group' => [ 'Groupe' ],
			],
		],
	];
}

describe( 'EditorLocaleData::resolve()', function (): void {
	it( 'reads the app locale\'s wp i18n make-json file', function (): void {
		( $this->writeLocale )( 'fr', frenchMakeJson() );
		app()->setLocale( 'fr' );

		expect( EditorLocaleData::resolve() )->toBe( [
			''      => [ 'domain' => 'messages', 'lang' => 'fr', 'plural-forms' => 'nplurals=2; plural=(n > 1);' ],
			'Group' => [ 'Groupe' ],
		] );
	} );

	it( 'falls back to the language, then the app fallback locale', function (): void {
		( $this->writeLocale )( 'fr', frenchMakeJson() );

		expect( EditorLocaleData::resolve( 'fr_CA' ) )->toHaveKey( 'Group' );

		config()->set( 'app.fallback_locale', 'fr' );

		expect( EditorLocaleData::resolve( 'de' ) )->toHaveKey( 'Group' );
	} );

	it( 'returns null when no file resolves or it holds no translations', function (): void {
		expect( EditorLocaleData::resolve( 'de' ) )->toBeNull();

		( $this->writeLocale )( 'de', '{not json' );
		expect( EditorLocaleData::resolve( 'de' ) )->toBeNull();

		( $this->writeLocale )( 'de', [ '' => [ 'lang' => 'de' ] ] );
		expect( EditorLocaleData::resolve( 'de' ) )->toBeNull();
	} );

	it( 'never reads outside the locale directory', function (): void {
		File::put( $this->langPath . '/secret.json', json_encode( [ 'Group' => [ 'leak' ] ] ) );

		expect( EditorLocaleData::resolve( '../../secret' ) )->toBeNull();
	} );

	it( 'lets a host supply the data through the filter', function (): void {
		addFilter( EditorLocaleData::FILTER, static fn ( mixed $data, string $locale ): array => [ 'Group' => [ 'Gruppe (' . $locale . ')' ] ] );

		expect( EditorLocaleData::resolve( 'de' ) )->toBe( [ 'Group' => [ 'Gruppe (de)' ] ] );
	} );

	it( 'drops malformed entries', function (): void {
		expect( EditorLocaleData::normalise( [
			''      => [ 'lang' => 'fr' ],
			'Group' => [ 'Groupe' ],
			'Stack' => 'Pile',
			'Row'   => [ null, 'Rangée' ],
			'Grid'  => [ 42 ],
		] ) )->toBe( [
			''      => [ 'lang' => 'fr' ],
			'Group' => [ 'Groupe' ],
			'Row'   => [ 'Rangée' ],
		] );
	} );
} );

describe( 'mounts (#896)', function (): void {
	it( 'stamps data-locale-data on <x-visual-editor>', function (): void {
		( $this->writeLocale )( 'fr', frenchMakeJson() );
		app()->setLocale( 'fr' );

		$model = TestBlockContentModel::create( [ 'title' => 'Locale', 'status' => 'published', 'content' => [] ] );
		$html  = Blade::render( '<x-visual-editor :model="$model" />', [ 'model' => $model ] );

		preg_match( '/data-locale-data="([^"]*)"/', $html, $matches );

		expect( $matches )->toHaveKey( 1 );
		expect( json_decode( html_entity_decode( $matches[1], ENT_QUOTES ), true ) )->toMatchArray( [ 'Group' => [ 'Groupe' ] ] );
	} );

	it( 'omits the attribute without a translation', function (): void {
		$model = TestBlockContentModel::create( [ 'title' => 'Locale', 'status' => 'published', 'content' => [] ] );

		expect( Blade::render( '<x-visual-editor :model="$model" />', [ 'model' => $model ] ) )->not->toContain( 'data-locale-data' );
	} );

	it( 'stamps it on the /editor/mount view and the site editor', function ( string $view, array $data ): void {
		( $this->writeLocale )( 'fr', frenchMakeJson() );
		app()->setLocale( 'fr' );
		$this->withoutVite();

		expect( view( $view, $data )->render() )->toContain( 'data-locale-data="{' );
	} )->with( [
		'post editor' => [ 'visual-editor::editor.mount', [ 'resource' => 'posts', 'modelId' => '1', 'apiBase' => '/visual-editor/api' ] ],
		'site editor' => [ 'visual-editor::site-editor.index', [] ],
	] );
} );

it( 'keeps a numeric msgid (#896)', function (): void {
	expect( EditorLocaleData::normalise( json_decode( '{"404":["Introuvable"]}', true ) ) )->toBe( [ '404' => [ 'Introuvable' ] ] );
} );
