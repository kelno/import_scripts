<?php

include_once(__DIR__ . '/config.php');

$output_filename = "convert_game_event.sql";
$file = fopen($output_filename, "w");
if (!$file)
	die("Couldn't open {$output_filename}");

//SUN => TC
$correspondance_table = [
	1  => 1, // Midsummer Fire Festival
	2  => 2, // Winter Veil
	3  => 3, // Darkmoon Faire (Terokkar Forest)
	4  => 4, // Darkmoon Faire (Elwynn Forest)
	5  => 5, // Darkmoon Faire (Mulgore)
	6  => 6, // New Year's Eve
	7  => 7, // Lunar Festival
	8  => 8, // Love is in the Air
	9  => 9, // Noblegarden
	10 => 10, // Children's Week 
	11 => 11, // Harvest Festival
	12 => 12, // Hallow's End
	13 => 13, // Elemental Invasions
	14 => 14, // Fishing Extravaganza Announce
	15 => 15, // Fishing Extravaganza
	16 => 16, // Gurubashi Arena Booty Run
	17 => 17, // Scourge Invasion
	18 => 18, // Call to Arms: Alterac Valley!
	19 => 19, // Call to Arms: Warsong Gulch!
	20 => 20, // Call to Arms: Arathi Basin!
	21 => 21, // Call to Arms: Eye of the Storm!
	22 => 22, // AQ War Effort
	23 => 23, // Darkmoon Faire Building (Elwynn Forest)
	24 => null, // Unknown Holiday PVP Event
	25 => null, // Call to Arms: Unknown Event
	26 => 24, // Brewfest
	27 => 25, // Nights
	28 => null, // Noblegarden ... duplicate event, can't fix it with this script
	29 => 27, // Edge of Madness, Gri'lek
	30 => 28, // Edge of Madness, Hazza'rah
	31 => 29, // Edge of Madness, Renataki
	32 => 30, // Edge of Madness, Wushoolay
	33 => 31, // Arena Tournament
	34 => 32, // L70ETC Concert
	35 => null, // Shattered Sun Offensive: Phase 1 - Reclaiming the Sanctum
	36 => null, // Shattered Sun Offensive: Phase 1 - Reclaiming the Sanctum COMPLETE
	37 => null, // Shattered Sun Offensive: Phase 2 - Sun's Reach Armory
	38 => null, // Shattered Sun Offensive: Phase 2 - Sun's Reach Armory COMPLETE
	39 => null, // Shattered Sun Offensive: Phase 2b - Activating the Sunwell Portal
	40 => null, // Shattered Sun Offensive: Phase 2b - Activating the Sunwell Portal COMPLETE
	41 => null, // Shattered Sun Offensive: Phase 3 - Sun's Reach Harbor
	42 => null, // Shattered Sun Offensive: Phase 3 - Sun's Reach Harbor COMPLETE
	43 => null, // Shattered Sun Offensive: Phase 3b - Rebuilding the Anvil and Forge
	44 => null, // Shattered Sun Offensive: Phase 3b - Rebuilding the Anvil and Forge COMPLETE
	45 => null, // Shattered Sun Offensive: Phase 4A - Creating the Alchemy Lab
	46 => null, // Shattered Sun Offensive: Phase 4A - Creating the Alchemy Lab COMPLETE
	47 => null, // Shattered Sun Offensive: Phase 4B - Building the Monument to the Fallen
	48 => null, // Shattered Sun Offensive: Phase 4B - Building the Monument to the Fallen COMPLETE
	49 => null, // Shattered Sun Offensive: Phase 4 - COMPLETE
	50 => null, // Wickerman Festival
	51 => null, // Shade of the Horseman - Brill
	52 => null, // Shade of the Horseman - Goldshire
	53 => null, // Shade of the Horseman - Silvermoon
	54 => null, // Shade of the Horseman - Kharanos
	55 => null, // Shade of the Horseman - Azure Watch
	56 => null, // Shade of the Horseman - Razor Hill
	57 => 52, // Winter Veil: Presents
	58 => null, // Fishing: Winter
	59 => null, // Fishing: Summer
	60 => 76, // Childeren of Goldshire
	61 => null, // GMIsland - Brewfest
	62 => null, // BETA
	63 => null, // GMIsland - Let's dance !
	64 => null, // GMIsland - Christmas time
	65 => null, // 2.1
	66 => null, // 2.3
	67 => null, // 2.4
	68 => null, // Skettis - Sha'tari Skyguard
	69 => null, // Nether Drake
	70 => null, // 2.4.3 -- Use 2.4 instead
	71 => null, // Ogri'la -> should be convert to 2.1 patch
	72 => 0, // Season 1
	73 => 0, // Season 2
	74 => 55, // Season 3
	75 => 56, // Season 4
	76 => null, // 2.2
	79 => null, // Test
	80 => 50, // Pirates Day
	81 => 51, // Day of the Dead (TLK)
	82 => 62, // Stranglethorn Fishing Extravaganza Turn-ins
];

