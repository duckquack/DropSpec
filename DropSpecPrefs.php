<?

// DropSpecPrefs

function makeWindowString($p, $strings) {

	$conf = "
	# Set window title
	*.title = Preferences
	*.floating = 1
	
	# Soxbin
	soxbin.type = textfield
	soxbin.label = Sox binary (leave blank for bundled)
	soxbin.default = ".$p['soxbin']."
	soxbin.width = 200
	
	# X
	x.type = textfield
	x.label = X dimension
	x.default = ".$p['x']."
	x.width = 80
	
	# Y
	y.type = textfield
	y.label = Y dimension
	y.default = ".$p['y']."
	y.x = 120
	y.y = 278
	y.width = 80
	
	# Destination
	destination.type = popup
	destination.label = Destination
	destination.width = 120
	destination.option = ".$strings[0][0]."
	destination.option = ".$strings[0][1]."
	destination.default = ".$strings[0][$p['destination']]."

	# Limit
	limit.type = textfield
	limit.label = Max files to process without warning
	limit.default = ".$p['limit']."
	limit.width = 80

	# Postflight
	postflight.type = popup
	postflight.label = After generating specs
	postflight.width = 120
	postflight.option = ".$strings[1][0]."
	postflight.option = ".$strings[1][1]."
	postflight.default = ".$strings[1][$p['postflight']]."
	
	# Stay open?
	stay_open.type = checkbox
	stay_open.label = Stay open
	stay_open.default = ".$p['stay_open']."
	
	# Buttons
	#gb.type = button
	#gb.label = Detect Key...
	cb.type = cancelbutton
	db.type = defaultbutton
	db.label = Save
	";
	
	return $conf;
	
	}

// Read Prefs

$prefs_file = "/Users/".get_current_user()."/Library/Preferences/org.anatidae.DropSpec.php";
$p = unserialize(file_get_contents($prefs_file));

// Load strings

$strings[] = array("Temp","Inline");
$strings[] = array("Do nothing","Quicklook");

// Launch Pashua and parse results

$path = __DIR__."/Pashua.app/Contents/MacOS/Pashua";
$raw = shell_exec("echo ".escapeshellarg(makeWindowString($p, $strings))." | ".escapeshellarg($path)." - ");
$result = array();
foreach (explode("\n", $raw) as $line) {
	preg_match('/^(\w+)=(.*)$/', $line, $matches);
    if (empty($matches) or empty($matches[1])) {
    	continue;
        }
    $result[$matches[1]] = $matches[2];
    }
    
// User cancelled

if (@$result['cb']) {
	echo "0";
	die;
	}

// Fix strings

$result['destination'] = array_search($result['destination'],$strings[0]);
$result['postflight'] = array_search($result['postflight'],$strings[1]);

// Write Prefs

file_put_contents($prefs_file,serialize($result));
echo "1";

?>