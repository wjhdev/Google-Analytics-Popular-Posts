import { Config } from '@stencil/core';
import { sass } from '@stencil/sass';

export const config: Config = {
  namespace: 'analytics-bridge',
  outputTargets: [
    {
      type: 'dist',
      esmLoaderPath: '../loader',
      copy: [{ src: 'functions/**/*' }, { src: 'functions/**/*.php' }],
    },
    {
      type: 'www',
      buildDir: 'app',
      dir: 'bin/wp-content/themes/analytics-bridge.test/',
      copy: [
        { src: 'index.php' },
        { src: 'functions/' },
        { src: 'assets/' },
        { src: 'style.css' },
        {
          src: '../node_modules/@webpress/core/dist/collection/theme-overlay/functions.php',
          dest: 'functions.php',
        },
        { src: '../node_modules/@webpress/core/dist/collection/theme-overlay/etc', dest: 'etc' },
      ],
    },
  ],
  plugins: [sass({ injectGlobalPaths: ['src/global/sass/foundations.scss'] })],
};
