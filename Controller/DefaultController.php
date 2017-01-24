<?php
namespace BW\AssetsBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class DefaultController extends Controller
{
    public function render($view, array $parameters = array(), Response $response = null)
    {
        $parameters['gulp_extend_path'] = $view;
        
		//render the latest twig template that extends whatever was submitted as the view
    	$rendered = parent::render('gulpLoader', $parameters, $response);

    	return $rendered;
    }
}