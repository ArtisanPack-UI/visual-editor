#!/usr/bin/env node
/**
 * sync-social-link-services — build the Social Icons service registries.
 *
 * The `artisanpack/social-link` block (#1023) needs the same service list
 * (slug, label, glyph) in four places: the editor bundle and the Blade,
 * React and Vue renderers, each of which ships as its own package and
 * cannot import the others. This script is the single source of truth:
 * it reads the glyphs from the tracked Font Awesome Free SVGs under
 * `resources/icons/font-awesome/` (CC BY 4.0, see NOTICE.md) and writes
 * one generated registry per consumer.
 *
 * `artisanpack/author-social-icons` and `artisanpack/social-share-content`
 * draw from the same services (#1027), so a brand looks the same in every
 * social block. Their saved slugs predate the service list, so each of
 * their platforms names the service that supplies its glyph.
 *
 * Usage:
 *   node scripts/sync-social-link-services.mjs          write the registries
 *   node scripts/sync-social-link-services.mjs --check  exit 1 if any is stale
 *
 * The service list and order follow WordPress's `core/social-link`
 * variations so content and muscle memory carry over; the glyphs do not
 * (WordPress's are GPL), only the slugs and names.
 */

import { existsSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, relative, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const PACKAGE_ROOT = resolve( dirname( fileURLToPath( import.meta.url ) ), '..' )
const ICON_ROOT = resolve( PACKAGE_ROOT, 'resources/icons/font-awesome' )
const CHECK = process.argv.includes( '--check' )

// Gravatar has no Font Awesome Free glyph; this one is from Simple Icons
// (https://simpleicons.org, CC0 1.0).
const GRAVATAR = {
	viewBox: '0 0 24 24',
	path: 'M12 0c-1.326 0-2.4 1.074-2.4 2.4v8.4c0 1.324 1.074 2.398 2.4 2.398s2.4-1.074 2.4-2.398V5.21c2.795.99 4.799 3.654 4.799 6.789 0 3.975-3.225 7.199-7.199 7.199S4.801 15.975 4.801 12c0-1.989.805-3.789 2.108-5.091.938-.938.938-2.458 0-3.396s-2.458-.938-3.396 0C1.344 5.686 0 8.686 0 12c0 6.627 5.373 12 12 12s12-5.373 12-12S18.627 0 12 0',
}

// [ slug, label, glyph ] — glyph is a `set/name` Font Awesome reference
// or an inline `{ viewBox, path }`. The first entry is the default
// inserter variation.
const SERVICES = [
	[ 'wordpress', 'WordPress', 'fab/wordpress' ],
	[ 'fivehundredpx', '500px', 'fab/500px' ],
	[ 'amazon', 'Amazon', 'fab/amazon' ],
	[ 'bandcamp', 'Bandcamp', 'fab/bandcamp' ],
	[ 'behance', 'Behance', 'fab/behance' ],
	[ 'bluesky', 'Bluesky', 'fab/bluesky' ],
	[ 'chain', 'Link', 'fas/link' ],
	[ 'codepen', 'CodePen', 'fab/codepen' ],
	[ 'deviantart', 'DeviantArt', 'fab/deviantart' ],
	[ 'discord', 'Discord', 'fab/discord' ],
	[ 'dribbble', 'Dribbble', 'fab/dribbble' ],
	[ 'dropbox', 'Dropbox', 'fab/dropbox' ],
	[ 'etsy', 'Etsy', 'fab/etsy' ],
	[ 'facebook', 'Facebook', 'fab/facebook' ],
	[ 'feed', 'RSS Feed', 'fas/rss' ],
	[ 'flickr', 'Flickr', 'fab/flickr' ],
	[ 'foursquare', 'Foursquare', 'fab/foursquare' ],
	[ 'goodreads', 'Goodreads', 'fab/goodreads' ],
	[ 'google', 'Google', 'fab/google' ],
	[ 'github', 'GitHub', 'fab/github' ],
	[ 'gravatar', 'Gravatar', GRAVATAR ],
	[ 'instagram', 'Instagram', 'fab/instagram' ],
	[ 'lastfm', 'Last.fm', 'fab/lastfm' ],
	[ 'linkedin', 'LinkedIn', 'fab/linkedin' ],
	[ 'mail', 'Mail', 'fas/envelope' ],
	[ 'mastodon', 'Mastodon', 'fab/mastodon' ],
	[ 'meetup', 'Meetup', 'fab/meetup' ],
	[ 'medium', 'Medium', 'fab/medium' ],
	[ 'patreon', 'Patreon', 'fab/patreon' ],
	[ 'pinterest', 'Pinterest', 'fab/pinterest' ],
	[ 'pocket', 'Pocket', 'fab/get-pocket' ],
	[ 'reddit', 'Reddit', 'fab/reddit' ],
	[ 'skype', 'Skype', 'fab/skype' ],
	[ 'snapchat', 'Snapchat', 'fab/snapchat' ],
	[ 'soundcloud', 'SoundCloud', 'fab/soundcloud' ],
	[ 'spotify', 'Spotify', 'fab/spotify' ],
	[ 'telegram', 'Telegram', 'fab/telegram' ],
	[ 'threads', 'Threads', 'fab/threads' ],
	[ 'tiktok', 'TikTok', 'fab/tiktok' ],
	[ 'tumblr', 'Tumblr', 'fab/tumblr' ],
	[ 'twitch', 'Twitch', 'fab/twitch' ],
	[ 'twitter', 'Twitter', 'fab/twitter' ],
	[ 'vimeo', 'Vimeo', 'fab/vimeo' ],
	[ 'vk', 'VK', 'fab/vk' ],
	[ 'whatsapp', 'WhatsApp', 'fab/whatsapp' ],
	[ 'x', 'X', 'fab/x-twitter' ],
	[ 'yelp', 'Yelp', 'fab/yelp' ],
	[ 'youtube', 'YouTube', 'fab/youtube' ],
]

// Keywords mirror the upstream variations that declare any.
const KEYWORDS = {
	mail: [ 'email', 'e-mail' ],
	x: [ 'twitter' ],
}

// [ slug, service, label ] — the slug is what the block saves in its
// `socialIcons` attribute and the chip's class, so it can't change. The
// service supplies the glyph, and its label is used unless one is given.
const AUTHOR_PLATFORMS = [
	[ 'facebook', 'facebook' ],
	[ 'twitter', 'x' ],
	[ 'mastodon', 'mastodon' ],
	[ 'instagram', 'instagram' ],
	[ 'tumblr', 'tumblr' ],
	[ 'email', 'mail', 'Email' ],
	[ 'website', 'chain', 'Website' ],
]

// `copyLink` is a copy-to-clipboard chip rather than a share target; its
// resolved `url` is the bare permalink (#963).
const SHARE_PLATFORMS = [
	[ 'facebook', 'facebook' ],
	[ 'twitter', 'x' ],
	[ 'linkedin', 'linkedin' ],
	[ 'mastodon', 'mastodon' ],
	[ 'reddit', 'reddit' ],
	[ 'pinterest', 'pinterest' ],
	[ 'email', 'mail', 'Email' ],
	[ 'copyLink', 'chain', 'Copy link' ],
]

const HEADER = 'Generated by scripts/sync-social-link-services.mjs from the service list there — do not edit by hand.'

function fail( message ) {
	console.error( `[sync-social-link-services] ${ message }` )
	process.exit( 1 )
}

function readGlyph( glyph ) {
	if ( 'object' === typeof glyph ) {
		return glyph
	}

	const file = resolve( ICON_ROOT, `${ glyph }.svg` )

	if ( ! existsSync( file ) ) {
		fail( `Missing glyph ${ glyph } (${ relative( PACKAGE_ROOT, file ) }). Run \`npm run sync:fa-icons\` first.` )
	}

	const svg = readFileSync( file, 'utf8' )
	const viewBox = svg.match( /viewBox="([^"]+)"/ )?.[ 1 ]
	const paths = [ ...svg.matchAll( /<path[^>]*\sd="([^"]+)"/g ) ].map( ( match ) => match[ 1 ] )

	if ( ! viewBox || 1 !== paths.length ) {
		fail( `${ glyph } must have a viewBox and exactly one <path>.` )
	}

	return { viewBox, path: paths[ 0 ] }
}

