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
    // Fork: artisanpack/paragraph reuses the same renderer as core/paragraph.
    // Both blocks emit identical markup so mixed documents (during the V2
    // migration window) render identically regardless of which namespace
    // the editor persisted.
    'artisanpack/paragraph': ParagraphBlock,
    'core/heading': HeadingBlock,
    'core/list': ListBlock,
    'core/list-item': ListItemBlock,
    'core/quote': QuoteBlock,
    'core/code': CodeBlock,
    'core/preformatted': PreformattedBlock,
    'core/verse': VerseBlock,
    'core/pullquote': PullquoteBlock,
    'core/image': ImageBlock,
    'core/gallery': GalleryBlock,
    'core/video': VideoBlock,
    'core/audio': AudioBlock,
    'core/file': FileBlock,
    'core/embed': EmbedBlock,
    'core/group': GroupBlock,
    'core/row': RowBlock,
    'core/stack': StackBlock,
    'core/columns': ColumnsBlock,
    'core/column': ColumnBlock,
    'core/buttons': ButtonsBlock,
    'core/button': ButtonBlock,
    'core/cover': CoverBlock,
    'core/media-text': MediaTextBlock,
    'core/table': TableBlock,
    'core/details': DetailsBlock,
    'core/search': SearchBlock,
    'core/separator': SeparatorBlock,
    'core/spacer': SpacerBlock,
    'core/template-part': TemplatePartBlock,
    'core/block': SyncedPatternBlock,
    'core/query': QueryBlock,
    'core/post-template': PostTemplateBlock,
    'core/post-title': PostTitleBlock,
    'core/post-content': PostContentBlock,
    'core/post-excerpt': PostExcerptBlock,
    'core/post-date': PostDateBlock,
    'core/post-author': PostAuthorBlock,
    'core/post-featured-image': PostFeaturedImageBlock,
    'core/site-title': SiteTitleBlock,
    'core/site-tagline': SiteTaglineBlock,
    'core/site-logo': SiteLogoBlock,
    'core/navigation': NavigationBlock,
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
