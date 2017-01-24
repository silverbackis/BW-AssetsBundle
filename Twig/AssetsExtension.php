<?php
namespace BW\AssetsBundle\Twig;
use BW\AssetsBundle\Assets;

class AssetsExtension extends \Twig_Extension
{
	protected $BWAssets;
	public function __construct(Assets $BWAssets){
		$this->BWAssets = $BWAssets;
	}
	public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('outputLocalAssets', array($this->BWAssets, 'outputLocalAssets'), array(
	            'is_safe' => array('html')
	        )),
            new \Twig_SimpleFunction('addLocalAssets', array($this->BWAssets, 'addLocalAssets')),
            new \Twig_SimpleFunction('removeLocalAssets', array($this->BWAssets, 'removeLocalAssets')),
            new \Twig_SimpleFunction('getAssetsArray', array($this->BWAssets, 'getAssetsArray')),
            new \Twig_SimpleFunction('getRealLocalAssets', array($this->BWAssets, 'getRealLocalAssets')),
            new \Twig_SimpleFunction('createManifests', array($this->BWAssets, 'createManifests')),
            new \Twig_SimpleFunction('disableBower', array($this->BWAssets, 'disableBower')),
            new \Twig_SimpleFunction('enableBower', array($this->BWAssets, 'enableBower'))
        );
    }
    public function getName()
    {
        return 'bw.assets.twig';
    }
}