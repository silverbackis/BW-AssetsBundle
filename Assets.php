<?php
namespace BW\AssetsBundle;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Router;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Config\FileLocator;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Asset\Packages;

class Assets implements EventSubscriberInterface{
	protected $config,
	$kernel,
	$requestStack,
    $fileLocator,
    $packages,
	$params = array(),
	$assetKeys = array(),
	$assets = array(),
	$scheme,
    $configProcessed = false,
    $silentIncludes = array(),
    $assetLoadError,
    $defaultPackage,
    $allowedExtensions = array(
        "css",
        "js",
        "scss"
    ),
    $extensionCompiled = array(
        "css"=>array("scss")
    ),
    $fileHashes = array();

	public function __construct(KernelInterface $kernel, RequestStack $requestStack, FileLocator $fileLocator, Packages $packages)
    {  
    	$this->kernel = $kernel;
    	$this->requestStack = $requestStack;
        $this->fileLocator = $fileLocator;
    	$this->params['root'] = substr($this->kernel->getRootDir(),0,-3);
        $this->packages = $packages;
        $this->defaultPackage = $this->packages->getPackage();
    }

    /**
     * subscribe to the kernel.request event because we want to be able to tell whether the request is secure or not
     */
    public static function getSubscribedEvents()
    {
        // return the subscribed events, their methods and priorities
        return array(
           KernelEvents::REQUEST => array(
               array('processConfig', 0),//0 is a priority if more than 1 method to listen to the event
           )
        );
    }

    protected function pathMapToManifest($filename){
        $manifestPath =  __DIR__.'/Resources/manifests/revisionManifest.json';
        
        if (!file_exists($manifestPath)) {
            $this->assetLoadError = '**NO-REVISION-MANIFEST-FILE**';
            return false;
        }

        $paths = json_decode(file_get_contents($manifestPath), true);

        if (!isset($paths[$filename])) {
            $this->assetLoadError = '**NO-REVISION-KEY-IN-MANIFEST**';
            return false;
        }
        return 'bundles/bwassets/'.$paths[$filename];
    }
    
    protected function generateFileHash($extension,$remakeHash=false){
        if(!isset($this->fileHashes[$extension]) || $remakeHash){
            $this->fileHashes[$extension] = ($this->config['local']['includeBower'] ? "+" : "").hash("crc32b",serialize($this->config['local']['assets'][$extension])).".$extension";
        }
    }
    /**
     * getLocalAssetPath will determine what the path will be for a local asset, including hashing the serialized array for each file extension group
     * @param  string  $extension  file extension (e.g. js, css, scss)
     * @param  boolean $remakeHash [description]
     * @return [type]              [description]
     */
    protected function getLocalAssetPath($extension){
        //use the minified versions in production
        if(!$this->config['local']['includeBower'] && !isset($this->config['local']['assets'][$extension])) return false;

        $srcExt = !$this->kernel->isDebug() ? "min.$extension" : $extension;
        //if the extension does not exist for local assets and we are here, include bower - if bower not to be included, we'd have returned false above
        if(!isset($this->config['local']['assets'][$extension])){
            $path = $this->pathMapToManifest("bower/dist/compiled.$srcExt");
        }else{
            $path = $this->pathMapToManifest("static/dist/$extension/".str_replace($extension,$srcExt,$this->fileHashes[$extension]));
        }
        return $path;
    }
    /**
     * outputLocalAssets - function called from twig to output the asset URL that gulp will create for the files on the page
     * will create a manifest for the specific file extension requested
     */
    public function outputLocalAssets($extension){
        $path = $this->getLocalAssetPath($extension);
        if(!$path){
            $tags = '';
            foreach($this->config['local']['assets'][$extension] as $sourceFile){
                $tags .= $this->getTag($sourceFile,$extension);
            }
            return $tags;
        };
        //return the HTML tag
        return $this->getTag($path,$extension);
    }

