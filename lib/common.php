<?php

include_once(__DIR__ . '/helpers.php');

set_error_handler(function($severity, $message, $file, $line) {
    if (error_reporting() & $severity) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
});

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL | E_STRICT);

ini_set('memory_limit','4096M'); //for the DB stores

abstract class LoadMode
{
	const sunstrider = 0;
	const trinitycore = 1;
}
	
class DBStore
{
	public $gossip_menu = []; //key has NO MEANING
	public $gossip_text = []; //key is text id
	public $gossip_menu_option = []; //key has NO MEANING
	public $broadcast_text = []; //key is broadcast id
	public $creature_template = []; //key is entry
	public $gameobject_template = [];  //key is entry
	public $conditions = []; //key has NO MEANING
	public $points_of_interest = []; //key has NO MEANING
	public $smart_scripts = []; //key has NO MEANING
	public $creature_text = []; //key has NO MEANING
	public $waypoints = []; //key has NO MEANING
	public $waypoint_data = []; //key has NO MEANING
	public $creature = []; //key is spawnID
	public $creature_entry = []; //key has NO MEANING
	public $creature_addon = []; //key is spawnID
	public $gameobject = []; //key is spawnID
	public $game_event_creature = []; //key is spawnID
	public $spawn_group = []; //key has NO MEANING
	public $creature_formations = []; //key is memberGUID
	public $pool_creature = []; //key is spawnID
	public $trainer = []; //key is ID
	public $trainer_spell = []; //key has NO MEANING
	
	private $loadmode = null;
	