const services = SERVICES.map( ( [ slug, label, glyph ] ) => ( { slug, label, ...readGlyph( glyph ) } ) )

for ( const [ slug, service ] of [ ...AUTHOR_PLATFORMS, ...SHARE_PLATFORMS ] ) {
	if ( ! services.some( ( candidate ) => service === candidate.slug ) ) {
		fail( `Platform ${ slug } names an unknown service: ${ service }.` )
	}
}

const js = ( value ) => `'${ value.replace( /\\/g, '\\\\' ).replace( /'/g, "\\'" ) }'`

function editorPlatforms( platforms ) {
	return platforms.map( ( [ slug, service, label ] ) => {
		const translated = label ? `, __(${ js( label ) }, TEXT_DOMAIN)` : ''

		return `        [${ js( slug ) }, ${ js( service ) }${ translated }],`
	} ).join( '\n' )
}

function rendererPlatforms( platforms ) {
	return platforms.map( ( [ slug, service, label ] ) =>
		`    ${ js( slug ) }: { service: ${ js( service ) }${ label ? `, label: ${ js( label ) }` : '' } },`
	).join( '\n' )
}

function phpPlatforms( platforms ) {
	const width = Math.max( ...platforms.map( ( [ slug ] ) => js( slug ).length ) )

	return platforms.map( ( [ slug, service, label ] ) =>
		`\t\t${ js( slug ).padEnd( width ) } => [ 'service' => ${ js( service ) }${ label ? `, 'label' => ${ js( label ) }` : '' } ],`
	).join( '\n' )
}

