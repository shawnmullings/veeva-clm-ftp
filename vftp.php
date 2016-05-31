<?php

/****************************************************************

******************************************************************/

// set PHP timeout to 10min or 600 seconds
ini_set('max_execution_time', 1600);

// turn off errors
error_reporting(1);
ini_set("display_errors", 1); 

// load json config file
$url = './config.json';
$json = file_get_contents( $url);
$config_data = json_decode( $json, TRUE);
$target_project;
$target_project_name;

require_once('./util.php');

// flag to check which project data will be targeted from config file
$stream = $argv[1];
if (isset($config_data[$stream])) {
     $target_project =  $config_data[$argv[1]];
	 $target_project_name = $argv[1];
}

// put arguments into a loop and check the options. This should make them work out of order
for($i = 0; $i < count($argv); $i++){
	switch($argv[$i]){
		case 'upload':
			$goForUpload = true;
			break;
		case 'titles':
			$useSlideTitles = true;
			break;
		case 'metadata':
			$showMetaData = true;
			break;
		case 'norelink':
			$noRelink = true;
			break;
		case 'noserverchanges':
			$noServerChanges = true;
			break;
	}
}

$util = new util();

$errors = 0; // keep track of error issues that will crash viva

$revNumber; // for keeping track of the SVN revision number

if (is_dir('temp/'.$target_project_name)) {
	echo "Remove previous vcs files within 'temp' folder \n";
	shell_exec("rm -Rf " . 'temp/'. $target_project_name);
}

echo "\n\n";
echo "Getting vcs info prior to export starting \n\n";

// get the info on the repo prior to export starting
$svnCheckoutInfo = shell_exec("svn info --username " .  $target_project['vcs-user'] . " --password " .  $target_project['vcs-password'] . ' ' .  $target_project['vcs-url'] );

$svnInfo = explode("\n", $svnCheckoutInfo);

$revNumber = $svnInfo[4]; // raw Rev number from SVN checkout info


// Revision string to iVAs
if($target_project['rev-number']){
	$RevisionString = "<div id=\"svn-revision-number\">" . $revNumber . "</div></body>";
}


// get just the revision number
$revNumber = "rev=" . substr($revNumber, strpos($revNumber, " ")+1); // start at the number and not the space

// use pass through to stream SVN export as it happens
passthru("svn export --username " .  $target_project['vcs-user'] . " --password " .  $target_project['vcs-password'] . " " .  $target_project['vcs-url']  . ' temp/'. $target_project_name);


if(!chdir('temp/'. $target_project_name)){
	echo "error: vcs checkout failed.";
	$errors++;
}


if($errors == 0){
	echo("Begin assembly of slide files [_globals]\n\n");
}


$parent_dir = getcwd(); // starting point for processing slides
$globals = "../_global"; // global lib used in the slides


$slides = scandir($parent_dir); // all directories in the parent directory

$i = 0;


