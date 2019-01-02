<?php

//declare(strict_types = 1);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL | E_STRICT);
ini_set('memory_limit','4096M'); //for the DB stores

set_error_handler(function($severity, $message, $file, $line) {
    if (error_reporting() & $severity) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
});



include_once(__DIR__ . '/helpers.php');
include_once(__DIR__ . '/smartai.php');
include_once(__DIR__ . '/../config.php');

abstract class LoadMode
{
	const sunstrider = 0;
	const trinitycore = 1;
}
	
class DBStore
{
	public $broadcast_text = []; //key is broadcast id
	public $conditions = []; //key has NO MEANING
	public $creature = []; //key is spawnID
	public $creature_addon = []; //key is spawnID
	public $creature_entry = []; //key has NO MEANING
	public $creature_formations = []; //key is memberGUID
	public $creature_template = []; //key is entry TODO, should switch to no meaning
	public $creature_text = []; //key has NO MEANING
	public $game_event_creature = []; //key is spawnID
	public $gameobject = []; //key is spawnID
	public $game_event_gameobject = []; //key is spawnID
	public $gameobject_template = [];  //key is entry
	public $gossip_menu = []; //key has NO MEANING
	public $gossip_menu_option = []; //key has NO MEANING
	public $gossip_text = []; //key is text id
	public $points_of_interest = []; //key has NO MEANING
	public $pool_creature = []; //key is spawnID
	public $pool_gameobject = []; //key is spawnID
	public $pool_template = []; //key is pool entry
	public $smart_scripts = []; //key has NO MEANING
	public $spawn_group = []; //key has NO MEANING
	public $spell_template = []; //key is spell id
	public $trainer = []; //key is ID
	public $trainer_spell = []; //key has NO MEANING
	public $waypoint_data = []; //key has NO MEANING
	public $waypoint_scripts = []; //key has NO MEANING
	public $waypoints = []; //key has NO MEANING
	
	private $loadmode = null;
	
	function __construct(&$conn, string $databaseName, $loadmode)
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
		
		$stmt = $conn->query("SELECT * FROM {$databaseName}.waypoint_scripts");
		$stmt->setFetchMode(PDO::FETCH_OBJ);
		foreach($stmt->fetchAll() as $v)
			$this->waypoint_scripts[$v->id] = $v;
	
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
			
			$stmt = $conn->query("SELECT * FROM {$databaseName}.spell_template");
			$stmt->setFetchMode(PDO::FETCH_OBJ);
			foreach($stmt->fetchAll() as $v)
				$this->spell_template[$v->entry] = $v->spellName1;
		}
		
		$stmt = $conn->query("SELECT * FROM {$databaseName}.gameobject");
		$stmt->setFetchMode(PDO::FETCH_OBJ);
		foreach($stmt->fetchAll() as $v)
			$this->gameobject[$v->guid] = $v;
			
		$stmt = $conn->query("SELECT * FROM {$databaseName}.game_event_gameobject");
		$stmt->setFetchMode(PDO::FETCH_OBJ);
		foreach($stmt->fetchAll() as $v)
			$this->game_event_gameobject[$v->guid] = $v;
					
		$stmt = $conn->query("SELECT * FROM {$databaseName}.game_event_creature");
		$stmt->setFetchMode(PDO::FETCH_OBJ);
		foreach($stmt->fetchAll() as $v)
			$this->game_event_creature[$v->guid] = $v;
		
		$stmt = $conn->query("SELECT * FROM {$databaseName}.creature_formations");
		$stmt->setFetchMode(PDO::FETCH_OBJ);
		foreach($stmt->fetchAll() as $v) {
			$this->creature_formations[$v->memberGUID] = $v;
			if(!$v->leaderGUID)
				$this->creature_formations[$v->memberGUID]->leaderGUID = $v->memberGUID; //special handling for leader, it's always NULL in db for leader
		}
		
		$stmt = $conn->query("SELECT * FROM {$databaseName}.spawn_group");
		$stmt->setFetchMode(PDO::FETCH_OBJ);
		$this->spawn_group = $stmt->fetchAll();
			
		$stmt = $conn->query("SELECT * FROM {$databaseName}.pool_creature");
		$stmt->setFetchMode(PDO::FETCH_OBJ);
		foreach($stmt->fetchAll() as $v)
			$this->pool_creature[$v->guid] = $v;
		
		$stmt = $conn->query("SELECT * FROM {$databaseName}.pool_gameobject");
		$stmt->setFetchMode(PDO::FETCH_OBJ);
		foreach($stmt->fetchAll() as $v)
			$this->pool_gameobject[$v->guid] = $v;
		
		$stmt = $conn->query("SELECT * FROM {$databaseName}.pool_template");
		$stmt->setFetchMode(PDO::FETCH_OBJ);
		foreach($stmt->fetchAll() as $v)
			$this->pool_template[$v->entry] = $v;
		
		echo "\tDone" . PHP_EOL;
	}
}

class DBConverter
{
	public $conn;
	public $file;
	
	public $tcStore;
	public $sunStore;
	
	public $debug = false;
	
	function __construct(&$file, bool $_debug = false)
	{
		global $sunWorld, $tcWorld, $login, $password;
		
		$this->file = $file;
		$this->debug = $_debug;
		
		// Connect
		//$this->conn = new PDO("mysql:host=localhost;dbname=$sunWorld", $login, $password);
		$this->conn = new PDO("mysql:host=localhost", $login, $password);
		$this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		$this->sunStore = new DBStore($this->conn, $sunWorld, LoadMode::sunstrider);
		$this->tcStore  = new DBStore($this->conn, $tcWorld,  LoadMode::trinitycore);
	}
	
	/* This test pass if:
	- sunContainer does not contain key with this value
	- sunContainer does contain key with value but has the same as tcContainer
	Else, crash everything
	*/
	function CheckExists(string $tableName, string $keyname, $value)
	{
		$sunResults = FindAll($this->sunStore->$tableName, $keyname, $value);
		if(empty($sunResults))
			return;
		
		$tcResults = FindAll($this->tcStore->$tableName, $keyname, $value);
		if(empty($tcResults)) {
			echo "Checked for existence but TC has no value for this container?" . PHP_EOL;
			assert(false);
			exit(1);
		}
		
		if($sunResults != $tcResults) { //does this work okay? This is supposed to compare keys + values, but we don't care about keys.
			echo "TC and SUN containers have different results for table {$tableName} and value {$value}" . PHP_EOL;
			assert(false);
			exit(1);
		}
		
		//OK
		return;
	}

	function CheckBroadcast(int $broadcast_id)
	{
		if(!array_key_exists($broadcast_id, $this->tcStore->broadcast_text))
		{
			echo "ERROR: BroadcastText $broadcast_id does not exists in TC db" . PHP_EOL;
			assert(false);
			exit(1);
		}
		if(!array_key_exists($broadcast_id, $this->sunStore->broadcast_text))
		{
			echo "ERROR: BroadcastText $broadcast_id does not exists in Sun db" . PHP_EOL;
			assert(false);
			exit(1);
		}
		$this->CheckExists("broadcast_text", "ID", $broadcast_id);
	}

	//overrideCheckSourceEntry is for menus that changed id, condition must apply to that new menu
	function SunHasCondition(&$tc_condition, $overrideCheckSourceEntry = null)
	{
		//probably wrong... can't use the $key for sunstore here
		foreach(array_keys($this->sunStore->conditions) as $key) {
			if(   $tc_condition->SourceTypeOrReferenceId == $this->sunStore->conditions[$key]->SourceTypeOrReferenceId
			   && $tc_condition->SourceGroup == $this->sunStore->conditions[$key]->SourceGroup
			   && ($overrideCheckSourceEntry ? $overrideCheckSourceEntry : $tc_condition->SourceEntry) == $this->sunStore->conditions[$key]->SourceEntry
			   && $tc_condition->SourceId == $this->sunStore->conditions[$key]->SourceId
			   && $tc_condition->ElseGroup == $this->sunStore->conditions[$key]->ElseGroup
			   && $tc_condition->ConditionTypeOrReference == $this->sunStore->conditions[$key]->ConditionTypeOrReference
			   && $tc_condition->ConditionTarget == $this->sunStore->conditions[$key]->ConditionTarget
			   && $tc_condition->ConditionValue1 == $this->sunStore->conditions[$key]->ConditionValue1
			   && $tc_condition->ConditionValue2 == $this->sunStore->conditions[$key]->ConditionValue2
			   && $tc_condition->ConditionValue3 == $this->sunStore->conditions[$key]->ConditionValue3
			   && $tc_condition->NegativeCondition == $this->sunStore->conditions[$key]->NegativeCondition
			   && $tc_condition->ErrorType == $this->sunStore->conditions[$key]->ErrorType
		       && $tc_condition->ErrorTextId == $this->sunStore->conditions[$key]->ErrorTextId
		       && $tc_condition->ScriptName == $this->sunStore->conditions[$key]->ScriptName)
			   return true;
		}
		
		return false;
	}