function editorModule() {
	const entries = services.map( ( { slug, label, viewBox, path } ) => [
		'        {',
		`            slug: ${ js( slug ) },`,
		`            label: _x(${ js( label ) }, 'social link block variation name', TEXT_DOMAIN),`,
		...( KEYWORDS[ slug ] ? [ `            keywords: [${ KEYWORDS[ slug ].map( js ).join( ', ' ) }],` ] : [] ),
		`            viewBox: ${ js( viewBox ) },`,
		`            path: ${ js( path ) },`,
		'        },',
	].join( '\n' ) )

	return `/**
 * Social Icons service registry — editor (#1023).
 *
 * ${ HEADER }
 * Glyphs: Font Awesome Free (CC BY 4.0) and, for Gravatar, Simple Icons
 * (CC0 1.0) — see NOTICE.md.
 */

import { __, _x } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';

export interface SocialLinkService {
    readonly slug: string;
    readonly label: string;
    readonly keywords?: ReadonlyArray<string>;
    readonly viewBox: string;
    readonly path: string;
}

/** A platform chip in the Author Social Icons or Social Share block (#1027). */
export interface SocialIconDefinition {
    readonly slug: string;
    readonly label: string;
    readonly viewBox: string;
    readonly path: string;
}

/** Saved platform slug, the service that draws it, and any label of its own. */
type SocialIconEntry = readonly [slug: string, service: string, label?: string];

export const AUTHOR_SOCIAL_PLATFORM_SLUGS: ReadonlyArray<string> = [${ AUTHOR_PLATFORMS.map( ( [ slug ] ) => js( slug ) ).join( ', ' ) }];

export const SHARE_SOCIAL_PLATFORM_SLUGS: ReadonlyArray<string> = [${ SHARE_PLATFORMS.map( ( [ slug ] ) => js( slug ) ).join( ', ' ) }];

/**
 * Every service, in inserter order. A function so the labels translate
 * against the locale active when it is called.
 */
export function socialLinkServices(): ReadonlyArray<SocialLinkService> {
    return [
${ entries.join( '\n' ) }
    ];
}

function socialIcons(entries: ReadonlyArray<SocialIconEntry>): ReadonlyArray<SocialIconDefinition> {
    const services = new Map(socialLinkServices().map((service) => [service.slug, service]));

    return entries.map(([slug, service, label]) => {
        const glyph = services.get(service) as SocialLinkService;

        return { slug, label: label ?? glyph.label, viewBox: glyph.viewBox, path: glyph.path };
    });
}

/**
 * The Author Social Icons platforms, in picker order. A function for the
 * same reason as \`socialLinkServices()\`.
 */
export function authorSocialPlatforms(): ReadonlyArray<SocialIconDefinition> {
    return socialIcons([
${ editorPlatforms( AUTHOR_PLATFORMS ) }
    ]);
}

/**
 * The Social Share platforms, in picker order. A function for the same
 * reason as \`socialLinkServices()\`.
 */
export function shareSocialPlatforms(): ReadonlyArray<SocialIconDefinition> {
    return socialIcons([
${ editorPlatforms( SHARE_PLATFORMS ) }
    ]);
}
`
}

