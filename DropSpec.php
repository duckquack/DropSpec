#!/usr/bin/php
<?

// DropSpec

$version = "1.0.1.2";
$debug = 0;

$vscale = .66; // vertical scale of generated spec (relative to overall screen height)
$ratio = 1.1; // aspect ratio of generated spec

// If the app is launched with the shift key held down, open this file in a text editor

echo "PROGRESS:0\n";

if(exec(__DIR__."/keys") == 512) {
	exec("open -t ".__FILE__);
	echo "QUITAPP\n";
	die;
	}

// Generated specs are $percent of screen height tall with an aspect ratio of $ratio
// beware sox can be unpredictable and actual size may vary

$resolution_string = exec("system_profiler SPDisplaysDataType | grep Resolution");
if (!$resolution_string) {
	echo "ALERT:No resolution|Can't determine screen resolution\n";
	die;
	}
preg_match_all('!\d+!', $resolution_string, $matches);

$height = floor($matches[0][1]*$vscale);
$width = floor($height*$ratio);

if ($debug) {
	echo "\nOutput Resolution = ".$width."x".$height."\n";
	}

// If no files are dragged, use the current Finder context

$from_finder=0;
if (!@$argv[1]) {
	echo "\nGathering path from current Finder context...\n";
	$argv[1] = exec("osascript -e 'tell application \"Finder\" to get the POSIX path of (target of front window as alias)'");
	$from_finder=1;
	}

unset($argv[0]);

$files = array();

// Build list of valid dropped files

foreach ($argv as $target) {

	$allowed = array("flac","mp3","wav","aif","aiff","m4a");

	if (is_dir($target)) {

		$it = new RecursiveDirectoryIterator($target);
		foreach(new RecursiveIteratorIterator($it) as $file) {
    		if (in_array(strtolower(@array_pop(explode('.', $file))), $allowed)) {
        		$files[] = $file->getpathname();
				}
			}
			
		} elseif (in_array(strtolower(@array_pop(explode('.', $target))), $allowed)) {
		
			$files[] = $target;
			
		}
	}

if (!$files) {
	if (!$from_finder) {
		echo "ALERT:No files|No valid files were dropped\n";
		}
	die;
	} else {
	sort($files);
	}

$limit = 200;
if (count($files) > $limit) {
	$result = exec("osascript -e \"display dialog \\\"Really process ".count($files)." files?\\\"\" 2>&1");
	if (strpos($result,"canceled") !== false) {
		echo "\nUser Cancelled\n";
		die;
		}
	}

// Make a tmp dir for the pngs

$workdir = "/tmp/".md5(implode(".",$argv));

if (!file_exists($workdir)) {
	mkdir($workdir);
	}

// Build commands for sox

foreach ($files as $file) {
	$makecmd[] = "";
	$hash = md5($file);
	$img = $workdir."/".$hash.".png";
	if (!file_exists($img)) {
		$makecmd[] = __DIR__."/sox \"".$file."\" -n spectrogram -x ".$width." -Y ".$height." -t \"".basename($file)."\" -c \"DropSpec ".$version."\" -o ".$img;
		}
	echo "\n".basename($file);
	}
	
echo "\nBuilding specs...\n";

// Execute sox commands in parallel

if ($debug) {
	echo implode("\n\n",$makecmd);
	}

exec(implode(" > /dev/null 2>&1 & ",$makecmd)." > /dev/null 2>&1 &");

// Loop over destination dir to check progress

while (count(glob($workdir."/*.png")) < count($files)) {
	$count = count(glob($workdir."/*.png"));
	$total = count($files);
	$percent = floor(($count/$total)*100);
	echo "PROGRESS:".$percent."\n";
	usleep(10000);
	}

echo "PROGRESS:100\n";

echo "\nOpening...\n";

exec("qlmanage -p ".$workdir."/*.png > /dev/null 2>&1");

if (!$debug) {
	echo "QUITAPP\n";
	}

?>