	function __construct(&$conn, $databaseName, $loadmode)
	{
		switch($loadmode)
		{
			case LoadMode::sunstrider:
			case LoadMode::trinitycore:
				break;
			default:
				assert(false);
				exit(1);
		}
		
		$this->loadmode = $loadmode;
		
		echo "Loading " . ($loadmode == LoadMode::sunstrider ? 'sunstrider' : 'trinity') . " store... ";
		
		$stmt = $conn->query("SELECT * FROM {$databaseName}.gossip_menu");
		$stmt->setFetchMode(PDO::FETCH_OBJ);
		$this->gossip_menu = $stmt->fetchAll();
				
		$gossipTextTableName = $loadmode == LoadMode::sunstrider ? 'gossip_text' : 'npc_text';
		$stmt = $conn->query("SELECT * FROM {$databaseName}.{$gossipTextTableName}");
		$stmt->setFetchMode(PDO::FETCH_OBJ);
		foreach($stmt->fetchAll() as $v)
			$this->gossip_text[$v->ID] = $v;
		
		$stmt = $conn->query("SELECT * FROM {$databaseName}.gossip_menu_option");
		$stmt->setFetchMode(PDO::FETCH_OBJ);
		$this->gossip_menu_option = $stmt->fetchAll();
		
		$stmt = $conn->query("SELECT * FROM {$databaseName}.broadcast_text");
		$stmt->setFetchMode(PDO::FETCH_OBJ);
		foreach($stmt->fetchAll() as $v)
			$this->broadcast_text[$v->ID] = $v;
		
		$stmt = $conn->query("SELECT * FROM {$databaseName}.creature_template");
		$stmt->setFetchMode(PDO::FETCH_OBJ);
		foreach($stmt->fetchAll() as $v)
			$this->creature_template[$v->entry] = $v;
		
		$stmt = $conn->query("SELECT * FROM {$databaseName}.gameobject_template");
		$stmt->setFetchMode(PDO::FETCH_OBJ);
		foreach($stmt->fetchAll() as $v)
			$this->gameobject_template[$v->entry] = $v;
		
		$stmt = $conn->query("SELECT * FROM {$databaseName}.conditions");
		$stmt->setFetchMode(PDO::FETCH_OBJ);
		$this->conditions = $stmt->fetchAll();
		
		$stmt = $conn->query("SELECT * FROM {$databaseName}.points_of_interest");
		$stmt->setFetchMode(PDO::FETCH_OBJ);
		$this->points_of_interest = $stmt->fetchAll();
			
		$stmt = $conn->query("SELECT * FROM {$databaseName}.smart_scripts");
		$stmt->setFetchMode(PDO::FETCH_OBJ);
		$this->smart_scripts = $stmt->fetchAll();
			
		$stmt = $conn->query("SELECT * FROM {$databaseName}.creature_text");
		$stmt->setFetchMode(PDO::FETCH_OBJ);
		$this->creature_text = $stmt->fetchAll();
	
		$stmt = $conn->query("SELECT * FROM {$databaseName}.waypoints");
		$stmt->setFetchMode(PDO::FETCH_OBJ);
		$this->waypoints = $stmt->fetchAll();
		
		$stmt = $conn->query("SELECT * FROM {$databaseName}.waypoint_data");
		$stmt->setFetchMode(PDO::FETCH_OBJ);
		$this->waypoint_data = $stmt->fetchAll();
	
		$stmt = $conn->query("SELECT * FROM {$databaseName}.creature");
		$stmt->setFetchMode(PDO::FETCH_OBJ);
		$keyName = $loadmode == LoadMode::sunstrider ? 'spawnID' : 'guid';
		foreach($stmt->fetchAll() as $v)
			$this->creature[$v->$keyName] = $v;
		
		$stmt = $conn->query("SELECT * FROM {$databaseName}.creature_addon");
		$stmt->setFetchMode(PDO::FETCH_OBJ);
		$keyName = $loadmode == LoadMode::sunstrider ? 'spawnID' : 'guid';
		foreach($stmt->fetchAll() as $v)
			$this->creature_addon[$v->$keyName] = $v;
		
		if($loadmode == LoadMode::sunstrider) {
			$stmt = $conn->query("SELECT * FROM {$databaseName}.creature_entry");
			$stmt->setFetchMode(PDO::FETCH_OBJ);
			$this->creature_entry = $stmt->fetchAll();
			
			$stmt = $conn->query("SELECT * FROM {$databaseName}.trainer");
			$stmt->setFetchMode(PDO::FETCH_OBJ);
			foreach($stmt->fetchAll() as $v)
				$this->trainer[$v->Id] = $v;
			
			$stmt = $conn->query("SELECT * FROM {$databaseName}.trainer_spell");
			$stmt->setFetchMode(PDO::FETCH_OBJ);
			$this->trainer_spell = $stmt->fetchAll();
		}
		
		$stmt = $conn->query("SELECT * FROM {$databaseName}.gameobject");
		$stmt->setFetchMode(PDO::FETCH_OBJ);
		foreach($stmt->fetchAll() as $v)
			$this->gameobject[$v->guid] = $v;
		
		$stmt = $conn->query("SELECT * FROM {$databaseName}.game_event_creature");
		$stmt->setFetchMode(PDO::FETCH_OBJ);
		foreach($stmt->fetchAll() as $v)
			$this->game_event_creature[$v->guid] = $v;
		
		$stmt = $conn->query("SELECT * FROM {$databaseName}.creature_formations");
		$stmt->setFetchMode(PDO::FETCH_OBJ);
		foreach($stmt->fetchAll() as $v)
			$this->creature_formations[$v->memberGUID] = $v;
		
		$stmt = $conn->query("SELECT * FROM {$databaseName}.spawn_group");
		$stmt->setFetchMode(PDO::FETCH_OBJ);
		$this->spawn_group = $stmt->fetchAll();
			
		$stmt = $conn->query("SELECT * FROM {$databaseName}.pool_creature");
		$stmt->setFetchMode(PDO::FETCH_OBJ);
		foreach($stmt->fetchAll() as $v)
			$this->pool_creature[$v->guid] = $v;
		
		echo "\tDone" . PHP_EOL;
	}
}

function CheckBroadcast($broadcast_id)
{
	global $tcStore, $sunStore;
	
	if(!array_key_exists($broadcast_id, $tcStore->broadcast_text))
	{
		echo "ERROR: BroadcastText $broadcast_id does not exists in TC db" . PHP_EOL;
		assert(false);
		exit(1);
	}
	if(!array_key_exists($broadcast_id, $sunStore->broadcast_text))
	{
		echo "ERROR: BroadcastText $broadcast_id does not exists in Sun db" . PHP_EOL;
		assert(false);
		exit(1);
	}
	CheckExists($sunStore->broadcast_text, $tcStore->broadcast_text, "ID", $broadcast_id);
	
	return;
}

