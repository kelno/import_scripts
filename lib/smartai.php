<?php

include_once(__DIR__ . '/common.php');
include_once(__DIR__ . '/creature.php');

abstract class SmartSourceType
{
	const creature = 0;
	const gameobject = 1;
	const areatrigger = 2;
	const timedactionlist = 9;
}

abstract class SmartEvent
{
	const UPDATE_IC                   = 0;    
	const UPDATE_OOC                  = 1;    
	const HEALT_PCT                   = 2;    
	const MANA_PCT                    = 3;    
	const AGGRO                       = 4;    
	const KILL                        = 5;    
	const DEATH                       = 6;    
	const EVADE                       = 7;    
	const SPELLHIT                    = 8;    
	const RANGE                       = 9;    
	const OOC_LOS                     = 10;   
	const RESPAWN                     = 11;   
	const TARGET_HEALTH_PCT           = 12;   
	const VICTIM_CASTING              = 13;   
	const FRIENDLY_HEALTH             = 14;   
	const FRIENDLY_IS_CC              = 15;   
	const FRIENDLY_MISSING_BUFF       = 16;   
	const SUMMONED_UNIT               = 17;   
	const TARGET_MANA_PCT             = 18;   
	const ACCEPTED_QUEST              = 19;   
	const REWARD_QUEST                = 20;   
	const REACHED_HOME                = 21;   
	const RECEIVE_EMOTE               = 22;   
	const HAS_AURA                    = 23;   
	const TARGET_BUFFED               = 24;   
	const RESET                       = 25;   
	const IC_LOS                      = 26;   
	const PASSENGER_BOARDED           = 27;   
	const PASSENGER_REMOVED           = 28;   
	const CHARMED                     = 29;   
	const CHARMED_TARGET              = 30;   
	const SPELLHIT_TARGET             = 31;   
	const DAMAGED                     = 32;   
	const DAMAGED_TARGET              = 33;   
	const MOVEMENTINFORM              = 34;   
	const SUMMON_DESPAWNED            = 35;   
	const CORPSE_REMOVED              = 36;   
	const AI_INIT                     = 37;   
	const DATA_SET                    = 38;   
	const WAYPOINT_START              = 39;   
	const WAYPOINT_REACHED            = 40;   
	const TRANSPORT_ADDPLAYER         = 41;   
	const TRANSPORT_ADDCREATURE       = 42;   
	const TRANSPORT_REMOVE_PLAYER     = 43;   
	const TRANSPORT_RELOCATE          = 44;   
	const INSTANCE_PLAYER_ENTER       = 45;   
	const AREATRIGGER_ONTRIGGER       = 46;   
	const QUEST_ACCEPTED              = 47; 
	const QUEST_OBJ_COPLETETION       = 48;   
	const QUEST_COMPLETION            = 49;   
	const QUEST_REWARDED              = 50; 
	const QUEST_FAIL                  = 51;   
	const TEXT_OVER                   = 52;   
	const RECEIVE_HEAL                = 53;   
	const JUST_SUMMONED               = 54;   
	const WAYPOINT_PAUSED             = 55;   
	const WAYPOINT_RESUMED            = 56;   
	const WAYPOINT_STOPPED            = 57;   
	const WAYPOINT_ENDED              = 58;   
	const TIMED_EVENT_TRIGGERED       = 59;   
	const UPDATE                      = 60;   
	const LINK                        = 61;   
	const GOSSIP_SELECT               = 62;   
	const JUST_CREATED                = 63;   
	const GOSSIP_HELLO                = 64;   
	const FOLLOW_COMPLETED            = 65;   
	const EVENT_PHASE_CHANGE          = 66;   
	const IS_BEHIND_TARGET            = 67;   
	const GAME_EVENT_START            = 68;   
	const GAME_EVENT_END              = 69;   
	const GO_STATE_CHANGED            = 70;   
	const GO_EVENT_INFORM             = 71;   
	const ACTION_DONE                 = 72;   
	const ON_SPELLCLICK               = 73;   
	const FRIENDLY_HEALTH_PCT         = 74;   
	const DISTANCE_CREATURE           = 75;   
	const DISTANCE_GAMEOBJECT         = 76;   
	const COUNTER_SET                 = 77;    
}

