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
WHERE sun.entry IS NULL OR (sun.patch >= 5 AND sun.entry NOT IN (SELECT entry FROM ${sunWorld}.creature_template WHERE patch = 0))
ORDER BY tc.entry
";

// MAIN
$stmt = $conn->query($query);
$stmt->setFetchMode(PDO::FETCH_ASSOC);

$results = $stmt->fetchAll();
$size = count($results);
$i = 1;
foreach($results as &$v) {
    echo "Importing creature template id {$v['entry']} (${i} of ${size})" .PHP_EOL;
	fwrite($file, "-- Importing creature template id {$v['entry']}" . PHP_EOL);
	$creature_id = $v['entry'];
    $converter->ImportCreatureTemplate($creature_id, true);
	
	fwrite($file, PHP_EOL . PHP_EOL);
    ++$i;
    //break; //testing
}

fclose($file);

$duration = microtime(true) - $start;
$duration = number_format($duration, 4);
echo PHP_EOL . "Finished in {$duration}s with {$warnings} warnings and {$errors} errors" . PHP_EOL;
