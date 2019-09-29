<?php

// DropSpec

$version = file_get_contents(__DIR__."/current_version.txt");

// Functions

function askMulti($string, $buttons) {
	$buttonstring = "buttons {\\\"".implode("\\\", \\\"",$buttons)."\\\"} default button ".count($buttons);
	$result = exec("osascript -e \"display dialog \\\"".$string."\\\" ".$buttonstring."\" | cut -f2 -d':'");
	return array_search($result,$buttons);
	}

// Load preferences

$prefs_file = "/Users/".get_current_user()."/Library/Preferences/org.anatidae.DropSpec.php";
if (!file_exists($prefs_file)) {
	if (!copy(__DIR__."/prefs.php",$prefs_file)) {
		echo "Error creating preferences file";
		die;
		}
	}
$p = unserialize(file_get_contents($prefs_file));

$longtitle_threshold = 2;

// Sox options

if (!@$p['soxbin']) {
	$p['soxbin'] = __DIR__."/sox";
	}

$types = trim(exec(escapeshellarg($p['soxbin'])." -h | grep 'AUDIO FILE FORMATS' | cut -f2 -d:")); 

$sizeopts = "";
if (@$p['x']) {
	$sizeopts .= " -x ".$p['x'];
	}
if (@$p['y']) {
	$sizeopts .= " -Y ".$p['y'];
	}

echo "PROGRESS:0\n";

// No files dropped

switch (@$argv[1]) {
	case NULL:
		// get path from the frontmost finder window
		echo "\nGathering path from current Finder context...\n";
		$argv[1] = exec("osascript -e 'tell application \"Finder\" to get the POSIX path of (target of front window as alias)'");
		break;
	case "Preferences...":
		exec("php ".__DIR__."/DropSpecPrefs.php");
		die;
	case "Check for Updates...":
		$curr_version = file_get_contents("https://raw.githubusercontent.com/duckquack/DropSpec/master/current_version.txt");
		if (!$curr_version) {
			echo "\nALERT:Can't connect|Error checking for latest version\n";
			die;
			}
		if ($curr_version > $version) {
			if(askMulti("A new version of DropSpec is available", array("Skip","Download")) == 1) {
				exec("open https://github.com/duckquack/DropSpec");
				echo "QUITAPP\n";
				} else {
				die;
				}
			} else {
			echo "\nALERT:Up-to-date|".$version." is the latest version.\n";
			die;
			}
	}

unset($argv[0]);

if (!is_executable($p['soxbin'])) {
	echo "\nALERT:Sox error|Error locating sox binary. Check preferences.\n";
	die;
	}

$files = array();

// Build list of valid dropped files

$deepest_file = 0;

foreach ($argv as $target) {

	$allowed = explode(" ",$types);

	if (is_dir($target)) {

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
	echo "ALERT:No supported files dropped|".$p['soxbin']." supports these filetypes: ".str_replace(" ",", ",$types)."\n";
	die;
	} else {
	sort($files);
	}

if (count($files) > $p['limit']) {
	$result = exec("osascript -e \"display dialog \\\"Really process ".count($files)." files?\\\"\" 2>&1");
	if (strpos($result,"canceled") !== false) {
		echo "\nUser Cancelled\n";
		die;
		}
	}

// Make a tmp dir for the pngs

$parentworkdir = "/tmp/dropspec/";

if (!file_exists($parentworkdir)) {
	mkdir($parentworkdir);
	}

$workdir = $parentworkdir.md5(implode(".",$argv));
$lockdir = $workdir."/".time();

if (!file_exists($workdir)) {
	mkdir($workdir);
	}
if (!file_exists($lockdir)) {
	mkdir($lockdir);
	}

// Build commands for sox
	
$makecmd = array();
$label = array();
foreach ($files as $file) {
	if ($deepest_file > $longtitle_threshold) {
		$title = implode("/",array_slice(explode("/",$file),-2,2));
		} else {
		$title = basename($file);
		}
	$hash = md5($file);
	$lockfile = $lockdir."/".$hash.".lock";
	if ($p['destination'] == 0) {
		$img = $workdir."/".$hash.".png";
		} else {
		$img = dirname($file)."/specs/".pathinfo($file,PATHINFO_FILENAME).".png";
		if (!file_exists(dirname($img))) {
			mkdir(dirname($img));
			}
		}
	if (!file_exists($img)) {
		$makecmd[] = escapeshellarg($p['soxbin'])." ".escapeshellarg($file)." -n spectrogram ".$sizeopts." -t ".escapeshellarg($title)." -c \"DropSpec ".$version."\" -o ".escapeshellarg($img)."; touch ".escapeshellarg($lockfile);
		} else {
		$makecmd[] = "touch ".escapeshellarg($lockfile);
		}
	$opencmd[] = $img;
	$label[] = $title;
	}

// Execute sox commands in parallel
// A better way would be to use pcntl_fork, but as it is not compiled in default osx php, this is the workaround

$pfile = $workdir."/exec.sh";
$log = $workdir."/log_".time().".txt";
file_put_contents($pfile, implode("\n",$makecmd));
exec(escapeshellarg(__DIR__."/parallel")." < ".$pfile." >> ".$log." 2>&1 &");
	
// We need to update the progressbar, but without pcntl_fork we have no indication of command completion
// Workaround is to loop over the lock dir repeatedly to check file count

array_unshift($label, "Spawning threads...");

while (count(glob($lockdir."/*.lock")) < count($files)) {
	
	$count = count(glob($lockdir."/*.lock"));
	
	if ($label[$count]) {
		echo "\n".$label[$count]."\n";
		$label[$count] = 0;
		}
	
	$total = count($files);
	$percent = floor(($count/$total)*100);
	echo "PROGRESS:".$percent."\n";

	}

echo "PROGRESS:100\n";

if (@filesize($log)) { echo file_get_contents($log); }

if ($p['postflight'] == 1) {
	echo "\nOpening...\n";
	foreach ($opencmd as $file) { if (file_exists($file)) { $openme[] = escapeshellarg($file); } }
	if (@$openme) { exec("qlmanage -p ".implode(" ",@$openme)." > /dev/null 2>&1"); } else { echo "No images generated."; }
	}

if (!$p['stay_open']) {
	echo "QUITAPP\n";
	}

?>