abstract class SmartAction
{
	const NONE                               = 0;  
	const TALK                               = 1;  
	const SET_FACTION                        = 2;  
	const MORPH_TO_ENTRY_OR_MODEL            = 3;  
	const SOUND                              = 4;  
	const PLAY_EMOTE                         = 5;  
	const FAIL_QUEST                         = 6;  
	const ADD_QUEST                          = 7;  
	const SET_REACT_STATE                    = 8;  
	const ACTIVATE_GOBJECT                   = 9;  
	const RANDOM_EMOTE                       = 10; 
	const CAST                               = 11; 
	const SUMMON_CREATURE                    = 12; 
	const THREAT_SINGLE_PCT                  = 13; 
	const THREAT_ALL_PCT                     = 14; 
	const CALL_AREAEXPLOREDOREVENTHAPPENS    = 15; 
	const RESERVED_16                        = 16; 
	const SET_EMOTE_STATE                    = 17; 
	const SET_UNIT_FLAG                      = 18; 
	const REMOVE_UNIT_FLAG                   = 19; 
	const AUTO_ATTACK                        = 20; 
	const ALLOW_COMBAT_MOVEMENT              = 21; 
	const SET_EVENT_PHASE                    = 22; 
	const INC_EVENT_PHASE                    = 23; 
	const EVADE                              = 24; 
	const FLEE_FOR_ASSIST                    = 25; 
	const CALL_GROUPEVENTHAPPENS             = 26; 
	const COMBAT_STOP                        = 27; 
	const REMOVEAURASFROMSPELL               = 28; 
	const FOLLOW                             = 29; 
	const RANDOM_PHASE                       = 30; 
	const RANDOM_PHASE_RANGE                 = 31; 
	const RESET_GOBJECT                      = 32; 
	const CALL_KILLEDMONSTER                 = 33; 
	const SET_INST_DATA                      = 34; 
	const SET_INST_DATA64                    = 35; 
	const UPDATE_TEMPLATE                    = 36; 
	const DIE                                = 37; 
	const SET_IN_COMBAT_WITH_ZONE            = 38; 
	const CALL_FOR_HELP                      = 39; 
	const SET_SHEATH                         = 40; 
	const FORCE_DESPAWN                      = 41; 
	const SET_INVINCIBILITY_HP_LEVEL         = 42; 
	const MOUNT_TO_ENTRY_OR_MODEL            = 43; 
	const SET_INGAME_PHASE_MASK              = 44; 
	const SET_DATA                           = 45; 
	const MOVE_FORWARD                       = 46; 
	const SET_VISIBILITY                     = 47; 
	const SET_ACTIVE                         = 48; 
	const ATTACK_START                       = 49; 
	const SUMMON_GO                          = 50; 
	const KILL_UNIT                          = 51; 
	const ACTIVATE_TAXI                      = 52; 
	const WP_START                           = 53; 
	const WP_PAUSE                           = 54; 
	const WP_STOP                            = 55; 
	const ADD_ITEM                           = 56; 
	const REMOVE_ITEM                        = 57; 
	const INSTALL_AI_TEMPLATE                = 58; 
	const SET_RUN                            = 59; 
	const SET_DISABLE_GRAVITY                = 60; 
	const SET_SWIM                           = 61; 
	const TELEPORT                           = 62; 
	const SET_COUNTER                        = 63; 
	const STORE_TARGET_LIST                  = 64; 
	const WP_RESUME                          = 65; 
	const SET_ORIENTATION                    = 66; 
	const CREATE_TIMED_EVENT                 = 67; 
	const PLAYMOVIE                          = 68; 
	const MOVE_TO_POS                        = 69; 
	const ENABLE_TEMP_GOBJ                   = 70; 
	const EQUIP                              = 71; 
	const CLOSE_GOSSIP                       = 72; 
	const TRIGGER_TIMED_EVENT                = 73; 
	const REMOVE_TIMED_EVENT                 = 74; 
	const ADD_AURA                           = 75; 
	const OVERRIDE_SCRIPT_BASE_OBJECT        = 76; 
	const RESET_SCRIPT_BASE_OBJECT           = 77; 
	const CALL_SCRIPT_RESET                  = 78; 
	const SET_RANGED_MOVEMENT                = 79; 
	const CALL_TIMED_ACTIONLIST              = 80; 
	const SET_NPC_FLAG                       = 81; 
	const ADD_NPC_FLAG                       = 82; 
	const REMOVE_NPC_FLAG                    = 83; 
	const SIMPLE_TALK                        = 84; 
	const SELF_CAST                          = 85; 
	const CROSS_CAST                         = 86; 
	const CALL_RANDOM_TIMED_ACTIONLIST       = 87; 
	const CALL_RANDOM_RANGE_TIMED_ACTIONLIST = 88; 
	const RANDOM_MOVE                        = 89; 
	const SET_UNIT_FIELD_BYTES_1             = 90; 
	const REMOVE_UNIT_FIELD_BYTES_1          = 91; 
	const INTERRUPT_SPELL                    = 92;
	const SEND_GO_CUSTOM_ANIM                = 93; 
	const SET_DYNAMIC_FLAG                   = 94; 
	const ADD_DYNAMIC_FLAG                   = 95; 
	const REMOVE_DYNAMIC_FLAG                = 96; 
	const JUMP_TO_POS                        = 97; 
	const SEND_GOSSIP_MENU                   = 98; 
	const GO_SET_LOOT_STATE                  = 99; 
	const SEND_TARGET_TO_TARGET              = 100;
	const SET_HOME_POS                       = 101;
	const SET_HEALTH_REGEN                   = 102;
	const SET_ROOT                           = 103;
	const SET_GO_FLAG                        = 104;
	const ADD_GO_FLAG                        = 105;
	const REMOVE_GO_FLAG                     = 106;
	const SUMMON_CREATURE_GROUP              = 107;
	const SET_POWER                          = 108;
	const ADD_POWER                          = 109;
	const REMOVE_POWER                       = 110;
	const GAME_EVENT_STOP                    = 111;
	const GAME_EVENT_START                   = 112;
	const START_CLOSEST_WAYPOINT             = 113;
	const MOVE_OFFSET                        = 114;
	const RANDOM_SOUND                       = 115;
	const SET_CORPSE_DELAY                   = 116;
	const DISABLE_EVADE                      = 117;
	const GO_SET_GO_STATE                    = 118;
	const SET_CAN_FLY                        = 119;
	const REMOVE_AURAS_BY_TYPE               = 120;
	const SET_SIGHT_DIST                     = 121;
	const FLEE                               = 122;
	const ADD_THREAT                         = 123;
	const LOAD_EQUIPMENT                     = 124;
	const TRIGGER_RANDOM_TIMED_EVENT         = 125;
	const REMOVE_ALL_GAMEOBJECTS             = 126;
	const REMOVE_MOVEMENT                    = 127;
	const PLAY_ANIMKIT                       = 128;
	const SCENE_PLAY                         = 129;
	const SCENE_CANCEL                       = 130;
	const SPAWN_SPAWNGROUP                   = 131;
	const DESPAWN_SPAWNGROUP                 = 132;
	const RESPAWN_BY_SPAWNID                 = 133;
	const INVOKER_CAST                       = 134;
}

