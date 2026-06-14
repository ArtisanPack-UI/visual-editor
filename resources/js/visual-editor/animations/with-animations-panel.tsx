/**
 * `editor.BlockEdit` HOC — inject the AnimationPanel into every block
 * that opts into `supports.artisanpackAnimations` (#489).
 *
 * Unlike the responsive / state HOCs, this one does NOT rewrite reads
 * or writes — the `artisanpackAnimations` attribute is a leaf bag, and
 * the panel mutates it directly via `setAttributes`. The HOC just
 * makes sure the panel renders inside the inspector column whenever a
 * supporting block is selected.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.1.0
 */

import { InspectorControls } from '@wordpress/block-editor';
import { createHigherOrderComponent } from '@wordpress/compose';
import { addFilter } from '@wordpress/hooks';
import type { ComponentType } from 'react';

import { AnimationPanel } from './AnimationPanel';
import { AnimationRegistry, DEFAULT_ANIMATIONS } from './registry';
import type { AnimationsAttribute } from './types';

const FILTER_HOOK      = 'editor.BlockEdit';
const FILTER_NAMESPACE = 'artisanpack-ui/visual-editor/animations-panel';

const REGISTERED_KEY = Symbol.for(
	'artisanpack-ui.visual-editor.animations-panel.registered',
);

interface GlobalSentinelHost {
	[REGISTERED_KEY]?: boolean;
	__artisanpackAnimationRegistry?: AnimationRegistry;
}

interface BlockEditProps {
	name: string;
	attributes: Record<string, unknown> & {
		artisanpackAnimations?: AnimationsAttribute | null;
	};
	setAttributes: ( updates: Record<string, unknown> ) => void;
	[key: string]: unknown;
}

function getRegistry(): AnimationRegistry {
	// v1.1.0 seeds from DEFAULT_ANIMATIONS. Hydration from a merged
	// PHP config + theme.json + Global Styles snapshot (via
	// `registryFromSnapshot`) is the planned follow-up — host apps
	// can pre-stamp `globalThis.__artisanpackAnimationRegistry`
	// today as an escape hatch.
	const host = globalThis as unknown as GlobalSentinelHost;
	if ( ! host.__artisanpackAnimationRegistry ) {
		host.__artisanpackAnimationRegistry = new AnimationRegistry( DEFAULT_ANIMATIONS );
	}
	return host.__artisanpackAnimationRegistry;
}

function blockSupports( _name: string, attributes: BlockEditProps['attributes'] ): boolean {
	// `registerAnimationsAttribute` injects the `artisanpackAnimations`
	// attribute (default `null`) whenever a block.json declares
	// `supports.artisanpackAnimations: true`. So attribute presence is
	// the canonical signal: if the schema has it, the block opted in.
	return 'artisanpackAnimations' in attributes;
}

export const withAnimationsPanel = createHigherOrderComponent(
	( BlockEdit: ComponentType<BlockEditProps> ) => {
		function AnimationsBlockEdit( props: BlockEditProps ): JSX.Element {
			const supports = blockSupports( props.name, props.attributes );

			if ( ! supports ) {
				return <BlockEdit { ...props } />;
			}

			const value = ( props.attributes.artisanpackAnimations ?? undefined ) as AnimationsAttribute | undefined;

			return (
				<>
					<BlockEdit { ...props } />
					<InspectorControls>
						<AnimationPanel
							registry={ getRegistry() }
							value={ value }
							onChange={ ( next ) =>
								props.setAttributes( { artisanpackAnimations: next } )
							}
						/>
					</InspectorControls>
				</>
			);
		}

		AnimationsBlockEdit.displayName = 'AnimationsBlockEdit';

		return AnimationsBlockEdit;
	},
	'withAnimationsPanel',
);

export function registerAnimationsPanel(): void {
	const host = globalThis as unknown as GlobalSentinelHost;

	if ( host[ REGISTERED_KEY ] ) {
		return;
	}

	addFilter( FILTER_HOOK, FILTER_NAMESPACE, withAnimationsPanel );
	host[ REGISTERED_KEY ] = true;
}