    private function getTag($path,$extension){
        switch($extension){
            case "css":
                return '<link rel="stylesheet" href="'.$this->defaultPackage->getUrl($path).'" />';
            break;
            case "js":
               return '<script src="'.$this->defaultPackage->getUrl($path).'"></script>';
            break;
        }
    }
    public function disableBower(){
        $this->config['local']['includeBower'] = false;
    }
    public function enableBower(){
        $this->config['local']['includeBower'] = true;
    }
    /**
     * createManifests processes the current page assets and creates manifests - assetHashes which determines a filename and the files to be concat into it & pageAssets which determines which pages use which concat files
     * pathMapToManifest function will use another manifest created by gulp to map to revisions as the file changes
     * @return [type] [description]
     */
    public function createManifests(){
        //only update manifests when in dev
        if(!$this->kernel->isDebug()) return;

        $currentRequest = $this->requestStack->getCurrentRequest();

        //manifest source
        $manifestRoot = __DIR__.'/Resources/manifests';
        $publicRoot = __DIR__.'/Resources/public';
        $manifestSrcs = array(
            "pageAssets" => $manifestRoot . '/pageAssets.json',
            "assetHashes" => $manifestRoot . '/assetHashes.json',
        );
        $manifests = array();
        //create files if they don't exist
        foreach($manifestSrcs as $manifestKey=>$manifestSrc){
            if(!file_exists($manifestSrc)){
                touch($manifestSrc);
            }
            $manifests[$manifestKey] = json_decode(file_get_contents($manifestSrc),true);
        }
        $origManifests = $manifests;

        //reset the pageAssets manifest data for this page, we are recreating it here
        $manifests['pageAssets'][$currentRequest->get("_route")] = array();
        foreach($this->config['local']['assets'] as $extension=>$assetsArray){
            $this->generateFileHash($extension,$remake=true);
            //this this asset set path - e.g. 123456ab.css
            $path = $this->getLocalAssetPath($extension);

            //set the manifest array for the asset hashes and what files it will contain
            //key will simply be the file name
            $manifests['assetHashes'][$this->fileHashes[$extension]] = array(
                "src"=>$assetsArray,
                "dest"=>"$extension",
                "includeBower"=>$this->config['local']['includeBower']
            );

            //set the pageAssets so we know that's the file we want to use for the page
            //this will be the path including bundles/bwassets/$extension/file.$extension
            $manifests['pageAssets'][$currentRequest->get("_route")][$extension] = $this->fileHashes[$extension];
        }

        //check through pageAssets to see al the hashes that are being used
        //delete all files that are no longer used and remove them from the manifest for gulp to create
        //find out all hashes that are being used now
        $allUsedHashes = array();
        foreach($manifests['pageAssets'] as $pageKey=>$pageAssets){
            foreach($pageAssets as $ext=>$pageAsset){
                $allUsedHashes[] = $pageAsset;
            }
        }
        //check manifest that gulp will use to create files
        foreach($manifests['assetHashes'] as $assetsKey=>$assetsArray){
            if(!in_array($assetsKey,$allUsedHashes)){
                //remove from the manifest - gulp will process this manifest and recreate the revisions manifest
                //the revisions manifest will no longer have this file in it, so in revisions cleanup the file fill be deleted
                unset($manifests['assetHashes'][$assetsKey]);
            }
        }
        //update all the manifest files
        foreach($manifestSrcs as $manifestKey=>$manifestSrc){
            if($origManifests[$manifestKey]!==$manifests[$manifestKey]){
                @file_put_contents($manifestSrc,json_encode($manifests[$manifestKey],JSON_PRETTY_PRINT));

                if($manifestKey=='assetHashes'){
                    //if coonfig set to run gulp on page load, and the manifest keys have changed, and there are some asset hashes for this page - then we run gulp for the file hashes on this page
                    if($this->config['local']['gulpOnLoad'] && sizeof($this->fileHashes[$extension])>0){
                        $exec = 'gulp -f="'.join('" -f="',$this->fileHashes).'"';
                        //die($exec);
                        exec($exec);
                    }
                }
            }
        }

        
    }

    /**
     * takes a path and simplifies it so path is relative from project root
     */
    private function normalizePath($path=""){
        return str_replace($this->params['root'],"",$path);
    }

