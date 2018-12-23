<?php

include_once(__DIR__ . '/lib/helpers.php');

/*
-> Altered tc.creature table to add an 'import' column (see enum below)
ALTER TABLE `trinityworld`.`creature`   
  ADD COLUMN `import` ENUM('IMPORT','REPLACE_ALL','LK_ONLY','MOVE_UNIQUE_IMPORT_WP','','UPDATE_SPAWNID','IMPORT_WP', 'IGNORE') CHARSET utf8 COLLATE utf8_general_ci NULL;

-> Used this request to list creatures
SELECT tc.*
FROM trinityworld.creature tc
JOIN world.creature_template ct ON ct.entry = tc.id
LEFT JOIN world.creature c ON c.spawnId = tc.guid
WHERE c.spawnId IS NULL
AND tc.map IN (0,1,530)
AND tc.id IN (SELECT id FROM trinityworld.creature GROUP BY id HAVING COUNT(*) = 1) 

OR to check missing waypoints only

SELECT tca.*, tc.*
FROM trinityworld.creature tc
JOIN trinityworld.creature_addon tca ON tca.guid = tc.guid
JOIN world.creature_template ct ON ct.entry = tc.id
JOIN world.creature c ON c.spawnId = tc.guid
LEFT JOIN world.creature_addon ca ON ca.spawnId = c.spawnId
WHERE tc.map IN (0,1,530)
AND tca.path_id != 0 AND ca.path_id IS NULL

Or to just batch import
UPDATE trinityworld.creature tc
JOIN trinityworld.creature_addon tca ON tca.guid = tc.guid
JOIN world.creature_template ct ON ct.entry = tc.id
JOIN world.creature c ON c.spawnId = tc.guid
LEFT JOIN world.creature_addon ca ON ca.spawnId = c.spawnId
LEFT JOIN world.smart_scripts ss ON ss.entryOrGuid = -tc.guid AND source_type = 0 AND action_type IN (152, 113, 13) 
SET IMPORT = "IMPORT_WP"
WHERE 
 tca.path_id != 0 AND ca.path_id IS NULL AND ss.entryOrGuid IS NULL
 

-> Then checked one by one and set an import mode in the following list:
"MOVE_UNIQUE_IMPORT_WP": //Import the TC creatures and completely override the TC one". Also import WP, game_event and formation if any
"IMPORT": //Straight import the creature, no delete of anything. 
"LK_ONLY": //IMPORT but for LK patch only
"UPDATE_SPAWNID": //Keep Sun data but update the spawnid to match TC
"REPLACE_ALL": //Remove all creatures with this id from sun, and replace with all creatures from TC with this id
"IMPORT_WP": //Import waypoints + formation member if any (will warn if not found on sun)
IGNORE: ignore

(check for MAIN in code if you're looking where to start)
*/

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$sunWorld = "world";
$tcWorld = "trinityworld";
$login = "root";
$password = "canard";

// static deletes, if any 
$deleteSun = [  ];

$masterQuery = "SELECT tc.*, tca.guid as addon_guid, tca.path_id as path_id, tca.mount as mount, tca.bytes1 as bytes1, tca.bytes2 as bytes2, tca.emote as emote, tca.auras as auras, gec.eventEntry as eventEntry
FROM $tcWorld.creature tc
LEFT JOIN $tcWorld.creature_addon tca ON tca.guid = tc.guid
LEFT JOIN $tcWorld.game_event_creature gec ON gec.guid = tc.guid ";

