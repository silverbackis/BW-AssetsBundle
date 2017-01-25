<?php
namespace BW\AssetsBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

use Symfony\Component\Yaml\Yaml;
/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @link http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
class BWAssetsExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        
        //insert the default configuration
        $defaultConfig = Yaml::parse(
            file_get_contents(__DIR__.'/../Resources/config/config.yml')
        );
        array_unshift($configs, $defaultConfig);

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');

        $layoutService = $container->getDefinition( 'bw.assets' );

        //reuporpose the input local assets - not straight into config, but to be processed like they are from twig template with addLocalAssets method
        $newLocalAssets = $config['local']['assets'];
        unset($config['local']['assets']);

        $layoutService->addMethodCall( 'setConfig', array($config) );
        $layoutService->addMethodCall( 'addLocalAssets', array($newLocalAssets) );
    }
}
