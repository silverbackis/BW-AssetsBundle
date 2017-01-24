<?php
namespace BW\AssetsBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Routing\Router;
use Symfony\Component\Routing\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Debug\Exception\UndefinedMethodException;

class ManifestCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
        	->setName('bwcassets:manifest')
        	->addOption(
        		'gulp', 
        		'g', 
        		InputOption::VALUE_NONE, 
        		'Whether you\'d also like to run `gulp default` once the assetHashes.json file is made'
        	)
        	->setDescription('Updates assetHashes.json for Gulp')
        	->setHelp('Finds all available routes and updates assetHashes.json file for Gulp.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
    	$output->writeln([
	        "",
	        '<info>Truncating current manifest...</info>',
	    ]);

    	$manifestFile = __DIR__.'/../Resources/manifests/pageAssets.json';
		if (($f = fopen($manifestFile, "w"))!==false) {
		    fclose($f);
		}

		$output->writeln([
			"<info>DONE</info>",
			"",
			"<info>Generating manifest...</info>"]);

	    $CONTAINER = $this->getContainer();
	    $routes = $CONTAINER->get('router')->getRouteCollection();
		$requestStack = $CONTAINER->get('request_stack');
	    foreach($routes as $routeKey=>$route){
	    	if(substr($routeKey,0,1)==='_') continue;

	    	$output->writeln([
	    		"----"
	    	]);
	    	$request = Request::create(
	    		$route->getPath(),
	    		'GET',
	    		array('_route'=>$routeKey)
	    	);
	    	$requestStack->push($request);
	    	$request->request->set('route',$routeKey);
	    	$requestStack->push($request);
	    	$currentRequest = $requestStack->getCurrentRequest()->get("_route");
	    	$output->writeln([
	    		"Found route with name <comment>$currentRequest</comment>"
	    	]);
	    	
	    	//get the default controller for the route that's been found
	    	$defaultContoller = $route->getDefault('_controller');
	    	$namespaceMethodExplode = explode("::",$defaultContoller);

	    	//call the controller and set current container
	    	$controllerClassStr = $namespaceMethodExplode[0];
	    	if (!class_exists($controllerClassStr)) {
	    		$output->writeln([
	    			"<fg=red;options=bold,underscore>You have an undefined class in your router configuration: ".$controllerClassStr."</>"
	    		]);
	    	}else{
	    		$output->writeln([
	    			"Starting controller class controller <comment>new $controllerClassStr()</comment>"
	    		]);
		    	$controller = new $controllerClassStr();
		    	$controller->setContainer($CONTAINER);
		    	$methodStr = $namespaceMethodExplode[1];
		    	if(!method_exists($controllerClassStr,$methodStr)){
		    		$output->writeln([
		    			"<fg=red;options=bold,underscore>You have an undefined method named `$methodStr` of class `$controllerClassStr`</>"
		    		]);
		    	}else{
		    		//call the method/action and set current request
			    	$methodStr = $namespaceMethodExplode[1];
			    	$output->writeln([
			    		"Calling method: <comment>$methodStr()</comment>"
			    	]);
			    	$controller->$methodStr($request);
			    	$output->writeln([
			    		"<comment>Done</comment>"
			    	]);
		    	}
	    	}
	    	$requestStack->pop($request);
	    	$output->writeln([
	    		"----"
	    	]);
	    }
	    if($input->getOption('gulp')===true){
	    	$exec = 'gulp default';
	    	$output->writeln([
	    		"<fg=cyan;options=bold,underscore>Attempting to execute `$exec`</>"
	    	]);
	    	exec($exec);
	    	$output->writeln([
	    		""
	    	]);
	    }else{
	    	$output->writeln([
	    		"<fg=cyan;options=bold,underscore>If you are not currently running `gulp watch` you should now run `gulp build` to rebuild your assets</>",
	    		""
	    	]);
	    }
    }
}