function rendererModule( renderer ) {
	const entries = services.map( ( { slug, label, viewBox, path } ) =>
		`    ${ js( slug ) }: { label: ${ js( label ) }, viewBox: ${ js( viewBox ) }, path: ${ js( path ) } },`
	)

	return `/**
 * Social Icons service registry — ${ renderer } renderer (#1023).
 *
 * ${ HEADER }
 * Glyphs: Font Awesome Free (CC BY 4.0) and, for Gravatar, Simple Icons
 * (CC0 1.0) — see NOTICE.md in the visual-editor package.
 */

export interface SocialLinkService {
    readonly label: string;
    readonly viewBox: string;
    readonly path: string;
}

export const SOCIAL_LINK_SERVICES: Readonly<Record<string, SocialLinkService>> = {
${ entries.join( '\n' ) }
};

/**
 * The service for \`slug\`, falling back to the generic link glyph for an
 * unknown service so a link is never dropped for want of an icon.
 */
export function getSocialLinkService(slug: string): SocialLinkService {
    return Object.prototype.hasOwnProperty.call(SOCIAL_LINK_SERVICES, slug)
        ? SOCIAL_LINK_SERVICES[slug]
        : SOCIAL_LINK_SERVICES.chain;
}

interface SocialPlatform {
    readonly service: string;
    readonly label?: string;
}

const AUTHOR_PLATFORMS: Readonly<Record<string, SocialPlatform>> = {
${ rendererPlatforms( AUTHOR_PLATFORMS ) }
};

const SHARE_PLATFORMS: Readonly<Record<string, SocialPlatform>> = {
${ rendererPlatforms( SHARE_PLATFORMS ) }
};

function platformIcon(
    platforms: Readonly<Record<string, SocialPlatform>>,
    slug: string
): SocialLinkService | undefined {
    if (!Object.prototype.hasOwnProperty.call(platforms, slug)) {
        return undefined;
    }

    const { service, label } = platforms[slug];
    const glyph = SOCIAL_LINK_SERVICES[service];

    return { ...glyph, label: label ?? glyph.label };
}

/** The chip for an Author Social Icons platform (#1027). */
export function getAuthorIcon(slug: string): SocialLinkService | undefined {
    return platformIcon(AUTHOR_PLATFORMS, slug);
}

/** The chip for a Social Share platform (#1027). */
export function getShareIcon(slug: string): SocialLinkService | undefined {
    return platformIcon(SHARE_PLATFORMS, slug);
}
`
}