	function CreateMenuConditions(int $tc_menu_id, int $sun_menu_id, int $tc_text_id, int $sun_text_id)
	{
		static $CONDITION_SOURCE_TYPE_GOSSIP_MENU = 14;
		 
		$this->timelol("CMO1");
		
		foreach(array_keys($this->tcStore->conditions) as $key) {
			if($this->tcStore->conditions[$key]->SourceTypeOrReferenceId != $CONDITION_SOURCE_TYPE_GOSSIP_MENU)
			   continue;
			   
			if($this->tcStore->conditions[$key]->SourceGroup != $tc_menu_id) 
			   continue;
			   
			if($this->tcStore->conditions[$key]->SourceEntry != $tc_text_id) 
			   continue;
		   
			if($this->SunHasCondition($this->tcStore->conditions[$key], $sun_menu_id)) {
				fwrite($this->file, "-- Sun db already has this condition (tc menu {$tc_menu_id}, sun menu {$sun_menu_id})" . PHP_EOL);
				continue;
			}
			
			$this->timelol("CMOcopy");
			$sun_condition = $this->tcStore->conditions[$key]; //copy
			$sun_condition->SourceGroup = $sun_menu_id;
			$sun_condition->SourceEntry = $sun_text_id;
			$sun_condition->Comment = "(autoimported) " . $this->tcStore->conditions[$key]->Comment;
			$sun_condition->Comment = str_replace($tc_text_id, $sun_text_id, $sun_condition->Comment);
			$sun_condition->Comment = str_replace($tc_menu_id, $sun_menu_id, $sun_condition->Comment);
							
			fwrite($this->file, WriteObject($this->conn, "conditions", $sun_condition));
		}
		$this->timelol("CMO2");
	}

	function CreateMenuOptionsConditions(int $tc_menu_id, int $sun_menu_id)
	{
		static $CONDITION_SOURCE_TYPE_GOSSIP_MENU_OPTION = 15;
		
		foreach(array_keys($this->tcStore->conditions) as $key) {
			if($this->tcStore->conditions[$key]->SourceTypeOrReferenceId != $CONDITION_SOURCE_TYPE_GOSSIP_MENU_OPTION)
			   continue;
			   
			if($this->tcStore->conditions[$key]->SourceGroup != $tc_menu_id) //SourceGroup = menu_id / SourceEntry = option_id
			   continue;
		   
			if($this->SunHasCondition($this->tcStore->conditions[$key], $sun_menu_id)) {
				fwrite($this->file, "-- Sun db already has this condition (tc menu {$tc_menu_id}, sun menu {$sun_menu_id})" . PHP_EOL);
				continue;
			}
			
			$sun_condition = $this->tcStore->conditions[$key]; //copy
			$sun_condition->SourceGroup = $sun_menu_id;
			$sun_condition->Comment = "(autoimported) " . $this->tcStore->conditions[$key]->Comment;
			$sun_condition->Comment = str_replace($tc_menu_id, $sun_menu_id, $sun_condition->Comment);
			
			fwrite($this->file, WriteObject($this->conn, "conditions", $sun_condition));
		}
	}

	private $reusedSunTexts = [];
	private $movedTCTexts = [];