// Connect
$conn = new PDO("mysql:host=localhost;dbname=$sunWorld", $login, $password);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function UpdateSpawnId(&$v)
{
	global $conn, $sunWorld;
	
	$stmt = $conn->query("SELECT spawnID FROM $sunWorld.creature_entry WHERE entry = {$v['id']};");
	$stmt->setFetchMode(PDO::FETCH_NUM);
	$results = $stmt->fetchAll();
	if(sizeof($results) != 1) //when using UPDATE_SPAWNID, it is expected that there is only one creature to update
	{
		echo "ERROR: Trying to update spawnId with entry {$v['id']} but found ".sizeof($results)." results instead of 1" . PHP_EOL;
		exit(1);
	}
	$sunSpawnID = $results[0][0];
	
	$sql =  "UPDATE creature_entry SET spawnID = {$v['guid']} WHERE entry = {$v['id']};" . PHP_EOL;
	$sql .= "UPDATE conditions SET ConditionValue3 = {$v['guid']} WHERE ConditionValue3 = {$sunSpawnID} AND ConditionTypeOrReference = 31;" . PHP_EOL;
	$sql .= "UPDATE conditions SET SourceEntry = -{$v['guid']} WHERE SourceEntry = -{$sunSpawnID} AND SourceTypeOrReferenceId = 22;" . PHP_EOL;
	$sql .= "UPDATE smart_scripts SET entryorguid = -{$v['guid']} WHERE entryorguid = -{$sunSpawnID} AND source_type = 0;" . PHP_EOL;
	$sql .= "UPDATE smart_scripts SET target_param1 = {$v['guid']} WHERE target_param1 = {$sunSpawnID} AND target_type = 10;" . PHP_EOL;
	$sql .= "UPDATE spawn_group SET spawnID = {$v['guid']} WHERE spawnID = {$sunSpawnID} AND spawnType = 0;" . PHP_EOL;
			
	return $sql;
}

$firstFreeBroadcastID = null;
$stmt = $conn->query("SELECT max(id) + 1 FROM $sunWorld.broadcast_text;");
$stmt->setFetchMode(PDO::FETCH_NUM);
$firstFreeBroadcastID = $stmt->fetch()[0];

$importedBroadcastID = [ ];

function ImportBroadcastText(&$textID)
{
	global $conn, $tcWorld, $sunWorld, $firstFreeBroadcastID, $importedBroadcastID;

	//0 - Check if already imported
	if(in_array($textID, $importedBroadcastID))
		return; //already imported
	
	array_push($importedBroadcastID, $textID);
	
	//1 - Check if id exists at sun
	$useId = 0;
	
	//Check if id is free
	$stmt = $conn->query("SELECT * FROM $sunWorld.broadcast_text WHERE ID = {$textID};");
	$stmt->setFetchMode(PDO::FETCH_ASSOC);
	$results = $stmt->fetch();
	
	$stmt2 = $conn->query("SELECT * FROM $tcWorld.broadcast_text WHERE ID = {$textID};");
	$stmt2->setFetchMode(PDO::FETCH_ASSOC);
	$results2 = $stmt2->fetch();
	
	if($results) {
		//id exists, but maybe its the same?
		assert($results2); //assert tc broadcast exists
		$TCText = $results2['MaleText'];
		$SunText = $results['MaleText'];
		if($TCText == $SunText)
			return; //text are the same, we can stop now, nothing to import
		
		$useId = $firstFreeBroadcastID++;
		echo "Importing broadcast textid {$textID}, id was already existing but is different. Using new id {$useId}" . PHP_EOL;
	}
	else
		$useId = $textID;
	
	assert($useId != 0);
	
	$maleText = $results2['MaleText'] ? $conn->quote($results2['MaleText']) : "NULL";
	$femaleText = $results2['FemaleText'] ? $conn->quote($results2['FemaleText']) : "NULL";
	
	//Import broadcast text
	$sql = "INSERT INTO broadcast_text (ID, Language, MaleText, FemaleText, EmoteID0, EmoteID1, EmoteID2, EmoteDelay0, EmoteDelay1, EmoteDelay2, SoundId, Unk1, Unk2, VerifiedBuild) VALUES " .
		   "({$useId}, {$results2['Language']}, {$maleText}, {$femaleText}, {$results2['EmoteID0']}, {$results2['EmoteID1']}, {$results2['EmoteID2']}, ".
           "{$results2['EmoteDelay0']}, {$results2['EmoteDelay1']}, {$results2['EmoteDelay2']}, {$results2['SoundId']}, {$results2['Unk1']}, {$results2['Unk2']}, ".
		   "{$results2['VerifiedBuild']});" . PHP_EOL;
	
	return $sql;
}