function phpModule() {
	const php = ( value ) => `'${ value.replace( /\\/g, '\\\\' ).replace( /'/g, "\\'" ) }'`
	const width = Math.max( ...services.map( ( { slug } ) => php( slug ).length ) )
	const entries = services.map( ( { slug, label, viewBox, path } ) =>
		`\t\t${ php( slug ).padEnd( width ) } => [ 'label' => ${ php( label ) }, 'viewBox' => ${ php( viewBox ) }, 'path' => ${ php( path ) } ],`
	)

	return `<?php

/**
 * Social Icons service registry — Blade renderer (#1023).
 *
 * ${ HEADER }
 * Glyphs: Font Awesome Free (CC BY 4.0) and, for Gravatar, Simple Icons
 * (CC0 1.0) — see NOTICE.md in the visual-editor package.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @since      1.18.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\\VisualEditorRendererBlade\\Support;

/**
 * Service slug → label and glyph for the \`artisanpack/social-link\`,
 * \`artisanpack/author-social-icons\` and
 * \`artisanpack/social-share-content\` blocks.
 *
 * @since 1.18.0
 */
final class SocialLinkServices
{
	/**
	 * @var array<string, array{label: string, viewBox: string, path: string}>
	 */
	public const SERVICES = [
${ entries.join( '\n' ) }
	];

	/**
	 * Author Social Icons platform slug → service and any label of its own.
	 *
	 * @since 1.20.0
	 *
	 * @var array<string, array{service: string, label?: string}>
	 */
	public const AUTHOR_PLATFORMS = [
${ phpPlatforms( AUTHOR_PLATFORMS ) }
	];

	/**
	 * Social Share platform slug → service and any label of its own.
	 *
	 * @since 1.20.0
	 *
	 * @var array<string, array{service: string, label?: string}>
	 */
	public const SHARE_PLATFORMS = [
${ phpPlatforms( SHARE_PLATFORMS ) }
	];

	/**
	 * The service for \`$slug\`, falling back to the generic link glyph for
	 * an unknown service so a link is never dropped for want of an icon.
	 *
	 * @since 1.18.0
	 *
	 * @return array{label: string, viewBox: string, path: string}
	 */
	public static function get( string $slug ): array
	{
		return self::SERVICES[ $slug ] ?? self::SERVICES['chain'];
	}

	/**
	 * The chip for an Author Social Icons platform.
	 *
	 * @since 1.20.0
	 *
	 * @return array{label: string, viewBox: string, path: string}|null
	 */
	public static function author( string $slug ): ?array
	{
		return self::platform( self::AUTHOR_PLATFORMS[ $slug ] ?? null );
	}

	/**
	 * The chip for a Social Share platform.
	 *
	 * @since 1.20.0
	 *
	 * @return array{label: string, viewBox: string, path: string}|null
	 */
	public static function share( string $slug ): ?array
	{
		return self::platform( self::SHARE_PLATFORMS[ $slug ] ?? null );
	}

	/**
	 * @param  array{service: string, label?: string}|null  $platform
	 *
	 * @return array{label: string, viewBox: string, path: string}|null
	 */
	private static function platform( ?array $platform ): ?array
	{
		if ( null === $platform ) {
			return null;
		}

		$glyph = self::SERVICES[ $platform['service'] ];

		return [
			'label'   => $platform['label'] ?? $glyph['label'],
			'viewBox' => $glyph['viewBox'],
			'path'    => $glyph['path'],
		];
	}
}
`
}

const OUTPUTS = [
	[ 'resources/js/visual-editor/blocks/social-link/services.ts', editorModule() ],
	[ 'packages/visual-editor-renderer-blade/src/Support/SocialLinkServices.php', phpModule() ],
	[ 'packages/visual-editor-renderer-react/src/blocks/artisanpack/socialLinkServices.ts', rendererModule( 'React' ) ],
	[ 'packages/visual-editor-renderer-vue/src/blocks/artisanpack/socialLinkServices.ts', rendererModule( 'Vue' ) ],
]

let stale = 0

for ( const [ target, contents ] of OUTPUTS ) {
	const file = resolve( PACKAGE_ROOT, target )
	const current = existsSync( file ) ? readFileSync( file, 'utf8' ) : null

	if ( current === contents ) {
		continue
	}

	if ( CHECK ) {
		console.error( `[sync-social-link-services] ${ target } is out of date.` )
		stale++
		continue
	}

	writeFileSync( file, contents )
	console.log( `[sync-social-link-services] wrote ${ target }` )
}

if ( CHECK && stale > 0 ) {
	fail( `${ stale } registr${ 1 === stale ? 'y is' : 'ies are' } stale — run \`npm run sync:social-links\`.` )
}