	function CreateText(int $tc_text_id) : int
	{
		if(array_key_exists($tc_text_id, $this->movedTCTexts)) {
			fwrite($this->file, "-- Text {$tc_text_id} is already imported as " . $this->movedTCTexts[$tc_text_id] . PHP_EOL);
			return $this->movedTCTexts[$tc_text_id];
		}
		
		if(CheckAlreadyImported($tc_text_id)) {
			fwrite($this->file, "-- Text {$tc_text_id} is already imported" . PHP_EOL);
			return $tc_text_id;
		}
		
		if($this->debug)
			echo "Importing text $tc_text_id" .PHP_EOL;
		
		if(!array_key_exists($tc_text_id, $this->tcStore->gossip_text)) {
			echo "TextId {$tc_text_id} does not exists in TC db?" . PHP_EOL;
			assert(false);
			exit(1);
		}
		
		$tc_text = &$this->tcStore->gossip_text[$tc_text_id];
		$sun_text_id = $tc_text_id;
		if(array_key_exists($tc_text_id, $this->sunStore->gossip_text)) {
			$sun_text = $this->sunStore->gossip_text[$tc_text_id];
			if($sun_text->text0_0 == $tc_text->text0_0 && $sun_text->text0_1 == $tc_text->text0_1) {
				array_push($this->reusedSunTexts, $tc_text_id);
				fwrite($this->file, "-- Text {$tc_text_id} already present in Sun DB" . PHP_EOL); //same text, stop here
				return $tc_text_id;
			}
			$sun_text_id = max(array_keys($this->sunStore->gossip_text)) + 1;
			$this->movedTCTexts[$tc_text_id] = $sun_text_id;
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
				$this->CheckBroadcast($broadcast_id);
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
		
		$this->sunStore->gossip_text[$sun_text_id] = $sun_text;
		
		fwrite($this->file, WriteObject($this->conn, "gossip_text", $sun_text));
		return $sun_text_id;
	}

	function CreatePOI(int $poi_id)
	{
		if(CheckAlreadyImported($poi_id)) {
			fwrite($this->file, "-- POI {$poi_id} is already imported" . PHP_EOL);
			return;
		}
		
		$results = FindAll($this->tcStore->points_of_interest, "ID", $poi_id);
		if(count($results) != 1) {
			echo "TC points_of_interest has 0 or > 1 PoI for id {$poi_id}" . PHP_EOL;
			assert(false);
			exit(1);
		}
		
		$tc_poi = $results[0];
		
		//we assume if we have a poi with this id, it's already the same
		$results = FindAll($this->sunStore->points_of_interest, "ID", $poi_id);
		if(count($results) > 0) {
			fwrite($this->file, "-- POI {$poi_id} already present in sun db" . PHP_EOL);
			return;
		}
		
		$sun_poi = $tc_poi; //simple copy
		$sun_poi->Icon = ConvertPoIIcon($tc_poi->Icon);
		fwrite($this->file, WriteObject($this->conn, "points_of_interest", $sun_poi));
			array_push($this->sunStore->points_of_interest, $sun_poi);
		
		if($tc_poi->Icon != $sun_poi->Icon) {
			$sun_poi_tlk = $tc_poi;
			$sun_poi_tlk->patch = 5; //LK patch
			fwrite($this->file, WriteObject($this->conn, "points_of_interest", $sun_poi_tlk));
			array_push($this->sunStore->points_of_interest, $sun_poi_tlk);
		} 
	}

	function CreateMenuOptions(int $tc_menu_id, int $sun_menu_id)
	{
		if(CheckAlreadyImported($tc_menu_id)) {
			fwrite($this->file, "-- Menu options for {$tc_menu_id} are already imported" . PHP_EOL);
			return;
		}
		
		$results = FindAll($this->tcStore->gossip_menu_option, "MenuID", $tc_menu_id);
		if(empty($results))
			return; //no menu options found, this is a normal case
		
		foreach($results as $tc_option) {
			
			if($this->debug)
				echo "Importing tc menu option {$tc_menu_id}|{$tc_option->OptionID} into sun menu {$sun_menu_id}" .PHP_EOL;
			
			$sun_option = new stdClass; //anonymous object
			$sun_option->MenuID = $sun_menu_id;
			$sun_option->OptionID = $tc_option->OptionID;
			$sun_option->OptionIcon = $tc_option->OptionIcon;
			$sun_option->OptionText = $tc_option->OptionText;
			if($broadcast_id1 = $tc_option->OptionBroadcastTextID) {
				$this->CheckBroadcast($broadcast_id1);
				$sun_option->OptionBroadcastTextID = $broadcast_id1;
			} else {
				$sun_option->OptionBroadcastTextID = 'NULL';
			}
			$sun_option->OptionType = $tc_option->OptionType;
			$sun_option->OptionNpcFlag = $tc_option->OptionNpcFlag;
			if($tc_option->ActionMenuID) {
				$new_sun_menu_id = $this->CreateMenu($tc_option->ActionMenuID);
				$sun_option->ActionMenuID = $new_sun_menu_id;
			} else 
				$sun_option->ActionMenuID = 'NULL';
			
			if($tc_option->ActionPoiID) {
				$this->CreatePOI($tc_option->ActionPoiID);
				$sun_option->ActionPoiID = $tc_option->ActionPoiID; //may be NULL
			} else {
				$sun_option->ActionPoiID = 'NULL';
			}
			$sun_option->BoxCoded = $tc_option->BoxCoded; 
			$sun_option->BoxMoney = $tc_option->BoxMoney; 
			$sun_option->BoxText = $tc_option->BoxText;
			if($broadcast_id2 = $tc_option->BoxBroadcastTextID) {
				$this->CheckBroadcast($broadcast_id2);
				$sun_option->BoxBroadcastTextID = $broadcast_id2;
			} else {
				$sun_option->BoxBroadcastTextID = 'NULL';
			}
			if (   (strpos($sun_option->OptionText, 'Dual Talent') !== false)
				|| ($sun_option->ActionPoiID > 0 && strpos($sun_option->OptionText, 'Inscription') !== false)
				|| (strpos($sun_option->OptionText, 'Lexicon of') !== false)
				|| (strpos($sun_option->OptionText, 'Barber') !== false)
				) {
				$sun_option->patch_min = 5; //TLK
			}
			
			array_push($this->sunStore->gossip_menu_option, $sun_option);
			fwrite($this->file, WriteObject($this->conn, "gossip_menu_option", $sun_option)); 
		}
	}

	function DeleteSunMenu(int $sunMenuID)
	{
		if(CheckAlreadyImported($sunMenuID))
			return;
		
		//only delete if only one menu is found
		$results = FindAll($this->sunStore->creature_template, "gossip_menu_id", $sunMenuID);
		if(empty($results)) {
			echo "ERROR: Trying to delete non existing sun menu {$sunMenuID}" . PHP_EOL;
			assert(false);
			exit(1);
		}
		
		if(sizeof($results) > 1)
			return; //more than one ref to this menu, skip
		
		$sql = "DELETE FROM gossip_menu WHERE MenuID = {$sunMenuID};" . PHP_EOL;
		
		RemoveAny($this->sunStore->gossip_menu, "MenuID", $sunMenuID);
		
		//currently bugged, because we reuse text that are the same
		/*
		$results2 = FindAll($this->sunStore->gossip_menu, "MenuID", $sunMenuID);
		foreach($results2 as $sun_menu) {
			$text_id = $sun_menu->text_id;
			if(array_key_exists($text_id, $this->reusedSunTexts))
				continue; //we use it!
			$results3 = FindAll($this->sunStore->gossip_text, "ID", $text_id);	
			if(sizeof($results3) > 1 || array_key_exists($text_id, $this->reusedSunTexts))
				continue; //more than one ref to this text, skip
				
			$sql .= "DELETE FROM gossip_text WHERE ID = {$text_id};" . PHP_EOL;
		}*/
		fwrite($this->file, $sql);
	}

	private $convertedTCMenus = [];

	//return sun menu
	function CreateMenu(int $tc_menu_id) : int
	{
		if(array_key_exists($tc_menu_id, $this->convertedTCMenus))
		{
			fwrite($this->file, "-- Menu {$tc_menu_id} is already imported as " . $this->convertedTCMenus[$tc_menu_id] . PHP_EOL);
			return $this->convertedTCMenus[$tc_menu_id];
		}
		
		if(CheckAlreadyImported($tc_menu_id)) {
			fwrite($this->file, "-- Menu {$tc_menu_id} is already imported" . PHP_EOL);
			return $tc_menu_id;
		}
		
		$this->timelol("CM1");
		
		$results = FindAll($this->tcStore->gossip_menu, "MenuID", $tc_menu_id);
		if(empty($results)) {
			echo "Failed to find TC menu {$tc_menu_id}" . PHP_EOL;
			assert(false);
			exit(1);
		}
		
		$this->timelol("CM2");
		
		$sun_menu_id = null;
		if(HasAny($this->sunStore->gossip_menu, "MenuID", $tc_menu_id)) {
			//would be very complicated to compare menus... just import a new one
			$sun_menu_id = GetHighest($this->sunStore->gossip_menu, "MenuID") + 1;
			$this->convertedTCMenus[$tc_menu_id] = $sun_menu_id;
			fwrite($this->file, "-- Importing menu {$tc_menu_id} as {$sun_menu_id}".PHP_EOL); 
		}
		else
			$sun_menu_id = $tc_menu_id;
		
		$this->timelol("CM3");
		
		foreach($results as $tc_menu) {
			$tc_text_id = $tc_menu->TextID;
			
			if($this->debug)
				echo "Importing tc menu {$tc_menu_id} (text {$tc_text_id}) into sun menu {$sun_menu_id}" .PHP_EOL;
			
			$sun_text_id = $this->CreateText($tc_text_id);
			assert($sun_text_id != '' && $sun_text_id > 0);
			
			$sun_menu = new stdClass; //anonymous object
			$sun_menu->MenuID = $sun_menu_id;
			$sun_menu->TextID = $sun_text_id;
				
			array_push($this->sunStore->gossip_menu, $sun_menu);
			$this->CreateMenuConditions($tc_menu_id, $sun_menu_id, $tc_text_id, $sun_text_id);
			fwrite($this->file, WriteObject($this->conn, "gossip_menu", $sun_menu)); 
		}
		$this->timelol("CM4");
		$this->CreateMenuOptions($tc_menu_id, $sun_menu_id);
		$this->timelol("CM5");
		$this->CreateMenuOptionsConditions($tc_menu_id, $sun_menu_id);

		$this->timelol("CM6");
		return $sun_menu_id;
	}
		
	function SetMenuId(int $entry, int $sun_menu_id, bool $set_gossip_flag)
	{
		$npcflag = $set_gossip_flag ? "npcflag = (npcflag | 1), " : "";
		$sql = "UPDATE creature_template SET {$npcflag}gossip_menu_id = {$sun_menu_id} WHERE entry = {$entry};" . PHP_EOL;
		fwrite($this->file, $sql);
	}


	function FindAllSmart(int $sun, int $entryorguid, int $source_type) : array
	{
		$results = [];
		
		$this->timelol("FA1");
		
		/*
		$ref = $sun ? $this->sunStore->smart_scripts : $this->tcStore->smart_scripts;
		foreach($ref as $smart_script) {
			if($smart_script->entryorguid == $entryorguid && $smart_script->source_type == $source_type)
				array_push($results, $smart_script);
		}*/
		
		if($sun) {
			foreach(array_keys($this->sunStore->smart_scripts) as $key) {
				if($this->sunStore->smart_scripts[$key]->entryorguid == $entryorguid && $this->sunStore->smart_scripts[$key]->source_type == $source_type)
					array_push($results, $this->sunStore->smart_scripts[$key]);
			}
		}
		else {
			foreach(array_keys($this->tcStore->smart_scripts) as $key) {
				if($this->tcStore->smart_scripts[$key]->entryorguid == $entryorguid && $this->tcStore->smart_scripts[$key]->source_type == $source_type) {
					array_push($results, $this->tcStore->smart_scripts[$key]);
				}
			}
		}
		$this->timelol("FA2");
		
		return $results;
	}

	function DeleteAllSmart(int $entryorguid, int $source_type)
	{
		$this->timelol("a");
		
		if(CheckAlreadyImported($entryorguid + $source_type << 28)) //max entry is 30.501.000 (smaller number with 28 bits shift is 268.435.456)
			return;
		
		$sql = "DELETE FROM smart_scripts WHERE entryorguid = {$entryorguid} AND source_type = {$source_type};" . PHP_EOL;
		fwrite($this->file, $sql);
		
		$this->timelol("b");
		
		$results = $this->FindAllSmart(true, $entryorguid, $source_type);
		foreach($results as $sun_smart) {
			switch($sun_smart->action_type)
			{
				case SmartAction::CALL_TIMED_ACTIONLIST:
					$this->DeleteAllSmart($sun_smart->action_param1, SmartSourceType::timedactionlist);
					break;
			}
		}
		
		$this->timelol("c");
		
		//Unset in store
		
		foreach(array_keys($this->sunStore->smart_scripts) as $key) {
			if($this->sunStore->smart_scripts[$key]->entryorguid == $entryorguid && $this->sunStore->smart_scripts[$key]->source_type == $source_type)
				unset($this->sunStore->smart_scripts[$key]);
		}
				
		/*
		foreach($this->sunStore->smart_scripts as $k => $smart_script) {
			if($smart_script->entryorguid == $entryorguid && $smart_script->source_type == $source_type)
				unset($this->sunStore->smart_scripts[$k]);
		}*/

		$this->timelol("d");
	}

	function CreateCreatureText(int $tc_entry)
	{
		if(CheckAlreadyImported($tc_entry)) {
			fwrite($this->file, "-- Creature text {$tc_entry} is already imported" . PHP_EOL);
			return;
		}
		
		$this->timelol("CCT1");
		
		$results = FindAll($this->tcStore->creature_text, "CreatureID", $tc_entry);
		if(empty($results)) {
			echo "ERROR: Could not find TC creature_text for creature id {$tc_entry}" . PHP_EOL;
			assert(false);
			exit(1);
		}
		
		$sql = "DELETE FROM creature_text WHERE CreatureID = {$tc_entry};" . PHP_EOL;
		fwrite($this->file, $sql);
		
		$this->timelol("CCT2");
		foreach($results as $text_entry) {
			if($broadcast_id = $text_entry->BroadcastTextId)
				$this->CheckBroadcast($broadcast_id);
			
			array_push($this->sunStore->creature_text, $text_entry);
			fwrite($this->file, WriteObject($this->conn, "creature_text", $text_entry)); 
		}
		$this->timelol("CCT3");
	}

	function CreateSmartConditions(int $tc_entry, int $source_type)
	{
		if(CheckAlreadyImported($tc_entry + $source_type << 28)) { //max entry is 30.501.000 (smaller number with 28 bits shift is 268.435.456)
			fwrite($this->file, "-- Smart condition {$tc_entry} {$source_type} is already imported" . PHP_EOL);
			return;
		}
		
		static $CONDITION_SOURCE_TYPE_SMART_EVENT = 22;
		 
		foreach(array_keys($this->tcStore->conditions) as $key) {
			if($this->tcStore->conditions[$key]->SourceTypeOrReferenceId != $CONDITION_SOURCE_TYPE_SMART_EVENT)
			   continue;
			   
			//SourceGroup == id, but we import all
			   
			if($this->tcStore->conditions[$key]->SourceEntry != $tc_entry) 
			   continue;
		   
			if($this->tcStore->conditions[$key]->SourceId != $source_type) 
			   continue;
		   
			if($this->SunHasCondition($this->tcStore->conditions[$key])) {
				fwrite($this->file, "-- Sun db already has this condition (entry: {$tc_entry})" . PHP_EOL);
				continue;
			}
			$sun_condition = $this->tcStore->conditions[$key]; //copy
			$sun_condition->Comment = "(autoimported) " . $this->tcStore->conditions[$key]->Comment;
			
			fwrite($this->file, WriteObject($this->conn, "conditions", $sun_condition));
		}
	}

	function CreateSmartWaypoints(int $path_id)
	{
		if(CheckAlreadyImported($path_id)) {
			fwrite($this->file, "-- Smart Waypoints {$path_id} are already imported" . PHP_EOL);
			return;
		}
		
		$results = FindAll($this->tcStore->waypoints, "entry", $path_id);
		if(empty($results)) {
			echo "ERROR: Could not find TC waypoints {$path_id}" . PHP_EOL;
			assert(false);
			exit(1);
		}
		
		$sunResults = FindAll($this->sunStore->waypoints, "entry", $path_id);
		if(!empty($sunResults)) {
			if($sunResults != $results) //does this work okay? This is supposed to compare keys + values, but we don't care about keys.
				echo "TC and SUN table have different smart waypoints for path_id {$path_id}, overwritting with TC ones ". PHP_EOL;
				
			$sql = "DELETE FROM waypoints WHERE entry = {$path_id};" . PHP_EOL;
			fwrite($this->file, $sql);
			RemoveAny($this->sunStore->waypoints, "entry", $path_id);
		}
		
		foreach($results as $tc_waypoint) {
			array_push($this->sunStore->waypoints, $tc_waypoint);
			fwrite($this->file, WriteObject($this->conn, "waypoints", $tc_waypoint)); 
		}
	}

	function CheckImportCreature(int $from_entry, int $from_id, int $creature_id) 
	{
		if(CheckAlreadyImported($creature_id))
			return;
		
		if(!array_key_exists($creature_id, $this->tcStore->creature_template)) {
			echo "Smart TC {$from_entry} {$from_id} has SET_DATA on a non existing creature {$creature_id}" . PHP_EOL;
			assert(false);
			exit(1);
		}
		
		if($this->tcStore->creature_template[$creature_id]->AIName != "SmartAI") {
			echo "Smart TC {$from_entry} {$from_id} has SET_DATA on a non Smart creature {$creature_id}" . PHP_EOL;
			assert(false);
			exit(1);
		}
		
		$this->timelol("CIC1");
		
		echo "Smart TC {$from_entry} {$from_id} summon/targets creature id {$creature_id}, also importing it. "; //... continue this line later
		if(CheckIdentical($this->sunStore->smart_scripts, $this->tcStore->smart_scripts, "entryorguid", $creature_id)) {
			echo "Already identical, skipping" . PHP_EOL;
			fwrite($this->file, "-- SmartAI for creature {$creature_id} is already in db and identical" . PHP_EOL); //already imported
			return;
		}
		
		$this->timelol("CIC2");
		
		$sunAIName = $this->sunStore->creature_template[$creature_id]->AIName;
		$sunScriptName = $this->sunStore->creature_template[$creature_id]->ScriptName;
		
		if($sunAIName == "" && $sunScriptName == "")
			echo "(it currently has no script)" . PHP_EOL;
		else 
			echo "It currently had AIName '{$sunAIName}' and ScriptName '{$sunScriptName}'" . PHP_EOL;

		$this->CreateSmartAI($creature_id, SmartSourceType::creature);
		$this->timelol("CIC3");
	}

	function CheckImportGameObject(int $from_entry, int $from_id, int $gob_id)
	{
		if(!array_key_exists($gob_id, $this->tcStore->gameobject_template)) {
			echo "Smart TC {$from_entry} has SET_DATA on a non existing gameobject {$gob_id}" . PHP_EOL;
			assert(false);
			exit(1);
		}
		
		if($this->tcStore->gameobject_template[$gob_id]->AIName != "SmartAI") {
			echo "Smart TC {$from_entry}has SET_DATA on a non Smart gameobject {$gob_id}" . PHP_EOL;
			assert(false);
			exit(1);
		}
		
		echo "Smart TC {$from_entry} targets gameobject id {$gob_id}, also importing it. "; //... continue this line later
		
		if(CheckIdentical($this->sunStore->smart_scripts, $this->tcStore->smart_scripts, "entryorguid", $gob_id)) {
			echo "Already identical, skipping" . PHP_EOL;
			fwrite($this->file, "-- SmartAI for gob {$gob_id} is already in db and identical" . PHP_EOL); //already imported
			return;
		}
		
		$sunAIName = $this->sunStore->gameobject_template[$creature_id]->AIName;
		$sunScriptName = $this->sunStore->gameobject_template[$creature_id]->ScriptName;
		
		if($sunAIName == "" && $sunScriptName == "")
			echo "(it currently has no script)" . PHP_EOL;
		else 
			echo "It currently had AIName '{$sunAIName}' and ScriptName '{$sunScriptName}" . PHP_EOL;

		$this->CreateSmartAI($gob_id, SmartSourceType::gameobject);
	}

	function timelol(int $id, int $limit = 1)
	{
		if(!$this->debug)
			return;
		
		static $start = null;
		if($start != null)
		{
			$duration = microtime(true) - $start;
			if($duration > $limit) {
				echo "{$id} - Duration: {$duration}s" . PHP_EOL;
				assert(false);
			}
		}
		$start = microtime(true);
	}

	function CreateSmartAI(int $tc_entry, int $source_type, int $action_list_origin = 0)
	{
		if(CheckAlreadyImported($tc_entry + $source_type << 28)) { //max entry is 30.501.000 (smaller number with 28 bits shift is 268.435.456)
			fwrite($this->file, "-- SmartAI {$tc_entry} {$source_type} is already imported" . PHP_EOL);
			return;
		}
		
		$this->timelol("1");
		
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
		fwrite($this->file, $sql);
		$this->DeleteAllSmart($tc_entry, $source_type);
		
		$this->timelol("2");
		
		$results = $this->FindAllSmart(false, $tc_entry, $source_type);
		if(empty($results)) {
			echo "ERROR: Failed to find TC SmartAI with entry {$tc_entry} and type {$source_type}" . PHP_EOL;
			assert(false);
			exit(1);
		}
		
		$this->timelol("3");
		
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
						$this->CreateSmartWaypoints($path_id);
					break;
				case SmartEvent::GOSSIP_SELECT:
					if($tc_menu_id = $smart_entry->event_param1) {
						$sun_menu_id = $this->CreateMenu($tc_menu_id);
						$sun_smart_entry->event_param1 = $sun_menu_id;
					}
					break;
				default:
					break;
			}
			
			//echo $smart_entry->event_type . PHP_EOL;
			$this->timelol("4");
		
			switch($sun_smart_entry->action_type)
			{
				case SmartAction::TALK:
				case SmartAction::SIMPLE_TALK:
					$this->CreateCreatureText($action_list_origin ? $action_list_origin : $tc_entry);
					break;
				case SmartAction::CAST:
					$spell_id = $sun_smart_entry->action_param1;
					//check if spell exists for TBC
					if(!array_key_exists($spell_id, $this->sunStore->spell_template)) {
						$sun_smart_entry->patch_min = 5;
					}
					break;
				case SmartAction::CALL_TIMED_ACTIONLIST:
					$this->CreateSmartAI($sun_smart_entry->action_param1, SmartSourceType::timedactionlist, $action_list_origin);
					break;
				case SmartAction::CALL_RANDOM_TIMED_ACTIONLIST:			
					$SMART_AI_MAX_ACTION_PARAM = 6;
					for($i = 1; $i <= $SMART_AI_MAX_ACTION_PARAM; $i++) {
						$fieldName = "action_param" . $i;
						if($action_list = $sun_smart_entry->$fieldName)
							$this->CreateSmartAI($action_list, SmartSourceType::timedactionlist, $action_list_origin);
					}
					break;
				case SmartAction::CALL_RANDOM_RANGE_TIMED_ACTIONLIST:
					$min = $sun_smart_entry->action_param1;
					$max = $sun_smart_entry->action_param2;
					for($i = $min; $i <= $max; $i++) {
						$this->CreateSmartAI($i, SmartSourceType::timedactionlist, $action_list_origin);
					}
					break;
				case SmartAction::SEND_GOSSIP_MENU:
					$sun_menu_id = $this->CreateMenu($sun_smart_entry->action_param1); 
					$sun_smart_entry->action_param1 = $sun_menu_id;
					break;
				case SmartAction::WP_START:
					$path_id = $sun_smart_entry->action_param2;
					$this->CreateSmartWaypoints($path_id);
					break;
				case SmartAction::SUMMON_CREATURE:
					$summonID = $sun_smart_entry->action_param1;
					//echo "SmartAI {$tc_entry} ${source_type} does summon a creature {$summonID}" . PHP_EOL;
					$this->CheckImportCreature($tc_entry, $sun_smart_entry->id, $summonID);
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
			
			//echo "action type " . $sun_smart_entry->action_type . PHP_EOL;
			$this->timelol("5");
			
			switch($sun_smart_entry->target_type)
			{
				case SmartTarget::CREATURE_GUID:
					//creature must exists in sun db
					$spawnID = $sun_smart_entry->target_param1;
					if(!array_key_exists($spawnID, $this->sunStore->creature)) {
						$creatureID = $this->tcStore->creature[$spawnID]->id;
						$name = $this->tcStore->creature_template[$creatureID]->name;
						echo "Smart TC {$tc_entry} ${source_type} trying to target a creature guid {$spawnID} (id: {$creatureID}) existing only on TC" . PHP_EOL;
						echo "/!\ Importing ALL spawns for creature id {$creatureID} ({$name}). (to avoid this, import the creature before rerunning this script)" . PHP_EOL;
						$this->CreateReplaceAllCreature($creatureID);
					}
					break;
				case SmartTarget::GAMEOBJECT_GUID:
					//gameobject must exists in sun db
					$spawnID = $sun_smart_entry->target_param1;
					if(!array_key_exists($spawnID, $this->sunStore->gameobject)) {
						echo "{$tc_entry} ${source_type} trying to target non existing gameobject {$spawnID}" . PHP_EOL;
						assert(false);
						exit(1);
					}
					break;
				default:
					break;
			}
			
			$this->timelol("6");
			 
			if($sun_smart_entry->action_type == SmartAction::SET_DATA) {
				switch($sun_smart_entry->target_type) {
					case SmartTarget::CREATURE_RANGE:
					case SmartTarget::CREATURE_DISTANCE:
					case SmartTarget::CLOSEST_CREATURE:
						$creature_id = $sun_smart_entry->target_param1;
						$this->CheckImportCreature($tc_entry, $sun_smart_entry->id, $creature_id);
						break;
					case SmartTarget::CREATURE_GUID:
						$guid = $sun_smart_entry->target_param1;
						$results = FindAll($this->sunStore->creature_entry, "spawnID", $guid);
						if(empty($results)) {
							echo "ERROR: Smart TC {$tc_entry} ${source_type}: Could not find sun creature with guid {$guid} for target CREATURE_GUID" . PHP_EOL;
							assert(false);
							exit(1);
						}
						foreach($results as $creature_entry)
							$this->CheckImportCreature($tc_entry, $sun_smart_entry->id, $creature_entry->entry);
							
						break;
					case SmartTarget::GAMEOBJECT_RANGE:
					case SmartTarget::GAMEOBJECT_DISTANCE:
					case SmartTarget::CLOSEST_GAMEOBJECT:
						$gob_id = $sun_smart_entry->target_param1;
						$this->CheckImportGameObject($tc_entry, $sun_smart_entry->id, $gob_id);
						break;
					case SmartTarget::GAMEOBJECT_GUID:
						$guid = $sun_smart_entry->target_param1;
						if(!array_key_exists($guid, $this->sunStore->gameobject)) {
							echo "ERROR: Smart TC {$tc_entry} ${source_type}: Could not find sun gameobject with guid {$guid} for target GAMEOBJECT_GUID" . PHP_EOL;
							assert(false);
							exit(1);
						}
						$this->CheckImportGameObject($tc_entry, $sun_smart_entry->id, $this->sunStore->gameobject[$guid]->id);
						break;
					default:
						break;
				}
			}
			
			$this->timelol("7");
			
			array_push($this->sunStore->smart_scripts, $sun_smart_entry);
			array_push($sun_smart_entries, $sun_smart_entry);
			
			$this->timelol("8");
		}
		
		fwrite($this->file, WriteObjects($this->conn, "smart_scripts", $sun_smart_entries)); 
		$this->CreateSmartConditions($tc_entry, $source_type);
		
		$this->timelol("9");
	}
	
