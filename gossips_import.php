<?php

include_once(__DIR__ . '/lib/common.php');
include_once(__DIR__ . '/lib/smartai.php');

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

$file = fopen(__DIR__."/gossips.sql", "w");
if (!$file)
	die("Couldn't open gossips.txt");

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
$sql = "";
foreach($stmt->fetchAll() as $v) {
	$sql .= "-- Importing creature gossip with entry {$v['entry']} ({$v['name']}) with import type {$v['import']}" . PHP_EOL;
	switch($v["import"])
	{
		case "SMART": //GOSSIP+SMART
			$sql .= CreateSmartAI($v['entry'], SmartSourceType::creature);
			//nobreak
		case "GOSSIP": 
			$setflag = $v['npcflag'] & 1;
			$menu_id = $v['gossip_menu_id'];
			$sql .= CreateMenu($menu_id); //$menu_id might get changed here
			$sql .= SetMenuId($v['entry'], $menu_id, $setflag);
			if($current_sun_menu_id = $v['sunMenuID'])
				$sql .= DeleteSunMenu($current_sun_menu_id);
	
			break;
		default:
			echo "ERROR: Non handled enum value: " . $v["import"] . PHP_EOL;
			exit(1);
	}
	$sql .= PHP_EOL . PHP_EOL;
}

fwrite($file, $sql);
fclose($file);

echo "Finished!" . PHP_EOL;