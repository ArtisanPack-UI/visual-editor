/**
 * Code — the language select's options (#1044).
 *
 * Language names are the same in every locale, so they aren't translated.
 * `None` and `Plain text` are added by the edit component, which can.
 */

export const CODE_LANGUAGE_LABELS: ReadonlyArray<{ label: string; value: string }> = [
    { label: 'Bash', value: 'bash' },
    { label: 'Blade', value: 'blade' },
    { label: 'CSS', value: 'css' },
    { label: 'Go', value: 'go' },
    { label: 'HTML', value: 'html' },
    { label: 'JavaScript', value: 'javascript' },
    { label: 'JSON', value: 'json' },
    { label: 'Markdown', value: 'markdown' },
    { label: 'PHP', value: 'php' },
    { label: 'Python', value: 'python' },
    { label: 'Ruby', value: 'ruby' },
    { label: 'Rust', value: 'rust' },
    { label: 'SQL', value: 'sql' },
    { label: 'TypeScript', value: 'typescript' },
    { label: 'YAML', value: 'yaml' },
];

/** The classes Markdown and highlighters put on `<code>` to name its language. */
export const CODE_LANGUAGE_CLASS = /^lang(?:uage)?-/;

/** Fence and class names that differ from the block's own language values. */
const CODE_LANGUAGE_ALIASES: Readonly<Record<string, string>> = {
    cjs: 'javascript',
    golang: 'go',
    htm: 'html',
    js: 'javascript',
    jsx: 'javascript',
    md: 'markdown',
    mjs: 'javascript',
    plain: 'plaintext',
    py: 'python',
    python3: 'python',
    rb: 'ruby',
    rs: 'rust',
    sh: 'bash',
    shell: 'bash',
    text: 'plaintext',
    ts: 'typescript',
    tsx: 'typescript',
    txt: 'plaintext',
    yml: 'yaml',
    zsh: 'bash',
};

/**
 * Maps a language name from outside the editor, such as a Markdown fence,
 * to the block's `language` value (#1045).
 *
 * @since 1.20.0
 *
 * @param language The name to map, in any case.
 *
 * @return The block's value, or `undefined` for a language it doesn't list.
 */
export function normalizeCodeLanguage(language: string): string | undefined {
    const lowered = language.toLowerCase();
    const known   = CODE_LANGUAGE_ALIASES[lowered] ?? lowered;

    return 'plaintext' === known || CODE_LANGUAGE_LABELS.some(({ value }) => value === known)
        ? known
        : undefined;
}