function CreateMenuConditions($tc_menu_id, $sun_menu_id, $tc_text_id, $sun_text_id)
{
	global $sunStore, $tcStore, $debug;
	
	static $CONDITION_SOURCE_TYPE_GOSSIP_MENU = 14;
	
	foreach($tcStore->conditions as $tc_condition) {
		if($tc_condition->SourceTypeOrReferenceId != $CONDITION_SOURCE_TYPE_GOSSIP_MENU)
		   continue;
		   
		if($tc_condition->SourceGroup != $tc_menu_id) //SourceGroup = menu_id / SourceEntry = text_id
		   continue;
		   
	    if($tc_condition->SourceEntry != $tc_text_id) //SourceGroup = menu_id / SourceEntry = text_id
		   continue;
	   
		$sun_condition = $tc_condition; //copy
		$sun_condition->SourceGroup = $sun_menu_id;
		$sun_condition->SourceEntry = $sun_text_id;
		$sun_condition->Comment = "(autoimported) " . $tc_condition->Comment;
		$sun_condition->Comment = str_replace($tc_text_id, $sun_text_id, $sun_condition->Comment);
		$sun_condition->Comment = str_replace($tc_menu_id, $sun_menu_id, $sun_condition->Comment);
		
		if($tc_condition->ConditionTypeOrReference == Conditions::CONDITION_ACTIVE_EVENT) {
			//echo "Convert event... " . $sun_condition->ConditionValue1 . PHP_EOL;
			$sun_condition->ConditionValue1 = ConvertGameEventId($tc_condition->ConditionValue1);
		}
			
		WriteObject("conditions", $sun_condition);
	}
}

function CreateMenuOptionsConditions($tc_menu_id, $sun_menu_id)
{
	global $sunStore, $tcStore, $debug;
	
	static $CONDITION_SOURCE_TYPE_GOSSIP_MENU_OPTION = 15;
	
	foreach($tcStore->conditions as $tc_condition) {
		if($tc_condition->SourceTypeOrReferenceId != $CONDITION_SOURCE_TYPE_GOSSIP_MENU_OPTION)
		   continue;
		   
		if($tc_condition->SourceGroup != $tc_menu_id) //SourceGroup = menu_id / SourceEntry = option_id
		   continue;
	   
		$sun_condition = $tc_condition; //copy
		$sun_condition->SourceGroup = $sun_menu_id;
		$sun_condition->Comment = "(autoimported) " . $tc_condition->Comment;
		$sun_condition->Comment = str_replace($tc_menu_id, $sun_menu_id, $sun_condition->Comment);
		
		if($tc_condition->ConditionTypeOrReference == Conditions::CONDITION_ACTIVE_EVENT) {
			//echo "Convert event... " . $sun_condition->ConditionValue1 . PHP_EOL;
			$sun_condition->ConditionValue1 = ConvertGameEventId($tc_condition->ConditionValue1);
		}
			
		WriteObject("conditions", $sun_condition);
	}
}

$reusedSunTexts = [];
$movedTCTexts = [];

function CreateText($tc_text_id)
{
	global $sunStore, $tcStore, $debug, $reusedSunTexts, $movedTCTexts, $file;
	
	if(array_key_exists($tc_text_id, $movedTCTexts)) {
		fwrite($file, "-- Text {$tc_text_id} is already imported as " . $movedTCTexts[$tc_text_id] . PHP_EOL);
		return $movedTCTexts[$tc_text_id];
	}
	
	if(CheckAlreadyImported($tc_text_id)) {
		fwrite($file, "-- Text {$tc_text_id} is already imported" . PHP_EOL);
		return $tc_text_id;
	}
	
	if($debug)
		echo "Importing text $tc_text_id" .PHP_EOL;
	
	if(!array_key_exists($tc_text_id, $tcStore->gossip_text)) {
		echo "TextId {$tc_text_id} does not exists in TC db?" . PHP_EOL;
		assert(false);
		exit(1);
	}
	
	$tc_text = &$tcStore->gossip_text[$tc_text_id];
	$sun_text_id = $tc_text_id;
	if(array_key_exists($tc_text_id, $sunStore->gossip_text)) {
		$sun_text = $sunStore->gossip_text[$tc_text_id];
		if($sun_text->text0_0 == $tc_text->text0_0 && $sun_text->text0_1 == $tc_text->text0_1) {
			array_push($reusedSunTexts, $tc_text_id);
			fwrite($file, "-- Text {$tc_text_id} already present in Sun DB" . PHP_EOL); //same text, stop here
			return $tc_text_id;
		}
		$sun_text_id = max(array_keys($sunStore->gossip_text)) + 1;
		$movedTCTexts[$tc_text_id] = $sun_text_id;
	}
	
	//convert TC table to Sun table here
	$sun_text = new stdClass; //anonymous object
	$sun_text->ID = $sun_text_id;
	$sun_text->comment = "Imported from TC";
	for($i = 0; $i < 8; $i++) {
		for($j = 0; $j < 2; $j++) {
			$fieldName = 'text' . $i . '_' . $j;
			$sun_text->$fieldName = $tc_text->$fieldName;
		}
		
		$fieldName = 'BroadcastTextID' . $i;
		if($broadcast_id = $tc_text->$fieldName) {
			CheckBroadcast($broadcast_id);
			$sun_text->$fieldName = $broadcast_id;
		} else
			$sun_text->$fieldName = "NULL";
		
		$fieldName = 'lang' . $i;
		$sun_text->$fieldName = $tc_text->$fieldName;
		
		$fieldName = 'Probability' . $i;
		$sun_text->$fieldName = $tc_text->$fieldName;
		
		for($j = 0; $j < 6; $j++) {
			$fieldName = 'em' . $i . '_' . $j;
			$sun_text->$fieldName = $tc_text->$fieldName;
		}
	}
	
	$sunStore->gossip_text[$sun_text_id] = $sun_text;
	
	WriteObject("gossip_text", $sun_text);
	return $sun_text_id;
}

