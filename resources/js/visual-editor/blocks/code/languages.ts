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
