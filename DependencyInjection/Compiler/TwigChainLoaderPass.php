<?php
namespace BW\AssetsBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

class TwigChainLoaderPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $definition = $container->getDefinition('twig');
        $definition->addMethodCall('setLoader', array(new Reference('bw.assets.twig_chain_loader')));
    }
}