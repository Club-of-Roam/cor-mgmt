import js from '@eslint/js';
import globals from 'globals';

export default [
  {
    ...js.configs.recommended,
    files: ['playwright.config.cjs', 'tests/Browser/*.cjs', 'tests/broadcast-js.test.cjs'],
    languageOptions: { ecmaVersion: 'latest', sourceType: 'commonjs', globals: globals.node },
  },
  { ignores: ['js/vendor/**', 'js/isotope.pkgd.min.js', 'node_modules/**'] },
  {
    files: ['js/*.js'],
    ...js.configs.recommended,
    languageOptions: {
      ecmaVersion: 'latest', sourceType: 'script',
      globals: { ...globals.browser, ...globals.jquery, L: 'readonly', app_vars: 'readonly',
        h3_mgmt: 'readonly', Isotope: 'readonly', imagesLoaded: 'readonly' },
    },
    rules: {
      // Existing unused bindings are characterized during each module conversion.
      'no-unused-vars': 'off',
      'no-empty': ['error', { allowEmptyCatch: true }],
    },
  },
];
