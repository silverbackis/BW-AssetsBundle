<?php
namespace BWCore\AssetsBundle;
//script to add bundle to the kernel
use Sensio\Bundle\GeneratorBundle\Manipulator\KernelManipulator;
class Install{
	public function install(){
		$loader = require __DIR__.'/../../autoload.php';
		require_once __DIR__.'/../../../var/bootstrap.php.cache';
		$kernel = new \AppKernel('prod', false);
		$kernelManipulator = new KernelManipulator($kernel);
		$bundleClassName = 'BW\AssetsBundle\BWAssetsBundle';
		try {
			$ret = $kernelManipulator->addBundle($bundleClassName);
			if(!$ret){
				echo 'Add the bundle '.$bundleClassName.'() to your AppKernel.php manually';
			}
		} catch (\RuntimeException $e) {
			echo sprintf("Bundle %s is already defined in AppKernel::registerBundles()\n", $bundleClassName);
		}
	}
}
$install = new Install();
$install->install();