abstract class SmartTarget
{
	const NONE                           = 0; 
	const SELF                           = 1; 
	const VICTIM                         = 2; 
	const HOSTILE_SECOND_AGGRO           = 3; 
	const HOSTILE_LAST_AGGRO             = 4; 
	const HOSTILE_RANDOM                 = 5; 
	const HOSTILE_RANDOM_NOT_TOP         = 6; 
	const ACTION_INVOKER                 = 7; 
	const POSITION                       = 8; 
	const CREATURE_RANGE                 = 9; 
	const CREATURE_GUID                  = 10;
	const CREATURE_DISTANCE              = 11;
	const STORED                         = 12;
	const GAMEOBJECT_RANGE               = 13;
	const GAMEOBJECT_GUID                = 14;
	const GAMEOBJECT_DISTANCE            = 15;
	const INVOKER_PARTY                  = 16;
	const PLAYER_RANGE                   = 17;
	const PLAYER_DISTANCE                = 18;
	const CLOSEST_CREATURE               = 19;
	const CLOSEST_GAMEOBJECT             = 20;
	const CLOSEST_PLAYER                 = 21;
	const ACTION_INVOKER_VEHICLE         = 22;
	const OWNER_OR_SUMMONER              = 23;
	const THREAT_LIST                    = 24;
	const CLOSEST_ENEMY                  = 25;
	const CLOSEST_FRIENDLY               = 26;
	const LOOT_RECIPIENTS                = 27;
	const FARTHEST                       = 28;
	const VEHICLE_ACCESSORY              = 29;
}
	
