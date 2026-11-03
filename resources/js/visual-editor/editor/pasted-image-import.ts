/**
 * Offers to import pasted external images into the media library (#1066).
 *
 * It's a prompt rather than automatic, so a paste never starts downloads
 * the author didn't ask for. The server does the fetching, because most
 * image hosts don't allow a cross-origin read from the browser, and a
 * failed import leaves that block's external image as it was.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.20.0
 */

import { store as blockEditorStore } from '@wordpress/block-editor';
import type { BlockInstance } from '@wordpress/blocks';
import type { DataRegistry } from '@wordpress/data';
import { __, _n, sprintf } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';

import { getBindingsApiConfig } from '../bindings/config';
import { TEXT_DOMAIN } from '../vendor/i18n';
import { canImportMedia } from './media-import-access';

const IMAGE_BLOCKS = new Set( [ 'artisanpack/image', 'core/image' ] );

/** The longest alt text the import endpoint accepts. */
const MAX_ALT_LENGTH = 1000;

/** An Image block pointing at another site. */
export interface ExternalImage {
    readonly clientId: string;
    readonly url: string;
    readonly alt: string;
}

/**
 * Whether a URL is an image on another site.
 *
 * @since 1.20.0
 *
 * @param url    The block's URL.
 * @param origin This site's origin.
 */
export function isExternalImageUrl( url: unknown, origin: string ): url is string {
    if ( 'string' !== typeof url ) {
        return false;
    }

    try {
        const parsed = new URL( url, origin );

        return [ 'http:', 'https:' ].includes( parsed.protocol ) && origin !== parsed.origin;
    } catch {
        return false;
    }
}

/**
 * The Image blocks with an external URL among the given blocks.
 *
 * @since 1.20.0
 *
 * @param registry  The block editor's registry.
 * @param clientIds The blocks to look at.
 * @param origin    This site's origin.
 */
export function findExternalImages( registry: DataRegistry, clientIds: readonly string[], origin: string ): ExternalImage[] {
    const images: ExternalImage[] = [];

    for ( const clientId of clientIds ) {
        const block = registry.select( blockEditorStore ).getBlock( clientId );

        if (
            null !== block &&
            IMAGE_BLOCKS.has( block.name ) &&
            ! block.attributes.id &&
            isExternalImageUrl( block.attributes.url, origin )
        ) {
            images.push( { clientId, url: block.attributes.url, alt: String( block.attributes.alt ?? '' ) } );
        }
    }

    return images;
}

/**
 * Ask the server to import one image.
 *
 * @since 1.20.0
 *
 * @param image     The image.
 * @param fetchImpl `fetch`, replaceable in tests.
 *
 * @return The new media item.
 */
export async function requestImport(
    image: Pick< ExternalImage, 'url' | 'alt' >,
    fetchImpl: typeof fetch = fetch,
): Promise< { id: number; url: string } > {
    const csrf = document.querySelector( 'meta[name="csrf-token"]' )?.getAttribute( 'content' );

    const response = await fetchImpl( `${ getBindingsApiConfig().apiBase }/media/import`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...( csrf ? { 'X-CSRF-TOKEN': csrf } : {} ),
        },
        body: JSON.stringify( {
            // A protocol-relative URL means nothing to the server.
            url: new URL( image.url, window.location.origin ).href,
            alt: image.alt.slice( 0, MAX_ALT_LENGTH ),
        } ),
    } );

    if ( ! response.ok ) {
        throw new Error( `Media import failed with status ${ response.status }` );
    }

    return ( await response.json() ) as { id: number; url: string };
}

/**
 * The block for an image, if it's still the external image that was offered.
 * Removing it, pointing it elsewhere or picking a media item all opt out.
 */
function pendingImageBlock( registry: DataRegistry, image: ExternalImage ): BlockInstance | null {
    const block = registry.select( blockEditorStore ).getBlock( image.clientId );

    return null !== block && IMAGE_BLOCKS.has( block.name ) && ! block.attributes.id && image.url === block.attributes.url
        ? block
        : null;
}

/**
 * Import images one at a time, swapping in each local URL, and report how
 * it went. A block the author has changed since the offer is skipped, and
 * alt text is read when each import starts, so a later edit is kept.
 *
 * @since 1.20.0
 *
 * @param registry  The block editor's registry.
 * @param images    The images.
 * @param fetchImpl `fetch`, replaceable in tests.
 */
export async function importExternalImages(
    registry: DataRegistry,
    images: readonly ExternalImage[],
    fetchImpl: typeof fetch = fetch,
): Promise< void > {
    let imported = 0;
    let failed   = 0;

    for ( const image of images ) {
        const block = pendingImageBlock( registry, image );

        if ( null === block ) {
            continue;
        }

        try {
            const media = await requestImport( { url: image.url, alt: String( block.attributes.alt ?? '' ) }, fetchImpl );

            if ( null !== pendingImageBlock( registry, image ) ) {
                registry.dispatch( blockEditorStore ).updateBlockAttributes( image.clientId, { id: media.id, url: media.url } );
                imported++;
            }
        } catch {
            // The block keeps its external URL, which is the fallback.
            failed++;
        }
    }

    const notices = registry.dispatch( noticesStore );

    if ( imported > 0 ) {
        notices.createSuccessNotice(
            sprintf(
                /* translators: %d: number of images. */
                _n( '%d image imported.', '%d images imported.', imported, TEXT_DOMAIN ),
                imported,
            ),
            { type: 'snackbar' },
        );
    }

    if ( failed > 0 ) {
        notices.createWarningNotice(
            sprintf(
                /* translators: %d: number of images. */
                _n(
                    "%d image couldn't be imported and still loads from its original address.",
                    "%d images couldn't be imported and still load from their original address.",
                    failed,
                    TEXT_DOMAIN,
                ),
                failed,
            ),
            { type: 'snackbar' },
        );
    }
}

/**
 * Compares the blocks before and after a paste, and offers to import any
 * new external images. Does nothing when the server can't import them.
 *
 * @since 1.20.0
 *
 * @param registry The block editor's registry.
 * @param before   The client IDs from before the paste.
 * @param origin   This site's origin.
 */
export function offerPastedImageImport( registry: DataRegistry, before: ReadonlySet< string >, origin: string ): void {
    if ( ! canImportMedia() ) {
        return;
    }

    const added  = registry.select( blockEditorStore ).getClientIdsWithDescendants().filter( ( clientId: string ) => ! before.has( clientId ) );
    const images = findExternalImages( registry, added, origin );

    if ( 0 === images.length ) {
        return;
    }

    registry.dispatch( noticesStore ).createInfoNotice(
        sprintf(
            /* translators: %d: number of images. */
            _n( '%d pasted image loads from another site.', '%d pasted images load from another site.', images.length, TEXT_DOMAIN ),
            images.length,
        ),
        {
            type: 'snackbar',
            explicitDismiss: true,
            actions: [
                {
                    label: __( 'Import to media library', TEXT_DOMAIN ),
                    onClick: () => void importExternalImages( registry, images ),
                },
            ],
        },
    );
}