    /**
     * callable via a Twig extenion - populates local assets for the gulp manifest (unique assets)
     * @param array/string $assetsArray array of file paths - can include wildcards
     */
    protected function getFilePaths($assetsArray){
        $allowedExts = $this->allowedExtensions;
        $filter = function (\SplFileInfo $file) use ($allowedExts)
        {
            return in_array(pathinfo($file,PATHINFO_EXTENSION),$allowedExts);
        };

        $files = array();
        foreach($assetsArray as $assetPath){
            //normalise @ references, can use without the 'Bundle' postfix as usual in twig templates
            //No error though if they decide to add the Bundle themselves, or it doesn't end in Bundle
            if(substr($assetPath,0,1)==='@'){
                $pathExplode = explode("/",$assetPath);
                try{
                    $rootPath = $this->fileLocator->locate($pathExplode[0]."Bundle");
                }catch(\Exception $e){
                    $rootPath = $this->fileLocator->locate($pathExplode[0]);
                }
                array_shift($pathExplode);
                $assetPath = $this->normalizePath($rootPath.join("/",$pathExplode));
            }
            $pathInfo = pathinfo($assetPath);
            $finder = new Finder();
            $finder->files()->name($pathInfo['basename'])->filter($filter);
            foreach($finder->in($this->params['root'].$pathInfo['dirname']) as $file){
                $files[] = $file;
            }
            
        }
        return array_unique($files);
    }
    
    public function addLocalAssets($assetsArray){

        $files = $this->getFilePaths($assetsArray);
        $assetPaths = array();
        foreach($files as $foundFile){
            $realFilePath = $this->normalizePath($foundFile->getRealPath());
            $pathExtension = pathinfo($realFilePath,PATHINFO_EXTENSION);
            
            //change path extension to css for scss - scss will be compiled into a css file
            foreach($this->extensionCompiled as $compiledExt=>$processExts){
                if(in_array($pathExtension,$processExts)){
                    $pathExtension = $compiledExt;
                    break;
                }
            }
            $assetPaths[$pathExtension][] = $realFilePath;
        }

        foreach($assetPaths as $extension=>$paths){
            if(!isset($this->config['local']['assets'][$extension])){
                $this->config['local']['assets'][$extension] = array();
            }
            //add the assets to load for this page
            $this->config['local']['assets'][$extension] = array_unique(array_merge($this->config['local']['assets'][$extension],$paths));
        }
    }
    public function removeLocalAssets($assetsArray){
        $files = $this->getFilePaths($assetsArray);
        foreach($files as $foundFile){
            if(($key = array_search($foundFile, $this->config['local']['assets'][$extension])) !== false) {
                unset($this->config['local']['assets'][$extension][$key]);
            }
        }
    }

    public function getRealLocalAssets(){
        $realAssets = array(
            "includeBower"=>$this->config['local']['includeBower'],
            "assets"=>$this->config['local']['assets']
        );
        if($this->assetLoadError){
            $realAssets['Asset Load Error'] = $this->assetLoadError;
        }
        return $realAssets;
    }
    /**
     * getResources gets a selection of resources that have been configured
     * @param  string $extension optional: specify the file extensions you'd like (css or js)
     * @param  boolean $external  set to true to only get external resources, false to only get internal - default will return all
     * @return array resource URLs
     */
    public function getAssetsArray($extension=false,$external=null){
        if($extension){
            $returnResources = @$this->assets[$extension] ?: array();
            if(is_bool($external)){
                $returnResources = array_filter($returnResources,function($v) use ($external){
                    return $v['external']===$external;
                });
            }
        }else{
            $returnResources = $this->assets;
            if(is_bool($external)){
                foreach($returnResources as $key=>$rr){
                    $returnResources[$key] = array_filter($rr,function($v) use ($external){
                        return $v['external']===$external;
                    });
                }
            }
        }
        return $returnResources;
    }
    /**
     * updateConfig allows you to submit a new config array to inject into the current array
     * @param  array $newConfig the configuration array with modifications you'd like to make
     */
    public function updateConfig($newConfig){
        $config = array_replace_recursive($this->config,$newConfig);
        $this->setConfig($config);
    }
    /**
     * setResource as enabled or disabled from the defined external resources in the config
     * @param string  $resourceKey key from the config to load
     * @param string $fileKey     file key from the config to load (optional)
     * @param boolean $enabled    true to enable the resource, false to disable it - override the config file
     */
    public function includeResource($resourceKey,$fileKey=false,$enabled=true){
        $config = $this->config;
        if(!isset($config['remote'][$resourceKey])){
            $this->throwError("resNoExist",$resourceKey);
        }
        if(!$fileKey){
            $config['remote'][$resourceKey]['enabled'] = $enabled;
        }else{
            if(!isset($config['remote'][$resourceKey])){
                $this->throwError("fileNoExist",$resourceKey,$fileKey);
            }
            $config['remote'][$resourceKey]['files'][$fileKey]['enabled'] = $enabled;
        }
        $this->setConfig($config);
    }
    /**
     * Removes the an external resource and disables it if another function is called again, so it won't be re added to the resources array.
     * @param  string $resourceKey the config key to diable - should be a specific file e.g. gasp.TweenLite
     */
    public function removeResourceByKey($resourceKey){
        if(is_array($resourceKey)){
            foreach($resourceKey as $rk){
                unset($this->assets[$rk]);
            }
        }else{
            unset($this->assets[$resourceKey]);
        }
        $exploded = explode(".",$resourceKey);
        $config['remote'][$exploded[0]]['files'][$exploded[1]]['enabled'] = false;
        //don't need to reset config as we've removed from the array that matters. Simple removal of a script
    }

