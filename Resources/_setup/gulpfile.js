/**
 * Terminal shortcut:
 * `npm install --save-dev gulp gulp-sass gulp-sourcemaps gulp-concat gulp-uglify gulp-rename gulp-autoprefixer main-bower-files gulp-filter gulp-rev gulp-debug del fs stream-combiner2 path gulp-clean-css gulp-if gulp-rev-delete-original yargs child_process gulp-include through2`
 * npm install -g bower
 * bower install
 */

//Requirements
var gulp = require('gulp'),
    //concat files
    concat = require('gulp-concat'),
    //process sass
    sass = require('gulp-sass'),
    //generate sourcemaps
    sourcemaps = require('gulp-sourcemaps'),
    //minify js
    uglify = require('gulp-uglify'),
    //minify css
    cleancss = require('gulp-clean-css'),
    //change paths for manifest
    rename = require('gulp-rename'),
    //auto-prefix for different browsers with easy string for how many browser versions to auto-prefix for e.g. -webkit-backface-visibility -moz-backface-visibility
    autoprefixer = require('gulp-autoprefixer'),
    //read in bower dependancies automatically
    bower = require('main-bower-files'),
    //filter the input stream and restore if needed
    filter = require('gulp-filter'),
    //create revision hashes based on file content
    rev = require('gulp-rev'),
    //for deleting old revisions
    del = require('del'),
    //filesystem - read in manifests
    fs = require('fs'),
    //provides output of files being used for debugging
    debug = require('gulp-debug'),
    combiner = require('stream-combiner2'),
    path = require('path'),
    revDel = require('rev-del'),
    revDelOriginal = require('gulp-rev-delete-original'),
    through = require('through2'),
    gulpif = require('gulp-if'),
    include = require('gulp-include'),
    spawn = require('child_process').spawn,
    argv = require('yargs')
    .array('files')
    .alias('f', 'files')
    .default('files', false)
    .default('clean', false)
    .describe('f', 'Run gulp for a single file in assetHashes.json (Created by Symfony Bundle BWCore\\AssetsBundle)')
    .argv;

if(argv.files[0]===false){
  argv.files = false;
}

// PATHS
var bundleRoot = 'vendor/silverbackis/bw-assets-bundle',
manifestsRoot = bundleRoot + '/Resources/manifests/',
assetHashesPath = './' + manifestsRoot + 'assetHashes.json',
symfonyManifest = JSON.parse(fs.readFileSync("./" + manifestsRoot + 'assetHashes.json').toString());

//if we have spawned this script, we only want to watch src files from the assetHashes manifest
if(argv.spawned){
  gulp.task('watch-current-assets', function(){
    console.log("Watching current assets from assetHashes.json");
    var currentAssets = [],
    srcToKey = {};
    Object.keys(symfonyManifest).forEach(function(manifestKey){
      currentAssets = currentAssets.concat(symfonyManifest[manifestKey].src);
      symfonyManifest[manifestKey].src.forEach(function(srcFile){
        srcToKey[srcFile] = manifestKey;
      });
    });

    var currentAssetFileWatcher = gulp.watch(currentAssets);
    currentAssetFileWatcher.on('change',function(watchFile){
      //console.log(path.basename(watchFile));
      var gulpArgs = ['-f',srcToKey[watchFile]];
      console.log(gulpArgs);

      gulpSpawn = spawn('gulp',gulpArgs);
      gulpSpawn.stdout.on('close', (code) => {
        console.log('gulp spawn finished/closed with code: ' + code);
      });
    });
  });
  return;
}

