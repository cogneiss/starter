import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import babel from '@rolldown/plugin-babel';
import tailwindcss from '@tailwindcss/vite';
import react, { reactCompilerPreset } from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite-plus';

export default defineConfig({
    lint: {
        options: {
            typeAware: true,
            typeCheck: true,
        },
        categories: {
            correctness: 'error',
        },
        plugins: [
            'eslint',
            'typescript',
            'unicorn',
            'oxc',
            'react',
            'jsx-a11y',
        ],
        jsPlugins: ['eslint-plugin-better-tailwindcss'],
        settings: {
            'better-tailwindcss': { entryPoint: 'resources/css/app.css' },
        },
        rules: {
            'typescript/no-misused-promises': 'error',
            'typescript/no-unsafe-assignment': 'error',
            'typescript/no-unsafe-return': 'error',
            'typescript/no-unsafe-type-assertion': 'error',
            'typescript/no-unnecessary-condition': 'error',
            'typescript/switch-exhaustiveness-check': 'error',
            'typescript/no-floating-promises': 'error',
            'react/no-unstable-nested-components': 'error',
            // Every autoFocus sits on the first field of a single-purpose auth form or dialog, where focus belongs there anyway.
            'jsx-a11y/no-autofocus': 'off',
            // Flags valid ARIA widgets (combobox listbox/option, splitter separator, grouped fields) that have no native tag equivalent.
            'jsx-a11y/prefer-tag-over-role': 'off',
            'better-tailwindcss/no-unknown-classes': 'error',
            'better-tailwindcss/no-conflicting-classes': 'error',
            'better-tailwindcss/no-deprecated-classes': 'error',
        },
        ignorePatterns: [
            'vite.config.ts',
            'resources/js/components/ui/*',
            'resources/js/actions/*',
            'resources/js/routes/*',
            'resources/js/wayfinder/*',
            'resources/js/types/generated.d.ts',
        ],
    },
    fmt: {
        printWidth: 80,
        tabWidth: 4,
        useTabs: false,
        semi: true,
        singleQuote: true,
        overrides: [
            {
                files: ['**/*.yml'],
                options: {
                    tabWidth: 2,
                },
            },
        ],
        sortTailwindcss: {
            functions: ['clsx', 'cn'],
            stylesheet: 'resources/css/app.css',
        },
        sortImports: {
            groups: [
                'builtin',
                'external',
                'internal',
                'parent',
                'sibling',
                'index',
            ],
            newlinesBetween: false,
        },
        ignorePatterns: [
            'resources/js/components/ui/*',
            'resources/views/mail/*',
            'resources/js/actions/*',
            'resources/js/routes/*',
            'resources/js/wayfinder/*',
            'resources/js/types/generated.d.ts',
        ],
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            ssr: 'resources/js/ssr.tsx',
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        react(),
        babel({
            presets: [reactCompilerPreset()],
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ],
});
