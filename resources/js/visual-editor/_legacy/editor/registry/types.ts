/**
 * TypeScript types for the block.json manifest schema and block registration.
 *
 * Mirrors the WordPress Gutenberg block.json specification to ensure
 * forward-compatibility and familiarity for developers coming from WordPress.
 * Unknown keys in `supports` are stored but ignored — the schema is designed
 * to be extended without breaking existing code.
 */

// ---------------------------------------------------------------------------
// Attribute schema
// ---------------------------------------------------------------------------

export interface BlockAttributeSchema {
    type?: string;
    source?: 'attribute' | 'text' | 'html' | 'rich-text' | 'query' | 'meta';
    selector?: string;
    attribute?: string;
    multiline?: string;
    query?: Record<string, BlockAttributeSchema>;
    default?: unknown;
    enum?: readonly unknown[];
    role?: 'content' | 'local';
}

// ---------------------------------------------------------------------------
// Supports — WordPress-parity keys
// ---------------------------------------------------------------------------

export interface TypographySupports {
    fontSize?: boolean;
    lineHeight?: boolean;
    textAlign?: boolean | string[];
    textColumns?: boolean;
    textIndent?: boolean;
    fitText?: boolean;
    fontFamily?: boolean;
    fontStyle?: boolean;
    fontWeight?: boolean;
    letterSpacing?: boolean;
    textDecoration?: boolean;
    textTransform?: boolean;
    writingMode?: boolean;
    /** @internal Gutenberg experimental prefix retained for compat. */
    __experimentalFontFamily?: boolean;
    __experimentalFontStyle?: boolean;
    __experimentalFontWeight?: boolean;
    __experimentalLetterSpacing?: boolean;
    __experimentalTextDecoration?: boolean;
    __experimentalTextTransform?: boolean;
    __experimentalWritingMode?: boolean;
    __experimentalDefaultControls?: Record<string, boolean>;
}

export interface ColorSupports {
    background?: boolean;
    text?: boolean;
    gradients?: boolean;
    link?: boolean;
    heading?: boolean;
    button?: boolean;
    enableContrastChecker?: boolean;
    __experimentalDefaultControls?: Record<string, boolean>;
}

export interface SpacingSupports {
    margin?: boolean | string[];
    padding?: boolean | string[];
    blockGap?: boolean | { __experimentalDefault?: string; sides?: string[] };
    __experimentalDefaultControls?: Record<string, boolean>;
}

export interface DimensionsSupports {
    width?: boolean;
    height?: boolean;
    minHeight?: boolean;
    aspectRatio?: boolean;
}

export interface BorderSupports {
    color?: boolean;
    radius?: boolean;
    style?: boolean;
    width?: boolean;
    __experimentalDefaultControls?: Record<string, boolean>;
}

export interface LayoutSupports {
    default?: Record<string, unknown>;
    allowSwitching?: boolean;
    allowEditing?: boolean;
    allowInheriting?: boolean;
    allowSizingOnChildren?: boolean;
    allowVerticalAlignment?: boolean;
    allowJustification?: boolean;
    allowOrientation?: boolean;
    allowWrap?: boolean;
    allowCustomContentAndWideSize?: boolean;
}

export interface BlockSupports {
    // Layout & alignment
    align?: boolean | string[];
    alignWide?: boolean;
    layout?: boolean | LayoutSupports;

    // Typography, color, spacing, dimensions, border, shadow
    typography?: boolean | TypographySupports;
    color?: boolean | ColorSupports;
    spacing?: boolean | SpacingSupports;
    dimensions?: boolean | DimensionsSupports;
    border?: boolean | BorderSupports;
    shadow?: boolean | Record<string, unknown>;
    background?: boolean | { backgroundImage?: boolean; backgroundSize?: boolean };

    // Behavior
    anchor?: boolean;
    className?: boolean;
    customClassName?: boolean;
    html?: boolean;
    inserter?: boolean;
    multiple?: boolean;
    reusable?: boolean;
    lock?: boolean;
    renaming?: boolean;
    splitting?: boolean;

    // Gutenberg experimental / unstable flags (kept for compat)
    __experimentalBorder?: boolean | BorderSupports;
    __experimentalSelector?: string;
    __experimentalSlashInserter?: boolean;
    __experimentalOnEnter?: boolean;
    __experimentalOnMerge?: boolean;
    __unstablePasteTextInline?: boolean;
    interactivity?: boolean | { clientNavigation?: boolean; interactive?: boolean };

    // Forward-compatible: unknown keys are stored but ignored
    [key: string]: unknown;
}

// ---------------------------------------------------------------------------
// Style variants
// ---------------------------------------------------------------------------

export interface BlockStyle {
    name: string;
    label: string;
    isDefault?: boolean;
}

// ---------------------------------------------------------------------------
// Block variation
// ---------------------------------------------------------------------------

export interface BlockVariation {
    name: string;
    title: string;
    description?: string;
    category?: string;
    icon?: string;
    isDefault?: boolean;
    attributes?: Record<string, unknown>;
    innerBlocks?: unknown[];
    example?: Record<string, unknown>;
    scope?: Array<'block' | 'inserter' | 'transform'>;
    keywords?: string[];
    isActive?: string[] | ((blockAttributes: Record<string, unknown>, variationAttributes: Record<string, unknown>) => boolean);
}

// ---------------------------------------------------------------------------
// Block example (preview data)
// ---------------------------------------------------------------------------

export interface BlockExample {
    viewportWidth?: number;
    attributes?: Record<string, unknown>;
    innerBlocks?: unknown[];
}

// ---------------------------------------------------------------------------
// block.json metadata
// ---------------------------------------------------------------------------

export interface BlockJsonMetadata {
    // Required
    apiVersion?: number;
    name: string;
    title: string;

    // Metadata
    category?: string;
    description?: string;
    keywords?: string[];
    icon?: string;
    textdomain?: string;
    version?: string;

    // Nesting
    parent?: string[];
    ancestor?: string[];
    allowedBlocks?: string[];

    // Assets
    editorScript?: string | string[];
    editorStyle?: string | string[];
    script?: string | string[];
    style?: string | string[];
    viewScript?: string | string[];
    viewScriptModule?: string | string[];
    viewStyle?: string | string[];
    render?: string;

    // Configuration
    attributes?: Record<string, BlockAttributeSchema>;
    supports?: BlockSupports;
    selectors?: Record<string, unknown>;
    providesContext?: Record<string, string>;
    usesContext?: string[];
    styles?: BlockStyle[];
    variations?: BlockVariation[] | string;
    example?: BlockExample;
    blockHooks?: Record<string, 'before' | 'after' | 'firstChild' | 'lastChild'>;

    // Forward-compatible
    [key: string]: unknown;
}