$stmt = $conn->query("SELECT max(id) + 1 FROM $sunWorld.waypoint_data;");
$stmt->setFetchMode(PDO::FETCH_NUM);
$firstFreeWPId = $stmt->fetch()[0];

$stmt = $conn->query("SELECT max(id) + 1 FROM $sunWorld.waypoint_scripts;");
$stmt->setFetchMode(PDO::FETCH_NUM);
$firstFreeWPScriptsId = $stmt->fetch()[0];

$importedWPScriptsId = [ ];

function ImportWaypointScripts(&$id)
{
	global $conn, $tcWorld, $sunWorld, $firstFreeWPScriptsId, $importedWPScriptsId;
	
	if(in_array($id, $importedWPScriptsId))
		return; //already imported
	
	array_push($importedWPScriptsId, $id);
	
	$useId = 0;
	
	//Check if id is free
	$stmt = $conn->query("SELECT id FROM $sunWorld.waypoint_scripts WHERE id = {$id};");
	$stmt->setFetchMode(PDO::FETCH_NUM);
	$results = $stmt->fetch()[0];
	if($results != 0) {
		$useId = $firstFreeWPScriptsId++;
		//echo "Using new wp scripts id {$useId}" . PHP_EOL;
	}
	else
		$useId = $id;
	
	assert($useId != 0);
	
	//import
	$query = "SELECT * FROM $tcWorld.waypoint_scripts WHERE id = {$id}";
	//echo $query . PHP_EOL;
	$stmt = $conn->query($query);
	$stmt->setFetchMode(PDO::FETCH_ASSOC);

	$hasResult = false;
	
	$sql = "";
	foreach($stmt->fetchAll() as $k => $v) {
		$hasResult = true;
		$dataint = $v['dataint'];
		if($v['command'] == 0) // 0 = SCRIPT_COMMAND_TALK
			$sql .= ImportBroadcastText($dataint); //$dataint can be changed here
			
		$sql .= "INSERT INTO waypoint_scripts (id, delay, command, datalong, datalong2, dataint, x, y, z, o) VALUES ({$useId}, {$v['delay']}, {$v['command']}, {$v['datalong']}, {$v['datalong2']}, {$dataint}, {$v['x']}, {$v['y']}, {$v['z']}, {$v['o']});" . PHP_EOL;
	}
	if(!$hasResult)
	{
		echo "Tried to import wp scripts with id $id but it was not found. This is an error in TC db. Removing action." . PHP_EOL;
		$id = "NULL";
	} else {
		$id = $useId;
	}
	return $sql;
}

$importedWaypoints = [ ];

function ImportWaypoints($guid, &$id, $includeMovementTypeUpdate = true)
{
	global $conn, $tcWorld, $sunWorld, $firstFreeWPId, $importedWaypoints;
	
	if(in_array($id, $importedWaypoints))
		return; //already imported
	
	array_push($importedWaypoints, $id);
	
	$useId = 0;
	
	//Check if id is free
	$stmt = $conn->query("SELECT id FROM $sunWorld.waypoint_data WHERE id = {$id};");
	$stmt->setFetchMode(PDO::FETCH_NUM);
	$results = $stmt->fetch()[0];
	if($results != 0) {
		$useId = $firstFreeWPId++;
		//echo "Using new wp id {$useId}" . PHP_EOL;
	}
	else
		$useId = $id;
	
	assert($useId != 0);
	
	//import
	$query =  "SELECT * FROM $tcWorld.waypoint_data WHERE id = {$id}";
	//echo $query . PHP_EOL;
	$stmt = $conn->query($query);
	$stmt->setFetchMode(PDO::FETCH_ASSOC);

	$sql = "";
	if($includeMovementTypeUpdate)
		$sql .= "UPDATE creature SET MovementType = 2 WHERE spawnId = {$guid};" . PHP_EOL; //WAYPOINT_MOTION_TYPE
	
	$insert_data = [ ]; //could be done in the loop for this is here for speedup
	foreach($stmt->fetchAll() as $k => $v) {
		$action = $v['action'];
		if($action != 0)
			$sql .= ImportWaypointScripts($action); //$action might get changed here
		else
			$action = "NULL";
		
		//speed up here, create a batch insert instead of one line per entry. Cuz this is the slowest part of the resulting sql scripts.
		$v['action'] = $action;
		array_push($insert_data, $v);
		
	}
	if(!empty($insert_data)) {
		$sql .= "INSERT INTO waypoint_data VALUES " . PHP_EOL;
		foreach($insert_data as $v) {
			$sql .= "({$useId}, {$v['point']}, {$v['position_x']}, {$v['position_y']}, {$v['position_z']}, {$v['orientation']}, {$v['delay']}, {$v['move_type']}, {$v['action']}, {$v['action_chance']}, 0),".PHP_EOL;
		}
		$sql = substr_replace($sql, "", -3); //remove last comma
		$sql .= ";" . PHP_EOL;
	}
	
	$id = $useId;
	return $sql;
}