// flag to keep from adding all of the standard inventiv stuff to other agency's key messages
if(!$noRelink){

	// rebuild the paths in the global .js files to make them work inside the packages
	if(is_file('_global/js/config.js')){
		$gcontents = file_get_contents('_global/js/config.js');
		$gcontents = str_replace("../", "", $gcontents);
		$gcontents = $util->fixCaching($gcontents, $revNumber);
		$gcontents = file_put_contents('_global/js/config.js', $gcontents);
	}
	
	if(is_file('_global/js/includes.js')){
		$gcontents = file_get_contents('_global/js/includes.js');
		$gcontents = str_replace("../", "", $gcontents);
		$gcontents = $util->fixCaching($gcontents, $revNumber);
		$gcontents = file_put_contents('_global/js/includes.js', $gcontents);
	}
	
	// this is for some Canadian stuff that got named wrong
	if(is_file('_global/_javascript/include.js')){
		$gcontents = file_get_contents('_global/_javascript/include.js');
		$gcontents = str_replace("../", "", $gcontents);
		$gcontents = $util->fixCaching($gcontents, $revNumber);
		$gcontents = file_put_contents('_global/_javascript/include.js', $gcontents);
	}
	
	
	// scan all files in _global/_js/*.js
	$plugins = scandir($parent_dir . "/_global/js/plugins");
	
	//rebuild the paths in the plugin .js files to make them work inside the packages
	while($i < count($plugins)){
		if($plugins[$i] != '.' && $plugins[$i] != '..'){
			$gcontents = file_get_contents($parent_dir . '/_global/js/plugins/' . $plugins[$i]);
			$gcontents = str_replace("../", "", $gcontents);
			$gcontents = $util->fixCaching($gcontents, $revNumber);
			$gcontents = file_put_contents($parent_dir . '/_global/js/plugins/' . $plugins[$i], $gcontents);
		}
		$i++;
	}
	
	
	// scan all _global/css files
	$cssFiles = scandir($parent_dir . "/_global/css");
	
	// reset counter
	$i = 0;
	
	//add rev numbers for images in CSS files
	while($i < count($cssFiles)){
		if($cssFiles[$i] != '.' && $cssFiles[$i] != '..'){
			$gcontents = file_get_contents($parent_dir . '/_global/css/' . $cssFiles[$i]);
			$gcontents = $util->fixCaching($gcontents, $revNumber);
			$gcontents = file_put_contents($parent_dir . '/_global/css/' . $cssFiles[$i], $gcontents);
		}
		$i++;
	}
}

$slides = scandir($parent_dir); // get new list of slides w/o any .zips
$i = 0;
// loop through key messages and perform operations on them
while($i < count($slides)){
	if($util->isKeyMessage($slides[$i])){
		echo("\n" . $slides[$i] . "\n");
		
		
		// flag to keep from re-linking and adding things to other agency's key messages
		if(!$noRelink){
		
			// chrdir into the current key message
			chdir($slides[$i]);
				
			// add new globals
			$util->rcopy($globals, './_global');
			
			// check .html, thumbnail, and placeholder are named properly
			$errors += $util->checkName($slides[$i], $parent_dir);
	
			// see if lead file is of type .html		
			if(file_exists($slides[$i] . ".html")){
			
				// do find and replace inside the .html file to update the globals path
				$filename = $slides[$i] . '.html';
				$html = file_get_contents($filename);
				
				$html = str_replace("../_global", "_global", $html);
				
				// if showMetaData is enabled, display the assetID and revision -- for QC/internal use
				if($showMetaData){
					$html = str_replace("</body>", $util->makeEndBodyString($slides[$i], $revNumber), $html);
				}
				
				// if a  project, show the  Revision number <div>
				if($target_project['rev-number']){
					$html = str_replace("</body>", $RevisionString, $html);
				}
				$html = $util->fixCaching($html, $revNumber);
				$html = file_put_contents($filename, $html);
				
				
				// update paths inside the javascript files attached to a slide
				
				// scan all files in _global/_js/*.js
				$plugins = scandir($parent_dir . "/" . $slides[$i] . "/js");
				$p = 0; // counter for plugins subloop
				while($p < count($plugins)){
					if($plugins[$p] != '.' && $plugins[$p] != '..'){
						$gcontents = file_get_contents($parent_dir . "/" . $slides[$i] . '/js/' . $plugins[$p]);
						$gcontents = str_replace("../", "", $gcontents);
						$gcontents = $util->fixCaching($gcontents, $revNumber);
						$gcontents = file_put_contents($parent_dir . "/" . $slides[$i] . '/js/' . $plugins[$p], $gcontents);
					}
					$p++;
				}
				
				
				// update revision numbers in the css files attached to the slides
				$plugins = scandir($parent_dir . "/" . $slides[$i] . "/css");
				$p = 0; // counter for plugins subloop
				while($p < count($plugins)){
					if($plugins[$p] != '.' && $plugins[$p] != '..'){
						$gcontents = file_get_contents($parent_dir . "/" . $slides[$i] . '/css/' . $plugins[$p]);
						$gcontents = $util->fixCaching($gcontents, $revNumber);
						$gcontents = file_put_contents($parent_dir . "/" . $slides[$i] . '/css/' . $plugins[$p], $gcontents);
					}
					$p++;
				}

				if($html){
					echo ("Success: " . $slides[$i] . ".html _global paths updated\n\n");
				} else {
					echo ("Fail: " . $slides[$i] . ".html _global paths not updated\n\n");
					$errors++;
				}
			}
			
			// chdir back to the root/start
			chdir($parent_dir);
		}
		
		// write out the ctl file if uploading files via FTP
		if($goForUpload){
			if(file_put_contents($slides[$i]  . '.ctl', $util->writeCTLFileContents($slides[$i],  $target_project, $useSlideTitles, $noServerChanges))){
				echo ("Success: CTL file was created\n\n");
			} else {
				echo ("Fail: CTL file was not created\n\n");
				$errors++;
			}
		}
	}
	$i++;
}

