/**
 * Types for `scripts/extract-pot.mjs`, so the Vitest suite can import the
 * extractor under `strict`.
 *
 * @since 1.15.0
 */

export interface ExtractedCall {
	msgid: string;
	msgctxt?: string;
	msgid_plural?: string;
	/** Translators comment, without delimiters. */
	comment?: string;
	/** One-based line of the call. */
	line: number;
}

export interface PotEntry {
	msgid: string;
	msgctxt?: string;
	msgid_plural?: string;
	comments: Set<string>;
	references: Set<string>;
}

export const ROOT: string;
export const SOURCE_DIR: string;
export const TEXT_DOMAIN: string;

export function collectSourceFiles( dir: string ): Promise<string[]>;
export function extractFromSource( source: string, fileName?: string, warn?: ( message: string ) => void ): ExtractedCall[];
export function keyFor( entry: { msgid: string; msgctxt?: string } ): string;
export function extract( options?: { root?: string; sourceDir?: string; warn?: ( message: string ) => void } ): Promise<Map<string, PotEntry>>;
export function escapePotString( value: string ): string;
export function renderPot( entries: Map<string, PotEntry> ): string;
