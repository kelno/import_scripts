<?php

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
	const SMART_ACTION_PLAY_CINEMATIC        = 135;
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
	
	
function IsActionIgnoreTarget(int $action_type)
{
	switch($action_type)
	{
		case SmartAction::WP_PAUSE:
		case SmartAction::INSTALL_AI_TEMPLATE:
		case SmartAction::THREAT_ALL_PCT:
		case SmartAction::AUTO_ATTACK:
		case SmartAction::EVADE:
		case SmartAction::FLEE_FOR_ASSIST:
		case SmartAction::COMBAT_STOP:
		case SmartAction::RANDOM_PHASE:
		case SmartAction::RANDOM_PHASE_RANGE:
		case SmartAction::SET_INST_DATA:
		case SmartAction::SET_INST_DATA64:
		case SmartAction::DIE:
		case SmartAction::SET_IN_COMBAT_WITH_ZONE:
		case SmartAction::CALL_FOR_HELP:
		case SmartAction::SET_SHEATH:
		case SmartAction::MOVE_FORWARD:
		case SmartAction::SPAWN_SPAWNGROUP:
		case SmartAction::DESPAWN_SPAWNGROUP:
		case SmartAction::DISABLE_EVADE:
		case SmartAction::TRIGGER_RANDOM_TIMED_EVENT:
		case SmartAction::STORE_TARGET_LIST:
		case SmartAction::SET_DISABLE_GRAVITY:
		case SmartAction::SET_CAN_FLY:
		case SmartAction::SET_RUN:
		case SmartAction::SET_SWIM:
		case SmartAction::WP_PAUSE:
		case SmartAction::WP_STOP:
		case SmartAction::WP_RESUME:
		case SmartAction::SET_ORIENTATION:
		case SmartAction::CREATE_TIMED_EVENT:
		case SmartAction::TRIGGER_TIMED_EVENT:
		case SmartAction::RESET_SCRIPT_BASE_OBJECT:
		case SmartAction::CALL_SCRIPT_RESET:
		case SmartAction::GAME_EVENT_STOP:
		case SmartAction::GAME_EVENT_START:
		case SmartAction::RESPAWN_BY_SPAWNID:
			return true;
		default:
			return false;
	}
}
