<?php

include_once(__DIR__ . '/lib/helpers.php');
include_once(__DIR__ . '/lib/common.php');
include_once(__DIR__ . '/config.php');

$output_filename = "creature.sql";
$file = fopen($output_filename, "w");
if (!$file)
	die("Couldn't open {$output_filename}");
fwrite($file, "START TRANSACTION;" . PHP_EOL);

$start = microtime(true);

$debug = false;
$converter = new DBConverter($file, $debug);

$conn = new PDO("mysql:host=localhost", $login, $password);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$query = "SELECT tc.*
FROM $tcWorld.creature tc
WHERE tc.import IS NOT NULL AND tc.import != 'IGNORE'";

// MAIN
$stmt = $conn->query($query);
$stmt->setFetchMode(PDO::FETCH_ASSOC);

foreach($stmt->fetchAll() as $v) {
	fwrite($file, "-- Importing creature spawn with guid {$v['guid']} with import type {$v['import']}" . PHP_EOL);
	$guid = $v['guid'];
	$creature_id = $v["id"];
	switch($v["import"])
	{
		case "IMPORT": //Straight import the creature, no delete of anything. 
			echo "NYI" . PHP_EOL; assert(false); //$sql .= Import($v);
			break;
		case "LK_ONLY": //IMPORT but for LK patch only
			echo "NYI" . PHP_EOL; assert(false); //$sql .= ImportLK($v);
			break;
		case "UPDATE_SPAWNID": //Keep Sun data but update the spawnid to match TC
			echo "NYI" . PHP_EOL; assert(false); //$sql .= UpdateSpawnId($v);
			break;
		case "IMPORT_WP": //Import waypoints + formation member if any (will warn if not found on sun)
			$converter->ReplaceWaypoints($guid);
			break;
		case "MOVE_UNIQUE_IMPORT_WP": //Same as REPLACE_ALL but make sure there is only one on Sunstrider
			$results = FindAll($this->sunStore->creature_entry, "entry", $creature_id);
			assert(count($results) == 1);
			//no break
		case "REPLACE_ALL": //Remove all creatures with this id from sun, and replace with all creatures from TC with this id
			$converter->CreateReplaceAllCreature($creature_id);
			break;
		case "REPLACE_ALL_LK": //same but add LK patch condition
			$converter->CreateReplaceAllCreature($creature_id, 5);
			break;
		case "IMPORT_MAP":
			$converter->CreateReplaceMap($v["map"]);
			break;
		default:
			echo "ERROR: Non handled enum value: " . $v["import"] . PHP_EOL;
			exit(1);
	}
	fwrite($file, PHP_EOL . PHP_EOL);
}

fwrite($file, "COMMIT;" . PHP_EOL);
fclose($file);

$duration = microtime(true) - $start;
$duration = number_format($duration, 4);
echo "Finished in {$duration}s" . PHP_EOL;	