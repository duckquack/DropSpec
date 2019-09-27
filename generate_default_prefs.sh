#!/usr/bin/php
<?

$p['soxbin'] = "";
$p['x'] = 1600;
$p['y'] = 1100;
$p['destination'] = 0;
$p['limit'] = 200;
$p['postflight'] = 1;
$p['stay_open'] = 0;

file_put_contents("prefs.php",serialize($p));

?>