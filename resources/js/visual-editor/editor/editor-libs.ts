/**
 * The editor runtime singletons, exposed on
 * `window.ApVisualEditor.libs` for the client block escape hatch (#766).
 *
 * A host that registers a block through
 * `window.ApVisualEditor.registerBlockType` must build its `edit`/`save`
 * against the *same* React and `@wordpress/*` instances this bundle already
 * loaded — two copies of `@wordpress/element` or `@wordpress/data` would each
 * hold their own hook dispatcher and store registry, and the host's controls
 * would render detached from the editor. Rather than ask hosts to externalize
 * and version-match every package, the editor hands its own singletons back
 * (the way WordPress exposes `wp.element`, `wp.components`, …). A host reads
 * what it needs from here instead of bundling its own copy.
 */

import * as blockEditor from '@wordpress/block-editor';
import * as blocks from '@wordpress/blocks';
import * as components from '@wordpress/components';
import * as data from '@wordpress/data';
import * as element from '@wordpress/element';
import * as hooks from '@wordpress/hooks';
import * as i18n from '@wordpress/i18n';

import { ServerSideRender } from './server-side-render';

/** The shape handed to hosts on `window.ApVisualEditor.libs`. */
export interface EditorLibs {
    readonly element: typeof element;
    readonly components: typeof components;
    readonly blockEditor: typeof blockEditor;
    readonly blocks: typeof blocks;
    readonly data: typeof data;
    readonly hooks: typeof hooks;
    readonly i18n: typeof i18n;
    readonly ServerSideRender: typeof ServerSideRender;
}

/**
 * The singletons a host block builds against. Frozen so a host cannot mutate
 * another host's view of the runtime.
 */
export const editorLibs: EditorLibs = Object.freeze({
    element,
    components,
    blockEditor,
    blocks,
    data,
    hooks,
    i18n,
    ServerSideRender,
});
