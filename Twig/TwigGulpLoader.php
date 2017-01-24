<?php
namespace BW\AssetsBundle\Twig;

use Twig_LoaderInterface;

class TwigGulpLoader implements Twig_LoaderInterface
{
    public function getSource($name)
    {
        if($name=='gulpLoader'){
            return "{% extends gulp_extend_path %} {% block stylesheets %} {{ parent() }} {{ createManifests() }}{{ outputLocalAssets('css') }} {% endblock %} {% block javascripts %} {{ parent() }} {{ outputLocalAssets('js') }} {% endblock %}";
        }else{
            throw new \Twig_Error_Loader("This loader only precces gulpLoader, not $name");
        }
    }

    public function isFresh($name, $time)
    {
        return true;
    }

    public function getCacheKey($name)
    {
        // check if exists
        return 'gulp:'.$name;
    }
}