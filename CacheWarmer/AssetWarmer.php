<?php

namespace BW\AssetsBundle\CacheWarmer;

use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Templating\TemplateReference;
use Symfony\Bundle\FrameworkBundle\CacheWarmer\TemplateFinderInterface;
use Symfony\Component\Console\Output\OutputInterface;
//use Symfony\Bundle\TwigBundle\CacheWarmer\TemplateCacheCacheWarmer;
//use Symfony\Bundle\TwigBundle\CacheWarmer\TemplateCacheWarmer;

class AssetWarmer implements CacheWarmerInterface
{
	protected $finder;
	private $paths;

	public function __construct(\Twig_Environment $twig, TemplateFinderInterface $finder = null, array $paths = array())
	{
		$this->twig = $twig;
		$this->finder = $finder;
		$this->paths = $paths;
	}

	public function warmUp($cacheDir)
    {
        /*if (null === $this->finder) {
            return;
        }
        $templates = $this->finder->findAllTemplates();
        
        foreach ($this->paths as $path => $namespace) {
            $templates = array_merge($templates, $this->findTemplatesInFolder($namespace, $path));
        }

        foreach ($templates as $template) {
            if ('twig' !== $template->get('engine')) {
                continue;
            }
            try {
            	//changed this so we render the template, thereby executing the PHP and populating the manifest
            	echo $template."\n";
                echo $this->twig->render($template);
            } catch (\Twig_Error $e) {
                // problem during compilation, give up
            }
        }*/
    }

    public function isOptional()
    {
        return true;
    }

    /**
     * From Symfony\Bundle\TwigBundle\CacheWarmer\TemplateCacheCacheWarmer
     * Find templates in the given directory.
     *
     * @param string $namespace The namespace for these templates
     * @param string $dir       The folder where to look for templates
     *
     * @return array An array of templates of type TemplateReferenceInterface
     */
    private function findTemplatesInFolder($namespace, $dir)
    {
        if (!is_dir($dir)) {
            return array();
        }
        $templates = array();
        $finder = new Finder();
        foreach ($finder->files()->followLinks()->in($dir) as $file) {
            $name = $file->getRelativePathname();
            $templates[] = new TemplateReference(
                $namespace ? sprintf('@%s/%s', $namespace, $name) : $name,
                'twig'
            );
        }
        return $templates;
    }
}