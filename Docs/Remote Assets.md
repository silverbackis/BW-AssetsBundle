# BW Core - Assets Bundle #
## Configuring remote assets ##

Once you've setup this bundle, configuring remote assets is simple. You can also define if a file includes others (e.g. jquery standard edition includes jquery slim - you don't want jquery slim as well, that'd be a waste).

This bundle will detect whether the page has been loaded securely, and if so the URLs used for your external resources will match (unless the scheme is explicitly set in the base URL for an asset)

The first thing to note is an available parameter for remote assets:
```yaml
parameters:
  google_fonts: null
  local_assets: []
```

If you specify **google_fonts** it should be the string the 'family' querystring variable from the google fonts URL. E.g:
```yaml
parameters:
  google_fonts: 'Frank+Ruhl+Libre|Open+Sans:300,400|Padauk|Roboto'
```
will include the style loaded from **//fonts.googleapis.com/css?family=Frank+Ruhl+Libre|Open+Sans:300,400|Padauk|Roboto**

### Customising remote assets
This is your default configuration for remote assets:
```yaml
...
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
...
```

You can override any setting you like in your own configuration. You can also define new resources. Include **{version}** in any part of the URL being constructed and the **version** key will be inserted. The **includes** key on a file allows you to define if one file includes all the functions of another. You can also define dependancies to be includes automatically.

### Including remote assets from PHP
You may want to adjust the remote assets being loaded on specific pages, either to add functionality or reduce load time for unused assets.

You can retreive the service like this: 
```php
...
$bw_assets = $this->get('bw.assets');
...
```

#### Methods available:
* getResources($extension=false,$external=null)
* updateConfig($newConfig)
* includeResource($resourceKey,$fileKey=false,$enabled=true)
* removeResourceByKey($resourceKey)
* setConfig($config)
* getConfig()

### Including remote assets from Twig templates
This feature has not been developed yet. If you feel it would be useful please feel free to submit a pull request and expose methods in the current twig extension to these methods.

***
> * [Getting Started Readme](https://github.com/silverbackis/BWCore-AssetsBundle)
> * [Working with local assets](https://github.com/silverbackis/BWCore-AssetsBundle/blob/master/Docs/Local%20Assets.md)

***