function FindAllSmart($sun, $entryorguid, $source_type)
{
	global $tcStore, $sunStore;
	
	$results = [];
	
	foreach(($sun ? $sunStore->smart_scripts : $tcStore->smart_scripts) as $smart_script) {
		if($smart_script->entryorguid == $entryorguid && $smart_script->source_type == $source_type)
			array_push($results, $smart_script);
	}
	return $results;
}

function DeleteAllSmart($entryorguid, $source_type)
{
	global $sunStore, $file;
	
	if(CheckAlreadyImported($entryorguid + $source_type << 28)) //max entry is 30.501.000 (smaller number with 28 bits shift is 268.435.456)
		return;
	
	$sql = "DELETE FROM smart_scripts WHERE entryorguid = {$entryorguid} AND source_type = {$source_type};" . PHP_EOL;
	fwrite($file, $sql);
	
	$results = FindAllSmart(true, $entryorguid, $source_type);
	foreach($results as $sun_smart) {
		switch($sun_smart->action_type)
		{
			case SmartAction::CALL_TIMED_ACTIONLIST:
				DeleteAllSmart($sun_smart->action_param1, SmartSourceType::timedactionlist);
				break;
		}
	}
	
	//Unset in store
	foreach($sunStore->smart_scripts as $k => $smart_script) {
		if($smart_script->entryorguid == $entryorguid && $smart_script->source_type = $source_type)
			unset($sunStore->smart_scripts[$k]);
	}
}

function CreateCreatureText($tc_entry)
{
	global $tcStore, $sunStore, $file;
	
	if(CheckAlreadyImported($tc_entry)) {
		fwrite($file, "-- Creature text {$tc_entry} is already imported" . PHP_EOL);
		return;
	}
	
	$results = FindAll($tcStore->creature_text, "CreatureID", $tc_entry);
	if(empty($results)) {
		echo "ERROR: Could not find TC creature_text for creature id {$tc_entry}" . PHP_EOL;
		assert(false);
		exit(1);
	}
	
	$sql = "DELETE FROM creature_text WHERE CreatureID = {$tc_entry};" . PHP_EOL;
	fwrite($file, $sql);
	
	foreach($results as $text_entry) {
		if($broadcast_id = $text_entry->BroadcastTextId)
			CheckBroadcast($broadcast_id);
		
		array_push($sunStore->creature_text, $text_entry);
		WriteObject("creature_text", $text_entry); 
	}
}

function CreateSmartConditions($tc_entry, $source_type)
{
	global $sunStore, $tcStore, $file;
	
	if(CheckAlreadyImported($tc_entry + $source_type << 28)) { //max entry is 30.501.000 (smaller number with 28 bits shift is 268.435.456)
		fwrite($file, "-- Smart condition {$tc_entry} {$source_type} is already imported" . PHP_EOL);
		return;
	}
	
	static $CONDITION_SOURCE_TYPE_SMART_EVENT = 22;
	
	foreach($tcStore->conditions as $tc_condition) {
		if($tc_condition->SourceTypeOrReferenceId != $CONDITION_SOURCE_TYPE_SMART_EVENT)
		   continue;
		   
		//SourceGroup == id, but we import all
		   
		if($tc_condition->SourceEntry != $tc_entry) 
		   continue;
	   
	    if($tc_condition->SourceId != $source_type) 
		   continue;
	   
		$sun_condition = $tc_condition; //copy
		$sun_condition->Comment = "(autoimported) " . $tc_condition->Comment;
		
		if($tc_condition->ConditionTypeOrReference == Conditions::CONDITION_ACTIVE_EVENT) {
			//echo "Convert event... " . $sun_condition->ConditionValue1 . PHP_EOL;
			$sun_condition->ConditionValue1 = ConvertGameEventId($tc_condition->ConditionValue1);
		}
			
		WriteObject("conditions", $sun_condition);
	}
}