	function DeleteSunCreatureSpawn(int $spawn_id)
	{
		if(CheckAlreadyImported($spawn_id))
			return;
		
		$sql = "DELETE ce, c1, c2, sg FROM creature_entry ce " .
					"LEFT JOIN conditions c1 ON c1.ConditionTypeOrReference = 31 AND c1.ConditionValue1 = 3 AND c1.ConditionValue3 = {$spawn_id} " .
					"LEFT JOIN conditions c2 ON c2.SourceTypeOrReferenceId = 22 AND c2.SourceId = 0 AND c1.SourceEntry = -{$spawn_id} " . //SourceId = SMART_SCRIPT_TYPE_CREATURE
					"LEFT JOIN spawn_group sg ON sg.spawnId = {$spawn_id} AND spawnType = 0 " .
					"WHERE ce.spawnID = {$spawn_id};" . PHP_EOL;
		fwrite($this->file, $sql);
				
		//warn smart scripts references removal
		$results = FindAll($this->sunStore->smart_scripts, "entryorguid", -$spawn_id);
		foreach($results as $result) {
			if($result->source_type != SmartSourceType::creature)
				continue;
			
			echo "WARNING: Deleting a creature (guid: {$spawn_id}) with a per guid SmartScripts ({$result->entryorguid}, {$result->id}). Smart scripts ref has been left as is." . PHP_EOL;
		}
		
		$results = FindAll($this->sunStore->smart_scripts, "target_param1", $spawn_id);
		foreach($results as $result) {
			if($result->target_type != SmartTarget::CREATURE_GUID)
				continue;
			
			echo "WARNING: Deleting creature (guid: {$spawn_id}) targeted by a smartscript ({$result->entryorguid}, {$result->id}). Smart scripts ref has been left as is." . PHP_EOL;
		}
	}
	
