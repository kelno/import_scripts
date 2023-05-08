<?php

include_once(__DIR__ . '/lib/helpers.php');
include_once(__DIR__ . '/lib/common.php');
include_once(__DIR__ . '/config.php');

if ($argc < 2)
    die("missing map argument");

$mapId = $argv[1];
$output_filename = "map_spawns_{$mapId}.sql";
$file = fopen($output_filename, "w");
if (!$file)
	die("Couldn't open {$output_filename}");

$start = microtime(true);

$converter = new DBConverter($file);

echo "Importing all spawns for map {$mapId}" . PHP_EOL;

$converter->CreateReplaceMap($mapId);
"Done map" . PHP_EOL;
    
fclose($file);

$duration = microtime(true) - $start;
$duration = number_format($duration, 4);
echo PHP_EOL . "Finished in {$duration}s with {$warnings} warnings and {$errors} errors" . PHP_EOL;	