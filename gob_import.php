<?php

include_once(__DIR__ . '/lib/helpers.php');
include_once(__DIR__ . '/lib/common.php');
include_once(__DIR__ . '/config.php');

$output_filename = "gameobject.sql";
$file = fopen($output_filename, "w");
if (!$file)
	die("Couldn't open {$output_filename}");

$start = microtime(true);

$debug = false;
$converter = new DBConverter($file, $debug);

$conn = new PDO("mysql:host=localhost", $login, $password);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$query = "SELECT tc.*
FROM $tcWorld.gameobject tc
WHERE tc.import IS NOT NULL AND tc.import != 'IGNORE'";

// MAIN
$stmt = $conn->query($query);
$stmt->setFetchMode(PDO::FETCH_ASSOC);

foreach($stmt->fetchAll() as $v) {
	fwrite($file, "-- Importing gameobject spawn with guid {$v['guid']} with import type {$v['import']}" . PHP_EOL);
	$guid = $v['guid'];
	$gob_id = $v["id"];
	switch($v["import"])
	{
		case "IMPORT": //Straight import the gameobject, no delete of anything. 
			$converter->ImportTCGameObject($guid);
			break;
		case "REPLACE_ALL": //Remove all gameobjects with this id from sun, and replace with all gameobjects from TC with this id
			$converter->CreateReplaceAllGameObject($gob_id);
			break;
		default:
			echo "ERROR: Non handled enum value: " . $v["import"] . PHP_EOL;
			exit(1);
	}
	fwrite($file, PHP_EOL . PHP_EOL);
}

fclose($file);

$duration = microtime(true) - $start;
$duration = number_format($duration, 4);
echo PHP_EOL . "Finished in {$duration}s with {$warnings} warnings and {$errors} errors" . PHP_EOL;	