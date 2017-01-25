# BW Core - Assets Bundle
##This is a work in progress
This Symfony 3 Bundle was created to make the deployment of front-end assets using Bower and Gulp easier. It also provides a simple confirguration for including scripts and styles from remote URLs if you'd prefer to utilise the CDNs available.

## Requirements
* npm
* composer
* gulp-cli
* bower

This bundle has a gulpfile.js using Gulp 4 - provided package.json will install this. The setup console command will guide you. If you choose to copy and modify the configuration files manually, you can copy them from the within this bundle: **Resources/\_setup/**

## Installation
You can install this bundle using composer (from the current unreleased master branch):
```bash
composer require silverbackis/bw-assets-bundle:dev-master
```

Then enable the bundle:
```php
// app/AppKernel.php

// ...
class AppKernel extends Kernel
{
    // ...

    public function registerBundles()
    {
        $bundles = array(
            // ...
            new BW\AssetsBundle\BWAssetsBundle(),
        );

        // ...
    }
}
```

You can also run the following command to automatically add this to your AppKernel.php file
```bash
php vendor/silverbackis/bw-assets-bundle/Install.php
```

Once the bundle is enabled you can run the following command to copy the bower.json, gulpfile.js and optionally a package.json file
```bash
php bin/console bwcassets:setup
```

This will ask interactive questions to determine where you'd like to run your gulp and bower commands from - it will default to your Symfony project's root directory. Bower dependancies will be installed within this bundle. That is so the sourcemaps generated can refer back to them so you can find them when inspecting your assets in a browser. Additionally, all local assets you include in pages will also be copied into this bundle when they are processed.

Finally, it will be important to install your assets, otherwise your files will not exist to load. Symlink support is a must for an easy workflow:
```bash
php bin/console assets:install --symlink
```

## Getting Started
To automatically insert tags in your templates you'll need to make a couple of small modifications to your controllers and templates.

Your controllers must extend the default controller of this bundle. The DefaultController.php file below can be used as a template and will also include a simple test page. You can of course copy the index.html.twig file into your own bundle and include it so you can play around with the functions available and see which resources are included.
```php
# src/AppBundle/Controller/DefaultController.php
<?php
namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use BWCore\AssetsBundle\Controller\DefaultController as Controller;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        return $this->render('@BWAssets/Default/index.html.twig', [
            'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..').DIRECTORY_SEPARATOR,
        ]);
    }
}

```

Also, you should extend a base twig template with certain blocks that are used by this bundle:
```twig
{% extends '@Assets/base.html.twig' %}
```

Again, feel free to copy this base template and modify it for your own needs.

## Parameters and configuration
You'll probably want to familiarize yourself with the parameters and default configuration for this bundle (which you can override as usual).

**parameters.yml:**
```yaml
parameters:
  google_fonts: null
  local_assets: []
```

**config.yml:**
```yaml
bwc_assets:
	local:
	  gulpOnLoad: false
	  includeBower: true
	  read_from: '%kernel.root_dir%/../web/'
	  assets: '%local_assets%'
	dependancies:
	  bootstrap:
	  	tether: true
	    jquery: 
	      slim: true
	remote:
	    googleFonts:
	      enabled: true
	      version: null
	      base: //fonts.googleapis.com/css?family=
	      files:
	        css:
	          enabled: true
	          extension: css
	          file: '%google_fonts%'
	    jquery: 
	      version: 3.1.1
	      base: //code.jquery.com/
	      files:
	        slim: 
	          file: jquery-{version}.slim.min.js
	          integrity: sha256-/SIrNqv8h6QGKDuNoLGA4iret+kyesCkHGzVUUV0shc=
	        js: 
	          file: jquery-{version}.min.js
	          integrity: sha256-hVVnYaiADRTO2PzUGmuLJr8BLUSjGIZsDYGmIJLv2b8=
	          includes:
	            jquery: ['slim']
	    tether: 
	      version: 1.4.0
	      base: //cdnjs.cloudflare.com/ajax/libs/tether/{version}/
	      files:
	        js: 
	          file: js/tether.min.js
	          integrity: sha384-DztdAPBWPRXSA/3eYEEUWrWCy7G5KFbe8fFjk5JAIxUYHKkDx6Qin1DkWx51bBrb
	    bootstrap: 
	      version: 4.0.0-alpha.6
	      base: //maxcdn.bootstrapcdn.com/bootstrap/{version}/
	      #this also needs jquery OR jquey_slim and tether
	      files:
	        css:
	          file: css/bootstrap.min.css
	          integrity: sha384-rwoIResjU2yc3z8GV/NPeZWAv56rSmLldC3R/AZzGRnGxQQKnKkoFVhFQhNUwEyJ
	        js: 
	          file: js/bootstrap.min.js
	          integrity: sha384-vBWWzlZJ8ea9aCX4pEW3rVHjgjt7zpkNpZk+02D9phzyeVkE+jo0ieGizqPLForn
	    gsap: 
	      enabled: true
	      version: 1.19.0
	      base: //cdnjs.cloudflare.com/ajax/libs/gsap/{version}/
	      files:
	        TweenLite:
	          enabled: true
	          file: TweenLite.min.js
	        EasePack:
	          enabled: true
	          file: easing/EasePack.min.js
	        CSSPlugin:
	          enabled: true
	          file: plugins/CSSPlugin.min.js
	        TweenMax:
	          enabled: false
	          file: TweenMax.min.js
	          includes: 
	            gsap: ['TweenLite','EasePack','CSSPlugin','TimelineLite','TimelineMax','AttrPlugin','RoundPropsPlugin','DirectionalRotationPlugin','BezierPlugin']
	        TimelineLite:
	          enabled: false
	          file: TimelineLite.min.js
	        TimelineMax:
	          enabled: false
	          file: TimelineMax.min.js
	          includes: 
	            gsap: ['TimelineLite']
	        Draggable:
	          enabled: false
	          file: utils/Draggable.min.js
	        AttrPlugin:
	          enabled: false
	          file: plugins/AttrPlugin.min.js
	        BezierPlugin:
	          enabled: false
	          file: plugins/BezierPlugin.min.js
	        ColorPropsPlugin:
	          enabled: false
	          file: plugins/ColorPropsPlugin.min.js
	        CSSRulePlugin:
	          enabled: false
	          file: plugins/CSSRulePlugin.min.js
	        DirectionalRotationPlugin:
	          enabled: false
	          file: plugins/DirectionalRotationPlugin.min.js
	        EaselPlugin:
	          enabled: false
	          file: plugins/EaselPlugin.min.js
	        EndArrayPlugin:
	          enabled: false
	          file: plugins/EndArrayPlugin.min.js
	        ModifiersPlugin:
	          enabled: false
	          file: plugins/ModifiersPlugin.min.js
	        RaphaelPlugin:
	          enabled: false
	          file: plugins/RaphaelPlugin.min.js
	        RoundPropsPlugin:
	          enabled: false
	          file: plugins/RoundPropsPlugin.min.js
	        ScrollToPlugin:
	          enabled: false
	          file: plugins/ScrollToPlugin.min.js
	        TextPlugin:
	          enabled: false
	          file: plugins/TextPlugin.min.js
```

The parameters and configuration will be referenced in the documentation for remote and local assets:

***
> * [Working with remote assets](https://github.com/silverbackis/BW-AssetsBundle/blob/master/Docs/Remote%20Assets.md)
> * [Working with local assets](https://github.com/silverbackis/BW-AssetsBundle/blob/master/Docs/Local%20Assets.md)

***

## Limitations ##
* You cannot include assets inside conditional statements within your twig templates. It is possible in future there could be a config for you to specify which routes have conditions and what they are, but it has not been included yet.
