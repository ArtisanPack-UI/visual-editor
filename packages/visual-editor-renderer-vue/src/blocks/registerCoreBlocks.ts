/**
 * Core block registration entry point.
 *
 * Calling {@link registerCoreBlocks} populates the shared registry with every
 * `core/*` renderer this package ships. Host apps that want to override a
 * core renderer should call `registerCoreBlocks()` first, then register their
 * own component under the same name.
 *
 * The visual-editor block allow-list (from the package's M5 audit) is frozen
 * for V1, so the map below is exhaustive for every block type the editor can
 * persist.
 */

import { registerBlockRenderer } from '../registry';
import { CalloutBlock } from './artisanpack/callout';
import {
    CoverBlock,
    DetailsBlock,
    MediaTextBlock,
    SearchBlock,
    SeparatorBlock,
    SpacerBlock,
    TableBlock,
} from './core/design';
import {
    ButtonBlock,
    ButtonsBlock,
    ColumnBlock,
    ColumnsBlock,
    GroupBlock,
    RowBlock,
    StackBlock,
} from './core/layout';
import {
    AudioBlock,
    EmbedBlock,
    FileBlock,
    GalleryBlock,
    ImageBlock,
    VideoBlock,
} from './core/media';
import {
    NavigationBlock,
    NavigationLinkBlock,
    NavigationSubmenuBlock,
} from './core/navigation';
import {
    PostAuthorBlock,
    PostContentBlock,
    PostDateBlock,
    PostExcerptBlock,
    PostFeaturedImageBlock,
    PostTitleBlock,
} from './core/postContext';
import { PostTemplateBlock, QueryBlock } from './core/query';
import { SiteLogoBlock, SiteTaglineBlock, SiteTitleBlock } from './core/siteContext';
import { SyncedPatternBlock } from './core/syncedPattern';
import { TemplatePartBlock } from './core/templatePart';
import {
    CodeBlock,
    HeadingBlock,
    ListBlock,
    ListItemBlock,
    ParagraphBlock,
    PreformattedBlock,
    PullquoteBlock,
    QuoteBlock,
    VerseBlock,
} from './core/text';
import type { BlockRenderer } from '../types';

const CORE_BLOCKS: Record<string, BlockRenderer> = {
    'core/paragraph': ParagraphBlock,
    // Fork: artisanpack/* reuses the same renderer as the upstream core/*
    // because the saved markup is byte-equivalent across the two
    // namespaces. Mixed documents render identically regardless of which
    // namespace the editor persisted. Phase I1 content cluster (#409).
    'artisanpack/paragraph': ParagraphBlock,
    'core/heading': HeadingBlock,
    'artisanpack/heading': HeadingBlock,
    'core/list': ListBlock,
    'artisanpack/list': ListBlock,
    'core/list-item': ListItemBlock,
    'artisanpack/list-item': ListItemBlock,
    'core/quote': QuoteBlock,
    'artisanpack/quote': QuoteBlock,
    'core/code': CodeBlock,
    'artisanpack/code': CodeBlock,
    'core/preformatted': PreformattedBlock,
    'artisanpack/preformatted': PreformattedBlock,
    'core/verse': VerseBlock,
    'artisanpack/verse': VerseBlock,
    'core/pullquote': PullquoteBlock,
    'artisanpack/pullquote': PullquoteBlock,
    'core/image': ImageBlock,
    'artisanpack/image': ImageBlock,
    'core/gallery': GalleryBlock,
    'artisanpack/gallery': GalleryBlock,
    'core/video': VideoBlock,
    'artisanpack/video': VideoBlock,
    'core/audio': AudioBlock,
    'artisanpack/audio': AudioBlock,
    'core/file': FileBlock,
    'artisanpack/file': FileBlock,
    'core/embed': EmbedBlock,
    'artisanpack/embed': EmbedBlock,
    'core/group': GroupBlock,
    'artisanpack/group': GroupBlock,
    'core/row': RowBlock,
    'artisanpack/row': RowBlock,
    'core/stack': StackBlock,
    'artisanpack/stack': StackBlock,
    'core/columns': ColumnsBlock,
    'artisanpack/columns': ColumnsBlock,
    'core/column': ColumnBlock,
    'artisanpack/column': ColumnBlock,
    'core/buttons': ButtonsBlock,
    'artisanpack/buttons': ButtonsBlock,
    'core/button': ButtonBlock,
    'artisanpack/button': ButtonBlock,
    'core/cover': CoverBlock,
    'artisanpack/cover': CoverBlock,
    'core/media-text': MediaTextBlock,
    'artisanpack/media-text': MediaTextBlock,
    'core/table': TableBlock,
    'artisanpack/table': TableBlock,
    'core/details': DetailsBlock,
    'artisanpack/details': DetailsBlock,
    'core/search': SearchBlock,
    // Fork: artisanpack/search reuses the core/search renderer, which
    // already carries the #338 button-icon a11y fix, so the forked block's
    // front-end output is byte-equivalent. Phase I4 widgets cluster (#412).
    'artisanpack/search': SearchBlock,
    'core/separator': SeparatorBlock,
    'artisanpack/separator': SeparatorBlock,
    'core/spacer': SpacerBlock,
    'artisanpack/spacer': SpacerBlock,
    'core/template-part': TemplatePartBlock,
    'artisanpack/template-part': TemplatePartBlock,
    'core/query': QueryBlock,
    'core/post-template': PostTemplateBlock,
    'core/block': SyncedPatternBlock,
    'core/post-title': PostTitleBlock,
    'artisanpack/post-title': PostTitleBlock,
    'core/post-content': PostContentBlock,
    'artisanpack/post-content': PostContentBlock,
    'core/post-excerpt': PostExcerptBlock,
    'artisanpack/post-excerpt': PostExcerptBlock,
    'core/post-date': PostDateBlock,
    'artisanpack/post-date': PostDateBlock,
    'core/post-author': PostAuthorBlock,
    'artisanpack/post-author': PostAuthorBlock,
    'core/post-featured-image': PostFeaturedImageBlock,
    'artisanpack/post-featured-image': PostFeaturedImageBlock,
    'core/site-title': SiteTitleBlock,
    'artisanpack/site-title': SiteTitleBlock,
    'core/site-tagline': SiteTaglineBlock,
    'artisanpack/site-tagline': SiteTaglineBlock,
    'core/site-logo': SiteLogoBlock,
    'artisanpack/site-logo': SiteLogoBlock,
    'core/navigation': NavigationBlock,
    'artisanpack/navigation': NavigationBlock,
    'core/navigation-link': NavigationLinkBlock,
    'core/navigation-submenu': NavigationSubmenuBlock,
    'artisanpack/callout': CalloutBlock,
};

export function registerCoreBlocks(): void {
    for (const [name, component] of Object.entries(CORE_BLOCKS)) {
        registerBlockRenderer(name, component);
    }
}

export { CORE_BLOCKS };