	function DeleteSunCreatures(int $creature_id, array $not_in)
	{
		if(CheckAlreadyImported($creature_id))
			return;
		
		$results = FindAll($this->sunStore->creature_entry, "entry", $creature_id);
		foreach($results as $result) {
			if(!in_array($result->spawnID, $not_in))
				$this->DeleteSunCreatureSpawn($result->spawnID);
		}
	}

	function DeleteSunGameObjectsInMap(int $map_id, array $not_in)
	{
		if(CheckAlreadyImported($map_id))
			return;
		
		$results = FindAll($this->sunStore->gameobject, "map", $map_id);
		foreach($results as $result) {
			if(!in_array($result->guid, $not_in))
				$this->DeleteSunGameObjectSpawn($result->guid);
		}
	}
	
	function DeleteSunCreaturesInMap(int $map_id, array $not_in)
	{
		if(CheckAlreadyImported($map_id))
			return;
		
		$results = FindAll($this->sunStore->creature, "map", $map_id);
		foreach($results as $result) {
			if(!in_array($result->spawnID, $not_in))
				$this->DeleteSunCreatureSpawn($result->spawnID);
		}
	}
	
	function SunHasSameWaypointsScripts(array &$tcResults, array $sunResults)
	{
		if(count($tcResults) != count($sunResults))
			return false;
		
		$sunResults = array_values($sunResults); //this is to reset array keys
		$i = 0;
		foreach($tcResults as $tcResult) {
			if($tcResult != $sunResults[$i++])
				return false;
		}
		return true;
	}
	