    /**
     * just sets the internal configuration array from the resources extension
     */
    public function setConfig($config)
    {
    	//config loaded from both default and all potential overrides from config.yml
        $this->config = $config;
        if($this->configProcessed){
            $this->processConfig();
        }
    }
    /**
     * gets the current configuration array
     */
    public function getConfig()
    {
    	//config loaded from both default and all potential overrides from config.yml
        return $this->config;
    }
     /**
     * processConfig is called initially after the config has been parsed and will setup your configuration array
     *  This method is called on the kernel.request event listener
     * @param array $config - passed from DependancyInjection/BWCoreExtension.php
     */
    public function processConfig($config)
    {
        $currentRequest = $this->requestStack->getCurrentRequest();
        
    	if($currentRequest===null){
    		//Cannot set config yet. getCurrentRequest() is null - setting the default?
    		throw new \ErrorException("Cannot set config yet. getCurrentRequest() is null - setting the default?");
    	}
        $this->configProcessed = true;

        $this->scheme = $currentRequest->isSecure() ? "https:" : "http:";
        $this->validateDependantResourcesArray();

        /****
        EXTERNAL RESOURCES SETUP PATHS REQUIRED
        ****/
        foreach($this->config['remote'] as $resourceKey=>$resourceArray){
            //if it isn't enabled - skip
            if(!$resourceArray['enabled']) continue;

            //check dependants
            if(isset($this->config['dependancies'][$resourceKey])){
                $this->processDepsArray($this->config['dependancies'][$resourceKey]);
            }

            //enable the file keys that are set to be added by default
            $this->processResourceArray($resourceKey,$resourceArray['files']);

        }//finish looping through resources

        //now we have all the keys to load in $this->assetKeys
        $this->assetKeysToResources();
    }

