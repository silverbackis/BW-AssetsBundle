# BW Core - Assets Bundle #
## Configuring local assets ##

This is probably the most complex part of this bundle. It handles assets installed by Bower local assets and manifests which allow Gulp to process the files and always returns just 1 css style and 1 script. Gulp will create minified and unminified versions, but will always have a sourcemap for easy debugging in your browser. It also includes cache busting using **gulp-rev** so when you modify files, the path will change in your template forcing a browser to reload the file.

Gulp will copy all your local assets into this bundle so they can be referenced and linked to with source maps.

The first thing to note is an available parameter for local assets:
```yaml
parameters:
  local_assets: []
```

Your **local_asset** parameter can be an array of local assets that should be included with every page.

**config.yml (local resources)**
```yaml
...
    local:
      gulpOnLoad: false
      includeBower: true
      read_from: '%kernel.root_dir%/../web/'
      assets: '%local_assets%'
...
```

### Defining local assets paths
Whenever you define local assets, you can use bundle namespaces, either with or without the 'Bundle' postfix. E.g. to reference this bundle you could write **@Asset** or **@AssetBundle** at the start of your string.

You can also include wildcards after you've started to define your include path. E.g. you cannot have the path `*` but you can use `@Asset/Resources/Local/*` or `@Asset/Resources/Local/*.css`

If you do not reference a bundle, the path you specify will be relative to the **read_from** configuration key. By default this is the **web** directory. Make sure you've run **php app/console assets:install --symlink** so the paths are pointing into your bundles public resources.

You can include these file types:
* Javascript (.js)
* Stylesheets (.css)
* Sassy CSS (.scss)
Support for sass and less has not been added yet.

When you use **include** or **import** statement, the Bower root directory is defined as a potential include path. E.g. include Bootstrap and a custom \_variables file like so:
```scss
//custom bootstrap variables - path relative to current file
@import "bootstrap/variables";

// import bootstrap main scss - path relative to bower source root
@import "bootstrap/scss/bootstrap";
```

### Including local assets from Twig templates
Below is an example of the twig functions that you have available
```twig
{# Include your functions for local assets to be processed by gulp in a stylesheets_gulp block #}
{% block stylesheets_gulp %}
    {# If you don't include parent() when you start running functions, it'll prevent your previous twig template's functions from ever being called - start fresh #}
    {{ parent() }}

    {# disableBower() or enableBower() #}
    {{ disableBower() }}

    {# Add assets using @ namespace references and wildcards #}
    {{ addLocalAssets(['@Assets/Resources/sample/*']) }}

    {# You can also remove assets in the same way #}
    {{ removeLocalAssets(['@Assets/Resources/sample/*.js']) }}
{% endblock %}
```

### Creating your assets manifest for Gulp
The filenames generated in the page assets manifest are a hash of the source filenames, so if you change what files are being included in the template, the hash will change. This process is automated.

**Manifests will only generate when in the debug/development environment.**

In many cases, it probably will not be convenient having to load each page that includes assets to make sure the manifest is updated. You can run a console command which will loop through all available route keys and run the controller's method - thereby updating the manifest for each page:
```bash
php bin/console bwcassets:manifest
```

**This function also truncates your current manifest, cleaning it of any routes that may no longer exist**

If you are not running the **gulp watch** task then you can also use the `--gulp` or `-g` flag which will try and execute **gulp default** once all controller methods have been run.
```bash
php bin/console bwcassets:manifest --gulp
```

**Remember, for PHP to execute gulp for you, gulp must be able to be launched by the same user as PHP is running as. Otherwise, just run the gulp command manually.**

### Using the generated manifest
There are a number of ways you can set Gulp to process updates from the generated manifest. The first and probably most useful method is to run **`gulp watch`** from the command line. This will watch the page assets manifest. The command will also monitor all the source files, so if you change them your compiled files will be remade.

When gulp runs, it adds a revision hash to the end of your hashed filenames - this will change if the contents of your file changes. It also produces a manifest that is read by this bundle so that the template can insert the correct URL to the latest revsision.

The other way you can trigger gulp to run is by updating the **gulpOnLoad** options in your configuration to true. When the manifest changes (i.e. you change the files included on a page) and then you load the page, PHP will try to execute a gulp command to remake the files. This won't trigger an update if you update one of the source files though at the moment. If this feature proves a useful way to regenerate files, this may be extended.

### Available Gulp tasks
Below is a list of the tasks and options you can pass to Gulp with the configuration added by this bundle.
```bash
#run on specfic hashed filenames that have been generated by Symfony based on the filenames included
gulp -f filehash1.css -f filehash2.css

#Watch source files and file changes from assetHashes.json
gulp watch

#build all bower files and files present in assetHashes.json from Symfony bundle
gulp default
```

***
> * [Getting Started Readme](https://github.com/silverbackis/BW-AssetsBundle)
> * [Working with remote assets](https://github.com/silverbackis/BW-AssetsBundle/blob/master/Docs/Remote%20Assets.md)

***