function DeleteSunWaypoints($wp_id)
{
	global $conn, $sunWorld;
	
	$query = "SELECT action FROM {$sunWorld}.waypoint_data WHERE id = {$wp_id}";
	$stmt = $conn->query($query);
	$stmt->setFetchMode(PDO::FETCH_ASSOC);
	
	$sql = "";
	
	foreach($stmt->fetchAll() as $k => $v) {
		$action = $v['action'];
		if(!$action)
			continue;
		
		//Only delete if unique
		$query2 = "SELECT action FROM {$sunWorld}.waypoint_data WHERE action = {$action}";
		//echo $query2 . PHP_EOL;
		$stmt2 = $conn->query($query2);
		$stmt2->setFetchMode(PDO::FETCH_ASSOC);
		if(sizeof($stmt2->fetchAll()) == 1)
			$sql .= "DELETE FROM waypoint_scripts WHERE id = {$wp_id};" . PHP_EOL;
	}
	
	$sql .= "DELETE FROM waypoint_data WHERE id = {$wp_id};" . PHP_EOL;
	
	return $sql;
}

function DeleteSunWaypointsForGuid($guid)
{
	global $conn, $sunWorld;
	
	$query = "SELECT path_id FROM {$sunWorld}.creature_addon WHERE spawnID = {$guid}";
	$stmt = $conn->query($query);
	$stmt->setFetchMode(PDO::FETCH_ASSOC);
	
	$sql = "";
	foreach($stmt->fetchAll() as $k => $v) {
		$path_id = $v['path_id'];
		if(!$path_id)
			continue;
		
		//Only delete if unique
		$query2 = "SELECT path_id FROM {$sunWorld}.creature_addon WHERE path_id = {$path_id}";
		$stmt2 = $conn->query($query2);
		$stmt2->setFetchMode(PDO::FETCH_ASSOC);
		if(sizeof($stmt2->fetchAll()) == 1)
			$sql .= DeleteSunWaypoints($path_id);
	}
	return $sql;
}

$deletedSunCreatures = [];

function DeleteSunCreatureSpawnID($guid)
{
	global $conn, $sunWorld, $deletedSunCreatures;
	
	if(in_array($guid, $deletedSunCreatures))
		return; //already deleted this creature

	array_push($deletedSunCreatures, $guid);
	
	$sql = "DELETE ce, c1, c2, sg FROM creature_entry ce " .
				"LEFT JOIN conditions c1 ON c1.ConditionValue3 = {$guid} AND c1.ConditionTypeOrReference = 31 " .
				"LEFT JOIN conditions c2 ON c1.SourceEntry = -{$guid} AND c2.SourceTypeOrReferenceId = 22 " .
				"LEFT JOIN spawn_group sg ON sg.spawnID = {$guid} AND spawnType = 0 " .
				"WHERE ce.spawnID = {$guid};" . PHP_EOL;
	
	$sql .= DeleteSunWaypointsForGuid($guid);
	
	//warn smart scripts references removal
	$query = "SELECT * FROM {$sunWorld}.smart_scripts WHERE entryorguid = -{$guid} AND source_type = 0";
	$stmt = $conn->query($query);
	$stmt->setFetchMode(PDO::FETCH_ASSOC);
	foreach($stmt->fetchAll() as $v) {
		echo "WARNING: Deleting a creature with a per guid smartscript: {$guid}. Smart scripts ref has been left as is." . PHP_EOL;
	}
	
	$query = "SELECT * FROM {$sunWorld}.smart_scripts WHERE target_param1 = {$guid} AND target_type = 10";
	$stmt = $conn->query($query);
	$stmt->setFetchMode(PDO::FETCH_ASSOC);
	foreach($stmt->fetchAll() as $v) {
		echo "WARNING: Deleting creature {$guid} targeted by a smartscript ({$v['entryorguid']}, {$v['id']}). Smart scripts ref has been left as is." . PHP_EOL;
	}
	
	return $sql;
}

