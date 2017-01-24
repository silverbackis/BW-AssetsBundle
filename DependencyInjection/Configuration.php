<?php
namespace BW\AssetsBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/configuration.html}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('assets');

        // Here you should define the parameters that are allowed to
        // configure your bundle. See the documentation linked above for
        // more information on that topic.
        $rootNode
            ->children()
                ->arrayNode('local')
                    ->children()
                        ->booleanNode('gulpOnLoad')
                            ->isRequired()
                        ->end()
                        ->booleanNode('includeBower')
                            ->isRequired()
                        ->end()
                        ->scalarNode('read_from')
                            ->isRequired()
                        ->end()

                        ->arrayNode('assets')
                            ->beforeNormalization()
                                ->ifNull()
                                ->then(function($v){
                                   return array();
                                })
                                ->ifString()
                                ->then(function($v){
                                   return array($v);
                                })
                            ->end()
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('dependancies')
                    ->useAttributeAsKey('name')
                    ->prototype('array')//e.g. bootrap (the res that has dependants)
                            ->useAttributeAsKey('name')
                            ->prototype('array')// e.g jquery_slim (the dependant) - this can be true to enable all or array of which sub res is dependant
                                ->beforeNormalization()
                                    ->always(function($v){
                                        if(is_bool($v)){
                                            return array(
                                                'all'=>$v
                                            );
                                        }
                                        return $v;
                                    })
                                ->end()
                                //specify any key
                                ->prototype('boolean')
                                    ->defaultFalse()
                                ->end()
                            ->end()
                    ->end()
                ->end()
                ->arrayNode('remote')
                    ->useAttributeAsKey('name')
                    ->prototype('array')

                        ->treatFalseLike(array('enabled' => false))
                        ->treatNullLike(array('enabled' => false))
                        ->treatTrueLike(array('enabled' => true))

                        ->children()
                            ->booleanNode('enabled')
                                ->defaultFalse()
                            ->end()
                            ->scalarNode('version')->end()
                            ->scalarNode('base')
                                ->isRequired()
                            ->end()
                            ->arrayNode('files')
                                ->useAttributeAsKey('name')
                                ->prototype('array')

                                    ->treatFalseLike(array('enabled' => false))
                                    ->treatNullLike(array('enabled' => false))
                                    ->treatTrueLike(array('enabled' => true))

                                    ->children()
                                        ->booleanNode('enabled')
                                            ->defaultTrue()
                                        ->end()
                                        ->enumNode('extension')
                                            ->info("The extension property can be set if the extension cannot be discovered from the path. It can be css or js.")
                                            ->values(array("js","css"))
                                        ->end()
                                        ->scalarNode('file')
                                            ->isRequired()
                                        ->end()
                                        ->scalarNode('integrity')->end()

                                        ->arrayNode('includes')
                                            ->prototype('array')
                                                ->beforeNormalization()
                                                    ->always(function($v){
                                                        if(is_bool($v)){
                                                            return array(
                                                                'all'=>$v
                                                            );
                                                        }
                                                        return $v;
                                                    })
                                                ->end()
                                                ->prototype('scalar')->end()
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
        return $treeBuilder;
    }
}