$sql = "";

//Step 1: Offset known events
$offset = 100;
$sql .= "UPDATE conditions SET ConditionValue1 = ConditionValue1 + {$offset} WHERE ConditionTypeOrReference = 12;" . PHP_EOL;
//game_event_battleground_holiday has a foreign key
//game_event_condition has a foreign key
$sql .= "UPDATE game_event_creature SET event = event + {$offset} WHERE event > 0;" . PHP_EOL;
$sql .= "UPDATE game_event_creature SET event = event - {$offset} WHERE event < 0;" . PHP_EOL;
$sql .= "UPDATE game_event_gameobject SET event = event + {$offset} WHERE event > 0;" . PHP_EOL;
$sql .= "UPDATE game_event_gameobject SET event = event - {$offset} WHERE event < 0;" . PHP_EOL;
//game_event_creature_quest has a foreign key
//game_event_model_equip has a foreign key
//game_event_npc_vendor has a foreign key
//game_event_npcflag has a foreign key
//game_event_pool is empty
$sql .= "UPDATE game_event_pool SET eventEntry = eventEntry + {$offset};". PHP_EOL;
//game_event_prerequisite has a foreign key
//game_event_quest_condition has a foreign key
//game_event_seasonal_questrelation has a foreign key
$sql .= "UPDATE game_event SET entry = entry + {$offset};". PHP_EOL;

foreach($correspondance_table as $sun_id => $tc_id) {
	if(!$tc_id)
		continue; //just keep the id+offset for those
	
	$sun_id += $offset;
	
	$sql .= "UPDATE conditions SET ConditionValue1 = {$tc_id} WHERE ConditionTypeOrReference = 12 AND ConditionValue1 = {$sun_id};" . PHP_EOL;
	$sql .= "UPDATE game_event_creature SET event = {$tc_id} WHERE event = {$sun_id};" . PHP_EOL;
	$sql .= "UPDATE game_event_creature SET event = -{$tc_id} WHERE event = -{$sun_id};" . PHP_EOL;
	$sql .= "UPDATE game_event_gameobject SET event = {$tc_id} WHERE event = {$sun_id};" . PHP_EOL;
	$sql .= "UPDATE game_event_gameobject SET event = -{$tc_id} WHERE event = -{$sun_id};" . PHP_EOL;
	$sql .= "UPDATE game_event_pool SET eventEntry = {$tc_id} WHERE eventEntry = {$sun_id};". PHP_EOL;
	$sql .= "UPDATE game_event SET entry = {$tc_id} WHERE entry = {$sun_id};". PHP_EOL;
}

//Step 2: Import missing TC events
$conn = new PDO("mysql:host=localhost", $login, $password);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$query = "SELECT * FROM {$tcWorld}.game_event"; 
//echo $query . PHP_EOL;
$stmt = $conn->query($query);
$stmt->setFetchMode(PDO::FETCH_ASSOC);
		
$sql .= PHP_EOL;
foreach($stmt->fetchAll() as $v) {
	$tc_event_id = $v['eventEntry'];
	$desc = $conn->quote($v['description']);
	$start_time = $v['start_time'] ? $conn->quote($v['start_time']) : 'NULL';
	$end_time = $v['end_time'] ? $conn->quote($v['end_time']) : 'NULL';
	if(in_array($tc_event_id, $correspondance_table)) {
		//already have that one, just update name
		$sql .= "UPDATE game_event SET description = {$desc} WHERE entry = {$tc_event_id};" . PHP_EOL;
		continue; 
	}
	
	$sql .= "INSERT INTO game_event VALUES ({$tc_event_id}, {$start_time}, {$end_time}, {$v['occurence']}, {$v['length']}, {$desc}, {$v['world_event']}, 0, 5, 10);" . PHP_EOL;
}

fwrite($file, $sql);
fclose($file);
echo "Done." . PHP_EOL;

