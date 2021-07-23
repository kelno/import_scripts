<?php

include_once(__DIR__ . '/lib/common.php');
include_once(__DIR__ . '/lib/smartai.php');
include_once(__DIR__ . '/config.php');

/*
-> Altered tc.creature_template table to add an 'import' column (see enum below)
ALTER TABLE `trinityworld`.`creature_template`   
  ADD COLUMN `import` ENUM('IGNORE','GOSSIP','SMART') NULL AFTER `entry`;

-> Used this request to list creatures
SELECT tc.entry, tc.import, tc.gossip_menu_id, tc.name, tc.npcflag
FROM trinityworld.creature_template tc
JOIN world.creature_template sun ON sun.entry = tc.entry
WHERE sun.gossip_menu_id IS NULL AND tc.gossip_menu_id != 0

-> Then checked one by one and set an import mode in the following list:
"GOSSIP": Import gossip
IGNORE: ignore

(check for MAIN in code if you're looking where to start)
*/

$output_filename = "gossips.sql";
$file = fopen($output_filename, "w");
if (!$file)
	die("Couldn't open {$output_filename}");

$start = microtime(true);
	
$converter = new DBConverter($file);

$conn = new PDO("mysql:host=localhost", $login, $password);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// MAIN
$query = "SELECT tc.entry, tc.import, tc.gossip_menu_id, tc.name, tc.npcflag, sun.gossip_menu_id as sunMenuID
FROM {$tcWorld}.creature_template tc 
JOIN {$sunWorld}.creature_template sun ON sun.entry = tc.entry
WHERE tc.import IS NOT NULL AND tc.import != 'IGNORE' 
ORDER BY tc.gossip_menu_id "; 
//echo $query . PHP_EOL;
$stmt = $conn->query($query);
$stmt->setFetchMode(PDO::FETCH_ASSOC);
		
echo "Importing... " . PHP_EOL;

foreach($stmt->fetchAll() as $v) {
	fwrite($file, "-- Importing creature gossip with entry {$v['entry']} ({$v['name']}) with import type {$v['import']}" . PHP_EOL);
	switch($v["import"])
	{
		case "SMART": //SMART
			try {
			$converter->CreateSmartAI($v['entry'], SmartSourceType::creature, SmartSourceType::creature);
			} catch (ImportException $e) {
				echo "ERROR : {$e->getMessage()}" . PHP_EOL;
				fwrite($file, "-- FAILURE {$e->getMessage()}" . PHP_EOL);
			}
			break;
		case "GOSSIP": 
			$setflag = $v['npcflag'] & 1;
			$menu_id = $v['gossip_menu_id'];
			if($menu_id == 0) {
				echo "No menu for creature {$v['entry']}" . PHP_EOL;
				assert(false);
				exit(1);
			}
			$new_menu_id = $converter->CreateMenu($menu_id);
			$converter->SetMenuId($v['entry'], $new_menu_id, $setflag);
			if($current_sun_menu_id = $v['sunMenuID'])
				if($current_sun_menu_id != $new_menu_id)
					$converter->DeleteSunMenu($current_sun_menu_id);

			break;
		default:
			echo "ERROR: Non handled enum value: " . $v["import"] . PHP_EOL;
			exit(1);
	}
	$duration = microtime(true) - $start;
	$duration = number_format($duration, 4);
	if($debug)
		echo "Duration: {$duration}s" . PHP_EOL;
	fwrite($file, PHP_EOL . PHP_EOL);
}

fclose($file);

$duration = microtime(true) - $start;
$duration = number_format($duration, 4);
echo PHP_EOL . "Finished in {$duration}s with {$warnings} warnings and {$errors} errors" . PHP_EOL;	