var dest = {
  manifest:{
      //location of the output manifest for revisions
      file: manifestsRoot + 'revisionManifest.json',
      //path to files from web pages for manifest keys
      bower: 'bower/dist',
      symfony: 'static/dist'    
  },
  source:{
      symfony: bundleRoot + '/Resources/public/static/source/'
  },
  public:{
    bower: bundleRoot + '/Resources/public/bower/dist',
    symfony: bundleRoot + '/Resources/public/static/dist'
  },
  build:{
    bower: bundleRoot + '/Resources/public/bower/build',
    symfony: bundleRoot + '/Resources/public/static/build',
  }
};
var bowerSource = bundleRoot + '/Resources/public/bower/source',
scssIncludePaths = [bowerSource],
scriptIncludePaths = ['.', bowerSource];


//css auto-prefixer global options
var autoprefixer_ops = {
      browsers: ['last 2 versions'],
      cascade: false
},
//Generate sourcemap write options object
sourcemap_ops = function(publicDir,addComment){
  return {
    includeContent:false,
    addComment:addComment,
    sourceRoot:function(file){
      //relative path from file base to the output destination
      return path.relative(publicDir, file.base);
    }
  };
};

/**
 * Tasks used across both bower and symfony builds
 * @type {Object}
 */
var tasks = {
  scss:function(buildDir){
    var scssFilter = filter('**/*.scss', {restore: true});
    var sourcemapSourceRoot;
    return combiner.obj(
      scssFilter,
        //debug({title: 'tasks.scss'}),
        sourcemaps.init(),
          sass({
            includePaths: scssIncludePaths
          }),
          autoprefixer(autoprefixer_ops),
          concat('scss.css'),
        sourcemaps.write(".",sourcemap_ops(buildDir,false)),
        gulp.dest(buildDir),
      scssFilter.restore
    ).on('error', console.error.bind(console));
  },
  css:function(publicDir, concatFilename, manifestPrefix, buildDir){
    var cssFilter = filter('**/*.css', {restore: true});
    var cssFilter_incMaps = filter('**/*.css*', {restore: true});

    return combiner.obj(
      //move all to a build directory
      cssFilter_incMaps,
        gulp.dest(buildDir),
      cssFilter_incMaps.restore,

      cssFilter,
        sourcemaps.init({loadMaps:true}),
          autoprefixer(autoprefixer_ops),
          concat(concatFilename),
        sourcemaps.write(".",sourcemap_ops(publicDir,true)),
        gulp.dest(publicDir),
      cssFilter.restore
    ).on('error', console.error.bind(console));
  },
  css_min:function(publicDir, concatFilename, manifestPrefix){
    var cssFilter = filter('**/*.css', {restore: true});

    return combiner.obj(
      cssFilter,
        //debug({title: 'tasks.css_min'}),
        sourcemaps.init({loadMaps:true}),
          autoprefixer(autoprefixer_ops),
          cleancss({
            compatibility: 'ie8',
            sourceMap:true
          }),
          concat(concatFilename),
        sourcemaps.write(".",sourcemap_ops(publicDir,true)),
        gulp.dest(publicDir),
      cssFilter.restore
    ).on('error', console.error.bind(console));
  },
  js:function(publicDir, concatFilename, manifestPrefix, buildDir){
    var jsFilter = filter('**/*.js', {restore: true});
    var jsFilter_incMaps = filter('**/*.js*', {restore: true});

    return combiner.obj(
        jsFilter_incMaps,
          include({
            includePaths: scriptIncludePaths
          }),
          gulp.dest(buildDir),
        jsFilter_incMaps.restore,

        jsFilter,
          debug({title: 'tasks.js:build'}),
          sourcemaps.init({loadMaps:true}),
            concat(concatFilename),
          sourcemaps.write(".",sourcemap_ops(publicDir,true)),
          gulp.dest(publicDir),
        jsFilter.restore
    ).on('error', console.error.bind(console));
  },
  js_min:function(publicDir, concatFilename, manifestPrefix){
    var jsFilter = filter('**/*.js', {restore: true});

    return combiner.obj(
        jsFilter,
          include({
            includePaths: scriptIncludePaths
          }),
          debug({title: 'tasks.js_min'}),
          sourcemaps.init({loadMaps:true}),
            uglify(),
            concat(concatFilename),
          sourcemaps.write(".",sourcemap_ops(publicDir,true)),
          gulp.dest(publicDir),
        jsFilter.restore
    ).on('error', console.error.bind(console));
  }
};
function getMinFile(filename){
  var ext = path.extname(filename);
  return {
    extension:ext,
    filename:filename.replace(ext,".min"+ext)
  };
}