    /**
     * validateDependantResourcesArray checks the external resource dependancies array from the config
     * @param  array $requiredResorcesArray optional array to pass
     */
    private function validateDependantResourcesArray($requiredResorcesArray=false){
        if(!$requiredResorcesArray){
            $requiredResorcesArray = $this->config['dependancies'];
        }
        $availassetKeys = array_keys($this->config['remote']);

        foreach($requiredResorcesArray as $res=>$deps){
            if(!in_array($res, $availassetKeys)){
                //throw error, dependancies are been configured for $res which does not exist as a resource key
                $this->throwError("dep_resNoExist",$res);
            }

            foreach($deps as $mainDep=>$subDeps){
                if(!in_array($mainDep, $availassetKeys)){
                    //throw error, dependancies are been configured for $res which does not exist as a resource key
                    $this->throwError("dep_depNoExist",$res,$mainDep);
                }

                $availSubDeps = array_keys($this->config['remote'][$mainDep]['files']);
                
                //we may require just some parts of the dependancy - validate what's requested is OK
                foreach($subDeps as $subDep=>$subDepEnabled){
                    if($subDep==='all') continue;
                    //if set to being required and is not available sub dependancy
                    if($subDepEnabled===true && !in_array($subDep, $availSubDeps)){
                        //throw error, $res has a dependancy $dep which is not defined in the resources
                        $this->throwError("dep_fileNoExist",$res,$mainDep,$subDep);
                    }
                }
            }
        }
    }
    /**
     * processDepsArray checks a dependancy array for a required resource (determined in previous function)
     * Adds dependancies to array of resources to load
     * @param  array $depsArray the dependancies array for a required resource
     */
    private function processDepsArray($depsArray){
        //this resource is dependant on others
        foreach($depsArray as $depResourceKey=>$depFilesArray){
            $allEnabled = isset($depFilesArray['all']) && $depFilesArray['all']===true;
            if($allEnabled){
                //add all file keys
                $this->appendFilesToResourceKey($depResourceKey,array_keys($depFilesArray));
            }else{
                //just add file keys we need to
                $depResFiles = array();
                foreach($depFilesArray as $depFileKey=>$depFileEnabled){
                    if($depFileEnabled){
                        $depResFiles[] = $depFileKey;
                    }
                }
                $this->appendFilesToResourceKey($depResourceKey,$depResFiles);
            }
        }
    }
    /**
     * processResourceArray processes a resource key that is already determined as required. Will include files that are also enabled
     * @param  string $resourceKey   resource key from the config
     * @param  array $resourceArray the array of files which will be determined in this function whether they should be included (if they are enabled)
     */
    private function processResourceArray($resourceKey,$resourceArray){
        $resFiles = array();
        foreach($resourceArray as $fileKey=>$resourceFile){
            if($resourceFile['enabled'] && !is_null($resourceFile['file'])){
                $resFiles[] = $fileKey;
            }
        }
        $this->appendFilesToResourceKey($resourceKey,$resFiles);
    }
    /**
     * appendFilesToResourceKey populates local assetKeys array so we know difnitively what resources and files are required
     * @param  atring $res   resource key from config
     * @param  array  $files file to be added as resources
     */
    private function appendFilesToResourceKey($res,$files=array()){
        if(sizeof($files)===0){
            return false;
        }

        /***
        CREATE EMPTY ARRAYS
        ***/
        if(!isset($this->assetKeys[$res])){
            $this->assetKeys[$res] = array();
        }

        /***
        FILE CONFLICTS - CHECK IF DUPLICATE CODE BASED ON CONFIG STATING WHICH FILES INCLUDE WHICH OTHERS
        ***/
        foreach($files as $fileIndex=>$fileToAdd){

            //check 1) make sure the file we are trying to add has not already been silently included - easy!
            if(isset($this->silentIncludes[$res])){
                if(in_array($fileToAdd,$this->silentIncludes[$res])){
                    //this file has already been included
                    unset($files[$fileIndex]);
                    continue;
                }
            }

            //get the config to find out what other files this one may include
            $fileToAddConfig = $this->config['remote'][$res]['files'][$fileToAdd];
            foreach($fileToAddConfig['includes'] as $incRes=>$incFiles){
                
                //a) we should populate silent includes with all the files that this one will include if key 'all' is true
                if(isset($incFiles['all'])){
                    if($incFiles['all']===true){
                        $incFiles = array_keys($this->config['remote'][$incRes]['files']);
                    }else{
                        unset($incFiles['all']);
                    }
                }
                //b) check if any of those files have been added already and remove them
                
                //check 2) is the resource we're including already in assetKeys
                if(isset($this->assetKeys[$incRes])){
                    //it is! Let's loop through the files we are silently including now and find out whether they've already been added
                    foreach($incFiles as $incFile){
                        //$foundKey will not be false if we have found the file we are looking to include silently in the current array
                        if(($foundKey = array_search($incFile, $this->assetKeys[$incRes])) !== false){
                            //we don't need this file that had already been added, the file we are adding now has it included
                            unset($this->assetKeys[$res][$foundKey]);
                        }
                    }
                }

                //check 3) make the resource isn't about to be added now as well as part of same resource
                if($incRes===$res){
                    //well we are including some files from the same resource
                    //let's check further
                    foreach($incFiles as $incFile){
                        //if the file we are silently including is already being included we'll unset it
                        if(($foundKey = array_search($incFile, $files)) !== false){
                            unset($files[$foundKey]);
                        }
                    }
                }
                
                //create the silentIncludes array for this resource if it hasn't been created yet
                if(!isset($this->silentIncludes[$res])){
                    $this->silentIncludes[$res] = array();
                }
                //update cilentIncludes array so we know all the files silently included for check on next file
                $this->silentIncludes[$res] = array_merge($this->silentIncludes[$res],$incFiles);
            }
        }

        /***
        UPDATE FILES
        ***/
        $origArray = $this->assetKeys[$res];
        $this->assetKeys[$res] = array_unique(array_merge($this->assetKeys[$res],$files));        

        if(sizeof(array_diff($this->assetKeys[$res],$origArray))==0){
            return false;
        }
        return true;
    }

