/**
 * @file
 Gulpfile for indie theme.
 */

let gulp = require('gulp'),
  sass = require('gulp-sass'),
  sassGlob = require('gulp-sass-glob'),
  del = require('del'),
  sourcemaps = require('gulp-sourcemaps'),
  postcss = require('gulp-postcss'),
  autoprefixer = require('autoprefixer'),
  rename = require('gulp-rename'),
  twig = require('gulp-twig'),
  browserSync = require('browser-sync').create();

const paths = {
  scss: {
    src: './scss/theme.scss',
    dest: './dist/css',
    watch: './scss/**/*.scss'
  },
  js: {
    src: './js/global.js',
    dest: './dist/js'
  },
};

// Browser sync vars.
  let bsHost = 'https://saramagdits.docksal/',
  bsHttps = true;

// Require local config to override variables.
try {
  const localConfig = require('./gulpfile.local');
  bsHost = localConfig.bsHost;
  bsProxy = localConfig.bsProxy;
  bsHttps = localConfig.bsHttps;
}
catch (e) {
  // Do nothing.
}

// Compile sass into CSS & auto-inject into browsers.
async function styles() {
  await
   gulp.src([paths.scss.src])
    .pipe(sourcemaps.init())
    .pipe(sassGlob())
    .pipe(sass({
      includePaths: ['node_modules/']
    }).on('error', sass.logError))
    .pipe(postcss([autoprefixer({
      browsers: [
        'Chrome >= 35',
        'Firefox >= 38',
        'Edge >= 12',
        'iOS >= 8',
        'Safari >= 8',
        'Android 2.3',
        'Android >= 4',
        'Opera >= 12']
    })]))
    .pipe(sourcemaps.write())
    .pipe(gulp.dest(paths.scss.dest))
    .pipe(browserSync.stream())
}

// Move the javascript files into our js folder.
async function js() {
  await
    gulp.src([paths.js.src])
      .pipe(gulp.dest(paths.js.dest))
      .pipe(browserSync.stream())
}

// Static Server + watching scss/html files.
async function serve() {
  await
    browserSync.init({
      open: 'local',
      proxy: bsHost,
      https: bsHttps
    });

  // Watch for scss changes.
  gulp.watch([paths.scss.watch], styles);

  // gulp.watch('./src/twig/**/*.twig', templates);

  // Reload only for twig file changes.
  // gulp.watch(paths.twig.watch).on('change', browserSync.reload);
}

async function clean() {
  await
    del([
      'dist/**/*.css',
      'dist/**/*.js'
    ]);
}

const build = gulp.series(clean, styles, gulp.parallel(js, serve));
// const build = gulp.series(clean, styles, templates, gulp.parallel(js, serve));

exports.styles = styles;
exports.js = js;
exports.serve = serve;

exports.default = build;

// For the CI build, don't need the browser sync stuff.
// const deploy = gulp.series(styles, js);
// exports.deploy = deploy;