function DeleteSunCreature($id)
{
	global $conn, $sunWorld;
	
	$query = "SELECT spawnID FROM {$sunWorld}.creature_entry WHERE entry = {$id}";
	$stmt = $conn->query($query);
	$stmt->setFetchMode(PDO::FETCH_ASSOC);

	$sql = "";
	foreach($stmt->fetchAll() as $k => $v) {
		$spawnID = $v['spawnID'];
		$sql .= DeleteSunCreatureSpawnID($spawnID);
	}
		
	return $sql;
}

function ImportSpawnGroup($guid)
{
	global $conn, $tcWorld;
	
	$query = "SELECT * FROM {$tcWorld}.spawn_group WHERE spawnId = {$guid} AND spawnType = 0";
	$stmt = $conn->query($query);
	$stmt->setFetchMode(PDO::FETCH_ASSOC);

	$sql = "";
	
	foreach($stmt->fetchAll() as $k => $v) {
		$groupId = $v['groupId'];
		if(!ConvertSpawnGroup($groupId, $guid)) //may change groupId
			continue;
		
		$sql .= "INSERT INTO spawn_group VALUES ({$groupId}, 0, {$guid});" . PHP_EOL;
	}
		
	return $sql;
}

function ImportWaypointsOnly($v)
{
	global $conn, $sunWorld;
	
	$guid = $v['guid'];
	$pathID = $v['path_id'];
	if(!$pathID)
	{
		echo "ERROR: Trying to import waypoints for creature {$v['guid']} which has no path_id" . PHP_EOL;
		exit(1);
	}
	
	$sql = "";
	
	//delete old if any waypoints
	$sql .= DeleteSunWaypointsForGuid($guid);
	$sql .= ImportWaypoints($guid, $pathID); //$pathID might be changed here
	
	$sql .= "INSERT INTO creature_addon (spawnID, path_id) VALUES({$guid}, {$pathID}) ON DUPLICATE KEY UPDATE path_id = {$pathID};" . PHP_EOL;

	ImportFormation($v);
	
	return $sql;
}

function WarnPool(&$v)
{
	global $conn, $tcWorld;
	
	$guid = $v['guid'];
	$query = "SELECT * FROM {$tcWorld}.pool_creature WHERE guid = {$guid}";
	$stmt = $conn->query($query);
	$stmt->setFetchMode(PDO::FETCH_ASSOC);
	$result = $stmt->fetch();
	if($result)
		echo "WARNING: Creature {$v['guid']} is part of pool {$result['pool_entry']}" . PHP_EOL;
}

$importedCreatures = [ ];

