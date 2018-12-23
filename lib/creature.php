<?php

include_once(__DIR__ . '/helpers.php');
include_once(__DIR__ . '/common.php');
include_once(__DIR__ . '/smartai.php');

function DeleteSunCreatureSpawn($spawn_id)
{
	global $sunStore;
	
	if(CheckAlreadyImported($spawn_id))
		return "";
	
	$sql = "DELETE ce, c1, c2, sg FROM creature_entry ce " .
				"LEFT JOIN conditions c1 ON c1.ConditionValue3 = {$spawn_id} AND c1.ConditionTypeOrReference = 31 " .
				"LEFT JOIN conditions c2 ON c1.SourceEntry = -{$spawn_id} AND c2.SourceTypeOrReferenceId = 22 " .
				"LEFT JOIN spawn_group sg ON sg.spawnID = {$spawn_id} AND spawnType = 0 " .
				"WHERE ce.spawnID = {$spawn_id};" . PHP_EOL;
			
			
	//warn smart scripts references removal
	$results = FindAll($sunStore->smart_scripts, "entryorguid", -$spawn_id);
	foreach($results as $result) {
		if($result->source_type != SmartSourceType::creature)
			continue;
		
		echo "WARNING: Deleting a creature with a per guid smartscript: {$spawn_id}. Smart scripts ref has been left as is." . PHP_EOL;
	}
	
	$results = FindAll($sunStore->smart_scripts, "target_param1", $spawn_id);
	foreach($results as $result) {
		if($result->target_type != SmartTarget::CREATURE_GUID)
			continue;
		
		echo "WARNING: Deleting creature {$guid} targeted by a smartscript ({$result['entryorguid']}, {$result['id']}). Smart scripts ref has been left as is." . PHP_EOL;
	}
	
	return $sql;
}

function DeleteSunCreature($creature_id, array $not_in)
{
	global $sunStore;
	
	if(CheckAlreadyImported($creature_id))
		return "";
	
	$sql = "";
	$results = FindAll($sunStore->creature_entry, "entry", $creature_id);
	foreach($results as $result) {
		if(!in_array($result->spawnID, $not_in))
			$sql .= DeleteSunCreatureSpawn($result->spawnID);
	}
	
	return $sql;
}

function ImportWaypointScripts($action_id)
{
	echo "NYI". PHP_EOL;
	assert(false);
	exit(1);
}

function ImportWaypoints($guid, $path_id, $includeMovementTypeUpdate = true)
{
	echo "NYI". PHP_EOL;
	assert(false);
	exit(1);
}

function ImportSpawnGroup($guid)
{
	global $tcStore, $sunStore;
	
	if(CheckAlreadyImported($guid))
		return "";
	
	$sql = "";
	$results = FindAll($tcStore->spawn_group, "spawnId", $guid);
	foreach($results as $result) {
		if($result->spawnType != 0) //creature type
			continue;
			
		$groupId = $result->groupId;
		if(!ConvertSpawnGroup($groupId, $guid)) //may change groupId
			continue;
			
		$sun_spawn_group = $result; //copy
		$sun_spawn_group->groupId = $groupId;
		
		array_push($sunStore->spawn_group, $sun_spawn_group);
		$sql .= WriteObject("spawn_group", $sun_spawn_group);
	}
	return $sql;
}

function ImportFormation($guid)
{
	global $tcStore, $sunStore;
	
	if(!array_key_exists($guid, $tcStore->creature_formations))
		return;
	
	$tc_formation = $tcStore->creature_formations[$guid];
	$leaderGUID = $tc_formation->leaderGUID;
	if(!array_key_exists($leaderGUID, $tcStore->creature_formations))
		return;
	
	$results = FindAll($tcStore->creature_formations, "leaderGUID", $leaderGUID);
	foreach($results as $tc_formation) {
		$sun_formation = new stdClass; //anonymous object
		$sun_formation->leaderGUID = $leaderGUID;
		$sun_formation->memberGUID = $tc_formation->memberGUID;
		$sun_formation->groupAI = 2;//alway 2, we don't use the same AI system than TC
		$sun_formation->angle = deg2rad($tc_formation->angle); //TC has degree, Sun has radian
		
		if($sun_formation->leaderGUID == $sun_formation->memberGUID)
			$sun_formation->leaderGUID = "NULL"; //special on SUN as well
		
		$sunStore->creature_formations[$tc_formation->memberGUID] = $sun_formation;
		$sql .= WriteObject("creature_formations", $sun_formation);
	}
	
	return $sql;
}

function WarnPool($guid)
{
	global $tcStore;
	
	if(array_key_exists($guid, $tcStore->pool_creature)) {
		$pool_entry = $tcStore->pool_creature->pool_entry;
		echo "WARNING: Imported creature guid {$guid} is part of pool {$pool_entry}" . PHP_EOL;
	}
}

