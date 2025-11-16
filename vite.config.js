import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import { resolve } from 'path';
import tailwindcss from '@tailwindcss/postcss';
import autoprefixer from 'autoprefixer';
import cssnano from 'cssnano';

export default defineConfig(({ command, mode }) => {
    // Load env file based on `mode` in the current working directory.
    // Set the third parameter to '' to load all env regardless of the `VITE_` prefix.
    const env = loadEnv(mode, process.cwd(), '');
    
    return {
        plugins: [
            laravel({
                input: [
                    'resources/css/app.css',
                    'resources/js/app.js',
                ],
                refresh: true,
            }),
        ],
        
        // Production optimizations
        build: {
            // Optimize build performance
            target: 'esnext',
            minify: 'terser',
            sourcemap: env.NODE_ENV === 'development',
            
            // Asset optimization
            assetsInlineLimit: 4096, // Inline assets smaller than 4kb
            cssCodeSplit: true,
            rollupOptions: {
                output: {
                    // Optimize chunk splitting for better caching
                    manualChunks: (id) => {
                        // Vendor chunk optimization
                        if (id.includes('node_modules')) {
                            // Separate large vendor libraries
                            if (id.includes('react') || id.includes('vue')) {
                                return 'vendor-frameworks';
                            }
                            if (id.includes('lodash') || id.includes('moment')) {
                                return 'vendor-utils';
                            }
                            if (id.includes('@') || id.includes('tailwindcss')) {
                                return 'vendor-ui';
                            }
                            return 'vendor';
                        }
                    },
                    
                    // Chunk naming for better cache invalidation
                    chunkFileNames: (chunkInfo) => {
                        const facadeModuleId = chunkInfo.facadeModuleId
                            ? chunkInfo.facadeModuleId.split('/').pop().replace('.js', '')
                            : 'chunk';
                        return `js/${facadeModuleId}-[hash].js`;
                    },
                    
                    assetFileNames: (assetInfo) => {
                        const info = assetInfo.name.split('.');
                        const ext = info[info.length - 1];
                        
                        if (/\.(woff|woff2|eot|ttf|otf)$/i.test(assetInfo.name)) {
                            return `fonts/[name]-[hash].${ext}`;
                        }
                        
                        if (/\.(png|jpe?g|gif|svg|webp|avif)$/i.test(assetInfo.name)) {
                            return `images/[name]-[hash].${ext}`;
                        }
                        
                        if (/\.(css)$/i.test(assetInfo.name)) {
                            return `css/[name]-[hash].${ext}`;
                        }
                        
                        return `assets/[name]-[hash].${ext}`;
                    },
                },
            },
            
            // Terser options for advanced minification
            terserOptions: {
                compress: {
                    drop_console: env.NODE_ENV === 'production',
                    drop_debugger: true,
                    pure_funcs: env.NODE_ENV === 'production' ? ['console.log', 'console.info'] : [],
                    passes: 2,
                    dead_code: true,
                    unused: true,
                },
                mangle: {
                    safari10: true,
                },
                format: {
                    comments: false,
                },
            },
        },
        
        // Development server optimizations
        server: {
            hmr: {
                overlay: false, // Disable error overlay for better performance
            },
            fs: {
                strict: true,
            },
        },
        
        // Performance optimizations
        optimizeDeps: {
            include: [
                'react',
                'react-dom',
                'lodash',
                'axios',
            ],
            exclude: [
                // Exclude large libraries from pre-bundling
            ],
        },
        
        // CSS optimizations
        css: {
            postcss: {
                plugins: [
                    tailwindcss(),
                    autoprefixer,
                    ...(mode === 'production' ? [
                        cssnano({
                            preset: 'default',
                        }),
                    ] : []),
                ],
            },
        },
        
        // Define global constants
        define: {
            __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: false,
            __VUE_OPTIONS_API__: true,
            __VUE_PROD_DEVTOOLS__: false,
        },
        
        // Resolve configuration
        resolve: {
            alias: {
                '@': resolve(__dirname, 'resources/js'),
                '~': resolve(__dirname, 'node_modules'),
            },
            extensions: ['.js', '.ts', '.jsx', '.tsx', '.vue', '.json'],
        },
        
        // Performance monitoring
        preview: {
            port: 4173,
            strictPort: true,
        },
        
        // Worker configuration
        worker: {
            format: 'es',
        },
        
        // Web Worker support
        experimentalRenderers: true,
        
        // Performance profiling
        profile: env.NODE_ENV === 'development',
        
        // Cache configuration
        cache: {
            type: 'filesystem',
            buildDependencies: {
                config: [__filename],
            },
        },
    };
});
