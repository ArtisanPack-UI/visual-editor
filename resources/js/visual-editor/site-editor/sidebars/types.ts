/**
 * Shared types for the H6 minimal inspector sidebars (#431).
 *
 * Each shape mirrors the WP REST envelope produced by visual-editor's
 * H6 controllers (templates, parts, patterns, global-styles, menus,
 * menu-items) — they're the typed counterpart to the PHP adapter
 * outputs in `Http/Resources/Adapters/CmsFramework/SiteEditor/`.
 *
 * Kept loose / readonly because the sidebars only render document
 * settings — they don't drive the canvas. H7's UI rescope is the
 * natural place to expand these into editable views.
 */

export interface EntityTitle {
    readonly rendered: string;
    readonly raw?: string;
}

export interface EntityContent {
    readonly raw: string;
    readonly blocks: readonly unknown[];
}

export interface TemplateSidebarRecord {
    readonly id: number | string;
    readonly slug: string;
    readonly type: 'wp_template';
    readonly source: string;
    readonly origin: string | null;
    readonly title: EntityTitle;
    readonly description: string;
    readonly content: EntityContent;
    readonly status: string;
    readonly theme: string;
    readonly has_theme_file: boolean;
    readonly is_custom: boolean;
}

export interface TemplatePartSidebarRecord extends Omit<TemplateSidebarRecord, 'type'> {
    readonly type: 'wp_template_part';
    readonly area: string;
}

export interface PatternSidebarRecord {
    readonly id: number | string;
    readonly slug: string;
    readonly type: 'wp_block';
    readonly status: string;
    readonly title: EntityTitle;
    readonly content: EntityContent;
    readonly source: string;
    readonly synced: boolean;
    readonly categories: readonly string[];
    readonly block_types: readonly string[];
}

export interface GlobalStylesSidebarRecord {
    readonly id: number | string;
    readonly theme: string;
    readonly settings: Readonly<Record<string, unknown>>;
    readonly styles: Readonly<Record<string, unknown>>;
    readonly variations: readonly Readonly<Record<string, unknown>>[];
}

export interface MenuSidebarRecord {
    readonly id: number;
    readonly slug: string;
    readonly theme: string;
    readonly name: string;
    readonly description: string;
    readonly type: 'wp_navigation';
    readonly status: string;
    readonly title: EntityTitle;
    readonly auto_add_pages: boolean;
}