    /**
     * assetKeysToResources convert assetKeys array into resources array
     */
    private function assetKeysToResources(){
        $this->assets = array();
        foreach($this->assetKeys as $resKey=>$localResourceArray){
            $resourceArray = $this->config['remote'][$resKey];

            $base = $resourceArray['base'];
            $version = $resourceArray['version'];
            $allFilesInfo = $resourceArray['files'];
            if(in_array('all',$localResourceArray)){
                foreach($allFilesInfo as $fileKey=>$fileInfo){
                    $resKey = str_replace(".",":",$resKey);
                    $fileKey = str_replace(".",":",$fileKey);
                    $this->constructResourcePaths($version,$base,$fileInfo,"$resKey.$fileKey");
                }
            }else{
                foreach($localResourceArray as $fileKey){
                    $resKey = str_replace(".",":",$resKey);
                    $fileKey = str_replace(".",":",$fileKey);
                    $this->constructResourcePaths($version,$base,$allFilesInfo[$fileKey],"$resKey.$fileKey");
                }
            }
            
        }
    }

    /**
     * constructResourcePaths converts info provided into a URL and adds to resources array
     * @param  string $version      version to load
     * @param  string $base         Base URL
     * @param  array $resourceInfo info for all the files to include
     * @param  string $key          a key to give to this resource
     */
    private function constructResourcePaths($version,$base,$resourceInfo,$key){
        $path = str_replace("{version}",$version,$base.$resourceInfo['file']);
        $external = substr($path,0,2)=='//' || substr($path,0,7)=='http://' || substr($path,0,8)=='https://';
        //check if external path
        if(substr($path,0,2)=='//'){
            $path = $this->scheme.$path;
        }

        
        if(isset($resourceInfo['extension'])){
            $ext = $resourceInfo['extension'];
        }else{
            $ext = pathinfo(parse_url($path,PHP_URL_PATH), PATHINFO_EXTENSION);
        }

        if(!isset($this->assets[$ext])){
            $this->assets[$ext] = array();
        }
        $this->assets[$ext][$key] = array(
            "external"=>$external,
            "path"=>$path,
            "integrity"=>isset($resourceInfo['integrity']) ? $resourceInfo['integrity'] : false
        );
    }

    private function throwError($str,$res="unknown",$mainDep="unknown",$subDep="unknown"){
        switch($str){
            case "dep_resNoExist":
                throw new InvalidConfigurationException("Requirements have been configured for \"$res\" - \"$res\" does NOT exist in your config (bw_core:resources)");
            break;
            case "dep_depNoExist":
                throw new InvalidConfigurationException("\"$res\" requires \"$mainDep\" - \"$mainDep\" does NOT exist in your config (bw_core:resources)");
            break;
            case "dep_fileNoExist":
                throw new InvalidConfigurationException("\"$res\" requires \"$subDep\" within \"$mainDep\" - \"$subDep\" has not been configured as a file for \"$mainDep\" in your config (bw_core:resources)");
            break;
            case "res_fileNoExist":
                throw new InvalidConfigurationException("\"$res\" has no file with the key \"$subDep\" configured in your config (bw_core:resources)");
            break;
            case "key_notSupported":
                throw new InvalidConfigurationException("The variable $res must be $mainDep. It is currently ".json_encode($subDep));
            break;
            case "resNoExist":
                throw new InvalidConfigurationException("You cannot configure the key \"$res\" - \"$res\" does NOT exist in your config (bw_core:resources)");
            break;
            case "fileNoExist":
                throw new InvalidConfigurationException("You cannot set \"$mainDep\" inside \"$res\" - \"$mainDep\" does NOT exist in your config (bw_core:resources)");
            break;
            default:
                throw new InvalidConfigurationException("BWCoreBundle Error: ".$str);
            break;
        }
    }
}