function Import(&$v, $patch_min = 0, $patch_max = 10)
{
	global $importedCreatures;
	
	$tcGUID = $v['guid'];
	
	if(in_array($tcGUID, $importedCreatures))
		return; //already imported this creature

	array_push($importedCreatures, $tcGUID);

	$sql = "INSERT INTO creature_entry (spawnID, entry) VALUES ({$tcGUID}, {$v['id']});" . PHP_EOL;
	$sql .= "INSERT INTO creature (spawnID, map, spawnMask, modelid, equipment_id, position_x, position_y, position_z, orientation, spawntimesecs, spawndist, currentwaypoint, curhealth, curmana, MovementType, unit_flags, patch_min, patch_max) VALUES ({$tcGUID}, {$v['map']}, {$v['spawnMask']}, {$v['modelid']}, {$v['equipment_id']}, {$v['position_x']}, {$v['position_y']},  {$v['position_z']}, {$v['orientation']}, {$v['spawntimesecs']}, {$v['spawndist']}, {$v['currentwaypoint']}, {$v['curhealth']}, {$v['curmana']}, {$v['MovementType']}, {$v['unit_flags']}, {$patch_min}, {$patch_max});" . PHP_EOL;
	if($v['addon_guid'] != 0)
	{
		$pathID = $v['path_id'];
		if($pathID != 0)
			$sql .= ImportWaypoints($tcGUID, $pathID, false); //$pathID might be changed here
		else
			$pathID = "NULL";

		$auras = $v['auras'] ? $v['auras'] : "NULL";
		$sql .= "INSERT INTO creature_addon (spawnID, path_id, mount, bytes0, bytes1, bytes2, emote, auras) VALUES ({$tcGUID}, {$pathID}, {$v['mount']}, 0, {$v['bytes1']}, {$v['bytes2']}, {$v['emote']}, {$auras});" . PHP_EOL;
	}
	if($v['eventEntry'] != 0)
		if($sunEvent = ConvertGameEventId($v['eventEntry']))
			$sql .= "INSERT INTO game_event_creature (guid, event) VALUES ({$tcGUID}, {$sunEvent});" . PHP_EOL;

	$sql .= ImportSpawnGroup($tcGUID);
	ImportFormation($v);
	WarnPool($v);

	return $sql;
}

$importedFormations = [ ]; //by leader guid
$importFormations = "";

function ImportFormation(&$v)
{
	global $conn, $tcWorld, $importFormations, $importedFormations;
	
	$query = "SELECT * FROM {$tcWorld}.creature_formations WHERE memberGUID = {$v['guid']}";
	$stmt = $conn->query($query);
	$stmt->setFetchMode(PDO::FETCH_ASSOC);
	$results = $stmt->fetch();
	if(!$results)
		return; //no formation found
	
	$leaderGUID = $results["leaderGUID"];
		
	if(in_array($leaderGUID, $importedFormations))
		return; //already imported this creature

	array_push($importedFormations, $leaderGUID);
	
	//Import all formations
	$query2 = "SELECT * FROM {$tcWorld}.creature_formations WHERE leaderGUID = {$leaderGUID}";
	$stmt2 = $conn->query($query2);
	$stmt2->setFetchMode(PDO::FETCH_ASSOC);
	foreach($stmt2->fetchAll() as $k => $v2) {
		$leaderGUID = $v2["leaderGUID"];
		$memberGUID = $v2["memberGUID"];
		$groupAI = 2; //alway 2, we don't use the same AI system than TC
		$angle = deg2rad($v2['angle']); //TC has degree, Sun has radian
		if($leaderGUID == $memberGUID)
			$leaderGUID = "NULL"; //special on SUN as well
		
		$importFormations .= "({$leaderGUID}, {$memberGUID}, {$v2['dist']}, {$angle}, {$groupAI}, 1, 0),".PHP_EOL;
	}
}

function CheckSunCreatureExists($spawnID)
{
	global $conn, $sunWorld, $importedCreatures;
	
	if(in_array($spawnID, $importedCreatures))
		return true;
	
	$query = "SELECT 1 FROM {$sunWorld}.creature WHERE spawnID = {$spawnID}";
	$stmt = $conn->query($query);
	$stmt->setFetchMode(PDO::FETCH_ASSOC);
	$results = $stmt->fetch();
	if(!$results)
		return false;
	
	return true;
}

function WarnMissingFormationCreature($spawnID, $leaderGUID)
{
	echo "Creature $spawnID does not exists in sun db but is required by formation $leaderGUID, need db fix" . PHP_EOL;
	exit(1);
}

