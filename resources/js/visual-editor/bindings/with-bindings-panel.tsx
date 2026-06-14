/**
 * `editor.BlockEdit` HOC — injects the {@link BindingsPanel} into every
 * block's inspector (#504).
 *
 * The panel surfaces every scalar attribute the block declares (string,
 * number, boolean) and gives the author a "link to data" toggle per
 * attribute. The bindings map is persisted onto the block via
 * `setAttributes( { bindings } )` so the server-side
 * `BindingResolver` can read it at render time.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.1.0
 */

import { InspectorControls } from '@wordpress/block-editor';
import { getBlockType } from '@wordpress/blocks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { addFilter } from '@wordpress/hooks';
import type { ComponentType } from 'react';

import { BindingsPanel, type BindableAttribute } from './BindingsPanel';
import { setBindingsApiConfig } from './config';
import type { BindingsMap } from './types';
import { useResolvedBindings } from './use-resolved-bindings';

const FILTER_HOOK      = 'editor.BlockEdit';
const FILTER_NAMESPACE = 'artisanpack-ui/visual-editor/bindings-panel';

const REGISTERED_KEY = Symbol.for(
	'artisanpack-ui.visual-editor.bindings-panel.registered',
);

interface GlobalSentinelHost {
	[ REGISTERED_KEY ]?: boolean;
	__artisanpackBindingsResource?: string | null;
	__artisanpackBindingsRecordId?: number | string | null;
}

interface BlockEditProps {
	name: string;
	attributes: Record<string, unknown> & {
		bindings?: BindingsMap | null;
	};
	setAttributes: ( updates: Record<string, unknown> ) => void;
	[ key: string ]: unknown;
}

const BINDABLE_TYPES = new Set( [
	'string',
	'number',
	'integer',
	'boolean',
	// Object + array attributes are exposed too — blocks like
	// `artisanpack/icon` store their primary value as an object
	// (e.g. `iconRef: { set, name }`). The resolver substitutes whatever
	// the source returns, and the source/picker is responsible for
	// matching shape. The `allowIncompatible` flag is the escape hatch
	// when the picker hides a candidate field on type-strictness alone.
	'object',
	'array',
	// Gutenberg's rich-text type — the heading, paragraph, list-item,
	// etc. blocks all store their primary content this way. The
	// RichText component the inner edits use accepts a plain string
	// (or RichTextValue), so binding to a string-shaped source works
	// without any extra coercion.
	'rich-text',
] );

interface BlockAttributeSchema {
	type?: string | string[];
	role?: string;
	source?: string;
	__experimentalRole?: string;
}

function isBindableSchema( schema: BlockAttributeSchema | undefined ): boolean {
	if ( ! schema ) {
		return false;
	}

	const type = Array.isArray( schema.type ) ? schema.type[ 0 ] : schema.type;

	if ( ! type ) {
		return false;
	}

	return BINDABLE_TYPES.has( type );
}

function collectBindableAttributes( blockName: string ): BindableAttribute[] {
	const blockType = getBlockType( blockName );

	if ( ! blockType || ! blockType.attributes ) {
		return [];
	}

	const out: BindableAttribute[] = [];

	for ( const [ name, schema ] of Object.entries( blockType.attributes ) ) {
		const cast = schema as BlockAttributeSchema;

		// Skip our own sidecar key. Source-typed attributes (e.g. the
		// Heading block's `content`, extracted from rendered HTML via
		// `source: 'html'`) are intentionally NOT skipped — they're the
		// single most common bindable case, and the resolver overrides
		// `attrs[name]` before the renderer reaches them so the source
		// extraction never fires.
		if ( name === 'bindings' ) {
			continue;
		}

		if ( ! isBindableSchema( cast ) ) {
			continue;
		}

		const type = Array.isArray( cast.type ) ? cast.type[ 0 ] : cast.type;

		out.push( {
			name,
			label: name,
			type: type ?? 'string',
		} );
	}

	return out;
}

function getBindingsResource(): string | null {
	const host = globalThis as unknown as GlobalSentinelHost;
	return host.__artisanpackBindingsResource ?? null;
}

function getBindingsRecordId(): number | string | null {
	const host = globalThis as unknown as GlobalSentinelHost;
	return host.__artisanpackBindingsRecordId ?? null;
}

export function setBindingsResourceContext(
	resource: string | null,
	id: number | string | null = null,
): void {
	const host = globalThis as unknown as GlobalSentinelHost;
	host.__artisanpackBindingsResource = resource;
	host.__artisanpackBindingsRecordId = id;
}

export const withBindingsPanel = createHigherOrderComponent(
	( BlockEdit: ComponentType<BlockEditProps> ) => {
		function BindingsBlockEdit( props: BlockEditProps ): JSX.Element {
			const attributes = collectBindableAttributes( props.name );
			const resource   = getBindingsResource();
			const recordId   = getBindingsRecordId();
			const current: BindingsMap =
				( props.attributes.bindings as BindingsMap | undefined ) ?? {};

			// Live-resolve any active bindings against the editor's
			// current parent record so the canvas reflects the bound
			// values without waiting for a frontend render.
			const { values: resolved } = useResolvedBindings(
				current,
				props.attributes,
				resource,
				recordId,
			);

			if ( attributes.length === 0 ) {
				return <BlockEdit { ...props } />;
			}

			const onChange = ( next: BindingsMap ): void => {
				props.setAttributes( {
					bindings: Object.keys( next ).length === 0 ? undefined : next,
				} );
			};

			// Overlay resolved values on top of the saved attrs so the
			// inner edit component sees what the renderer would see.
			// `setAttributes` still writes through unmodified — the
			// SAVED attrs are the static fallback, only the EDIT-time
			// view is overlaid.
			const overlaid =
				Object.keys( resolved ).length > 0
					? { ...props.attributes, ...resolved }
					: props.attributes;

			return (
				<>
					<BlockEdit { ...props } attributes={ overlaid } />
					<InspectorControls>
						<BindingsPanel
							attributes={ attributes }
							bindings={ current }
							resource={ resource }
							onChange={ onChange }
						/>
					</InspectorControls>
				</>
			);
		}

		BindingsBlockEdit.displayName = 'BindingsBlockEdit';

		return BindingsBlockEdit;
	},
	'withBindingsPanel',
);

export interface RegisterBindingsPanelOptions {
	apiBase?: string;
	resource?: string | null;
}

export function registerBindingsPanel(
	options: RegisterBindingsPanelOptions = {}
): void {
	const host = globalThis as unknown as GlobalSentinelHost;

	if ( options.apiBase ) {
		setBindingsApiConfig( { apiBase: options.apiBase } );
	}

	if ( options.resource !== undefined ) {
		setBindingsResourceContext( options.resource );
	}

	if ( host[ REGISTERED_KEY ] ) {
		return;
	}

	addFilter( FILTER_HOOK, FILTER_NAMESPACE, withBindingsPanel );
	host[ REGISTERED_KEY ] = true;
}
