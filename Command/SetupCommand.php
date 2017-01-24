<?php
namespace BW\AssetsBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class SetupCommand extends ContainerAwareCommand
{
	protected function configure()
    {
        $this
        	->setName('bwcassets:setup')
        	
        	->setDescription('Copy configuration files for Gulp and Bower')
        	->setHelp('Finds all available routes and updates assetHashes.json file for Gulp.');
    }
    /**
	 * 
	 * Find the relative file system path between two file system paths
	 *
	 * @param  string  $frompath  Path to start from
	 * @param  string  $topath    Path we want to end up in
	 *
	 * @return string             Path leading from $frompath to $topath
	 * @author Oddbjørn Haaland https://gist.github.com/ohaal/2936041
	 */
	private function find_relative_path ( $frompath, $topath ) {
	    $from = explode( DIRECTORY_SEPARATOR, $frompath ); // Folders/File
	    $to = explode( DIRECTORY_SEPARATOR, $topath ); // Folders/File
	    $relpath = '';

	    $i = 0;
	    // Find how far the path is the same
	    while ( isset($from[$i]) && isset($to[$i]) ) {
	        if ( $from[$i] != $to[$i] ) break;
	        $i++;
	    }
	    $j = count( $from ) - 1;
	    // Add '..' until the path is the same
	    while ( $i <= $j ) {
	        if ( !empty($from[$j]) ) $relpath .= '..'.DIRECTORY_SEPARATOR;
	        $j--;
	    }
	    // Go to folder from where it starts differing
	    while ( isset($to[$i]) ) {
	        if ( !empty($to[$i]) ) $relpath .= $to[$i].DIRECTORY_SEPARATOR;
	        $i++;
	    }
	    
	    // Strip last separator
	    return substr($relpath, 0, -1);
	}
    protected function execute(InputInterface $input, OutputInterface $output)
    {
    	$CONTAINER = $this->getContainer();
    	$helper = $this->getHelper('question');


    	$projectRoot = realpath($CONTAINER->get('kernel')->getRootDir() .'/../');
    	$bundleRoot = realpath(__DIR__.'/../');
    	$setupDir = __DIR__."/../Resources/_setup/";
    	$installQuestion = new Question('Please enter the path you want your Gulp and Bower configurations to be copied to. <fg=red;options=bold>This will overwrite any gulpfile.js and bower.json files you have - remember to backup your files if you already have them setup</> [<options=bold,underscore>'.$projectRoot.'</>]: ', "$projectRoot");
    	$installDir = $helper->ask($input, $output, $installQuestion);

    	$npmQuestion = new Question('Would you like to move a package.json file to the same location with all the require dependancies for gulpfile.js? <fg=red;options=bold>This will overwrite any existing package.json file you may already have</> [y/<options=bold,underscore>N</>]: ', false);
    	$moveNPM = $helper->ask($input, $output, $npmQuestion);

    	//copy package.json if requested - no changes needed
    	copy("{$setupDir}package.json","$installDir/package.json");

    	//create .bowerrc for bower install
    	$relativePath = $this->find_relative_path($projectRoot,$bundleRoot);
    	$bowerSource = "$relativePath/Resources/public/bower/source";
    	file_put_contents("$installDir/.bowerrc",json_encode(array(
    		"directory"=>"$bowerSource"
    	),JSON_PRETTY_PRINT));

    	//get and copy bower.json
    	$bowerJSON = file_get_contents("{$setupDir}/bower.json");
    	$bowerJSON = str_replace("<%BUNDLE_ROOT%>",$relativePath,$bowerJSON);
    	file_put_contents("$installDir/bower.json",$bowerJSON);

    	//change root parameter and copy gulpfile.js
    	$gulpJS = file_get_contents("{$setupDir}/gulpfile.js");
    	$gulpJS = str_replace("<%BUNDLE_ROOT%>",$relativePath,$gulpJS);
    	file_put_contents("$installDir/gulpfile.js",$gulpJS);

		$output->writeln([
    		$relativePath.'',
	        ''
		]);
    	$output->writeln([
    		'<info>NPM Instructions',
	        '============</info>'
		]);
    	if(!$moveNPM){
    		$output->writeln([
    			"The following command can be run to install all the requirements for gulpfile.js",
    			"<comment>npm install -D gulp gulp-sass gulp-sourcemaps gulp-concat gulp-uglify gulp-rename gulp-autoprefixer main-bower-files gulp-filter gulp-rev gulp-debug del fs stream-combiner2 path gulp-clean-css gulp-if gulp-rev-delete-original yargs child_process gulp-include through2</comment>",
    			""
    		]);
    	}else{
    		$output->writeln([
    			"Run <comment>npm install</comment> in your selected folder to install gulp dependancies",
    			""
    		]);
    	}

    	$output->writeln([
    		'<info>Bower Instructions',
	        '============</info>',
			"To install bower if you haven't done so already run <comment>npm install -g bower</comment>",
			"When you have bower installed, you can run <comment>bower install</comment> command in your chosen folder to install Bootstrap which is already included in your bower.json file",
			""
		]);

		$output->writeln([
    		'<info>Gulp Instructions',
	        '============</info>',
	        "Make sure you have gulp-cli installed <comment>npm install -g gulp-cli</comment>",
			"You can run <comment>gulp</comment> or <comment>gulp default</comment> to process all your dependancies in one go once you've installed the required npm packages. The configuration uses Gulp 4.",
			"Run <comment>gulp watch</comment> to watch all your manifest files produced by Symfony and your source files for changes in real-time",
			""
		]);

		$output->writeln([
    		'<info>BWCore Assets Bundle Instructions',
	        '============</info>',
			"Please familiarize yourself with the Readme files on the github repository to discover all your configuration options. Enjoy!",
			"<fg=yellow;options=bold>https://github.com/silverbackis/BWCore-AssetsBundle</>",
			""
		]);
    }
}