function CreatePOI($poi_id)
{
	global $sunStore, $tcStore, $debug, $file;
	
	if(CheckAlreadyImported($poi_id)) {
		fwrite($file, "-- POI {$poi_id} is already imported" . PHP_EOL);
		return;
	}
	
	$results = FindAll($tcStore->points_of_interest, "ID", $poi_id);
	if(count($results) != 1) {
		echo "TC points_of_interest has 0 or > 1 PoI for id {$poi_id}" . PHP_EOL;
		assert(false);
		exit(1);
	}
	
	$tc_poi = $results[0];
	
	//we assume if we have a poi with this id, it's already the same
	$results = FindAll($sunStore->points_of_interest, "ID", $poi_id);
	if(count($results) > 0) {
		fwrite($file, "-- POI {$poi_id} already present in sun db" . PHP_EOL);
		return;
	}
	
	$sun_poi = $tc_poi; //simple copy
	$sun_poi->Icon = ConvertPoIIcon($tc_poi->Icon);
	WriteObject("points_of_interest", $sun_poi);
	$sunStore->points_of_interest[$poi_id] = $sun_poi;
	
	if($tc_poi->Icon != $sun_poi->Icon) {
		$sun_poi_tlk = $tc_poi;
		$sun_poi_tlk->patch = 5; //LK patch
		WriteObject("points_of_interest", $sun_poi_tlk);
		$sunStore->points_of_interest[$poi_id] = $sun_poi_tlk;
	} 
}

function CreateMenuOptions($tc_menu_id, $sun_menu_id)
{
	global $sunStore, $tcStore, $debug, $file;
	
	if(CheckAlreadyImported($tc_menu_id)) {
		fwrite($file, "-- Menu options for {$tc_menu_id} are already imported" . PHP_EOL);
		return;
	}
	
	$results = FindAll($tcStore->gossip_menu_option, "MenuID", $tc_menu_id);
	if(empty($results))
		return; //no menu options found, this is a normal case
	
	foreach($results as $tc_option) {
		
		if($debug)
			echo "Importing tc menu option {$tc_menu_id}|{$tc_option->OptionID} into sun menu {$sun_menu_id}" .PHP_EOL;
		
		
		$sun_option = new stdClass; //anonymous object
		$sun_option->MenuID = $sun_menu_id;
		$sun_option->OptionID = $tc_option->OptionID;
		$sun_option->OptionIcon = $tc_option->OptionIcon;
		$sun_option->OptionText = $tc_option->OptionText;
		if($broadcast_id1 = $tc_option->OptionBroadcastTextID) {
			CheckBroadcast($broadcast_id1);
			$sun_option->OptionBroadcastTextID = $broadcast_id1;
		} else {
			$sun_option->OptionBroadcastTextID = 'NULL';
		}
		$sun_option->OptionType = $tc_option->OptionType;
		$sun_option->OptionNpcFlag = $tc_option->OptionNpcFlag;
		if($tc_option->ActionMenuID) {
			$sun_menu_id = CreateMenu($tc_option->ActionMenuID);
			$sun_option->ActionMenuID = $sun_menu_id;
		} else 
			$sun_option->ActionMenuID = 'NULL';
		
		if($tc_option->ActionPoiID) {
			CreatePOI($tc_option->ActionPoiID);
			$sun_option->ActionPoiID = $tc_option->ActionPoiID; //may be NULL
		} else {
			$sun_option->ActionPoiID = 'NULL';
		}
		$sun_option->BoxCoded = $tc_option->BoxCoded; 
		$sun_option->BoxMoney = $tc_option->BoxMoney; 
		$sun_option->BoxText = $tc_option->BoxText;
		if($broadcast_id2 = $tc_option->BoxBroadcastTextID) {
			CheckBroadcast($broadcast_id2);
			$sun_option->BoxBroadcastTextID = $broadcast_id2;
		} else {
			$sun_option->BoxBroadcastTextID = 'NULL';
		}
		if (strpos($sun_option->OptionText, 'Dual Talent') !== false) {
			$sun_option->patch_min = 5; //TLK
		}
		
		array_push($sunStore->gossip_menu_option, $sun_option);
		
		WriteObject("gossip_menu_option", $sun_option); 
	}
}

