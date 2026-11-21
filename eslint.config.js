const js = require('@eslint/js');
const globals = require('globals');
const tseslint = require('typescript-eslint');
const pluginVue = require('eslint-plugin-vue');
const vueParser = require('vue-eslint-parser');

module.exports = tseslint.config(
    {
        ignores: ['node_modules/**', 'public/**', 'vendor/**', 'var/**'],
    },

    js.configs.recommended,
    ...tseslint.configs.recommended,
    ...pluginVue.configs['flat/recommended'],

    {
        files: ['**/*.{ts,vue}'],

        languageOptions: {
            globals: {
                ...globals.browser,
                ...globals.node,
            },
            parser: vueParser,
            parserOptions: {
                parser: tseslint.parser,
                sourceType: 'module',
                extraFileExtensions: ['.vue'],
            },
        },

        // add your custom rules here
        rules: {
            semi: ['error', 'always'],
            quotes: ['error', 'single', { 'avoidEscape': true, 'allowTemplateLiterals': true }],
            indent: ['error', 4],
            'comma-dangle': ['error', 'always-multiline'],

            'vue/script-indent': ['error', 4, {
                baseIndent: 1,
                switchCase: 1,
                ignores: [],
            }],
            'vue/html-indent': ['error', 4, {
                attribute: 1,
                baseIndent: 1,
                closeBracket: 0,
                alignAttributesVertically: false,
                ignores: [],
            }],
            'vue/html-self-closing': ['error', {
                html: {
                    void: 'never',
                    normal: 'never',
                    component: 'never',
                },
                svg: 'always',
                math: 'always',
            }],
            'vue/max-attributes-per-line': ['error', {
                singleline: {
                    max: Infinity,
                },
                multiline: {
                    max: 1,
                },
            }],
            'vue/v-on-event-hyphenation': ['error', 'always', {
                'autofix': false,
                'ignore': [
                    'modelValue',
                ],
            }],
            'vue/no-v-html': 'off',

            '@typescript-eslint/no-empty-object-type': ['error', {
                allowInterfaces: 'with-single-extends',
            }],
            '@typescript-eslint/no-this-alias': ['error', {
                'allowDestructuring': true, // Allow `const { props, state } = this`
                'allowedNames': ['vm'], // Allow `const vm= this`
            }],
            '@typescript-eslint/no-explicit-any': 'warn',
            '@typescript-eslint/no-unused-vars': 'warn',
        },
    },

    {
        files: ['**/*.vue'],
        rules: {
            indent: 'off',
        },
    },
);
