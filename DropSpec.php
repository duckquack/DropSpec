#!/usr/bin/php
<?

// DropSpec

$version = "1.0.1.8";
$debug = 0;
$soxdir = __DIR__;			// where to find sox binary
								// __DIR__ = precompiled binary
								// /opt/local/bin = macports binary
$enable_resizing = 1;		// if disabled, use sox default image size
$longtitle_threshold = 2;	// if files are n levels deep, add enclosing dirname to spec title
$vscale = .66;				// vertical scale of generated spec (relative to overall screen height)
$ratio = 1.1;				// aspect ratio of generated spec

// If the app is launched with the shift key held down, open this file in a text editor

echo "PROGRESS:0\n";

if(exec(__DIR__."/keys") == 512) {
	exec("open -t ".__FILE__);
	echo "QUITAPP\n";
	die;
	}

// Detect files supported by sox binary

$types = trim(exec($soxdir."/sox -h | grep 'AUDIO FILE FORMATS' | cut -f2 -d:")); 

// Generated specs are $percent of screen height tall with an aspect ratio of $ratio
// beware sox can be unpredictable and actual size may vary

if ($enable_resizing) {

	$resolution_string = exec("system_profiler SPDisplaysDataType | grep Resolution");
	if (!$resolution_string) {
		echo "ALERT:No resolution|Can't determine screen resolution\n";
		die;
		}
	preg_match_all('!\d+!', $resolution_string, $matches);

	$height = floor($matches[0][1]*$vscale);
	$width = floor($height*$ratio);

	$sizeopts = "-x ".$width." -Y ".$height;

	} else {
	
	$sizeopts = "";
	
	}

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

	$allowed = explode(" ",$types);

	if (is_dir($target)) {

		$deepest_file = 0;

		$it = new RecursiveDirectoryIterator($target);
		foreach(new RecursiveIteratorIterator($it) as $file) {
    		if (in_array(strtolower(@array_pop(explode('.', $file))), $allowed)) {
        		$depth = count(explode("/",str_replace($target,"",$file->getpathname())));
				if ($depth > $deepest_file) {
					$deepest_file = $depth;
					}
				$files[] = $file->getpathname();
				}
			}
			
		} elseif (in_array(strtolower(@array_pop(explode('.', $target))), $allowed)) {
		
			$files[] = $target;
			
		}
	}

if (!$files) {
	echo "ALERT:No supported files dropped|Your sox binary can only support these filetypes: ".str_replace(" ",", ",$types)."\n";
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
	$makecmd = array();
	$hash = md5($file);
	$img = $workdir."/".$hash.".png";
	if ($deepest_file > $longtitle_threshold && $enable_resizing) {
		echo $title = implode("/",array_slice(explode("/",$file),-2,2));
		} else {
		$title = basename($file);
		}
	if (!file_exists($img)) {
		$makecmd[] = $soxdir."/sox \"".$file."\" -n spectrogram ".$sizeopts." -t \"".addslashes($title)."\" -c \"DropSpec ".$version."\" -o ".$img;
		}
	$opencmd[] = $img;
	echo "\n".basename($file);
	}
	
echo "\nBuilding specs...\n";

// Execute sox commands in parallel
// A better way would be to use pcntl_fork, but as it is not compiled in default osx php, this is the workaround

if ($debug) {
	echo implode("\n\n",$makecmd);
	}

exec(implode(" > /dev/null 2>&1 & ",$makecmd)." > /dev/null 2>&1 &");

// We need to update the progressbar, but without pcntl_fork we have no indication of command completion
// Workaround is to loop over the dest dir repeatedly to check file count

while (count(glob($workdir."/*.png")) < count($files)) {
	$count = count(glob($workdir."/*.png"));
	$total = count($files);
	$percent = floor(($count/$total)*100);
	echo "PROGRESS:".$percent."\n";
	usleep(10000);
	}

echo "PROGRESS:100\n";

echo "\nOpening...\n";

exec("qlmanage -p ".implode(" ",$opencmd)." > /dev/null 2>&1");

if (!$debug) {
	echo "QUITAPP\n";
	}

?>