function CreateWaypoints($path_id)
{
	global $tcStore, $sunStore, $file;
	
	if(CheckAlreadyImported($path_id)) {
		fwrite($file, "-- Smart Waypoints {$path_id} are already imported" . PHP_EOL);
		return;
	}
	
	CheckExists($sunStore->waypoints, $tcStore->waypoints, "entry", $path_id);
	
	$sql = "DELETE FROM waypoints WHERE entry = {$path_id};" . PHP_EOL;
	fwrite($file, $sql);
	RemoveAny($sunStore->waypoints, "entry", $path_id);
	
	$results = FindAll($tcStore->waypoints, "entry", $path_id);
	if(empty($results)) {
		echo "ERROR: Could not find TC waypoints {$path_id}" . PHP_EOL;
		assert(false);
		exit(1);
	}
	
	foreach($results as $tc_waypoint) {
		array_push($sunStore->waypoints, $tc_waypoint);
		WriteObject("waypoints", $tc_waypoint); 
	}
}

function CheckImportCreature($from_entry, $from_id, $creature_id) 
{
	global $tcStore, $sunStore, $file;
	
	if(CheckAlreadyImported($creature_id))
		return;
	
	if(!array_key_exists($creature_id, $tcStore->creature_template)) {
		echo "Smart TC {$from_entry} {$from_id} has SET_DATA on a non existing creature {$creature_id}" . PHP_EOL;
		assert(false);
		exit(1);
	}
	
	if($tcStore->creature_template[$creature_id]->AIName != "SmartAI") {
		echo "Smart TC {$from_entry} {$from_id} has SET_DATA on a non Smart creature {$creature_id}" . PHP_EOL;
		assert(false);
		exit(1);
	}
	echo "Smart TC {$from_entry} {$from_id} summon/targets creature id {$creature_id}, also importing it. "; //... continue this line later
	if(CheckIdentical($sunStore->smart_scripts, $tcStore->smart_scripts, "entryorguid", $creature_id)) {
		echo "Already identical, skipping" . PHP_EOL;
		fwrite($file, "-- SmartAI for creature {$creature_id} is already in db and identical" . PHP_EOL); //already imported
		return;
	}
	
	$sunAIName = $sunStore->creature_template[$creature_id]->AIName;
	$sunScriptName = $sunStore->creature_template[$creature_id]->ScriptName;
	
	if($sunAIName == "" && $sunScriptName == "")
		echo "(it currently has no script)" . PHP_EOL;
	else 
		echo "It currently had AIName '{$sunAIName}' and ScriptName '{$sunScriptName}'" . PHP_EOL;

	CreateSmartAI($creature_id, SmartSourceType::creature);
}

function CheckImportGameObject($from_entry, $from_id, $gob_id)
{
	global $tcStore, $sunStore, $file;
	
	if(!array_key_exists($gob_id, $tcStore->gameobject_template)) {
		echo "Smart TC {$from_entry} has SET_DATA on a non existing gameobject {$gob_id}" . PHP_EOL;
		assert(false);
		exit(1);
	}
	
	if($tcStore->gameobject_template[$gob_id]->AIName != "SmartAI") {
		echo "Smart TC {$from_entry}has SET_DATA on a non Smart gameobject {$gob_id}" . PHP_EOL;
		assert(false);
		exit(1);
	}
	
	echo "Smart TC {$from_entry} targets gameobject id {$gob_id}, also importing it. "; //... continue this line later
	
	if(CheckIdentical($sunStore->smart_scripts, $tcStore->smart_scripts, "entryorguid", $gob_id)) {
		echo "Already identical, skipping" . PHP_EOL;
		fwrite($file, "-- SmartAI for gob {$gob_id} is already in db and identical" . PHP_EOL); //already imported
		return;
	}
	
	$sunAIName = $sunStore->gameobject_template[$creature_id]->AIName;
	$sunScriptName = $sunStore->gameobject_template[$creature_id]->ScriptName;
	
	if($sunAIName == "" && $sunScriptName == "")
		echo "(it currently has no script)" . PHP_EOL;
	else 
		echo "It currently had AIName '{$sunAIName}' and ScriptName '{$sunScriptName}" . PHP_EOL;

	CreateSmartAI($gob_id, SmartSourceType::gameobject);
}

