/**
 * Blade grammar (#1044). Prism ships none.
 *
 * Built like Prism's own PHP grammar: `markup-templating` swaps each Blade
 * construct for a placeholder, tokenizes the rest as HTML, then puts the
 * constructs back, tokenized with this grammar.
 *
 * `\B@` rather than a lookbehind, which is a syntax error on Safari before
 * 16.4 and would take the whole bundle down. It skips an `@` inside an email
 * address. `@@if` is Blade's escape, matched first so it stays literal.
 */

const CONSTRUCT =
    /\{\{--[\s\S]*?--\}\}|\{!![\s\S]*?!!\}|\{\{[\s\S]*?\}\}|@@[a-zA-Z]+|\B@[a-zA-Z]+(?:[ \t]*\((?:[^()'"]|'(?:\\.|[^'\\])*'|"(?:\\.|[^"\\])*"|\((?:[^()]|\([^()]*\))*\))*\))?/g;

export function registerBlade(Prism) {
    Prism.languages.blade = {
        comment: /^\{\{--[\s\S]*--\}\}$/,
        escaped: /^@@[a-zA-Z]+$/,
        delimiter: {
            pattern: /^(?:\{!!|\{\{)|(?:!!\}|\}\})$/,
            alias: 'punctuation',
        },
        directive: {
            pattern: /^@[a-zA-Z]+/,
            alias: 'keyword',
        },
        php: {
            pattern: /[\s\S]+/,
            inside: Prism.languages.php,
        },
    };

    Prism.hooks.add('before-tokenize', (env) => {
        Prism.languages['markup-templating'].buildPlaceholders(env, 'blade', CONSTRUCT);
    });

    Prism.hooks.add('after-tokenize', (env) => {
        Prism.languages['markup-templating'].tokenizePlaceholders(env, 'blade');
    });
}