function DeleteSunMenu($sunMenuID)
{
	global $sunStore, $debug, $file;
	
	if(CheckAlreadyImported($sunMenuID))
		return;
	
	//only delete if only one menu is found
	$results = FindAll($sunStore->creature_template, "gossip_menu_id", $sunMenuID);
	if(empty($results)) {
		echo "ERROR: Trying to delete non existing sun menu {$sunMenuID}" . PHP_EOL;
		assert(false);
		exit(1);
	}
	
	if(sizeof($results) > 1)
		return; //more than one ref to this menu, skip
	
	$sql = "DELETE FROM gossip_menu WHERE MenuID = {$sunMenuID};" . PHP_EOL;
	
	RemoveAny($sunStore->gossip_menu, "MenuID", $sunMenuID);
	
	//currently bugged, because we reuse text that are the same
	/*
	$results2 = FindAll($sunStore->gossip_menu, "MenuID", $sunMenuID);
	foreach($results2 as $sun_menu) {
		$text_id = $sun_menu->text_id;
		if(array_key_exists($text_id, $reusedSunTexts))
			continue; //we use it!
		$results3 = FindAll($sunStore->gossip_text, "ID", $text_id);	
		if(sizeof($results3) > 1 || array_key_exists($text_id, $reusedSunTexts))
			continue; //more than one ref to this text, skip
			
		$sql .= "DELETE FROM gossip_text WHERE ID = {$text_id};" . PHP_EOL;
	}*/
	fwrite($file, $sql);
}

//return sun menu
function CreateMenu($tc_menu_id)
{
	global $sunStore, $tcStore, $debug, $file;
	
	if(CheckAlreadyImported($tc_menu_id)) {
		fwrite($file, "-- Menu {$tc_menu_id} is already imported" . PHP_EOL);
		return;
	}
	
	$results = FindAll($tcStore->gossip_menu, "MenuID", $tc_menu_id);
	if(empty($results)) {
		echo "Failed to find TC menu {$tc_menu_id}" . PHP_EOL;
		assert(false);
		exit(1);
	}
	
	$sun_menu_id = null;
	if(HasAny($sunStore->gossip_menu, "MenuID", $tc_menu_id))
		$sun_menu_id = GetHighest($sunStore->gossip_menu, "MenuID") + 1;
	else
		$sun_menu_id = $tc_menu_id;
	
	foreach($results as $tc_menu) {
		$tc_text_id = $tc_menu->TextID;
		
		if($debug)
			echo "Importing tc menu {$tc_menu_id} (text {$tc_text_id}) into sun menu {$sun_menu_id}" .PHP_EOL;
		
		$sun_text_id = CreateText($tc_text_id);
		assert($sun_text_id != '' && $sun_text_id > 0);
		
		$sun_menu = new stdClass; //anonymous object
		$sun_menu->MenuID = $sun_menu_id;
		$sun_menu->TextID = $sun_text_id;
			
		array_push($sunStore->gossip_menu, $sun_menu);
		
		CreateMenuConditions($tc_menu_id, $sun_menu_id, $tc_text_id, $sun_text_id);
		WriteObject("gossip_menu", $sun_menu); 
	}
	CreateMenuOptions($tc_menu_id, $sun_menu_id);
	CreateMenuOptionsConditions($tc_menu_id, $sun_menu_id);

	return $sun_menu_id;
}
	
function SetMenuId($entry, $sun_menu_id, bool $set_gossip_flag)
{
	global $file;
	$npcflag = $set_gossip_flag ? "npcflag = (npcflag | 1), " : "";
	$sql = "UPDATE creature_template SET {$npcflag}gossip_menu_id = {$sun_menu_id} WHERE entry = {$entry};" . PHP_EOL;
	fwrite($file, $sql);
}

$sunWorld = "world";
$tcWorld = "trinityworld";
$login = "root";
$password = "canard";

// Connect
$conn = new PDO("mysql:host=localhost;dbname=$sunWorld", $login, $password);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sunStore = new DBStore($conn, $sunWorld, LoadMode::sunstrider);
$tcStore  = new DBStore($conn, $tcWorld,  LoadMode::trinitycore);
