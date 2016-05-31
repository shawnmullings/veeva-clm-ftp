<?php

class util{

// test for not a key message/slide
public function isKeyMessage($slide){
	switch($slide){
		case '.':
			return false;
		case '..':
			return false;
		case '.DS_Store':
			return false;
		case '_global':
			return false;
		case 'vftp.php':
			return false;
		default:
			return true;
	}
}

public function checkName($slide, $path){
	$criticals = 0;
	
	$path .= '/' . $slide;
	
	// test html file
	if(file_exists($path . '/' . $slide . ".html")){	
		echo("+ HTML\n");
	} else if(file_exists($path . '/' . $slide . ".mp4")) {
		echo("+ mp4\n");
	} else if(file_exists($path . '/' . $slide . ".jpg")) {
		echo("+ jpg\n");
	} else if(file_exists($path . '/' . $slide . ".png")) {
		echo("+ png\n");
	} else if(file_exists($path . '/' . $slide . ".pdf")) {
		echo("+ pdf\n");
	} else {
		echo("- no primary file\n");
		$criticals++;
	}
	
	// test thumbnail file
	if(file_exists($path . '/' . $slide . "-thumb.jpg")){	
		echo("+ THUMB\n");
	} else {
		echo("- THUMB\n");
		$criticals++;
	}
	
	// test placeholder file
	if(file_exists($path . '/' . $slide . "-full.jpg")){	
		echo("+ PLACEHOLDER\n");
	} else {
		echo("- PLACEHOLDER\n");
		$criticals++;
	}
	
	return $criticals; // keep track of errors that will bomb viva on import of content
}

// Veeva lacks interest in fixing the caching issues with iRep.
// Instead, they advised that we append a get URL to the end of all file requests.
// Thus, this function attaches the revision number to the end of all file requests (image, css, js)
// args: $contents = string contents of the file, $rev = current SVN revision 

public function fixCaching($contents, $rev){

	// look for css with " ending
	while(substr_count($contents, '.css"') > 0){ // count to see if \ is in the string.
		$contents = str_replace('.css"', '.css?' . $rev . '"', $contents);
	}
	
	// look for css with ' ending
	while(substr_count($contents, ".css'") > 0){ // count to see if \ is in the string.
		$contents = str_replace(".css'", ".css?" . $rev . "'", $contents);
	}
	
	// look for js
	while(substr_count($contents, '.js"') > 0){ // count to see if \ is in the string.
		$contents = str_replace('.js"', '.js?' . $rev . '"', $contents);
	}
	
	// look for js
	while(substr_count($contents, ".js'") > 0){ // count to see if \ is in the string.
		$contents = str_replace(".js'", ".js?" . $rev . "'", $contents);
	}
	
	// look for jpg in HTML tag: src="file.jpg"
	while(substr_count($contents, '.jpg"') > 0){ // count to see if \ is in the string.
		$contents = str_replace('.jpg"', '.jpg?' . $rev . '"', $contents);
	}
	
	// look for jpeg in css URL: url(file.jpg)
	while(substr_count($contents, '.jpg)') > 0){ // count to see if \ is in the string.
		$contents = str_replace('.jpg)', '.jpg?' . $rev . ')', $contents);
	}
	
	// look for png in HTML tag
	while(substr_count($contents, '.png"') > 0){ // count to see if \ is in the string.
		$contents = str_replace('.png"', '.png?' . $rev . '"', $contents);
	}
	
	// look for png in css URL
	while(substr_count($contents, '.png)') > 0){ // count to see if \ is in the string.
		$contents = str_replace('.png)', '.png?' . $rev . ')', $contents);
	}
	
	return $contents;
}



// creates the control file that accompanies each slide when we do an FTP upload
public function writeCTLFileContents($slide, $config, $useSlideTitles, $noServerChanges){
	
	if($useSlideTitles) {
		// pull info from the <head> block of the HTML file if HTML file exists
		if(file_exists($slide . "/" . $slide . ".html")){
			$name = file_get_contents($slide . "/" . $slide . ".html"); // get slide contents
			$start = strpos($name, "<title>") + 7;
					
			$name = substr($slide, $start, strpos($slide,"</title>") - $start);
			$name = substr($name, $start, $end);
			$description = $name; // separate these incase we need to make them different at some point
			echo ("Slide Title = " . $name . "\n");		
		} 
			// if PDF, video, etc, then look for a binary.json file with info
		else if(file_exists($slide . "/" . "binary.json")){
			$json = file_get_contents($slide . "/" . "binary.json");
			$info = json_decode($json);
			
			$name = $info->name;
			$description = $info->description;
		}
		
		$s = "USER=" . $config['content']['content-user'] . "\n";
		$s .= "PASSWORD=" . $config['content']['content-password']  . "\n";
		$s .= "FILENAME=" . $slide . ".zip\n";
		//$s .= "CLM_ID_vod__c=" . $config['clm-id'] . "\n";
		//$s .= "CLM_ID_vod__c=\n";
		$s .= "Name=" . $name . "\n";
		$s .= "Product_vod__c=" . $config['product-vod'] . "\n";
		//$s .= "Product_vod__c=\n"; // leave product blank
		$s .= "Slide_Version_vod__c=1.0.0\n"; // this may need to get changed later or left to be blank
		$s .= "Description_vod__c=" . $description;
				
		
	} else if ($noServerChanges){
		// create string that will be the ctl file for the slide
		$s = "USER=" . $config['content']['content-user'] . "\n";
		$s .= "PASSWORD=" . $config['content']['content-password']  . "\n";
		$s .= "FILENAME=" . $slide . ".zip\n";
		//$s .= "CLM_ID_vod__c=" . $config['clm-id'] . "\n";
		//$s .= "CLM_ID_vod__c=\n";
		//$s .= "Name=" . $slide . "\n";
		$s .= "Product_vod__c=" . $config['product-vod'] . "\n";
		//$s .= "Product_vod__c=\n"; // leave product blank
		$s .= "Slide_Version_vod__c=1.0.0\n"; // this may need to get changed later or left to be blank
		
		//echo getcwd() . "\n";
		//echo $slide . "/" . $slide . ".html\n";
		if(file_exists($slide . "/" . $slide . ".html")){
			$slide = file_get_contents($slide . "/" . $slide . ".html"); // get slide contents
			$start = strpos($slide, "<title>") + 7;
			$t = substr($slide, $start, strpos($slide,"</title>") - $start);
			echo ("Slide Title = " . $t . "\n");
			//$s .= "Description_vod__c=" . $t;
		}
	
	
	} else {
		// create string that will be the ctl file for the slide
		$s = "USER=" . $config['content']['content-user'] . "\n";
		$s .= "PASSWORD=" . $config['content']['content-password']  . "\n";
		$s .= "FILENAME=" . $slide . ".zip\n";
		//$s .= "CLM_ID_vod__c=" . $config['clm-id'] . "\n";
		//$s .= "CLM_ID_vod__c=\n";
		$s .= "Name=" . $slide . "\n";
		$s .= "Product_vod__c=" . $config['product-vod'] . "\n";
		//$s .= "Product_vod__c=\n"; // leave product blank
		$s .= "Slide_Version_vod__c=1.0.0\n"; // this may need to get changed later or left to be blank
		
		//echo getcwd() . "\n";
		//echo $slide . "/" . $slide . ".html\n";
		if(file_exists($slide . "/" . $slide . ".html")){
			$slide = file_get_contents($slide . "/" . $slide . ".html"); // get slide contents
			$start = strpos($slide, "<title>") + 7;
			$t = substr($slide, $start, strpos($slide,"</title>") - $start);
			echo ("Slide Title = " . $t . "\n");
			$s .= "Description_vod__c=" . $t;
		}
	}
	
	return $s; // return string. write the file outside the function to preserve file system location
}




// removes files and non-empty directories
// from http://us.php.net/manual/en/function.copy.php#104020
public function rrmdir($dir) {
  if (is_dir($dir)) {
    $files = scandir($dir);
    foreach ($files as $file)
    if ($file != "." && $file != "..") $this->rrmdir("$dir/$file");
    rmdir($dir);
  }
  else if (file_exists($dir)) unlink($dir);
} 

// copies files and non-empty directories
// from http://us.php.net/manual/en/function.copy.php#104020
public function rcopy($src, $dst) {
  if (file_exists($dst)) $this->rrmdir($dst);
  if (is_dir($src)) {
    mkdir($dst);
    $files = scandir($src);
    foreach ($files as $file)
    if ($file != "." && $file != "..") $this->rcopy("$src/$file", "$dst/$file"); 
  }
  else if (file_exists($src)) copy($src, $dst);
}

// create .zip of the directory
// from http://stackoverflow.com/questions/1334613/how-to-recursively-zip-a-directory-in-php
// this shouldn't be used when zipping things for veeva
public function Zip($source, $destination)
{
    if (!extension_loaded('zip') || !file_exists($source)) {
        return false;
    }

    $zip = new ZipArchive();
    if (!$zip->open($destination, ZIPARCHIVE::CREATE)) {
        return false;
    }

    $source = str_replace('\\', '/', realpath($source));

    if (is_dir($source) === true)
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);

        foreach ($files as $file)
        {
            $file = str_replace('\\', '/', realpath($file));

            if (is_dir($file) === true)
            {
                $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
            }
            else if (is_file($file) === true)
            {
                $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
            }
        }
    }
    else if (is_file($source) === true)
    {
        $zip->addFromString(basename($source), file_get_contents($source));
    }

    return $zip->close();
}


// create the ending string that is appended to the HTML file
// this string includes the assetID/file name and the revision number of the repository
// returns string
public function makeEndBodyString($assetID, $rev){
	return "<div id=\"theAssetName\">" . $assetID . " : " . $rev . "</div></body>"; // the string
}

}

?>