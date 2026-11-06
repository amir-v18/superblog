import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
                // The budbararen theme. Downloaded at build time and served from public/build, so no visitor
                // requests go to the font host. Not preloaded: every site and the admin load these, but a browser
                // only downloads a font when the page actually uses it. Aliases are fixed because @fonts names them.
                bunny('UnifrakturMaguntia', { alias: 'unifrakturmaguntia', preload: false }),
                bunny('Playfair Display', { alias: 'playfair-display', weights: [400, 700, 900], styles: ['normal', 'italic'], preload: false }),
                // Normal style only: this family has no bold italic, and asking for one could fail the download.
                bunny('Old Standard TT', { alias: 'old-standard-tt', weights: [400, 700], preload: false }),
                bunny('IM Fell English SC', { alias: 'im-fell-english-sc', preload: false }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