function CreateSmartAI($tc_entry, $source_type, $action_list_origin = 0)
{
	global $tcStore, $sunStore, $file;
	
	if(CheckAlreadyImported($tc_entry + $source_type << 28)) { //max entry is 30.501.000 (smaller number with 28 bits shift is 268.435.456)
		fwrite($file, "-- SmartAI {$tc_entry} {$source_type} is already imported" . PHP_EOL);
		return;
	}
	
	$sql = "";
	switch($source_type)
	{
		case SmartSourceType::creature:
			if(!$action_list_origin)
				$action_list_origin = $tc_entry;
			$sql .= "UPDATE creature_template SET ScriptName = '', AIName = 'SmartAI' WHERE entry = {$tc_entry};" . PHP_EOL;
			break;
		case SmartSourceType::gameobject:
			$sql .= "UPDATE gameobject_template SET ScriptName = '', AIName = 'SmartAI' WHERE entry = {$tc_entry};" . PHP_EOL;
			break;
		case SmartSourceType::areatrigger:
			$sql .= "UPDATE areatrigger_scripts SET ScriptName = 'SmartTrigger' WHERE entry = {$tc_entry};" . PHP_EOL;
			break;
		case SmartSourceType::timedactionlist:
			break; //nothing to do
		default:
			echo "Unknown source type {$source_type}" . PHP_EOL;
			assert(false);
			exit(1);
	}
	fwrite($file, $sql);
	DeleteAllSmart($tc_entry, $source_type);
	
	$results = FindAllSmart(false, $tc_entry, $source_type);
	if(empty($results)) {
		echo "ERROR: Failed to find TC SmartAI with entry {$tc_entry} and type {$source_type}" . PHP_EOL;
		assert(false);
		exit(1);
	}
	
	$sun_smart_entries = [];
	foreach($results as $smart_entry) {
		$sun_smart_entry = $smart_entry; //copy
		switch($smart_entry->event_type)
		{
			case SmartEvent::WAYPOINT_START:
			case SmartEvent::WAYPOINT_REACHED:
			case SmartEvent::WAYPOINT_PAUSED: 
			case SmartEvent::WAYPOINT_RESUMED:
			case SmartEvent::WAYPOINT_STOPPED:
			case SmartEvent::WAYPOINT_ENDED: 
				if($path_id = $smart_entry->event_param2)
					CreateWaypoints($path_id);
				break;
			case SmartEvent::GOSSIP_SELECT:
				if($tc_menu_id = $smart_entry->event_param1) {
					$sun_menu_id = CreateMenu($tc_menu_id);
					$sun_smart_entry->event_param1 = $sun_menu_id;
				}
				break;
			case SmartEvent::GAME_EVENT_START:
			case SmartEvent::GAME_EVENT_END:
				$event_id = $sun_smart_entry->event_param1;
				$sun_smart_entry->action_param1 = ConvertGameEventId($event_id);
				break;
			default:
				break;
		}
		
		switch($sun_smart_entry->action_type)
		{
			case SmartAction::TALK:
			case SmartAction::SIMPLE_TALK:
				CreateCreatureText($action_list_origin ? $action_list_origin : $tc_entry);
				break;
			case SmartAction::CALL_TIMED_ACTIONLIST:
				CreateSmartAI($sun_smart_entry->action_param1, SmartSourceType::timedactionlist, $action_list_origin);
				break;
			case SmartAction::CALL_RANDOM_TIMED_ACTIONLIST:			
				$SMART_AI_MAX_ACTION_PARAM = 6;
				for($i = 1; $i <= $SMART_AI_MAX_ACTION_PARAM; $i++) {
					$fieldName = "action_param" . $i;
					if($action_list = $sun_smart_entry->$fieldName)
						CreateSmartAI($action_list, SmartSourceType::timedactionlist, $action_list_origin);
				}
				break;
	        case SmartAction::CALL_RANDOM_RANGE_TIMED_ACTIONLIST:
				$min = $sun_smart_entry->action_param1;
				$max = $sun_smart_entry->action_param2;
				for($i = $min; $i <= $max; $i++) {
					CreateSmartAI($i, SmartSourceType::timedactionlist, $action_list_origin);
				}
				break;
			case SmartAction::SEND_GOSSIP_MENU:
				$sun_menu_id = CreateMenu($sun_smart_entry->action_param1); 
				$sun_smart_entry->action_param1 = $sun_menu_id;
				break;
			case SmartAction::WP_START:
				$path_id = $sun_smart_entry->action_param2;
				CreateWaypoints($path_id);
				break;
			case SmartAction::SUMMON_CREATURE:
				$summonID = $sun_smart_entry->action_param1;
				//echo "SmartAI {$tc_entry} ${source_type} does summon a creature {$summonID}" . PHP_EOL;
				CheckImportCreature($tc_entry, $sun_smart_entry->id, $summonID);
				break;
			case SmartAction::SUMMON_CREATURE_GROUP:
			case SmartAction::GAME_EVENT_STOP:
			case SmartAction::GAME_EVENT_START:
			case SmartAction::START_CLOSEST_WAYPOINT:
			case SmartAction::LOAD_EQUIPMENT:
			case SmartAction::SPAWN_SPAWNGROUP:
			case SmartAction::DESPAWN_SPAWNGROUP:
			case SmartAction::RESPAWN_BY_SPAWNID:
			case SmartAction::SET_INST_DATA:
			case SmartAction::SET_INST_DATA64:
				echo "NYI {$tc_entry} {$sun_smart_entry->action_type}" . PHP_EOL;
				assert(false);
				exit(1);
				break;
			case SmartAction::SET_DATA:
				//special handling below
				break;
			default:
				break;
		}
		
		switch($sun_smart_entry->target_type)
		{
			case SmartTarget::CREATURE_GUID:
				//creature must exists in sun db
				$spawnID = $sun_smart_entry->target_param1;
				if(!array_key_exists($spawnID, $sunStore->creature)) {
					$creatureID = $tcStore->creature[$spawnID]->id;
					$name = $tcStore->creature_template[$creatureID]->name;
					echo "Smart TC {$tc_entry} ${source_type} trying to target a creature guid {$spawnID} (id: {$creatureID}) existing only on TC" . PHP_EOL;
					echo "/!\ Importing ALL spawns for creature id {$creatureID} ({$name}). (to avoid this, import the creature before rerunning this script)" . PHP_EOL;
					CreateReplaceAllCreature($creatureID);
				}
				break;
			case SmartTarget::GAMEOBJECT_GUID:
				//gameobject must exists in sun db
				$spawnID = $sun_smart_entry->target_param1;
				if(!array_key_exists($spawnID, $sunStore->gameobject)) {
					echo "{$tc_entry} ${source_type} trying to target non existing gameobject {$spawnID}" . PHP_EOL;
					assert(false);
					exit(1);
				}
				break;
			default:
				break;
		}
		
		if($sun_smart_entry->action_type == SmartAction::SET_DATA) {
			switch($sun_smart_entry->target_type) {
				case SmartTarget::CREATURE_RANGE:
				case SmartTarget::CREATURE_DISTANCE:
				case SmartTarget::CLOSEST_CREATURE:
					$creature_id = $sun_smart_entry->target_param1;
					CheckImportCreature($tc_entry, $sun_smart_entry->id, $creature_id);
					break;
				case SmartTarget::CREATURE_GUID:
					$guid = $sun_smart_entry->target_param1;
					$results = FindAll($sunStore->creature_entry, "spawnID", $guid);
					if(empty($results)) {
						echo "ERROR: Smart TC {$tc_entry} ${source_type}: Could not find sun creature with guid {$guid} for target CREATURE_GUID" . PHP_EOL;
						assert(false);
						exit(1);
					}
					foreach($results as $creature_entry)
						CheckImportCreature($tc_entry, $sun_smart_entry->id, $creature_entry->entry);
						
					break;
				case SmartTarget::GAMEOBJECT_RANGE:
				case SmartTarget::GAMEOBJECT_DISTANCE:
				case SmartTarget::CLOSEST_GAMEOBJECT:
					$gob_id = $sun_smart_entry->target_param1;
					CheckImportGameObject($tc_entry, $sun_smart_entry->id, $gob_id);
					break;
				case SmartTarget::GAMEOBJECT_GUID:
					$guid = $sun_smart_entry->target_param1;
					if(!array_key_exists($guid, $sunStore->gameobject)) {
						echo "ERROR: Smart TC {$tc_entry} ${source_type}: Could not find sun gameobject with guid {$guid} for target GAMEOBJECT_GUID" . PHP_EOL;
						assert(false);
						exit(1);
					}
					CheckImportGameObject($tc_entry, $sun_smart_entry->id, $sunStore->gameobject[$guid]->id);
					break;
				default:
					break;
			}
		}
		
		array_push($sunStore->smart_scripts, $sun_smart_entry);
		array_push($sun_smart_entries, $sun_smart_entry);
	}
	WriteObjects("smart_scripts", $sun_smart_entries); 
	CreateSmartConditions($tc_entry, $source_type);
}