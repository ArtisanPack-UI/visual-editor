/**
 * JSX scanner for untranslated user-facing strings (M-12).
 *
 * Walks a `.tsx` file's AST with the TypeScript compiler API and reports
 * the two places an English string most often reaches the editor UI
 * without passing through `@wordpress/i18n`:
 *
 * - JSX text nodes containing a word (two or more letters once HTML
 *   entities such as `&rarr;` are removed);
 * - user-facing props ({@link UI_STRING_PROPS}) given a string literal
 *   containing a letter, either directly (`label="Foo"`) or inside an
 *   expression container, a conditional branch or a `??` / `||`
 *   fallback (`label={ x ? 'On' : 'Off' }`), or a template literal
 *   with text outside its placeholders.
 *
 * Strings passed through `__()` and friends are call expressions, not
 * literals, so they never match.
 *
 * @since 1.15.0
 */

import ts from 'typescript';

/** Props whose value is shown to (or announced for) the user. */
export const UI_STRING_PROPS = new Set( [
	'label',
	'title',
	'placeholder',
	'help',
	'aria-label',
	'aria-description',
	'alt',
	'description',
	'buttonLabel',
	'text',
	'heading',
	'emptyMessage',
] );

/**
 * One untranslated string found in a source file.
 *
 * @since 1.15.0
 */
export interface UntranslatedString {
	line: number;
	/** `text` for a JSX text node, otherwise the prop name. */
	kind: string;
	text: string;
}

const HTML_ENTITY = /&(?:[A-Za-z]+|#\d+|#x[0-9A-Fa-f]+);/g;

function jsxTextHasWords( text: string ): boolean {
	return /[A-Za-z]{2,}/.test( text.replace( HTML_ENTITY, ' ' ).trim() );
}

/**
 * The string literals an attribute expression can evaluate to directly,
 * without passing through a call.
 */
function literalBranches( node: ts.Expression ): Array<ts.StringLiteral | ts.NoSubstitutionTemplateLiteral | ts.TemplateExpression> {
	if ( ts.isParenthesizedExpression( node ) || ts.isAsExpression( node ) ) {
		return literalBranches( node.expression );
	}

	if ( ts.isStringLiteral( node ) || ts.isNoSubstitutionTemplateLiteral( node ) || ts.isTemplateExpression( node ) ) {
		return [ node ];
	}

	if ( ts.isConditionalExpression( node ) ) {
		return [ ...literalBranches( node.whenTrue ), ...literalBranches( node.whenFalse ) ];
	}

	if (
		ts.isBinaryExpression( node )
		&& (
			ts.SyntaxKind.QuestionQuestionToken === node.operatorToken.kind
			|| ts.SyntaxKind.BarBarToken === node.operatorToken.kind
		)
	) {
		return literalBranches( node.right );
	}

	return [];
}

function literalText( node: ts.StringLiteral | ts.NoSubstitutionTemplateLiteral | ts.TemplateExpression ): string {
	if ( ts.isTemplateExpression( node ) ) {
		return node.head.text + node.templateSpans.map( ( span ) => '${…}' + span.literal.text ).join( '' );
	}

	return node.text;
}

function staticText( node: ts.StringLiteral | ts.NoSubstitutionTemplateLiteral | ts.TemplateExpression ): string {
	if ( ts.isTemplateExpression( node ) ) {
		return node.head.text + node.templateSpans.map( ( span ) => ' ' + span.literal.text ).join( '' );
	}

	return node.text;
}

/**
 * Find untranslated JSX text and user-facing string props in one file.
 *
 * @since 1.15.0
 */
export function findUntranslatedJsxStrings( source: string, fileName = 'file.tsx' ): UntranslatedString[] {
	const sourceFile = ts.createSourceFile( fileName, source, ts.ScriptTarget.Latest, true, ts.ScriptKind.TSX );
	const found: UntranslatedString[] = [];

	const report = ( node: ts.Node, kind: string, text: string ): void => {
		const { line } = sourceFile.getLineAndCharacterOfPosition( node.getStart( sourceFile ) );
		found.push( { line: line + 1, kind, text: text.replace( /\s+/g, ' ' ).trim() } );
	};

	const visit = ( node: ts.Node ): void => {
		if ( ts.isJsxText( node ) && jsxTextHasWords( node.text ) ) {
			report( node, 'text', node.text );
		} else if ( ts.isJsxAttribute( node ) && node.initializer && UI_STRING_PROPS.has( node.name.getText( sourceFile ) ) ) {
			const prop        = node.name.getText( sourceFile );
			const initializer = node.initializer;
			const literals    = ts.isStringLiteral( initializer )
				? [ initializer ]
				: ts.isJsxExpression( initializer ) && initializer.expression
					? literalBranches( initializer.expression )
					: [];

			for ( const literal of literals ) {
				if ( /[A-Za-z]/.test( staticText( literal ) ) ) {
					report( literal, prop, literalText( literal ) );
				}
			}
		}

		ts.forEachChild( node, visit );
	};

	visit( sourceFile );

	return found;
}