	function ImportWaypointScripts(int $action_id) : int
	{
		if(CheckAlreadyImported($action_id))
			return $action_id;
		
		$results = FindAll($this->tcStore->waypoint_scripts, "id", $action_id);
		if(empty($results)) {
			echo "ERROR: Tried to import waypoint_scripts with id {$action_id} but no such entry exists" . PHP_EOL;
			assert(false);
			exit(1);
		}
		
		$sunResults = FindAll($this->sunStore->waypoint_scripts, "id", $action_id);
		if($this->SunHasSameWaypointsScripts($results, $sunResults)) {
			fwrite($this->file, "-- Waypoint scripts with id {$action_id} are already present and the same" . PHP_EOL);
			return $action_id;
		}
		
		$sun_action_id = $action_id;
		if(count($sunResults) > 0) {
			//we have a path with this id, but no the same...
			$sun_action_id = GetHighest($this->sunStore->waypoint_scripts, "id") + 1;
		}
		
		foreach($results as $tc_waypoint_script) {
			$sun_waypoint_script = $tc_waypoint_script; //copy
			$sun_waypoint_script->id = $sun_action_id;
			unset($sun_waypoint_script->guid); //let db generate a new one here
			switch($tc_waypoint_script->command)
			{
				case 0: //SCRIPT_COMMAND_TALK:
					//we already get the same broadcast_text tables, so nothing to change here!
					break;
				case 31: //SCRIPT_COMMAND_EQUIP
					echo "ERROR: NYI SCRIPT_COMMAND_EQUIP (31)" . PHP_EOL;
					assert(false);
					exit(1);
					break;
				case 35: //SCRIPT_COMMAND_MOVEMENT
					$movementType = $tc_waypoint_script->datalong;
					if($movementType == 2) { // WAYPOINT_MOTION_TYPE
						$path_id = $tc_waypoint_script->dataint;
						echo "ERROR: NYI SCRIPT_COMMAND_MOVEMENT (35) with WAYPOINT_MOTION_TYPE" . PHP_EOL;
						assert(false);
						exit(1);
					}
					break;
				default:
					break;
			}
				
			array_push($this->sunStore->waypoint_scripts, $sun_waypoint_script);
			fwrite($this->file, WriteObject($this->conn, "waypoint_scripts", $sun_waypoint_script));
		}
		
		return $sun_action_id;
	}

	//not sure this is working
	function SunHasSameWaypoints(array &$tcResults, array $sunResults) : bool
	{
		if(count($tcResults) != count($sunResults))
			return false;
		
		$sunResults = array_values($sunResults); //this is to reset array keys
		$i = 0;
		foreach($tcResults as $tcResult) {
			if($tcResult != $sunResults[$i++])
				return false;
		}
		return true;
	}
	
	//return new path_id
	function ImportWaypoints(int $guid, int $tc_path_id, bool $includeMovementTypeUpdate = true)
	{
		$results = FindAll($this->tcStore->waypoint_data, "id", $tc_path_id);
		if(empty($results)) {
			echo "ERROR: Tried to import waypoint_data with path_id {$tc_path_id} but no such path exists" . PHP_EOL;
			assert(false);
			exit(1);
		}
		
		if(CheckAlreadyImported($tc_path_id)) {
			fwrite($this->file, "-- Path {$tc_path_id} is already imported" . PHP_EOL);
			return;
		}
		
		$sunResults = FindAll($this->sunStore->waypoint_data, "id", $tc_path_id);
		if($this->SunHasSameWaypoints($results, $sunResults)) {
			fwrite($this->file, "-- Path {$tc_path_id} is already present and the same" . PHP_EOL);
			return;
		}
		
		$sun_path_id = $tc_path_id;
		if(count($sunResults) > 0) {
			//we have a path with this id, but no the same...
			$sun_path_id = GetHighest($this->sunStore->waypoint_data, "id") + 1;
		}
		
		foreach($results as $tc_waypoint) {
			$sun_waypoint = $tc_waypoint; //copy
			$sun_waypoint->id = $sun_path_id;
			if($tc_action = $tc_waypoint->action)
				$sun_waypoint->action = $this->ImportWaypointScripts($tc_action);
			else
				$sun_waypoint->action = 'NULL';
			
			array_push($this->sunStore->waypoint_data, $sun_waypoint);
			fwrite($this->file, WriteObject($this->conn, "waypoint_data", $sun_waypoint));
		}
		
		if($includeMovementTypeUpdate) {
			$this->sunStore->creature[$guid]->MovementType = 2;
			fwrite($this->file, "UPDATE creature SET MovementType = 2 WHERE spawnID = {$guid};" . PHP_EOL);
		}
		
		return $sun_path_id;
	}

	function ImportSpawnGroup(int $guid, bool $creature) //else gameobject
	{
		if(CheckAlreadyImported($guid + $creature << 31))
			return;
		
		$results = FindAll($this->tcStore->spawn_group, "spawnId", $guid);
		foreach($results as $result) {
			if($creature) {
				if($result->spawnType != 0) //creature type
					continue;
			} else {
				if($result->spawnType != 1) //gob type
					continue;
			}
				
			$groupId = $result->groupId;
			if(!ConvertSpawnGroup($groupId, $guid)) //may change groupId
				continue;
				
			$sun_spawn_group = $result; //copy
			$sun_spawn_group->groupId = $groupId;
			
			array_push($this->sunStore->spawn_group, $sun_spawn_group);
			fwrite($this->file, WriteObject($this->conn, "spawn_group", $sun_spawn_group));
		}
	}

	private $delayedFormationsImports = "";
	
	function ImportFormation(int $guid)
	{
		if(!array_key_exists($guid, $this->tcStore->creature_formations))
			return;
		
		$tc_formation = $this->tcStore->creature_formations[$guid];
		$leaderGUID = $tc_formation->leaderGUID;
		if(!array_key_exists($leaderGUID, $this->tcStore->creature_formations))
			return;
		
		if(array_key_exists($guid, $this->sunStore->creature_formations)) {
			$sun_formation = $this->sunStore->creature_formations[$guid];
			if($sun_formation != $tc_formation) {
				//a formation already exists for this creature but is not the same...
				fwrite($this->file, "DELETE FROM creature_formations WHERE memberGUID = {$guid};" . PHP_EOL);
				unset($this->sunStore->creature_formations[$guid]);
			} else {
				fwrite($this->file, "-- Formation for creature {$guid} already in db" . PHP_EOL);
				return;
			}
		}
		
		if(CheckAlreadyImported($leaderGUID))
			return;
		
		$results = FindAll($this->tcStore->creature_formations, "leaderGUID", $leaderGUID);
		foreach($results as $tc_formation) {
				
			if(!array_key_exists($tc_formation->memberGUID, $this->sunStore->creature)) {
				//we don't have that leader yet
				fwrite($this->file, "-- Trying to import formation for creature {$tc_formation->memberGUID}, but the creature isn't in our db yet. Importing it now" . PHP_EOL);
				$this->ImportTCCreature($tc_formation->memberGUID);
				//this call won't import the formation because of the CheckAlreadyImported before
			}
			
			$sun_formation = new stdClass; 
			$sun_formation->leaderGUID = $leaderGUID;
			$sun_formation->memberGUID = $tc_formation->memberGUID;
			$sun_formation->dist = $tc_formation->dist;
			$sun_formation->groupAI = 2;//alway 2, we don't use the same AI system than TC
			$sun_formation->angle = deg2rad($tc_formation->angle); //TC has degree, Sun has radian
			
			$this->sunStore->creature_formations[$sun_formation->memberGUID] = $sun_formation;
			
			if($sun_formation->leaderGUID == $sun_formation->memberGUID)
				$sun_formation->leaderGUID = "NULL"; //special on SUN as well (only do that for the WriteObject, not for the store)
			
			$this->delayedFormationsImports .= "DELETE FROM creature_formations WHERE memberGUID = {$sun_formation->memberGUID};" . PHP_EOL;
			$this->delayedFormationsImports .= WriteObject($this->conn, "creature_formations", $sun_formation);
		}
	}

