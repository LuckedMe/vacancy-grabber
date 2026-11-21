module.exports = {
    root: true,

    env: {
        browser: true,
        node: true,
    },

    parser: 'vue-eslint-parser',

    parserOptions: {
        parser: '@typescript-eslint/parser',
        sourceType: 'module',
        tsconfigRootDir: __dirname,
    },

    extends: [
        'eslint:recommended',
        'plugin:@typescript-eslint/recommended',
        'plugin:vue/vue3-recommended',
    ],

    plugins: ['@typescript-eslint'],

    // add your custom rules here
    rules: {
        semi: 'off',
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

        '@typescript-eslint/no-empty-interface': ['error', {
            'allowSingleExtends': true,
        }],
        '@typescript-eslint/no-this-alias': ['error', {
            'allowDestructuring': true, // Allow `const { props, state } = this`
            'allowedNames': ['vm'], // Allow `const vm= this`
        }],
        '@typescript-eslint/semi': 'error',
        '@typescript-eslint/no-explicit-any': 'warn',
        '@typescript-eslint/no-unused-vars': 'warn',
    },

    overrides: [
        {
            files: ['*.vue'],
            rules: {
                indent: 'off',
            },
        },
    ],
};