// if no error issues, then zip all key messages into individual .zip files
// http://us2.php.net/manual/en/zip.examples.php ex1


if($errors < 1){
	$i = 0;
	
	while($i < count($slides)){
		if($util->isKeyMessage($slides[$i])){
			
			// init .zip files/process
			$filename = "./" . $slides[$i] . ".zip";
			
			shell_exec("zip -r " . $slides[$i] . " " . $slides[$i]);
			chmod($filename, 0775);

		}
	$i++;
	}
	
	if($goForUpload){
		$conn_id = ftp_connect( $target_project['ftp']['ftp-server']);
				
		$login_result = ftp_login($conn_id,  $target_project['ftp']['ftp-user'],  $target_project['ftp']['ftp-password']);
		
		// check connection
		if((!$conn_id) || (!$login_result)){
			echo ("Error: Unable to establish connection with server: " . $target_project['ftp']['ftp-server'] . "\n\n");
			$errors++;
		} else {
			$ftpGo = true;
			echo ("Established connection with server: " . $target_project['ftp']['ftp-server']. "\n\n");
		}
		
		// start moving files
		if($ftpGo){			
			
			$i = 0;
			while($i < count($slides)){
				if($util->isKeyMessage($slides[$i])){
				
				// init .zip files/process
				$package = "./" . $slides[$i] . ".zip";
				$ctl = "./" . $slides[$i] . ".ctl";
				
				$upload = null;
				echo "Uploading >>> " . $slides[$i] . "\n\n";
				
				// move slide/key message
				$destination_file =  $slides[$i] . '.zip';
				$upload = ftp_put($conn_id, $package, $destination_file, FTP_BINARY);
				if($upload){
					echo ("Success: " .$slides[$i]. " .zip file uploaded\n");
				} else {
					echo ("Fail: " .$slides[$i]. " .zip file was not uploaded\n");
					$errors++;
				}
				
				$upload = null;
				
				// move control file
				ftp_chdir($conn_id, "ctlfile");
				$destination_file = $slides[$i] . '.ctl';
				$upload = ftp_put($conn_id, $ctl, $destination_file, FTP_BINARY);			
				if($upload){
					echo ("Success: " .$slides[$i]. " .ctl file uploaded\n\n");
				} else {
					echo ("Fail: " .$slides[$i]. " .ctl file was not uploaded\n\n");
					$errors++;
				}
				ftp_chdir($conn_id, "..");
				
				
				}
			$i++;
			}
		}
		
		ftp_close($conn_id);
	} else {
		echo ("\n\n");
		echo ("autoamated slide upload not specified.  Manual upload is required. \n");
	}
}


$i -= 3; // make count accurate for reporting on slides uploaded


echo "\n"; // create space

if($errors > 0){
	echo "There are " . $errors . " errors. Please check the above log and fix before deploying.\n";
} else {
	echo "*****************************************************************\n\n";
	echo "VCS Information:\n";
	echo $svnCheckoutInfo;
	echo "*****************************************************************\n\n";
	if($goForUpload){
		echo "$i file(s) FTP'd to " . $target_project['ftp']['ftp-server'] . "\n\n";
	}
	echo "Script processing has ended \n\n";
}

?>