	function ImportPool(int $guid, bool $creature) //else gameobject
	{
		$tc_pool_entry = null;
		if($creature) {
			if(!array_key_exists($guid, $this->tcStore->pool_creature))
				return;
			
			$tc_pool_entry = $this->tcStore->pool_creature[$guid]->pool_entry;
		} else {
			if(!array_key_exists($guid, $this->tcStore->pool_gameobject))
				return;
			
			$tc_pool_entry = $this->tcStore->pool_gameobject[$guid]->pool_entry;
		}
		
		if(!array_key_exists($tc_pool_entry, $this->tcStore->pool_template)) {
			echo "ERROR: TC has " . $creature ? "creature" : "gob" . " {$guid} which part of pool {$pool_entry}, but this pool does not exists" . PHP_EOL;
			return;
		}
		
		//Handle pool template
		if(array_key_exists($tc_pool_entry, $this->sunStore->pool_template)) { 
			if($this->sunStore->pool_template[$tc_pool_entry]->description != $this->tcStore->pool_template[$tc_pool_entry]->description)
			{ //we have that pool id but not the same pool
				echo "WARNING: Imported " . $creature ? "creature" : "gob" . " {$guid} is part of pool {$tc_pool_entry} but a pool with this entry (but different description already exists). Need manual fix." . PHP_EOL;
				return;
			}
			else { //we have that pool id and same pool, no need to import template
				//fwrite($this->file, "-- Imported " . $creature ? "creature" : "gob" . " {$guid} is part of pool {$tc_pool_entry} which already exists" . PHP_EOL);
			}
		}
		else { 
			//if sun doesn't have that pool
			$sun_pool_template = $this->tcStore->pool_template[$tc_pool_entry];
			$this->sunStore->pool_template[$tc_pool_entry] = $sun_pool_template;
			fwrite($this->file, WriteObject($this->conn, "pool_template", $sun_pool_template));
		}
		
		//and finally add to pool
		if($creature) {
			$pool_creature = $this->tcStore->pool_creature[$guid];
			$this->sunStore->pool_creature[$guid] = $pool_creature;
			fwrite($this->file, WriteObject($this->conn, "pool_creature", $pool_creature));
		} else {
			$pool_gameobject = $this->tcStore->pool_gameobject[$guid];
			$this->sunStore->pool_gameobject[$guid] = $pool_gameobject;
			fwrite($this->file, WriteObject($this->conn, "pool_gameobject", $pool_gameobject));
		}
	}

	function FilterTLKAuras(string $auras) : string
	{
		TODO;
	}
	
	function ImportCreatureTemplate($id)
	{
		assert(array_key_exists($id, $this->tcStore->creature_template));
			
		if(array_key_exists($id, $this->sunStore->creature_template)) {
			$sun_name = $this->sunStore->creature_template[$id]->name;
			$tc_name = $this->tcStore->creature_template[$id]->name;
			if($sun_name != $tc_name) {
				echo "ERROR: Sun db already has creature {$id} ({$tc_name})... but under a different name ({$sun_name}). Is this a custom creature?" . PHP_EOL;
				assert(false);
				exit(1);
			}
			return;
		}
		
		$sun_creature_template = new stdClass;
		$sun_creature_template->entry = $tc_creature->id;
		$sun_creature_template->name = $this->tcStore->creature_template[$tc_creature->id]->name;
		echo "NYI";
		assert(false);
		exit(1);
		$this->sunStore->creature_template[$sun_creature_template->entry] = $sun_creature_template;
		fwrite($this->file, WriteObject($this->conn, "creature_template", $sun_creature_template));
	}
	