function CheckFormations()
{
	global $conn, $tcWorld, $importedFormations;
	
	foreach($importedFormations as $leaderGUID) {
		if(!CheckSunCreatureExists($leaderGUID))
			WarnMissingFormationCreature($leaderGUID, $leaderGUID);
		
		$query = "SELECT memberGUID FROM {$tcWorld}.creature_formations WHERE leaderGUID = {$leaderGUID} AND leaderGUID != memberGUID";
		$stmt = $conn->query($query);
		$stmt->setFetchMode(PDO::FETCH_ASSOC);
		foreach($stmt->fetchAll() as $v)
			if(!CheckSunCreatureExists($v['memberGUID']))
				WarnMissingFormationCreature($v['memberGUID'], $leaderGUID);
	}
}

function ImportMoveUniqueGUID(&$v)
{
	$creature_id = $v['id'];
	$sql = DeleteSunCreature($creature_id);
	$sql .= Import($v);
	
	return $sql;
}

$replaceAllDone = [];
	
function ImportReplaceAll(&$v)
{
	global $conn, $replaceAllDone, $masterQuery, $tcWorld;
	
	$creature_id = $v['id'];
	if(in_array($creature_id, $replaceAllDone))
	{
		assert($v['import'] == "REPLACE_ALL");
		return "-- Already batch replaced" . PHP_EOL; //already mass replaced this creature
	}
		
	array_push($replaceAllDone, $creature_id);
				
	$creature_id = $v['id'];
	$sql = DeleteSunCreature($creature_id);
	
	//import all creatures
	$stmt = $conn->query($masterQuery . " WHERE tc.id = {$creature_id}");
	$stmt->setFetchMode(PDO::FETCH_ASSOC);

	foreach($stmt->fetchAll() as $k => $v) {
		$sql .= Import($v);
	}
	
	return $sql;
}

function ImportLK(&$v)
{
	$first_LK_patch = 5;
	$max_patch = 10;
	
	return Import($v, $first_LK_patch, $max_patch);
}

$file = fopen(__DIR__."/creature.sql", "w");
if (!$file)
	die("Couldn't open creature.txt");

$sql = "";

// MAIN
$stmt = $conn->query($masterQuery . " WHERE tc.import IS NOT NULL AND tc.import != 'IGNORE'");
$stmt->setFetchMode(PDO::FETCH_ASSOC);

foreach($stmt->fetchAll() as $k => $v) {
	$sql .= "-- Importing creature with guid {$v['guid']} with import type {$v['import']}" . PHP_EOL;
	switch($v["import"])
	{
		case "MOVE_UNIQUE_IMPORT_WP": //Import the TC creatures and completely override the TC one". Also import WP, game_event and formation if any
			$sql .= ImportMoveUniqueGUID($v);
			break;
		case "IMPORT": //Straight import the creature, no delete of anything. 
			$sql .= Import($v);
			break;
		case "LK_ONLY": //IMPORT but for LK patch only
			$sql .= ImportLK($v);
			break;
		case "UPDATE_SPAWNID": //Keep Sun data but update the spawnid to match TC
			$sql .= UpdateSpawnId($v);
			break;
		case "REPLACE_ALL": //Remove all creatures with this id from sun, and replace with all creatures from TC with this id
			$sql .= ImportReplaceAll($v);
			break;
		case "IMPORT_WP": //Import waypoints + formation member if any (will warn if not found on sun)
			$sql .= ImportWaypointsOnly($v);
			break;
		default:
			echo "ERROR: Non handled enum value: " . $v["import"] . PHP_EOL;
			exit(1);
	}
	$sql .= PHP_EOL;
}

$sql .= "-- Formations..." . PHP_EOL;
if($importFormations != "")
{
	CheckFormations();
	$sql .= "REPLACE INTO creature_formations VALUES " . PHP_EOL . substr_replace($importFormations, "", -3) . ';' . PHP_EOL;
}

if(!empty($deleteSun)) {
	$sql .= PHP_EOL . "-- Additional static deletes..." . PHP_EOL;
	foreach ($deleteSun as $v) {
		$sql .= DeleteSunCreatureSpawnID($v);
	}
}

fwrite($file, $sql);
fclose($file);

echo "Done" . PHP_EOL;