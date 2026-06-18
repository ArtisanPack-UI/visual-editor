/**
 * Shared masonry layout module (#593).
 *
 * Re-exports the JS fallback and the shared CSS so the post-template
 * and grid block entrypoints can pull from a single path:
 *
 *     import { initMasonry } from '../_shared/masonry';
 *     import '../_shared/masonry/masonry.css';
 */

export {
    initMasonry,
    supportsNativeMasonry,
    type MasonryController,
    type MasonryOptions,
} from './fallback';