	//don't forget to call HandleFormations after this
	function ImportTCCreature(int $guid, int $patch_min = 0, int $patch_max = 10)
	{
		if(CheckAlreadyImported($guid))
			return;
		
		if(array_key_exists($guid, $this->sunStore->creature)) {
			return;
			/*echo "ERROR: Trying to import creature with guid {$guid} but creature already exists" . PHP_EOL;
			assert(false);
			exit(1);*/
		}
		
		$tc_creature = &$this->tcStore->creature[$guid];
		$tc_creature_addon = null;
		if(array_key_exists($guid, $this->tcStore->creature_addon)) {
			$tc_creature_addon = &$this->tcStore->creature_addon[$guid];
		}
		
		//create creature_entry
		$sun_creature_entry = new stdClass; //anonymous object
		$sun_creature_entry->spawnID = $guid;
		$sun_creature_entry->entry = $tc_creature->id;
		
		if(IsTLKCreature($tc_creature->id)) {
			ImportCreatureTemplate($tc_creature->id);
			if($patch_min < 5)
				$patch_min = 5;
		}
		
		array_push($this->sunStore->creature_entry, $sun_creature_entry);
		fwrite($this->file, WriteObject($this->conn, "creature_entry", $sun_creature_entry));
		
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
		if(IsTLKMap($sun_creature->map))
			$patch_min = 5;
		$sun_creature->patch_min = $patch_min;
		$sun_creature->patch_max = $patch_max;
		
		$this->sunStore->creature[$guid] = $sun_creature;
		fwrite($this->file, WriteObject($this->conn, "creature", $sun_creature));
		
		//creature addon
		if($tc_creature_addon) {
			$path_id = $tc_creature_addon->path_id;
			if($path_id) {
				$this->ImportWaypoints($guid, $path_id, false); //$pathID might be changed here
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
			$sun_creature_addon->auras = $tc_creature_addon->auras ? $tc_creature_addon->auras : 'NULL';
			//todo: check auras with spell_template, some are TLK only
			
			$this->sunStore->creature_addon[$guid] = $sun_creature_addon;
			fwrite($this->file, WriteObject($this->conn, "creature_addon", $sun_creature_addon));
		}
		
		//game event creature
		if(array_key_exists($guid, $this->tcStore->game_event_creature)) {
			$sun_gec = new stdClass;
			$sun_gec->event = $this->tcStore->game_event_creature[$guid]->eventEntry;
			$sun_gec->guid = $guid;
			$this->sunStore->game_event_creature[$guid] = $sun_gec;
			fwrite($this->file, WriteObject($this->conn, "game_event_creature", $sun_gec));
		}
		
		$this->ImportSpawnGroup($guid, true);
		$this->ImportFormation($guid);
		$this->ImportPool($guid, true);
	}

	function CreateReplaceAllCreature(int $creature_id, int $patch_min = 0, int $patch_max = 10)
	{
		if(CheckAlreadyImported($creature_id))
			return;
			
		$results = FindAll($this->tcStore->creature, "id", $creature_id);
		if(empty($results)) {
			echo "ERROR: Failed to find any TC creature with id {$creature_id}" . PHP_EOL;
			assert(false);
			exit(1);
		}
						
		$tc_guids = [];
		foreach($results as $tc_creature) {
			array_push($tc_guids, $tc_creature->guid);
			if(!array_key_exists($tc_creature->guid, $this->sunStore->creature))
				$this->ImportTCCreature($tc_creature->guid, $patch_min, $patch_max);
		}
		$this->DeleteSunCreatures($creature_id, $tc_guids);
		$this->HandleFormations();
	}
	
	function CreateReplaceMap(int $map_id, int $patch_min = 0, int $patch_max = 10)
	{
		if(CheckAlreadyImported($map_id))
			return;
			
		//handle creatures
		$results = FindAll($this->tcStore->creature, "map", $map_id);
		if(empty($results)) {
			echo "ERROR: Failed to find any TC creature in map {$map_id}" . PHP_EOL;
			assert(false);
			exit(1);
		}
		
		$tc_guids = [];
		foreach($results as $tc_creature) {
			array_push($tc_guids, $tc_creature->guid);
			if(!array_key_exists($tc_creature->guid, $this->sunStore->creature))
				$this->ImportTCCreature($tc_creature->guid, $patch_min, $patch_max);
		}
		$this->DeleteSunCreaturesInMap($map_id, $tc_guids);
		$this->HandleFormations();
		
		//handle gameobjects
		$results = FindAll($this->tcStore->gameobject, "map", $map_id);
		if(empty($results)) {
			echo "ERROR: Failed to find any TC gameobject in map {$map_id}" . PHP_EOL;
			assert(false);
			exit(1);
		}
						
		$tc_guids = [];
		foreach($results as $tc_gob) {
			array_push($tc_guids, $tc_gob->guid);
			if(!array_key_exists($tc_gob->guid, $this->sunStore->gameobject))
				$this->ImportTCGameObject($tc_gob->guid, $patch_min, $patch_max);
		}
		$this->DeleteSunGameObjectsInMap($creature_id, $tc_guids);
	}

	//Write formations stored in $this->delayedFormationsImports
	function HandleFormations()
	{
		if(!$this->delayedFormationsImports)
			return;
		
		fwrite($this->file, "-- Formations" . PHP_EOL);
		fwrite($this->file, $this->delayedFormationsImports);
		$this->delayedFormationsImports = "";
	}
	
	function ImportGameObjectTemplate($id)
	{
		assert(array_key_exists($id, $this->tcStore->gameobject_template));
		$tc_gameobject_template = &$this->tcStore->gameobject_template[$id];
		
		$sun_gameobject_template = new stdClass;
		$sun_gameobject_template->entry = $id;
		$sun_gameobject_template->type = $tc_gameobject_template->type;
		$sun_gameobject_template->displayId = $tc_gameobject_template->displayId;
		$sun_gameobject_template->name = $tc_gameobject_template->name;
		$sun_gameobject_template->castBarCaption = $tc_gameobject_template->castBarCaption;
		$sun_gameobject_template->faction = 0;
		$sun_gameobject_template->flags = 0;
		$sun_gameobject_template->size = $tc_gameobject_template->size;
		for($i = 0; $i < 24; $i++) {
			$sun_field_name = 'data' . $i;
			$tc_field_name = 'Data' . $i;
			$sun_gameobject_template->$sun_field_name = $tc_gameobject_template->$tc_field_name;
		}
		$sun_gameobject_template->AIName = $tc_gameobject_template->AIName;
		$sun_gameobject_template->ScriptName = $tc_gameobject_template->ScriptName;
		
		if ($sun_gameobject_template->AIName != "") 
			echo "WARNING: Importing gameobject template {$id} which has AIName {$sun_gameobject_template->AIName}" . PHP_EOL;
		
		if ($sun_gameobject_template->ScriptName != "")
			echo "WARNING: Importing gameobject template {$id} which has ScriptName {$sun_gameobject_template->ScriptName}" . PHP_EOL;
		
		$this->sunStore->gameobject_template[$sun_gameobject_template->entry] = $sun_gameobject_template;
		fwrite($this->file, WriteObject($this->conn, "gameobject_template", $sun_gameobject_template));
	}
	
	function ImportTCGameObject(int $guid, int $patch_min = 0, int $patch_max = 10)
	{
		if(CheckAlreadyImported($guid))
			return;
		
		if(array_key_exists($guid, $this->sunStore->gameobject)) {
			return;
			/*echo "ERROR: Trying to import creature with guid {$guid} but creature already exists" . PHP_EOL;
			assert(false);
			exit(1);*/
		}
		
		$tc_gameobject = &$this->tcStore->gameobject[$guid];
		
		$tlk_gameobject = !array_key_exists($tc_gameobject->id, $this->sunStore->gameobject_template);
		if($tlk_gameobject) {
			ImportGameObjectTemplate($tc_gameobject->id);
			if(IsTLKGameObject($tc_gameobject->id) && $patch_min < 5)
				$patch_min = 5;
		}
		
		//create gameobject
		$sun_gameobject = new stdClass;
		$sun_gameobject->guid          = $guid;
		$sun_gameobject->id            = $tc_gameobject->id;
		$sun_gameobject->map           = $tc_gameobject->map;
		$sun_gameobject->spawnMask     = $tc_gameobject->spawnMask;
		$sun_gameobject->position_x    = $tc_gameobject->position_x;
		$sun_gameobject->position_y    = $tc_gameobject->position_y;
		$sun_gameobject->position_z    = $tc_gameobject->position_z;
		$sun_gameobject->rotation0     = $tc_gameobject->rotation0;
		$sun_gameobject->rotation1     = $tc_gameobject->rotation1;
		$sun_gameobject->rotation2     = $tc_gameobject->rotation2;
		$sun_gameobject->rotation3     = $tc_gameobject->rotation3;
		$sun_gameobject->spawntimesecs = $tc_gameobject->spawntimesecs;
		$sun_gameobject->animprogress  = $tc_gameobject->animprogress;
		$sun_gameobject->state         = $tc_gameobject->state;
		$sun_gameobject->ScriptName    = $tc_gameobject->ScriptName;
		if(IsTLKMap($sun_gameobject->map))
			$patch_min = 5;
		$sun_gameobject->patch_min     = $patch_min;
		$sun_gameobject->patch_max     = $patch_max;
		
		$this->sunStore->gameobject[$guid] = $sun_gameobject;
		fwrite($this->file, WriteObject($this->conn, "gameobject", $sun_gameobject));
		
		//game event gameobject
		if(array_key_exists($guid, $this->tcStore->game_event_gameobject)) {
			$sun_geg = new stdClass;
			$sun_geg->event = $this->tcStore->game_event_gameobject[$guid]->eventEntry;
			$sun_geg->guid = $guid;
			$this->sunStore->game_event_gameobject[$guid] = $sun_geg;
			fwrite($this->file, WriteObject($this->conn, "game_event_gameobject", $sun_geg));
		}
		
		$this->ImportSpawnGroup($guid, false);
		$this->ImportPool($guid, false);
	}
	
	function DeleteSunGameObjectSpawn(int $spawn_id)
	{
		if(CheckAlreadyImported($spawn_id))
			return;
		
		$sql = "DELETE g, c1, c2, sg FROM gameobject g " .
					"LEFT JOIN conditions c1 ON c1.ConditionTypeOrReference = 31 AND c1.ConditionValue1 = 5 AND c1.ConditionValue3 = {$spawn_id} " .
					"LEFT JOIN conditions c2 ON c1.SourceEntry = -{$spawn_id} AND c2.SourceId = 1 AND c2.SourceTypeOrReferenceId = 22 " . //SourceId = SMART_SCRIPT_TYPE_GAMEOBJECT
					"LEFT JOIN spawn_group sg ON sg.spawnId = {$spawn_id} AND spawnType = 1 " . //gob type
					"WHERE g.guid = {$spawn_id};" . PHP_EOL;
		fwrite($this->file, $sql);
				
		//warn smart scripts references removal
		$results = FindAll($this->sunStore->smart_scripts, "entryorguid", -$spawn_id);
		foreach($results as $result) {
			if($result->source_type != SmartSourceType::gameobject)
				continue;
			
			echo "WARNING: Deleting a gameobject (guid: {$spawn_id}) with a per guid SmartScripts ({$result->entryorguid}, {$result->id}). Smart scripts ref has been left as is." . PHP_EOL;
		}
		
		$results = FindAll($this->sunStore->smart_scripts, "target_param1", $spawn_id);
		foreach($results as $result) {
			if($result->target_type != SmartTarget::GAMEOBJECT_GUID)
				continue;
			
			echo "WARNING: Deleting gameobject (guid: {$spawn_id}) targeted by a smartscript ({$result->entryorguid}, {$result->id}). Smart scripts ref has been left as is." . PHP_EOL;
		}
	}

	function DeleteSunGameObjects(int $gob_id, array $not_in)
	{
		if(CheckAlreadyImported($gob_id))
			return;
		
		$results = FindAll($this->sunStore->gameobject, "id", $gob_id);
		foreach($results as $result) {
			if(!in_array($result->guid, $not_in))
				$this->DeleteSunGameObjectSpawn($result->guid);
		}
	}

	function CreateReplaceAllGameObject(int $gob_id, int $patch_min = 0, int $patch_max = 10)
	{
		if(CheckAlreadyImported($gob_id))
			return;
			
		$results = FindAll($this->tcStore->gameobject, "id", $gob_id);
		if(empty($results)) {
			echo "ERROR: Failed to find any TC gameobject with id {$gob_id}" . PHP_EOL;
			assert(false);
			exit(1);
		}
						
		$tc_guids = [];
		foreach($results as $tc_gob) {
			array_push($tc_guids, $tc_gob->guid);
			if(!array_key_exists($tc_gob->guid, $this->sunStore->gameobject))
				$this->ImportTCGameObject($tc_gob->guid, $patch_min, $patch_max);
		}
		$this->DeleteSunGameObjects($gob_id, $tc_guids);
	}
};
