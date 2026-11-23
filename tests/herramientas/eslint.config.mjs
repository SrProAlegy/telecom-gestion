export default [{
    files: ['frontend/public/assets/js/*.js'],
    languageOptions: {
        ecmaVersion: 2022,
        sourceType: 'script',
        globals: { document: 'readonly', fetch: 'readonly', AbortSignal: 'readonly' }
    },
    rules: {
        'no-undef': 'error',
        'no-unused-vars': 'error',
        eqeqeq: ['error', 'always'],
        semi: ['error', 'always']
    }
}];