/**
 * Compile deps from assetsHashes manifest
 */
var compilePageTasks = [],
compressPageTasks = [],
copySrc = [];

function revisionsFull(){
  //create revisions of files
  return combiner.obj(
    rev(),
    revDelOriginal(),
    gulp.dest(
      function(thisFile){
        if(argv.files!==false){
          return bundleRoot+'/Resources/public/static/dist/'+path.extname(thisFile.path).substring(1)+'';
        }else{
          return bundleRoot + '/Resources/public';
        }
      }
    ),
    rename(function (localPath) {
      localPath.dirname = argv.files!==false ? 'static/dist/' + localPath.extname.substring(1) + '/' + localPath.dirname : localPath.dirname;
    }),
    rev.manifest({
      path: dest.manifest.file,
      merge: argv.files!==false//merge with existing if we're just processing 1 file/key
    })
  );
}
function deleteUnusedFiles(){
  return through.obj(function(file, enc, cb) {
    var updatedContents = JSON.parse(file.contents.toString());
    
    for(var revManifestKey in updatedContents){
      //don't check for .min files or bower dist files
      if(revManifestKey.includes(".min") || revManifestKey.indexOf("bower/dist/")===0){ continue; }
     
      var symfonyManifestKeys = Object.keys(symfonyManifest);

      if(symfonyManifestKeys.indexOf(revManifestKey)===-1){
        console.log(revManifestKey+" is no longer in assetHash keys. Remove the files",symfonyManifestKeys);
        updatedContents[revManifestKey] = undefined;

        var minFile = getMinFile(revManifestKey).filename;

        if(updatedContents.hasOwnProperty(minFile)){
          updatedContents[minFile] = undefined;
        }
      }
    }
    updatedContents = JSON.stringify(updatedContents, null, 2);
    file.contents = new Buffer(updatedContents);
    cb(null, file);
  });
}
function deleteUnusedBuildFolders(){
  return through.obj(function(file, enc, cb) {
      file.revDeleted.map(function(deletedFile){
        //get the map files because they haven't had the revision postfix applied
        if(path.extname(deletedFile)==='.map'){
          //get the css file name instead now and the path where the build folder would be
          var folderName = path.basename(deletedFile).replace(".map",""),
          buildFolderPath = "./" + bundleRoot + '/Resources/public/static/build/' + folderName + '/**';
          del([buildFolderPath + '/**']);
        }
      });
      cb(null,file);
    });
}
(function processSymfonyManifest(){
  compilePageTasks = [];
  compressPageTasks = [];
  copySrc = [];
  var updatedSymfonyManifest = {};

  Object.keys(symfonyManifest).forEach(function (filename) {
    var cfg = symfonyManifest[filename];
    copySrc = copySrc.concat(cfg.src);
    //internally change all source paths to where they are saved after the copy process
    cfg.src = cfg.src.map(function(e) { return dest.source.symfony + e; });
    updatedSymfonyManifest['static/dist/' + path.extname(filename).substring(1) + '/' + filename] = cfg;

    //only create tasks that are needed - if we are only working on compiling a single key/file, move on
    if(argv.files && argv.files.indexOf(filename)===-1){ return; }

    var compileTaskName = 'compile:symfony:'+filename,
    compressTaskName = 'compress:symfony:'+filename;

    /**
     * Compile task
     */
    gulp.task(compileTaskName,function(){
      var pageFiles = cfg.includeBower ? bowerFiles.concat(cfg.src) : cfg.src;
      return gulp.src(pageFiles)
        .pipe(gulpif(cfg.dest==='css',tasks.scss(dest.build.symfony + '/' + filename)))
        .pipe(gulpif(cfg.dest==='css',tasks.css(dest.public.symfony + "/" + cfg.dest + '/', filename, dest.manifest.symfony + "/" + cfg.dest, dest.build.symfony + '/' + filename)))
        .pipe(gulpif(cfg.dest==='js',tasks.js(dest.public.symfony + "/" + cfg.dest, filename, dest.manifest.symfony + "/" + cfg.dest, dest.build.symfony + '/' + filename)));
    });
    compilePageTasks.push(compileTaskName);

    /**
     * Compress task
     */
    gulp.task(compressTaskName,function(){      
      var pageFiles = cfg.dest==='css' ? dest.build.symfony + '/' + filename + '/*' : (cfg.includeBower ? bowerFiles.concat(cfg.src) : cfg.src);
      return gulp.src(pageFiles)
        .pipe(gulpif(cfg.dest==='css',tasks.css_min(
          dest.public.symfony + "/" + cfg.dest, filename.replace(".css",".min.css"), dest.manifest.symfony + "/" + cfg.dest
        )))
        .pipe(gulpif(cfg.dest==='js',tasks.js_min(
          dest.public.symfony + "/" + cfg.dest, filename.replace(".js",".min.js"), dest.manifest.symfony + "/" + cfg.dest
        )));
    });
    compressPageTasks.push(compressTaskName);
  });

  if(copySrc.length>0){
    copySrc = copySrc.concat(['!'+bundleRoot+'/Resources/public/static/**','!web/bundles/assets/static/**']);
  }
  symfonyManifest = updatedSymfonyManifest;
})();

