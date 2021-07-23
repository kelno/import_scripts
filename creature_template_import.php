<?php

include_once(__DIR__ . '/lib/helpers.php');
include_once(__DIR__ . '/lib/common.php');
include_once(__DIR__ . '/config.php');

$output_filename = "creature_template.sql";
$file = fopen($output_filename, "w");
if (!$file)
	die("Couldn't open {$output_filename}");

$start = microtime(true);

$converter = new DBConverter($file);

$conn = new PDO("mysql:host=localhost", $login, $password);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$query = "SELECT tc.entry
FROM ${sunWorld}.creature_template sun
RIGHT JOIN ${tcWorld}.creature_template tc ON tc.entry = sun.entry
WHERE sun.entry IS NULL OR sun.patch >= 5";

// MAIN
$stmt = $conn->query($query);
$stmt->setFetchMode(PDO::FETCH_ASSOC);

foreach($stmt->fetchAll() as $v) {
	fwrite($file, "-- Importing creature template id {$v['entry']}" . PHP_EOL);
	$creature_id = $v['entry'];
    $converter->ImportCreatureTemplate($creature_id);
	
	fwrite($file, PHP_EOL . PHP_EOL);
    
    break; //testing
}

fclose($file);

$duration = microtime(true) - $start;
$duration = number_format($duration, 4);
echo PHP_EOL . "Finished in {$duration}s with {$warnings} warnings and {$errors} errors" . PHP_EOL;	