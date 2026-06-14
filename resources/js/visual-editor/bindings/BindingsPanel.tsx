/**
 * Per-attribute binding inspector panel (#504).
 *
 * Lists the block's scalar attributes; for each one, a toggle reveals
 * a source select, a field picker (driven by
 * {@link useBindingFields}), the empty-value policy, and the
 * "allow incompatible types" override. Mutations flow back through the
 * `onChange` callback as a complete `BindingsMap` so the HOC can hand
 * it to `setAttributes( { bindings } )`.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.1.0
 */

import {
	PanelBody,
	PanelRow,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import {
	useBindingFields,
	useBindingSources,
} from './use-binding-sources';
import type {
	BindingDefinition,
	BindingFieldDefinition,
	BindingsMap,
	EmptyValuePolicy,
} from './types';

export interface BindableAttribute {
	name: string;
	label: string;
	type: string;
}

export interface BindingsPanelProps {
	attributes: BindableAttribute[];
	bindings: BindingsMap;
	resource: string | null;
	onChange: ( next: BindingsMap ) => void;
}

const POLICY_OPTIONS: Array<{ label: string; value: EmptyValuePolicy }> = [
	{ label: __( 'Fall back to static value', 'visual-editor' ), value: 'fallback' },
	{ label: __( 'Hide (set to null)', 'visual-editor' ),       value: 'hide' },
	{ label: __( 'Show placeholder',     'visual-editor' ),     value: 'placeholder' },
];

const FREE_FORM_SOURCES = new Set( [ 'relation' ] );

function emptyBinding( source: string ): BindingDefinition {
	return {
		source,
		args: {},
		onEmpty: 'fallback',
	};
}

function isCompatibleType(
	attributeType: string,
	field: BindingFieldDefinition
): boolean {
	if ( ! attributeType ) {
		return true;
	}

	if ( attributeType === field.type ) {
		return true;
	}

	// `string` / `rich-text` attrs accept anything — non-strings
	// stringify cleanly, and the RichText component renders a plain
	// string just as well as a RichTextValue.
	if ( attributeType === 'string' || attributeType === 'rich-text' ) {
		return true;
	}

	// `object` / `array` attrs accept anything — compound shapes vary
	// per block and the picker can't sniff them any better than the
	// resolver will at render time. Lets icon-block `iconRef` bind to a
	// cms-framework image / json custom field without forcing the user
	// to flip "Show incompatible types".
	if ( attributeType === 'object' || attributeType === 'array' ) {
		return true;
	}

	// Number ↔ integer ↔ number; both sides interchangeable.
	if (
		( attributeType === 'integer' || attributeType === 'number' ) &&
		( field.type === 'number' || field.type === 'integer' )
	) {
		return true;
	}

	// Booleans are strict.
	if ( attributeType === 'boolean' && field.type === 'boolean' ) {
		return true;
	}

	return false;
}

function AttributeRow( {
	attribute,
	binding,
	resource,
	sources,
	sourcesLoading,
	onChange,
}: {
	attribute: BindableAttribute;
	binding: BindingDefinition | undefined;
	resource: string | null;
	sources: ReturnType<typeof useBindingSources>['sources'];
	sourcesLoading: boolean;
	onChange: ( next: BindingDefinition | null ) => void;
} ): JSX.Element {
	const enabled = !! binding;
	const sourceName = binding?.source ?? '';
	const isFreeForm = FREE_FORM_SOURCES.has( sourceName );
	const argKey = isFreeForm ? 'path' : 'key';
	const argValue = typeof binding?.args?.[ argKey ] === 'string'
		? ( binding!.args![ argKey ] as string )
		: '';
	const allowIncompatible = binding?.allowIncompatible === true;

	const { fields, loading: fieldsLoading, error: fieldsError } = useBindingFields(
		enabled ? sourceName : null,
		resource
	);

	const filteredFields = allowIncompatible
		? fields
		: fields.filter( ( field ) => isCompatibleType( attribute.type, field ) );

	function patchBinding( patch: Partial<BindingDefinition> ): void {
		if ( ! binding ) {
			return;
		}

		const next: BindingDefinition = {
			...binding,
			...patch,
			args: { ...( binding.args ?? {} ), ...( patch.args ?? {} ) },
		};

		onChange( next );
	}

	return (
		<div style={ { borderTop: '1px solid #e0e0e0', paddingTop: 12, marginTop: 12 } }>
			<ToggleControl
				label={ `${ attribute.label } (${ attribute.type })` }
				help={
					enabled
						? __( 'This attribute will be replaced with the bound value at render time.', 'visual-editor' )
						: __( 'Link to data from the parent post / page / CPT.', 'visual-editor' )
				}
				checked={ enabled }
				onChange={ ( checked: boolean ) => {
					if ( checked ) {
						const fallbackSource = sources[ 0 ]?.name ?? 'custom_field';
						onChange( emptyBinding( fallbackSource ) );
					} else {
						onChange( null );
					}
				} }
			/>

			{ enabled && (
				<>
					<SelectControl
						label={ __( 'Source', 'visual-editor' ) }
						value={ sourceName }
						options={ [
							{ label: __( '— pick a source —', 'visual-editor' ), value: '' },
							...sources.map( ( source ) => ( {
								label: source.name,
								value: source.name,
							} ) ),
						] }
						disabled={ sourcesLoading }
						onChange={ ( next: string ) => {
							onChange( {
								source: next,
								args: {},
								onEmpty: binding!.onEmpty ?? 'fallback',
							} );
						} }
					/>

					{ isFreeForm ? (
						<TextControl
							label={ __( 'Path', 'visual-editor' ) }
							help={ __( 'Dotted path, e.g. author.name', 'visual-editor' ) }
							value={ argValue }
							onChange={ ( next: string ) => patchBinding( { args: { path: next } } ) }
						/>
					) : (
						<SelectControl
							label={ __( 'Field', 'visual-editor' ) }
							value={ argValue }
							disabled={ fieldsLoading || ! sourceName }
							options={ [
								{ label: __( '— pick a field —', 'visual-editor' ), value: '' },
								...filteredFields.map( ( field ) => ( {
									label: `${ field.label } (${ field.type })`,
									value: field.key,
								} ) ),
							] }
							onChange={ ( next: string ) => patchBinding( { args: { key: next } } ) }
						/>
					) }

					{ ! isFreeForm && fields.length > 0 && filteredFields.length === 0 && (
						<PanelRow>
							<em>
								{ __(
									'No fields match this attribute type. Toggle "Show incompatible types" to widen the picker.',
									'visual-editor'
								) }
							</em>
						</PanelRow>
					) }

					{ fieldsError && (
						<PanelRow>
							<em style={ { color: '#cc1818' } }>{ fieldsError.message }</em>
						</PanelRow>
					) }

					{ ! isFreeForm && (
						<ToggleControl
							label={ __( 'Show incompatible types', 'visual-editor' ) }
							help={ __(
								'Allow binding to fields whose type does not match the attribute.',
								'visual-editor'
							) }
							checked={ allowIncompatible }
							onChange={ ( checked: boolean ) => patchBinding( { allowIncompatible: checked } ) }
						/>
					) }

					<SelectControl
						label={ __( 'When the field is empty', 'visual-editor' ) }
						value={ binding!.onEmpty ?? 'fallback' }
						options={ POLICY_OPTIONS }
						onChange={ ( next: string ) => patchBinding( { onEmpty: next as EmptyValuePolicy } ) }
					/>

					{ binding!.onEmpty === 'placeholder' && (
						<TextControl
							label={ __( 'Placeholder', 'visual-editor' ) }
							value={ binding!.placeholder ?? '' }
							onChange={ ( next: string ) => patchBinding( { placeholder: next } ) }
						/>
					) }
				</>
			) }
		</div>
	);
}

export function BindingsPanel( {
	attributes,
	bindings,
	resource,
	onChange,
}: BindingsPanelProps ): JSX.Element | null {
	const { sources, loading: sourcesLoading, error: sourcesError } = useBindingSources();

	if ( attributes.length === 0 ) {
		return null;
	}

	function setBinding( name: string, next: BindingDefinition | null ): void {
		const draft = { ...bindings };

		if ( next === null ) {
			delete draft[ name ];
		} else {
			draft[ name ] = next;
		}

		onChange( draft );
	}

	return (
		<PanelBody title={ __( 'Block bindings', 'visual-editor' ) } initialOpen={ false }>
			{ sourcesError && (
				<PanelRow>
					<em style={ { color: '#cc1818' } }>{ sourcesError.message }</em>
				</PanelRow>
			) }

			{ attributes.map( ( attribute ) => (
				<AttributeRow
					key={ attribute.name }
					attribute={ attribute }
					binding={ bindings[ attribute.name ] }
					resource={ resource }
					sources={ sources }
					sourcesLoading={ sourcesLoading }
					onChange={ ( next ) => setBinding( attribute.name, next ) }
				/>
			) ) }
		</PanelBody>
	);
}