/**
 * Compile deps added by bower - add tasks if we aren't just processing files
 */
var bowerFiles = bower({base:bowerSource});
if(argv.files===false){
  gulp.task('compile:bower', function() {  
    return gulp.src(bowerFiles)
    .pipe(tasks.scss(dest.build.bower))
    .pipe(tasks.css(dest.public.bower, 'compiled.css', dest.manifest.bower, dest.build.bower))
    .pipe(tasks.js(dest.public.bower, 'compiled.js', dest.manifest.bower, dest.build.bower));
  });
  gulp.task('build:bower', gulp.series('compile:bower', function() { 
    //fetch files that have been built - not the uncompressed file as we won't get the right maping
    return gulp.src([dest.public.bower + '/!(*-*).+(js|css)'])
    .pipe(debug({title: 'build:bower'}))
    .pipe(tasks.css_min(dest.public.bower, 'compiled.min.css', dest.manifest.bower))
    .pipe(tasks.js_min(dest.public.bower, 'compiled.min.js', dest.manifest.bower));
  }));
}

gulp.task('copy:symfony', function(){
  return gulp.src(copySrc, { base: '.' })
      .pipe(gulp.dest(dest.source.symfony));
});
gulp.task('build:symfony', gulp.series(
  'copy:symfony',
  gulp.parallel(compilePageTasks)
));
gulp.task('compress:symfony', gulp.series(
  'build:symfony',
  gulp.parallel(compressPageTasks)
));

