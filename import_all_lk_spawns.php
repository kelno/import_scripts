<?php

include_once(__DIR__ . '/lib/helpers.php');
include_once(__DIR__ . '/lib/common.php');
include_once(__DIR__ . '/config.php');

$output_filename = "lk_spawns.sql";
$file = fopen($output_filename, "w");
if (!$file)
	die("Couldn't open {$output_filename}");

$start = microtime(true);

$converter = new DBConverter($file);

$conn = new PDO("mysql:host=localhost", $login, $password);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$query = "SELECT UNIQUE map
FROM $tcWorld.creature
UNION 
SELECT UNIQUE map
FROM $tcWorld.gameobject
ORDER BY -map";

 // MAIN
$stmt = $conn->query($query);
$stmt->setFetchMode(PDO::FETCH_ASSOC);

$lk_map_ids = array();
foreach($stmt->fetchAll() as $v) {
    $map_id = $v['map'];
    if (IsTLKMap($map_id))
        array_push($lk_map_ids, $map_id);
}

echo "Importing all spawns for maps: " . PHP_EOL;
foreach($lk_map_ids as $map_id)
    echo $map_id . PHP_EOL;
    
echo PHP_EOL;

foreach($lk_map_ids as $map_id) {
    echo "Starting map $map_id" . PHP_EOL;
    $converter->CreateReplaceMap($map_id);
    echo "Done map $map_id" . PHP_EOL;
}
    
fclose($file);

$duration = microtime(true) - $start;
$duration = number_format($duration, 4);
echo PHP_EOL . "Finished in {$duration}s with {$warnings} warnings and {$errors} errors" . PHP_EOL;	