function ImportTCCreature($guid, $patch_min = 0, $patch_max = 10)
{
	global $tcStore, $sunStore;
	
	if(CheckAlreadyImported($guid))
		return "";
	
	if(array_key_exists($guid, $sunStore->creature)) {
		echo "ERROR: Trying to import creature with guid {$guid} but creature already exists" . PHP_EOL;
		assert(false);
		exit(1);
	}
	
	$tc_creature = &$tcStore->creature[$guid];
	$tc_creature_addon = null;
	if(array_key_exists($guid, $tcStore->creature_addon)) {
		$tc_creature_addon = &$tcStore->creature_addon[$guid];
	}
	
	$sql = "";
	
	//create creature_entry
	$sun_creature_entry = new stdClass; //anonymous object
	$sun_creature_entry->spawnID = $guid;
	$sun_creature_entry->entry = $tc_creature->id;
	
	array_push($sunStore->creature_entry, $sun_creature_entry);
	$sql .= WriteObject("creature_entry", $sun_creature_entry);
	
	//create creature
	$sun_creature = new stdClass;
	$sun_creature->spawnID = $guid;
	$sun_creature->map = $tc_creature->map;
	$sun_creature->spawnMask = $tc_creature->spawnMask;
	$sun_creature->modelid = $tc_creature->modelid;
	$sun_creature->equipment_id = $tc_creature->equipment_id; //import equip ID?
	$sun_creature->position_x = $tc_creature->position_x;
	$sun_creature->position_y = $tc_creature->position_y;
	$sun_creature->position_z = $tc_creature->position_z;
	$sun_creature->orientation= $tc_creature->orientation;
	$sun_creature->spawntimesecs = $tc_creature->spawntimesecs;
	$sun_creature->spawndist = $tc_creature->spawndist;
	$sun_creature->currentwaypoint = $tc_creature->currentwaypoint;
	$sun_creature->curhealth = $tc_creature->curhealth;
	$sun_creature->curmana = $tc_creature->curmana;
	$sun_creature->MovementType = $tc_creature->MovementType;
	$sun_creature->unit_flags = $tc_creature->unit_flags;
	$sun_creature->pool_id = 0;
	$sun_creature->patch_min= $patch_min;
	$sun_creature->patch_max = $patch_max;
	
	$sunStore->creature[$guid] = $sun_creature;
	$sql .= WriteObject("creature", $sun_creature);
	
	//creature addon
	if($tc_creature_addon) {
		$path_id = $tc_creature_addon->path_id;
		if($path_id) {
			$sql .= ImportWaypoints($guid, $path_id, false); //$pathID might be changed here
		} else {
			$path_id = "NULL";
		}
		
		$sun_creature_addon = new stdClass;
		$sun_creature_addon->spawnID = $guid;
		$sun_creature_addon->path_id = $path_id;
		$sun_creature_addon->mount = $tc_creature_addon->mount;
		$sun_creature_addon->bytes0 = 0;
		$sun_creature_addon->bytes1 = $tc_creature_addon->bytes1;
		$sun_creature_addon->bytes2 = $tc_creature_addon->bytes2;
		$sun_creature_addon->emote = $tc_creature_addon->emote;
		$sun_creature_addon->auras = $tc_creature_addon->auras ? $tc_creature_addon->auras : "NULL";
		
		$sunStore->creature_addon[$guid] = $sun_creature_addon;
		$sql .= WriteObject("creature_addon", $sun_creature_addon);
	}
	
	//game event creature
	if(array_key_exists($guid, $tcStore->game_event_creature)) {
		if($tc_gec = &$tcStore->game_event_creature[$guid])
			if($sunEvent = ConvertGameEventId($v['eventEntry'])) {
				$sun_game_event_creature = new stdClass;
				$sun_game_event_creature->guid = $guid;
				$sun_game_event_creature->event = $sunEvent;
				
				$sunStore->game_event_creature[$guid] = $sun_game_event_creature;
				$sql .= WriteObject("game_event_creature", $sun_game_event_creature);
			}
	}
	
	$sql .= ImportSpawnGroup($guid);
	$sql .= ImportFormation($guid);
	WarnPool($guid);
	
	return $sql;
}

function CreateReplaceAllCreature($creature_id)
{
	global $tcStore, $sunStore;
	
	if(CheckAlreadyImported($creature_id))
		return "";
	
	$sql = "";
	
	$results = FindAll($tcStore->creature, "id", $creature_id);
	if(empty($results)) {
		echo "ERROR: Failed to find any TC creature with id {$creature_id}" . PHP_EOL;
		assert(false);
		exit(1);
	}
					
	$tc_guids = [];
	foreach($results as $tc_creature) {
		array_push($tc_guids, $tc_creature->guid);
		if(!array_key_exists($tc_creature->guid, $sunStore->creature))
			$sql .= ImportTCCreature($tc_creature->guid);
	}
	$sql .= DeleteSunCreature($creature_id, $tc_guids);
	return $sql;
}