gulp.task('revisions', function(){
  var revisionsSrc;
  if(argv.files!==false){
    revisionsSrc = [];
    argv.files.forEach(function(fileArg){
      var minFileInfo = getMinFile(fileArg),
      minFile = minFileInfo.filename,
      revRoot = bundleRoot+'/Resources/public/static/dist/'+minFileInfo.extension.substring(1)+'/';

      revisionsSrc.push(
        revRoot + fileArg,
        revRoot + minFile
      );
    });
  }else if(argv.clean!==false){
    revisionsSrc = [
      dest.manifest.file
    ];
  }else{
    revisionsSrc = [
      bundleRoot+'/Resources/public/*/dist/**/!(*-*).+(js|css)'
    ];
  }
  return gulp.src(revisionsSrc, { allowEmpty:true })

    .pipe(debug({title: 'revisionsSrc'}))

    // if we aren't cleaning then the input is the files we should be making revisions of
    .pipe(gulpif( (argv.clean===false), revisionsFull() ))
    //Check for files no longer used to remove them manually, if we are only updating a set of files
    .pipe(gulpif( (argv.files!==false || argv.clean!==false), deleteUnusedFiles() ))

    //compare old and new manifest, delete any files which are no longer in the manifest
    .pipe(
      revDel({
        oldManifest: dest.manifest.file,
        dest: bundleRoot+'/Resources/public',
        deleteMapExtensions: true
      })
    )
    //write the new updated manifest
    .pipe(gulp.dest('.'))

    //delete folders where the build files are for the files which have been deleted
    .pipe(deleteUnusedBuildFolders());
});

//Do not compile bower if we are only asked to cxompile a single key/file
var defaultTasks = argv.files!==false ? gulp.series('compress:symfony','revisions') : gulp.series('build:bower','compress:symfony','revisions');
gulp.task('default', defaultTasks);

gulp.task('watch', function(){
  var origKeys;
  function onlyNewManifestKeys(value, index, self) { 
      return origKeys.indexOf(value) === -1;
  }

  var assetWatcher = gulp.watch(assetHashesPath);
  assetWatcher.on('change',function(watchEvent, watchPath, watchStats){
    var origKeys = Object.keys(symfonyManifest).map(function(val){
      return path.basename(val);
    }),
    gulpArgs = [];

    //update Symfony manifest var
    symfonyManifest = JSON.parse(fs.readFileSync(assetHashesPath).toString());

    var newManifestKeys = Object.keys(symfonyManifest);
    //check new keys against old
    newManifestKeys.forEach(function(manifestKey){
      var basename = path.basename(manifestKey);
      if(origKeys.indexOf(manifestKey)===-1){
        //we have a file that was not in the last manifest
        gulpArgs.push('-f');
        gulpArgs.push(basename);
      }
      return basename;
    });

    var gulpSpawn;
    if(gulpArgs.length>0){
      console.log(gulpArgs);
      gulpSpawn = spawn('gulp',gulpArgs);
      gulpSpawn.stdout.on('close', (code) => {
        console.log('gulp spawn finished/closed with code: ' + code);
      });
    }else if(newManifestKeys.length!==origKeys.length){
      gulpArgs = ['revisions','--clean'];
      console.log(gulpArgs);
      //something must be missing
      gulpSpawn = spawn('gulp',gulpArgs);
      gulpSpawn.stdout.on('close', (code) => {
        console.log('gulp spawn finished/closed with code: ' + code);
      });
    }
    //respawn to watch new asset files
    spawnWatchAssets();
  });

  var bowerWatcher = gulp.watch(bundleRoot + "/Resources/public/bower/source/**",function(watchEvent, watchPath, watchStats){
    console.log('bower changed - running `gulp default`');
    gulpSpawn = spawn('gulp',['default']);
    gulpSpawn.stdout.on('close', (code) => {
      console.log('gulp spawn finished/closed with code: ' + code);
    });
  });

  var spawnedAssetWatcherProcess;
  function spawnWatchAssets() {
    // kill previous spawned process
    if(spawnedAssetWatcherProcess) { console.log('Killed assetHashes.json watcher'); spawnedAssetWatcherProcess.kill(); }

    // `spawn` a child `gulp` process linked to the parent `stdio`
    console.log('Launching process to watch assets from assetHashes.json');
    spawnedAssetWatcherProcess = spawn('gulp', ['watch-current-assets','--spawned']);
    spawnedAssetWatcherProcess.stdout.on('data', (output) => {
      console.log('assetHash.json watcher: ' + output);
    });
  }
  spawnWatchAssets();
});