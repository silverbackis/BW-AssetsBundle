<?php
namespace BW\AssetsBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use BW\AssetsBundle\DependencyInjection\Compiler\TwigChainLoaderPass;

class BWAssetsBundle extends Bundle
{
	public function build(ContainerBuilder $container)
    {
        parent::build($container);
        $container->setParameter('google_fonts', null);
        $container->setParameter('local_assets', array());
        $container->addCompilerPass(new TwigChainLoaderPass());
    }
}