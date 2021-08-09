<?php

/*TODO:
- creature_text not always imported?
- make smart_scripts ported for tlk be patch 5
- check spell existence on tbc (else, patch 5 as well)
- conditions on menu options don't check patch 5 either
*/

//declare(strict_types = 1);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL | E_STRICT);
ini_set('memory_limit','8096M'); //for the DB stores

set_error_handler(function($severity, $message, $file, $line) {
    if (error_reporting() & $severity) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
});

include_once(__DIR__ . '/enums.php');
include_once(__DIR__ . '/helpers.php');
include_once(__DIR__ . '/smartai.php');
include_once(__DIR__ . '/../config.php');

abstract class LoadMode
{
	const sunstrider = 0;
	const trinitycore = 1;
}

class ImportException extends Exception 
{ 
	function __construct (string $message = "", bool $error = true) 
	{
		$this->_error = $error;
		parent::__construct($message);
	}
	
	public $_error;
}

$debug = false;
$errors = 0;
$warnings = 0;
function LogError($msg)
{
	global $errors, $file;

	echo "ERROR: {$msg}" . PHP_EOL;
	fwrite($file, "-- ERROR {$msg}" . PHP_EOL);
	++$errors;
}

//expect ImportException
function LogException($e, string $msg = "")
{
	if ($msg == "")
		$msg = $e->getMessage();
	
	if ($e->_error)
		LogError($msg);
	else
		LogWarning($msg);
}

function LogWarning(string $msg)
{
	global $warnings, $file;

	echo "WARNING: {$msg}" . PHP_EOL;
	fwrite($file, "-- WARNING {$msg}" . PHP_EOL);
	++$warnings;
}

function LogNotice(string $msg)
{
	global $file;

	echo "NOTICE: {$msg}" . PHP_EOL;
	fwrite($file, "-- NOTICE {$msg}" . PHP_EOL);
}

function LogDebug(string $msg)
{
	global $debug, $file;

	if ($debug)
		echo "{$msg}" . PHP_EOL;

	fwrite($file, "-- DEBUG {$msg}" . PHP_EOL);
}

class DBStore
{
	public $broadcast_text = null; //key is broadcast id
	public $conditions = null; //key has NO MEANING
	public $creature = null; //key is spawnID TODO: should be NO MEANING
	public $creature_addon = null; //key is spawnID
	public $creature_entry = null; //key has NO MEANING
	public $creature_formations = null; //key is memberGUID
	public $creature_loot_template = null; //key is Entry
	public $creature_model_info = null; //key is modelid
	public $creature_summon_groups = null; //key has NO MEANING
	public $creature_template = null; //key has NO MEANING
	public $creature_template_addon = null; //key has NO MEANING
	public $creature_template_movement = null; //key has NO MEANING
	public $creature_template_resistance = null; //key has NO MEANING
	public $creature_template_spell = null; //key has NO MEANING
	public $creature_text = null; //key has NO MEANING
	public $game_event_creature = null; //key is spawnID
	public $gameobject = null; //key is spawnID
	public $game_event_gameobject = null; //key is spawnID
	public $gameobject_template = null;  //key is entry
	public $gossip_menu = null; //key has NO MEANING
	public $gossip_menu_option = null; //key has NO MEANING
	public $gossip_text = null; //key is text id
	public $item_template = null; //key is item id
	public $pickpocketing_loot_template = null; //key is Entry
	public $points_of_interest = null; //key has NO MEANING
	public $pool_members = null; //key has NO MEANING
	public $pool_template = null; //key is pool entry
	public $reference_loot_template = null; //key has NO MEANING
	public $skinning_loot_template = null; //key is Entry
	public $smart_scripts = null; //key has NO MEANING
	public $spawn_group = null; //key has NO MEANING
	public $spell_template = null; //key is spell id
	public $trainer = null; //key is ID
	public $trainer_spell = null; //key has NO MEANING
	public $waypoint_info = null; //key is ID
	public $waypoint_data = null; //key has NO MEANING
	public $waypoint_scripts = null; //key has NO MEANING
	public $waypoints = null; //key has NO MEANING
	
	private $loadmode = null;
    private $databaseName;
    
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
        $this->databaseName = $databaseName;
	}

    function LoadTableNoKey(&$conn, string $tableName)
    {
		$stmt = $conn->query("SELECT * FROM {$this->databaseName}.{$tableName}");
		$stmt->setFetchMode(PDO::FETCH_OBJ);
		$this->$tableName = $stmt->fetchAll();
    }
    
    function LoadTableWithKey(&$conn, string $tableName, string $keyName)
    {
        $stmt = $conn->query("SELECT * FROM {$this->databaseName}.{$tableName}");
		$stmt->setFetchMode(PDO::FETCH_OBJ);
		foreach($stmt->fetchAll() as $v)
        {
			$this->$tableName[$v->$keyName] = $v;
            //HACK //special handling for leader, it's always NULL in db for leader
            if ($tableName == "creature_formations" && !$v->leaderGUID)
                $this->creature_formations[$v->memberGUID]->leaderGUID = $v->memberGUID;
        }
    }
    
    function LoadTable(&$conn, string $tableName)
    {
        global $loadTableInfos;
        
        if ($this->$tableName != null)
            return; //already loaded
        
        if (!isset($loadTableInfos[$tableName]))
			throw new ImportException("Could not find load table info for table {$tableName}");
        
        $this->$tableName = [];
        $key = $this->loadmode == LoadMode::sunstrider ? $loadTableInfos[$tableName]->sunKey : $loadTableInfos[$tableName]->tcKey;
        
        if ($key != null)
            $this->LoadTableWithKey($conn, $tableName, $key);
        else
            $this->LoadTableNoKey($conn, $tableName, $key);
    }
}

class LoadTableInfo
{
    public $sunKey;
    public $tcKey;
    public $disableSun;
    public $disableTC;
    
	function __construct(string $sun = null, string $tc = null, bool $disableSun = false, bool $disableTC = false) 
    {
        $sunKey = $sun;
        $tcKey = $tc;
        $disableSun = $disableSun;
        $disableTC = $disableTC;
    }
}

$loadTableInfos = [];
$loadTableInfos["broadcast_text"] = new LoadTableInfo("ID", "ID");
$loadTableInfos["creature"] = new LoadTableInfo("spawnID", "spawnID"); //key is spawnID TODO: should be NO MEANING (have to change usage)
$loadTableInfos["creature_addon"] = new LoadTableInfo("spawnID", "guid");
$loadTableInfos["creature_entry"] = new LoadTableInfo(null, null, false, true);
$loadTableInfos["conditions"] = new LoadTableInfo();
$loadTableInfos["creature_formations"] = new LoadTableInfo("memberGUID", "memberGUID");
$loadTableInfos["creature_loot_template"] = new LoadTableInfo("Entry", "Entry");
$loadTableInfos["creature_model_info"] = new LoadTableInfo("modelid", "DisplayID");
$loadTableInfos["creature_summon_groups"] = new LoadTableInfo();
$loadTableInfos["creature_template"] = new LoadTableInfo();
$loadTableInfos["creature_template_addon"] = new LoadTableInfo();
$loadTableInfos["creature_template_movement"] = new LoadTableInfo();
$loadTableInfos["creature_template_resistance"] = new LoadTableInfo();
$loadTableInfos["creature_template_spell"] = new LoadTableInfo();
$loadTableInfos["creature_text"] = new LoadTableInfo();
$loadTableInfos["game_event_creature"] = new LoadTableInfo("guid", "guid");
$loadTableInfos["game_event_gameobject"] = new LoadTableInfo("guid", "guid");
$loadTableInfos["gameobject"] = new LoadTableInfo("spawnID", "guid");
$loadTableInfos["gameobject_template"] = new LoadTableInfo("entry", "entry");
$loadTableInfos["gossip_menu"] = new LoadTableInfo();
$loadTableInfos["gossip_menu_option"] = new LoadTableInfo();
$loadTableInfos["gossip_text"] = new LoadTableInfo("ID", null, false, true); // npc_text on TC
$loadTableInfos["item_template"] = new LoadTableInfo("entry", "entry");
$loadTableInfos["npc_text"] = new LoadTableInfo(null, "ID", true, false); // gossip_text on Sun
$loadTableInfos["pickpocketing_loot_template"] = new LoadTableInfo("Entry", "Entry");
$loadTableInfos["points_of_interest"] = new LoadTableInfo();
$loadTableInfos["pool_members"] = new LoadTableInfo();
$loadTableInfos["pool_template"] = new LoadTableInfo("entry", "entry");
$loadTableInfos["reference_loot_template"] = new LoadTableInfo("Entry", "Entry");
$loadTableInfos["skinning_loot_template"] = new LoadTableInfo("Entry", "Entry");
$loadTableInfos["smart_scripts"] = new LoadTableInfo();
$loadTableInfos["spawn_group"] = new LoadTableInfo();
$loadTableInfos["spell_template"] = new LoadTableInfo("entry", null, false, true);
$loadTableInfos["trainer"] = new LoadTableInfo("Id", "Id");
$loadTableInfos["trainer_spell"] = new LoadTableInfo();
$loadTableInfos["waypoints"] = new LoadTableInfo();
$loadTableInfos["waypoint_data"] = new LoadTableInfo();
$loadTableInfos["waypoint_info"] = new LoadTableInfo("id", null, false, true);
$loadTableInfos["waypoint_scripts"] = new LoadTableInfo("id", "id");

class DBConverter
{
	public $conn;
	public $file;
	
	public $tcStore;
	public $sunStore;
	
	//Import referenced smartai even if it already has a scriptname
	private $smartImportContagious = false;
	//always import those regardless of smartImportContagious
	private $forceImport = array("npc_obsidia", "npc_kalaran_windblade", "mobs_spitelashes");
	
	function __construct(&$file)
	{
		global $sunWorld, $tcWorld, $login, $password;
		
		$this->file = $file;
		
		// Connect
		$this->conn = new PDO("mysql:host=localhost", $login, $password);
		$this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		$this->sunStore = new DBStore($this->conn, $sunWorld, LoadMode::sunstrider);
		$this->tcStore  = new DBStore($this->conn, $tcWorld,  LoadMode::trinitycore);
	}
    
    function LoadTable(string $tableName)
    {
		echo "Loading tables {$tableName}... ";
        
        $this->sunStore->LoadTable($this->conn, $tableName);
        $this->tcStore->LoadTable($this->conn, $tableName);
        
		echo "   Done" . PHP_EOL;
    }
	
	/* This test pass if:
	- sunContainer does not contain key with this value
	- sunContainer does contain key with value but has the same as tcContainer
	Else, crash everything
	*/
	function CheckExistsBroadcast(string $tableName, string $keyname, $value)
	{
		$sunResults = FindAll($this->sunStore->$tableName, $keyname, $value);
		if (empty($sunResults))
			return;
		
		$tcResults = FindAll($this->tcStore->$tableName, $keyname, $value);
		if (empty($tcResults))
			throw new ImportException("Checked for broadcast existence but TC has no value?");
		
		$sunMaleText = substr($sunResults[0]->MaleText, 0, 255);
		$tcMaleText = substr($tcResults[0]->MaleText, 0, 255);
		if (levenshtein($sunMaleText, $tcMaleText) > 2) { //allow very similar strings
		//if ($sunResults != $tcResults) { //does this work okay? This is supposed to compare keys + values, but we don't care about keys.
			//var_dump($sunResults);
			//var_dump($tcResults);
			throw new ImportException("TC and SUN containers have different results for table {$tableName} and value {$value}");
		}
		
		//OK
		return;
	}

	function CheckBroadcast(int $broadcast_id)
	{
		if (!array_key_exists($broadcast_id, $this->tcStore->broadcast_text))
			throw new ImportException("BroadcastText $broadcast_id does not exists in TC db");

		if (!array_key_exists($broadcast_id, $this->sunStore->broadcast_text))
			throw new ImportException("BroadcastText $broadcast_id does not exists in Sun db");

		$this->CheckExistsBroadcast("broadcast_text", "ID", $broadcast_id);
	}

	//overrideCheckSourceEntry is for menus that changed id, condition must apply to that new menu
	function SunHasCondition(&$tc_condition, $overrideCheckSourceEntry = null)
	{
        $this->LoadTable("conditions");
        
		//probably wrong... can't use the $key for sunstore here
		foreach(array_keys($this->sunStore->conditions) as $key) {
			if (   $tc_condition->SourceTypeOrReferenceId == $this->sunStore->conditions[$key]->SourceTypeOrReferenceId
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
			if ($this->tcStore->conditions[$key]->SourceTypeOrReferenceId != $CONDITION_SOURCE_TYPE_GOSSIP_MENU)
			   continue;
			   
			if ($this->tcStore->conditions[$key]->SourceGroup != $tc_menu_id) 
			   continue;
			   
			if ($this->tcStore->conditions[$key]->SourceEntry != $tc_text_id) 
			   continue;
		   
			if ($this->SunHasCondition($this->tcStore->conditions[$key], $sun_menu_id)) {
				LogDebug("Sun db already has this condition (tc menu {$tc_menu_id}, sun menu {$sun_menu_id})");
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
			if ($this->tcStore->conditions[$key]->SourceTypeOrReferenceId != $CONDITION_SOURCE_TYPE_GOSSIP_MENU_OPTION)
			   continue;
			   
			if ($this->tcStore->conditions[$key]->SourceGroup != $tc_menu_id) //SourceGroup = menu_id / SourceEntry = option_id
			   continue;
		   
			if ($this->SunHasCondition($this->tcStore->conditions[$key], $sun_menu_id)) {
				LogDebug("Sun db already has this condition (tc menu {$tc_menu_id}, sun menu {$sun_menu_id})");
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
		if (array_key_exists($tc_text_id, $this->movedTCTexts)) {
			LogDebug("Text {$tc_text_id} is already imported as " . $this->movedTCTexts[$tc_text_id]);
			return $this->movedTCTexts[$tc_text_id];
		}
		
		if (CheckAlreadyImported($tc_text_id)) {
			LogDebug("Text {$tc_text_id} is already imported");
			return $tc_text_id;
		}
		
        $this->LoadTable("gossip_text");
        
		LogDebug("Importing text {$tc_text_id}");
		
		if (!array_key_exists($tc_text_id, $this->tcStore->gossip_text)) {
			echo "TextId {$tc_text_id} does not exists in TC db?" . PHP_EOL;
			assert(false);
			exit(1);
		}
		
		$tc_text = &$this->tcStore->gossip_text[$tc_text_id];
		$sun_text_id = $tc_text_id;
		if (array_key_exists($tc_text_id, $this->sunStore->gossip_text)) {
			$sun_text = $this->sunStore->gossip_text[$tc_text_id];
			if ($sun_text->text0_0 == $tc_text->text0_0 && $sun_text->text0_1 == $tc_text->text0_1) {
				array_push($this->reusedSunTexts, $tc_text_id);
				LogDebug("Text {$tc_text_id} already present in Sun DB"); //same text, stop here
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
			if ($broadcast_id = $tc_text->$fieldName) {
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
		if (CheckAlreadyImported($poi_id)) {
			LogDebug("POI {$poi_id} is already imported");
			return;
		}
		
        $this->LoadTable("points_of_interest");
        
		$results = FindAll($this->tcStore->points_of_interest, "ID", $poi_id);
		if (count($results) != 1) {
			echo "TC points_of_interest has 0 or > 1 PoI for id {$poi_id}" . PHP_EOL;
			assert(false);
			exit(1);
		}
		
		$tc_poi = $results[0];
		
		//we assume if we have a poi with this id, it's already the same
		$results = FindAll($this->sunStore->points_of_interest, "ID", $poi_id);
		if (count($results) > 0) {
			LogDebug("POI {$poi_id} already present in sun db");
			return;
		}
		
		$sun_poi = $tc_poi; //simple copy
		$sun_poi->Icon = ConvertPoIIcon($tc_poi->Icon);
		fwrite($this->file, WriteObject($this->conn, "points_of_interest", $sun_poi));
			array_push($this->sunStore->points_of_interest, $sun_poi);
		
		if ($tc_poi->Icon != $sun_poi->Icon) {
			$sun_poi_tlk = $tc_poi;
			$sun_poi_tlk->patch = 5; //LK patch
			fwrite($this->file, WriteObject($this->conn, "points_of_interest", $sun_poi_tlk));
			array_push($this->sunStore->points_of_interest, $sun_poi_tlk);
		} 
	}

	function CreateMenuOptions(int $tc_menu_id, int $sun_menu_id)
	{
		if (CheckAlreadyImported($tc_menu_id)) {
			LogDebug("Menu options for {$tc_menu_id} are already imported");
			return;
		}
		
        $this->LoadTable("gossip_menu_option");
        
		$results = FindAll($this->tcStore->gossip_menu_option, "MenuID", $tc_menu_id);
		if (empty($results))
			return; //no menu options found, this is a normal case
		
		foreach($results as $tc_option) {
			LogDebug("Importing tc menu option {$tc_menu_id}|{$tc_option->OptionID} into sun menu {$sun_menu_id}");
			
			$sun_option = new stdClass; //anonymous object
			$sun_option->MenuID = $sun_menu_id;
			$sun_option->OptionID = $tc_option->OptionID;
			$sun_option->OptionIcon = $tc_option->OptionIcon;
			$sun_option->OptionText = $tc_option->OptionText;
			if ($broadcast_id1 = $tc_option->OptionBroadcastTextID) {
				$this->CheckBroadcast($broadcast_id1);
				$sun_option->OptionBroadcastTextID = $broadcast_id1;
			} else {
				$sun_option->OptionBroadcastTextID = 'NULL';
			}
			$sun_option->OptionType = $tc_option->OptionType;
			$sun_option->OptionNpcFlag = $tc_option->OptionNpcFlag;
			if ($tc_option->ActionMenuID) {
				$new_sun_menu_id = $this->CreateMenu($tc_option->ActionMenuID);
				$sun_option->ActionMenuID = $new_sun_menu_id;
			} else 
				$sun_option->ActionMenuID = 'NULL';
			
			if ($tc_option->ActionPoiID) {
				$this->CreatePOI($tc_option->ActionPoiID);
				$sun_option->ActionPoiID = $tc_option->ActionPoiID; //may be NULL
			} else {
				$sun_option->ActionPoiID = 'NULL';
			}
			$sun_option->BoxCoded = $tc_option->BoxCoded; 
			$sun_option->BoxMoney = $tc_option->BoxMoney; 
			$sun_option->BoxText = $tc_option->BoxText;
			if ($broadcast_id2 = $tc_option->BoxBroadcastTextID) {
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
		if (CheckAlreadyImported($sunMenuID))
			return;
		
        $this->LoadTable("creature_template");
        $this->LoadTable("gossip_menu");
        
		//only delete if only one menu is found
		$results = FindAll($this->sunStore->creature_template, "gossip_menu_id", $sunMenuID);
		if (empty($results)) {
			echo "ERROR: Trying to delete non existing sun menu {$sunMenuID}" . PHP_EOL;
			assert(false);
			exit(1);
		}
		
		if (sizeof($results) > 1)
			return; //more than one ref to this menu, skip
		
		$sql = "DELETE FROM gossip_menu WHERE MenuID = {$sunMenuID};" . PHP_EOL;
		
		RemoveAny($this->sunStore->gossip_menu, "MenuID", $sunMenuID);
		
		//currently bugged, because we reuse text that are the same
		/*
		$results2 = FindAll($this->sunStore->gossip_menu, "MenuID", $sunMenuID);
		foreach($results2 as $sun_menu) {
			$text_id = $sun_menu->text_id;
			if (array_key_exists($text_id, $this->reusedSunTexts))
				continue; //we use it!
			$results3 = FindAll($this->sunStore->gossip_text, "ID", $text_id);	
			if (sizeof($results3) > 1 || array_key_exists($text_id, $this->reusedSunTexts))
				continue; //more than one ref to this text, skip
				
			$sql .= "DELETE FROM gossip_text WHERE ID = {$text_id};" . PHP_EOL;
		}*/
		fwrite($this->file, $sql);
	}

	private $convertedTCMenus = [];

	//return sun menu
	function CreateMenu(int $tc_menu_id) : int
	{
		if (array_key_exists($tc_menu_id, $this->convertedTCMenus))
		{
			LogDebug("Menu {$tc_menu_id} is already imported as " . $this->convertedTCMenus[$tc_menu_id]);
			return $this->convertedTCMenus[$tc_menu_id];
		}
		
		if (CheckAlreadyImported($tc_menu_id)) {
			LogDebug("Menu {$tc_menu_id} is already imported");
			return $tc_menu_id;
		}
		
        $this->LoadTable("gossip_menu");
        
		$this->timelol("CM1");
		
		$results = FindAll($this->tcStore->gossip_menu, "MenuID", $tc_menu_id);
		if (empty($results))
			throw new ImportException("Failed to find TC menu {$tc_menu_id}");
		
		$this->timelol("CM2");
		
		$sun_menu_id = null;
		if (HasAny($this->sunStore->gossip_menu, "MenuID", $tc_menu_id)) {
			//would be very complicated to compare menus... just import a new one
			$sun_menu_id = GetHighest($this->sunStore->gossip_menu, "MenuID") + 1;
			$this->convertedTCMenus[$tc_menu_id] = $sun_menu_id;
			LogDebug("Importing menu {$tc_menu_id} as {$sun_menu_id}");
		}
		else
			$sun_menu_id = $tc_menu_id;
		
		$this->timelol("CM3");
		
		foreach($results as $tc_menu) {
			$tc_text_id = $tc_menu->TextID;
			
			LogDebug("Importing tc menu {$tc_menu_id} (text {$tc_text_id}) into sun menu {$sun_menu_id}");
			
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
        $this->LoadTable("smart_scripts");
        
		$results = [];
		
		$this->timelol("FA1");
		
		/*
		$ref = $sun ? $this->sunStore->smart_scripts : $this->tcStore->smart_scripts;
		foreach($ref as $smart_script) {
			if ($smart_script->entryorguid == $entryorguid && $smart_script->source_type == $source_type)
				array_push($results, $smart_script);
		}*/
		
		if ($sun) {
			foreach(array_keys($this->sunStore->smart_scripts) as $key) {
				if ($this->sunStore->smart_scripts[$key]->entryorguid == $entryorguid && $this->sunStore->smart_scripts[$key]->source_type == $source_type)
					array_push($results, $this->sunStore->smart_scripts[$key]);
			}
		}
		else {
			foreach(array_keys($this->tcStore->smart_scripts) as $key) {
				if ($this->tcStore->smart_scripts[$key]->entryorguid == $entryorguid && $this->tcStore->smart_scripts[$key]->source_type == $source_type) {
					array_push($results, $this->tcStore->smart_scripts[$key]);
				}
			}
		}
		$this->timelol("FA2");
		
		return $results;
	}

	function DeleteAllSmart(int $entryorguid, int $source_type)
	{
        $this->LoadTable("smart_scripts");
        
		$this->timelol("a");
		
		if (CheckAlreadyImported($entryorguid + $source_type << 28)) //max entry is 30.501.000 (smaller number with 28 bits shift is 268.435.456)
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
			if ($this->sunStore->smart_scripts[$key]->entryorguid == $entryorguid && $this->sunStore->smart_scripts[$key]->source_type == $source_type)
				unset($this->sunStore->smart_scripts[$key]);
		}
				
		/*
		foreach($this->sunStore->smart_scripts as $k => $smart_script) {
			if ($smart_script->entryorguid == $entryorguid && $smart_script->source_type == $source_type)
				unset($this->sunStore->smart_scripts[$k]);
		}*/

		$this->timelol("d");
	}

	function CreateCreatureText(int $tc_entry)
	{
		if (CheckAlreadyImported($tc_entry)) {
			LogDebug("Creature text {$tc_entry} is already imported");
			return;
		}
        $this->LoadTable("creature_text");
		
		$this->timelol("CCT1");
		
		$results = FindAll($this->tcStore->creature_text, "CreatureID", $tc_entry);
		if (empty($results)) 
			throw new ImportException("ERROR: Could not find TC creature_text for creature id {$tc_entry}");
		
		$sql = "DELETE FROM creature_text WHERE CreatureID = {$tc_entry};" . PHP_EOL;
		fwrite($this->file, $sql);
		
		$this->timelol("CCT2");
		foreach($results as $text_entry) {
			if ($broadcast_id = $text_entry->BroadcastTextId)
				$this->CheckBroadcast($broadcast_id);
			
			array_push($this->sunStore->creature_text, $text_entry);
			fwrite($this->file, WriteObject($this->conn, "creature_text", $text_entry)); 
		}
		$this->timelol("CCT3");
	}

	function CreateSmartConditions(int $tc_entry, int $source_type)
	{
		if (CheckAlreadyImported($tc_entry + $source_type << 28)) { //max entry is 30.501.000 (smaller number with 28 bits shift is 268.435.456)
			LogDebug("Smart condition {$tc_entry} {$source_type} is already imported");
			return;
		}
		
        $this->LoadTable("conditions");
        
		static $CONDITION_SOURCE_TYPE_SMART_EVENT = 22;
		 
		foreach(array_keys($this->tcStore->conditions) as $key) {
			if ($this->tcStore->conditions[$key]->SourceTypeOrReferenceId != $CONDITION_SOURCE_TYPE_SMART_EVENT)
			   continue;
			   
			//SourceGroup == id, but we import all
			   
			if ($this->tcStore->conditions[$key]->SourceEntry != $tc_entry) 
			   continue;
		   
			if ($this->tcStore->conditions[$key]->SourceId != $source_type) 
			   continue;
		   
			if ($this->SunHasCondition($this->tcStore->conditions[$key])) {
				LogDebug("Sun db already has this condition (entry: {$tc_entry})");
				continue;
			}
			$sun_condition = $this->tcStore->conditions[$key]; //copy
			$sun_condition->Comment = "(autoimported) " . $this->tcStore->conditions[$key]->Comment;
			
			fwrite($this->file, WriteObject($this->conn, "conditions", $sun_condition));
		}
	}

	function ImportSummonGroup(int $npcEntry, int $groupID)
	{
		if (CheckAlreadyImported($npcEntry + $groupID << 28)) {
			LogDebug("Summon group for npc {$npcEntry} group {$groupID} is already imported");
			return;
		}
		
        $this->LoadTable("creature_summon_groups");
        
		// could be improved, currently we just port everything and ignore group
		$results = FindAll($this->tcStore->creature_summon_groups, "summonerId", $npcEntry);
		if (empty($results))
			throw new ImportException("Could not find TC summon group {$npcEntry}");

		$sunResults = FindAll($this->sunStore->creature_summon_groups, "summonerId", $npcEntry);
		if ($results == $sunResults) {
			LogDebug("Summon group for npc {$npcEntry} group {$groupID} is already existing and identical");
			return;
		}
	
		$sql = "DELETE FROM creature_summon_groups WHERE summonerId = {$npcEntry};" . PHP_EOL;
		fwrite($this->file, $sql);
		RemoveAny($this->sunStore->creature_summon_groups, "summonerId", $npcEntry);

		foreach($results as $tc_group) {
			array_push($this->sunStore->creature_summon_groups, $tc_group);
			fwrite($this->file, WriteObject($this->conn, "creature_summon_groups", $tc_group)); 

			$entry = $tc_group->entry;
			$dummy = 0;
			try {
				$this->CheckImportCreatureSmart($entry, $dummy, false);
			} catch (ImportException $e) {
				LogException($e, "Summon group {$npcEntry} group {$groupID}: Failed to import smart for summoned creature: {$e->getMessage()}");
				continue;
			}
		}
	}

	function CreateSmartWaypoints(int $path_id)
	{
		if (CheckAlreadyImported($path_id)) {
			LogDebug("Smart Waypoints {$path_id} are already imported");
			return;
		}
        
        $this->LoadTable("waypoints");
		
		$results = FindAll($this->tcStore->waypoints, "entry", $path_id);
		if (empty($results)) 
			throw new ImportException("Could not find TC waypoints {$path_id}");
		
		$sunResults = FindAll($this->sunStore->waypoints, "entry", $path_id);
		if (!empty($sunResults)) {
			if ($sunResults != $results) //does this work okay? This is supposed to compare keys + values, but we don't care about keys.
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
	
    function ConvertFlagsExtraTCtoSun(int $tcFlags) : int
    {
        // first 8 flags are the same
        $sunFlags = $tcFlags & 0xFF; 
        
        if ($tcFlags & TCCreatureFlagsExtra::CREATURE_FLAG_EXTRA_NO_TAUNT)
            $sunFlags |= SunCreatureFlagsExtra::CREATURE_FLAG_EXTRA_NO_TAUNT;
        if ($tcFlags & TCCreatureFlagsExtra::CREATURE_FLAG_EXTRA_GHOST_VISIBILITY)
            $sunFlags |= SunCreatureFlagsExtra::CREATURE_FLAG_EXTRA_GHOST_VISIBILITY;
        if ($tcFlags & TCCreatureFlagsExtra::CREATURE_FLAG_EXTRA_GUARD)
            $sunFlags |= SunCreatureFlagsExtra::CREATURE_FLAG_EXTRA_GUARD;
        if ($tcFlags & TCCreatureFlagsExtra::CREATURE_FLAG_EXTRA_NO_CRIT)
            $sunFlags |= SunCreatureFlagsExtra::CREATURE_FLAG_EXTRA_NO_CRIT;
        if ($tcFlags & TCCreatureFlagsExtra::CREATURE_FLAG_EXTRA_ALL_DIMINISH)
            $sunFlags |= SunCreatureFlagsExtra::CREATURE_FLAG_EXTRA_ALL_DIMINISH;
        if ($tcFlags & TCCreatureFlagsExtra::CREATURE_FLAG_EXTRA_NO_PLAYER_DAMAGE_REQ)
            $sunFlags |= SunCreatureFlagsExtra::CREATURE_FLAG_EXTRA_NO_PLAYER_DAMAGE_REQ;
        
        //others have no equivalence as of writing (23/07/2021)
        
        return $sunFlags;
    }
    
    function ImportCreatureTemplateAddon(int $creature_id)
    {
		if (CheckAlreadyImported($creature_id))
			return;
        
        $this->LoadTable("creature_template_addon");
        
		$tc_results = FindAll($this->tcStore->creature_template_addon, "entry", $creature_id);
		if (empty($tc_results))
            return;
        
		$sun_results = FindAll($this->sunStore->creature_template_addon, "entry", $creature_id);
		if (!empty($sun_results))
			throw new ImportException("Trying to import already existing creature template addon {$creature_id}");

        // copy TC one and make some arrangements
		$creature_template_addon = $tc_results[0];
        $creature_template_addon->patch = 5;
        $standState = $creature_template_addon->bytes1 & 0xF; // first byte is stand state
        $creature_template_addon->standState = $standState;
        unset($creature_template_addon->bytes1);
        unset($creature_template_addon->bytes2);

        if ($creature_template_addon->path_id)
            $creature_template_addon->path_id = $this->ImportWaypoints(0, $creature_template_addon->path_id);
        else
            $creature_template_addon->path_id = null;
        
		array_push($this->sunStore->creature_template_addon, $creature_template_addon);
		fwrite($this->file, WriteObject($this->conn, "creature_template_addon", $creature_template_addon)); 
    }

    function ImportCreatureTemplateMovement(int $creature_id)
    {
		if (CheckAlreadyImported($creature_id))
			return;

        $this->LoadTable("creature_template_movement");

		$tc_results = FindAll($this->tcStore->creature_template_movement, "CreatureId", $creature_id);
		if (empty($tc_results))
            return;

		$sun_results = FindAll($this->sunStore->creature_template_movement, "CreatureId", $creature_id);
		if (!empty($sun_results))
			throw new ImportException("Trying to import already existing creature template movement {$creature_id}");

        // copy TC one and make some arrangements
		$creature_template_movement = $tc_results[0];
        unset($creature_template_movement->InteractionPauseTimer);
        
		array_push($this->sunStore->creature_template_movement, $creature_template_movement);
		fwrite($this->file, WriteObject($this->conn, "creature_template_movement", $creature_template_movement)); 
    }

    function ImportCreatureTemplateResistance(int $creature_id)
    {
		if (CheckAlreadyImported($creature_id))
			return;

        $this->LoadTable("creature_template_resistance");
        
        $tc_results = FindAll($this->tcStore->creature_template_resistance, "CreatureID", $creature_id);
		if (empty($tc_results))
            return;
        
		$sun_results = FindAll($this->sunStore->creature_template_resistance, "CreatureID", $creature_id);
		if (!empty($sun_results))
			throw new ImportException("Trying to import already existing creature template resistance {$creature_id}");

        // copy TC one and make some arrangements
		$creature_template_resistance = $tc_results[0];
        $creature_template_resistance->patch = 5;
        unset($creature_template_resistance->VerifiedBuild);
        
		array_push($this->sunStore->creature_template_resistance, $creature_template_resistance);
		fwrite($this->file, WriteObject($this->conn, "creature_template_resistance", $creature_template_resistance)); 
    }
    
    function ImportCreatureTemplateSpell(int $creature_id)
    {
		if (CheckAlreadyImported($creature_id))
			return;
        
        $this->LoadTable("creature_template_spell");
        
        $tc_results = FindAll($this->tcStore->creature_template_spell, "CreatureID", $creature_id);
		if (empty($tc_results))
            return;
        
		$sun_results = FindAll($this->sunStore->creature_template_spell, "CreatureID", $creature_id);
		if (!empty($sun_results))
			throw new ImportException("Trying to import already existing creature template spells {$creature_id}");

        // copy TC one and make some arrangements
		$creature_template_spell = $tc_results[0];
        $creature_template_spell->patch = 5;
        unset($creature_template_spell->VerifiedBuild);
        
		array_push($this->sunStore->creature_template_spell, $creature_template_spell);
		fwrite($this->file, WriteObject($this->conn, "creature_template_spell", $creature_template_spell)); 
    }
    
	function ImportCreatureTemplate(int $creature_id, bool $force = true)
	{
		if (CheckAlreadyImported($creature_id))
			return;
        
        $this->LoadTable("creature_template");
        
		$tc_results = FindAll($this->tcStore->creature_template, "entry", $creature_id);
		if (empty($tc_results))
			throw new ImportException("Trying to import non existing TLK creature template {$creature_id}");
        
		$sun_results = FindAll($this->sunStore->creature_template, "entry", $creature_id);
		if (!empty($sun_results))
        {
            if ($force)
                RemoveAny($this->tcStore->creature_template, "entry", $creature_id);
            else
                throw new ImportException("Trying to import already existing creature template {$creature_id}");
        }

        // copy TC one and make some arrangements
		$creature_template = $tc_results[0];
		$creature_template->patch = 5;
        unset($creature_template->import);
        unset($creature_template->movementId);
        unset($creature_template->RegenHealth);
        unset($creature_template->VerifiedBuild);
        
        $creature_template->flags_extra = $this->ConvertFlagsExtraTCtoSun($creature_template->flags_extra);
        $creature_template->lootid = null; //TODO 
        $creature_template->pickpocketloot = null; //TODO 
        $creature_template->skinloot = null; //TODO 
        $creature_template->gossip_menu_id = null; //TODO 
        
        $this->ImportCreatureTemplateAddon($creature_id);
        $this->ImportCreatureTemplateMovement($creature_id);
        $this->ImportCreatureTemplateResistance($creature_id);
        $this->ImportCreatureTemplateSpell($creature_id);
        
		array_push($this->sunStore->creature_template, $creature_template);
		fwrite($this->file, WriteObject($this->conn, "creature_template", $creature_template)); 
	}

	function CheckImportCreatureSmart(int $creature_id, int& $patch, bool $hasToBeSmart) 
	{
		if (CheckAlreadyImported($creature_id))
			return;
		
        $this->LoadTable("creature_template");
        $this->LoadTable("smart_scripts");
        
		$tc_results = FindAll($this->tcStore->creature_template, "entry", $creature_id);
		if (empty($tc_results))
			throw new ImportException("Has a reference on a non existing creature {$creature_id}");
		
		//Only use first result... TC always has only one entry per creature
		$tc_creature_template = $tc_results[0];
		if ($tc_creature_template->AIName != "SmartAI") {
			if ($hasToBeSmart)
				throw new ImportException("Has a reference on a non Smart creature {$creature_id}");
			else 
				return;
		}
		
		$this->timelol("CIC1");
		
		if (CheckIdentical($this->sunStore->smart_scripts, $this->tcStore->smart_scripts, "entryorguid", $creature_id)) {
			//echo "Already identical, skipping" . PHP_EOL;
			LogDebug("SmartAI for creature {$creature_id} is already in db and identical.");
			return;
		}
		
		$this->timelol("CIC2");
		
		$sun_results = FindAll($this->sunStore->creature_template, "entry", $creature_id);
		if (empty($sun_results)) {
			LogWarning("Targeted creature entry {$creature_id} doesn't exists in our database, importing a creature_template for it and setting this smart line to patch 5.");
			$patch = 5;
			$this->ImportCreatureTemplate($creature_id);
		}
		echo "Importing referenced summon/target creature id {$creature_id}, "; //... continue this line later
		foreach($sun_results as $sun_result) {
			$sunAIName = $sun_result->AIName;
			$sunScriptName = $sun_result->ScriptName;
			
			if ($sunAIName == "" && $sunScriptName == "")
				echo "it currently has no script." . PHP_EOL;
			else {
				if ($this->smartImportContagious || in_array($sunScriptName, $this->forceImport))
					echo "it currently had AIName '{$sunAIName}' and ScriptName '{$sunScriptName}'." . PHP_EOL;
				else {
					echo PHP_EOL;
					throw new ImportException("This would replace a creature which already has a script ({$sunAIName}/{$sunScriptName}), no import.", false);
				}
			}
		}
		
		$this->CreateSmartAI($creature_id, SmartSourceType::creature, SmartSourceType::creature);
		$this->timelol("CIC3");
	}

	function CheckImportGameObjectSmart(int $gob_id, int& $patch, bool $hasToBeSmart)
	{
		if (CheckAlreadyImported($gob_id))
			return;
		
        $this->LoadTable("gameobject_template");
        
		if (!array_key_exists($gob_id, $this->tcStore->gameobject_template))
			throw new ImportException("Smart reference a non existing gameobject {$gob_id}");
		
		if ($this->tcStore->gameobject_template[$gob_id]->AIName != "SmartAI")
			throw new ImportException("Smart reference a non Smart gameobject {$gob_id}");
		
		if (CheckIdentical($this->sunStore->smart_scripts, $this->tcStore->smart_scripts, "entryorguid", $gob_id)) {
			//echo "Already identical, skipping" . PHP_EOL;
			LogDebug("SmartAI for gob {$gob_id} is already in db and identical.");
			return;
		}
		
		$sun_results = FindAll($this->sunStore->gameobject_template, "entry", $gob_id);
		if (empty($sun_results)) {
			LogWarning("Targeted gob entry {$gob_id} doesn't exists in our database");
			$patch = 5;
			return;
		}
		
		echo "Smart TC {$from_entry} targets gameobject id {$gob_id}, also importing it, "; //... continue this line later
		
		$sunAIName = $this->sunStore->gameobject_template[$creature_id]->AIName;
		$sunScriptName = $this->sunStore->gameobject_template[$creature_id]->ScriptName;
		
		if ($sunAIName == "" && $sunScriptName == "")
			echo "it currently has no script." . PHP_EOL;
		else 
			echo "It currently had AIName '{$sunAIName}' and ScriptName '{$sunScriptName}." . PHP_EOL;

		$this->CreateSmartAI($gob_id, SmartSourceType::gameobject, SmartSourceType::gameobject);
	}

	function timelol($id, int $limit = 1)
	{
		global $debug;
		if (!$debug)
			return;
		
		static $start = null;
		if ($start != null)
		{
			$duration = microtime(true) - $start;
			if ($duration > $limit) {
				echo "{$id} - Duration: {$duration}s" . PHP_EOL;
				assert(false);
			}
		}
		$start = microtime(true);
	}

	function CheckImportCreature(int $spawnID)
	{
        $this->LoadTable("creature");
        
		if (!array_key_exists($spawnID, $this->tcStore->creature))
			throw new ImportException("Smart TC trying to target a non existing creature guid {$spawnID}... this is a tc db error. Ignoring.", false);
			
		if (array_key_exists($spawnID, $this->sunStore->creature))
			return; //already present
		
		$creatureID = $this->tcStore->creature[$spawnID]->id;
		$sun_results = FindAll($this->sunStore->creature_template, "entry", $creatureID);
		if (empty($sun_results))
			throw new ImportException("Smart TC trying to target a creature spawnID {$spawnID} (id: {$creatureID}) existing only on TC AND with an entry not existing on TBC. Ignoring.", false);
		
		$name = $sun_results[0]->name;
		LogWarning("Smart TC trying to target a creature spawnID {$spawnID} (id: {$creatureID}) existing only on TC");
		LogWarning("/!\ Importing ALL spawns for creature id {$creatureID} ({$name}). (to avoid this, import the creature before rerunning this script)");
		$this->CreateReplaceAllCreature($creatureID);
	}
	
	function CheckImportGameObject(int $spawnID)
	{
        $this->LoadTable("gameobject");
        
		if (!array_key_exists($spawnID, $this->tcStore->gameobject))
			throw new ImportException("Smart TC trying to target a non existing creature guid {$spawnID} on their own db... this is a tc db error. Ignoring.");

		if (array_key_exists($spawnID, $this->sunStore->gameobject))
			return; //already present
		
		//else import!
		$goID = $this->tcStore->gameobject[$spawnID]->id;
		$sun_results = FindAll($this->sunStore->gameobject_template, "entry", $goID);
		if (empty($sun_results))
			throw new ImportException("Smart TC trying to target a gameobject spawnID {$spawnID} (id: {$goID}) existing only on TC AND with an entry not existing on TBC. Ignoring.");
		
		$name = $sun_results[0]->name;
		
		throw new ImportException("NYI");
	}

	// return creature id for this target (import if missing)
	function GetTargetCreatureId($sun_smart_entry, int $creature_id, int& $patch) : int
	{
        $this->LoadTable("creature");
        
		switch($sun_smart_entry->target_type)
		{
			case SmartTarget::NONE:
			case SmartTarget::SELF:
				break;
			case SmartTarget::CREATURE_DISTANCE:
			case SmartTarget::CLOSEST_CREATURE:
			case SmartTarget::CREATURE_RANGE:
				if ($sun_smart_entry->target_param1)
					$creature_id = $sun_smart_entry->target_param1;
				else if ($sun_smart_entry->target_type != SmartTarget::CREATURE_RANGE)
					throw new ImportException("Target {$sun_smart_entry->target_type} of smart {$sun_smart_entry->entryorguid} id {$sun_smart_entry->id} has no target entry. Wut?");
				break;
			case SmartTarget::CREATURE_GUID:
				$spawnID = $sun_smart_entry->target_param1;
				//$creature_id = $sun_smart_entry->target_param2;
				$results = FindAll($this->tcStore->creature, "guid", $spawnID);
				if (empty($results)) 
					throw new ImportException("Could not find tc creature with guid {$spawnID} for target CREATURE_GUID");
					
				$creature_id = $results[0]->id;
				try {
					$this->CheckImportCreature($spawnID);
				} catch (ImportException $e) {
					throw new ImportException("importing creature spawnID {$spawnID} with error: {$e->getMessage()}", $e->_error);
				}
				break;
			case SmartTarget::VICTIM:
			case SmartTarget::CLOSEST_PLAYER:
			case SmartTarget::PLAYER_DISTANCE:
			case SmartTarget::PLAYER_RANGE:
			case SmartTarget::HOSTILE_SECOND_AGGRO:
			case SmartTarget::HOSTILE_LAST_AGGRO:
			case SmartTarget::HOSTILE_RANDOM:
			case SmartTarget::HOSTILE_RANDOM_NOT_TOP:
			case SmartTarget::GAMEOBJECT_RANGE:
			case SmartTarget::GAMEOBJECT_GUID:
			case SmartTarget::GAMEOBJECT_DISTANCE:
			case SmartTarget::INVOKER_PARTY:
			case SmartTarget::CLOSEST_GAMEOBJECT:
			case SmartTarget::ACTION_INVOKER_VEHICLE:
			case SmartTarget::THREAT_LIST:
			case SmartTarget::LOOT_RECIPIENTS:
			case SmartTarget::FARTHEST:
			case SmartTarget::VEHICLE_ACCESSORY:
			case SmartTarget::CLOSEST_ENEMY:
			case SmartTarget::CLOSEST_FRIENDLY:
			case SmartTarget::POSITION:
			case SmartTarget::ACTION_INVOKER:
			case SmartTarget::STORED:
			case SmartTarget::OWNER_OR_SUMMONER:
				return 0;
			default:
				throw new ImportException("Target {$sun_smart_entry->target_type} NYI for Smart TC {$sun_smart_entry->entryorguid} {$sun_smart_entry->id}");
		}

		if ($creature_id)
		{
			try {
				$this->CheckImportCreatureSmart($creature_id, $patch, false);
			} catch (ImportException $e) {
				throw new ImportException("importing creature smart {$creature_id} with error: {$e->getMessage()}", $e->_error);
			}
		}

		return $creature_id;
	}

	// return gob id for this target (import if missing)
	function GetTargetGameObjectId($sun_smart_entry, int $gob_id, int& $patch) : int
	{
        $this->LoadTable("gameobject");
        
		switch($sun_smart_entry->target_type) {
			case SmartTarget::NONE:
			case SmartTarget::SELF:
				break;
			case SmartTarget::CREATURE_DISTANCE:
			case SmartTarget::CLOSEST_CREATURE:
			case SmartTarget::CREATURE_RANGE:
			case SmartTarget::CREATURE_GUID:
			case SmartTarget::VICTIM:
			case SmartTarget::CLOSEST_PLAYER:
			case SmartTarget::PLAYER_DISTANCE:
			case SmartTarget::PLAYER_RANGE:
			case SmartTarget::HOSTILE_SECOND_AGGRO:
			case SmartTarget::HOSTILE_LAST_AGGRO:
			case SmartTarget::HOSTILE_RANDOM:
			case SmartTarget::HOSTILE_RANDOM_NOT_TOP:
			case SmartTarget::INVOKER_PARTY:
			case SmartTarget::ACTION_INVOKER_VEHICLE:
			case SmartTarget::THREAT_LIST:
			case SmartTarget::LOOT_RECIPIENTS:
			case SmartTarget::FARTHEST:
			case SmartTarget::VEHICLE_ACCESSORY:
			case SmartTarget::CLOSEST_ENEMY:
			case SmartTarget::CLOSEST_FRIENDLY:
			case SmartTarget::POSITION:
			case SmartTarget::ACTION_INVOKER:
			case SmartTarget::STORED:
			case SmartTarget::OWNER_OR_SUMMONER:
			case SmartTarget::CREATURE_GUID:
				return 0;
			case SmartTarget::GAMEOBJECT_RANGE:
			case SmartTarget::GAMEOBJECT_DISTANCE:
			case SmartTarget::CLOSEST_GAMEOBJECT:
				$gob_id = $sun_smart_entry->target_param1;
				break;
			case SmartTarget::GAMEOBJECT_GUID:
				$spawnID = $sun_smart_entry->target_param1;
				if (!array_key_exists($spawnID, $this->tcStore->gameobject))
					throw new ImportException("SCould not find TC gameobject with spawnID {$spawnID} for target GAMEOBJECT_GUID. This is a TC error.");

				$gob_id = $this->tcStore->gameobject[$guid].id;
				try {
					$this->CheckImportGameObject($spawnID);
				} catch (ImportException $e) {
					throw new ImportException("importing gameobject spawnID {$spawnID} with error: {$e->getMessage()}", $e->_error);
				}
				break;
			default:
				throw new ImportException("Target {$sun_smart_entry->target_type} NYI for Smart TC {$sun_smart_entry->entryorguid} {$sun_smart_entry->id}");
		}
		
		if ($gob_id)
		{
			try {
				$this->CheckImportGameObjectSmart($gob_id, $patch, false);
			} catch (ImportException $e) {
				throw new ImportException("importing gob smart {$gob_id} with error: {$e->getMessage()}", $e->_error);
			}
		}

		return $gob_id;
	}
	
	// return creature/gob id for this target (import if missing)
	function GetTargetId($sun_smart_entry, int $original_type, int $original_entry, int& $patch)
	{
		switch($original_type)
		{
			case SmartSourceType::creature:
				return $this->GetTargetCreatureId($sun_smart_entry, $original_entry, $patch);
				break;
			case SmartSourceType::gameobject:
				return $this->GetTargetGameObjectId($sun_smart_entry, $original_entry, $patch);
				break;
			default:
				throw new ImportException("GetTargetId: NYI type {$original_type}");
		}
	}

	function ImportSmartActionList(int $source_type, int $action_list_entry, int $original_type, int $original_entry, $sun_smart_entry, int& $patch)
	{
		$original_entry = $this->GetTargetId($sun_smart_entry, $original_type, $original_entry, $patch);
		$this->CreateSmartAI($action_list_entry, SmartSourceType::timedactionlist, $original_type, $original_entry); 	
	}
	
	// original_entry: if we're in an action list, the creature/gob it was called from
	function CreateSmartAI(int $tc_entry, int $source_type, int $original_type, int $original_entry = 0)
	{
		if (CheckAlreadyImported($tc_entry + $source_type << 28)) { //max entry is 30.501.000 (smaller number with 28 bits shift is 268.435.456)
			LogDebug("SmartAI {$tc_entry} {$source_type} is already imported");
			return;
		}
		
        $this->LoadTable("smart_scripts");
        $this->LoadTable("spell_template");
        
		$this->timelol("1");
		
		$sql = "";
		switch($source_type)
		{
			case SmartSourceType::creature:
				if (!$original_entry)
					$original_entry = $tc_entry;
				$sql .= "UPDATE creature_template SET ScriptName = '', AIName = 'SmartAI' WHERE entry = {$tc_entry};" . PHP_EOL;
				break;
			case SmartSourceType::gameobject:
				if (!$original_entry)
					$original_entry = $tc_entry;
				$sql .= "UPDATE gameobject_template SET ScriptName = '', AIName = 'SmartAI' WHERE entry = {$tc_entry};" . PHP_EOL;
				break;
			case SmartSourceType::areatrigger:
				if (!$original_entry)
					$original_entry = $tc_entry;
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
		if (empty($results))
			throw new ImportException("Failed to find TC SmartAI with entry {$tc_entry} and type {$source_type}");
		
		$this->timelol("3");
		
		$sun_smart_entries = [];
		foreach($results as $smart_entry) {
			$sun_smart_entry = $smart_entry; //copy
			$sun_smart_entry->patch_min = 0;

			switch($smart_entry->event_type)
			{
				case SmartEvent::WAYPOINT_START:
				case SmartEvent::WAYPOINT_REACHED:
				case SmartEvent::WAYPOINT_PAUSED: 
				case SmartEvent::WAYPOINT_RESUMED:
				case SmartEvent::WAYPOINT_STOPPED:
				case SmartEvent::WAYPOINT_ENDED: 
					if ($path_id = $smart_entry->event_param2)
					{
						try {
							$this->CreateSmartWaypoints($path_id);
						} catch (ImportException $e) {
							LogException($e, "Failed to create waypoints for entry {$original_entry} {$tc_entry} id {$sun_smart_entry->id}: {$e->getMessage()}");
							continue;
						}
					}
					break;
				case SmartEvent::GOSSIP_SELECT:
					if ($tc_menu_id = $smart_entry->event_param1) {
						try {
							$sun_menu_id = $this->CreateMenu($tc_menu_id);
						} catch (ImportException $e) {
							LogException($e, "Failed to create menu for entry {$original_entry} {$tc_entry} id {$sun_smart_entry->id}: {$e->getMessage()}");
							continue;
						}
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
					assert($source_type == SmartSourceType::creature || $source_type == SmartSourceType::timedactionlist);
					
					$creature_id = $original_entry ? $original_entry : $tc_entry;
					$useTalkTarget = $sun_smart_entry->action_type == SmartAction::TALK && $sun_smart_entry->action_param3;
					if (!$useTalkTarget) //the arguments makes it so we talk with specified target (does not make target talk)
					{
						switch($sun_smart_entry->target_type)
						{
						case SmartTarget::CLOSEST_PLAYER:
						case SmartTarget::PLAYER_DISTANCE:
						case SmartTarget::PLAYER_RANGE:
							break; //in this case, WE talk
						case SmartTarget::CLOSEST_ENEMY:
						case SmartTarget::CLOSEST_FRIENDLY:
						case SmartTarget::POSITION:
							//doesnt make any sense to use here... this is very probably a TC error
							LogWarning("Entry {$original_entry} {$tc_entry} id {$sun_smart_entry->id} uses target {$sun_smart_entry->target_type} without useTalkTarget... Makes no sense, let's enable useTalkTarget instead.");
							break; //then import normally like in the useTalkTarget case
						default:
							try  {
								$creature_id = $this->GetTargetCreatureId($sun_smart_entry, $creature_id, $sun_smart_entry->patch_min);
							} catch (ImportException $e) {
								LogException($e, "Failed to import target {$sun_smart_entry->target_type} for entry {$original_entry} {$tc_entry} id {$sun_smart_entry->id}: {$e->getMessage()}");
								continue;
							}
							break;
						}
					}
					
					if ($creature_id) //0 means we can't know easily which target it is (so we import the action with a warning)
					{
						try {
							$this->CreateCreatureText($creature_id);
						} catch (ImportException $e) {
							LogException($e, "Failed to import text talk with target {$sun_smart_entry->target_type} for entry {$original_entry} {$tc_entry} id {$sun_smart_entry->id}: {$e->getMessage()}");
							continue;
						}
					}
					break;
				case SmartAction::CAST:
					$spell_id = $sun_smart_entry->action_param1;
					//check if spell exists for TBC
					if (!array_key_exists($spell_id, $this->sunStore->spell_template)) {
						$sun_smart_entry->patch_min = 5;
					}
					break;
				case SmartAction::CALL_TIMED_ACTIONLIST:
					try {
						$this->ImportSmartActionList($source_type, $sun_smart_entry->action_param1, $original_type, $original_entry, $sun_smart_entry, $sun_smart_entry->patch_min);
					} catch (ImportException $e) {
						LogException($e, "Failed to import actionlist for entry {$original_entry} {$tc_entry} id {$sun_smart_entry->id}: {$e->getMessage()}");
						continue;
					}
					break;
				case SmartAction::CALL_RANDOM_TIMED_ACTIONLIST:			
					assert($source_type == SmartSourceType::creature || $source_type == SmartSourceType::timedactionlist); //we only handle creature action list atm
					$SMART_AI_MAX_ACTION_PARAM = 6;
					for($i = 1; $i <= $SMART_AI_MAX_ACTION_PARAM; $i++) {
						$fieldName = "action_param" . $i;
						if ($action_list = $sun_smart_entry->$fieldName) {
							try {
								$this->ImportSmartActionList($source_type, $action_list , $original_type, $original_entry, $sun_smart_entry, $sun_smart_entry->patch_min);
							} catch (ImportException $e) {
								LogException($e, "Failed to import actionlist for entry {$original_entry} {$tc_entry} id {$sun_smart_entry->id}: {$e->getMessage()}");
								continue;
							}
						}
					}
					break;
				case SmartAction::CALL_RANDOM_RANGE_TIMED_ACTIONLIST:
					assert($source_type == SmartSourceType::creature || $source_type == SmartSourceType::timedactionlist); //we only handle creature action list atm
					$min = $sun_smart_entry->action_param1;
					$max = $sun_smart_entry->action_param2;
					for($i = $min; $i <= $max; $i++) {
						try {
							$this->ImportSmartActionList($source_type, $i, $original_type, $original_entry, $sun_smart_entry, $sun_smart_entry->patch_min);
						} catch (ImportException $e) {
							LogException($e, "Failed to import actionlist for entry {$original_entry} {$tc_entry} id {$sun_smart_entry->id}: {$e->getMessage()}");
							continue;
						}
					}
					break;
				case SmartAction::SEND_GOSSIP_MENU:
					try {
						$sun_menu_id = $this->CreateMenu($sun_smart_entry->action_param1); 
					} catch (ImportException $e) {
						LogException($e, "Failed to create menu for entry {$original_entry} {$tc_entry} id {$sun_smart_entry->id}: {$e->getMessage()}");
						continue;
					}
					$sun_smart_entry->action_param1 = $sun_menu_id;
					break;
				case SmartAction::WP_START:
					$path_id = $sun_smart_entry->action_param2;
					try {
						$this->CreateSmartWaypoints($path_id);
					} catch (ImportException $e) {
						LogException($e, "Failed to create waypoints for entry {$original_entry} {$tc_entry} and id {$sun_smart_entry->id}: {$e->getMessage()}");
						continue;
					}
					break;
				case SmartAction::SUMMON_CREATURE:
					$summonID = $sun_smart_entry->action_param1;
					//echo "SmartAI {$tc_entry} ${source_type} does summon a creature {$summonID}" . PHP_EOL;
					try {
						$this->CheckImportCreatureSmart($summonID, $sun_smart_entry->patch_min, false);
					} catch (ImportException $e) {
						LogException($e, "Smart TC {$tc_entry} {$sun_smart_entry->id}: Failed to import smart for summoned creature: {$e->getMessage()}");
						continue;
					}
					break;
				case SmartAction::GAME_EVENT_STOP:
				case SmartAction::GAME_EVENT_START:
				case SmartAction::START_CLOSEST_WAYPOINT:
				case SmartAction::LOAD_EQUIPMENT:
				case SmartAction::SPAWN_SPAWNGROUP:
				case SmartAction::DESPAWN_SPAWNGROUP:
					throw new ImportException("NYI {$tc_entry} action {$sun_smart_entry->action_type}");
				case SmartAction::RESPAWN_BY_SPAWNID:
					$shouldKeep = false;
					$spawnType = $sun_smart_entry->action_param1;
					$spawnID = $sun_smart_entry->action_param2;
					try {
						if ($spawnType == MapSpawnType::creature) 
							$this->CheckImportCreature($spawnID);
						else if ($spawnType == MapSpawnType::gameobject)
							$this->CheckImportGameObject($spawnID);
						else
						{
							LogError("Smart TC {$tc_entry} {$sun_smart_entry->id}: NYI spawnType {$spawnType}");
							continue;
						}
					} catch (ImportException $e) {
						LogException($e, "Smart TC {$tc_entry} {$sun_smart_entry->id}: Failed to import smart for summoned creature: {$e->getMessage()}");
						continue;
					}
					break;
				case SmartAction::SUMMON_CREATURE_GROUP:
					$this->ImportSummonGroup($original_entry ? $original_entry : $tc_entry, $sun_smart_entry->action_param1);
					break;
				case SmartAction::SET_INST_DATA:
				case SmartAction::SET_INST_DATA64:
					//ignore those
					continue;
				case SmartAction::SET_DATA:
					//special handling below
					break;
				default:
					break;
			}
			
			//echo "action type " . $sun_smart_entry->action_type . PHP_EOL;
			$this->timelol("5");
			
			if (!IsActionIgnoreTarget($sun_smart_entry->action_type))
			{
				switch($sun_smart_entry->target_type)
				{
					case SmartTarget::CREATURE_GUID:
						//creature must exists in sun db
						$spawnID = $sun_smart_entry->target_param1;
						try {
							$this->CheckImportCreature($spawnID);
						} catch (ImportException $e) {
							LogWarning("Smart TC {$tc_entry} {$sun_smart_entry->id}: Failed to import smart for targeted creature spawnID {$spawnID}: {$e->getMessage()}");
							continue;
						}
						break;
					case SmartTarget::GAMEOBJECT_GUID:
						//gameobject must exists in sun db
						$spawnID = $sun_smart_entry->target_param1;
						try {
							$this->CheckImportGameObject($spawnID);
						} catch (ImportException $e) {
							LogWarning("Smart TC {$tc_entry} {$sun_smart_entry->id}: Failed to import smart for targeted gameobject spawnID {$spawnID}: {$e->getMessage()}");
							continue;
						}
					default:
						break;
				}
			}
			
			$this->timelol("6");
			 
			if ($sun_smart_entry->action_type == SmartAction::SET_DATA) {
				try {
					$this->GetTargetId($sun_smart_entry, $original_type, $original_entry, $sun_smart_entry->patch_min);  //will import creature/gob if missing
				} catch (ImportException $e) {
					LogException($e, "Smart TC {$tc_entry} {$sun_smart_entry->id}: Failed to import smart for targeted creature/gob spawnID: {$e->getMessage()}");
					continue;
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
		if (CheckAlreadyImported($spawn_id))
			return;
		
        $this->LoadTable("smart_scripts");
        $this->LoadTable("creature_addon");
        
		$sql = "CALL DeleteCreature({$spawn_id});" . PHP_EOL;
		fwrite($this->file, $sql);
				
		//warn smart scripts references removal
		$results = FindAll($this->sunStore->smart_scripts, "entryorguid", -$spawn_id);
		foreach($results as $result) {
			if ($result->source_type != SmartSourceType::creature)
				continue;
			
			echo "WARNING: Deleting a creature (guid: {$spawn_id}) with a per guid SmartScripts ({$result->entryorguid}, {$result->id}). Smart scripts ref has been left as is." . PHP_EOL;
		}
		
		$results = FindAll($this->sunStore->smart_scripts, "target_param1", $spawn_id);
		foreach($results as $result) {
			if ($result->target_type != SmartTarget::CREATURE_GUID)
				continue;
			
			echo "WARNING: Deleting creature (guid: {$spawn_id}) targeted by a smartscript ({$result->entryorguid}, {$result->id}). Smart scripts ref has been left as is." . PHP_EOL;
		}
		
		$results = FindAll($this->sunStore->creature_addon, "spawnID", $spawn_id);
		foreach($results as $result) {
			if (!$result->path_id)
				continue;
			
			echo "WARNING: Deleting creature (guid: {$spawn_id}) with a path (id {$result->path_id})" . PHP_EOL;
		}
	}
	
	function DeleteSunCreatures(int $creature_id, array $not_in)
	{
		if (CheckAlreadyImported($creature_id))
			return;
		
        $this->LoadTable("creature_entry");
        
		$results = FindAll($this->sunStore->creature_entry, "entry", $creature_id);
		foreach($results as $result) {
			if (!in_array($result->spawnID, $not_in))
				$this->DeleteSunCreatureSpawn($result->spawnID);
		}
	}

	function DeleteSunGameObjectsInMap(int $map_id, array $not_in)
	{
		if (CheckAlreadyImported($map_id))
			return;
		
        $this->LoadTable("gameobject");
        
		$results = FindAll($this->sunStore->gameobject, "map", $map_id);
		foreach($results as $result) {
			if (!in_array($result->guid, $not_in))
				$this->DeleteSunGameObjectSpawn($result->guid);
		}
	}
	
	function DeleteSunCreaturesInMap(int $map_id, array $not_in)
	{
		if (CheckAlreadyImported($map_id))
			return;
		
        $this->LoadTable("creature");
        
		$results = FindAll($this->sunStore->creature, "map", $map_id);
		foreach($results as $result) {
			if (!in_array($result->spawnID, $not_in))
				$this->DeleteSunCreatureSpawn($result->spawnID);
		}
	}
	
	function SunHasSameWaypointsScripts(array &$tcResults, array $sunResults) : bool
	{
		if (count($tcResults) != count($sunResults))
			return false;
		
		$sunResults = array_values($sunResults); //this is to reset array keys
		$i = 0;
		foreach($tcResults as $tcResult) {
			if ($tcResult != $sunResults[$i++])
				return false;
		}
		return true;
	}
	
	function ImportWaypointScripts(int $action_id) : int
	{
		if (CheckAlreadyImported($action_id))
			return $action_id;
		
        $this->LoadTable("waypoint_scripts");
        
		$results = FindAll($this->tcStore->waypoint_scripts, "id", $action_id);
		if (empty($results))
			throw new ImportException("ERROR: Tried to import waypoint_scripts with id {$action_id} but no such entry exists");
		
		$sunResults = FindAll($this->sunStore->waypoint_scripts, "id", $action_id);
		if ($this->SunHasSameWaypointsScripts($results, $sunResults)) {
			LogDebug("Waypoint scripts with id {$action_id} are already present and identical");
			return $action_id;
		}
		
		$sun_action_id = $action_id;
		if (count($sunResults) > 0) {
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
					throw new ImportException("NYI SCRIPT_COMMAND_EQUIP (31)");
				case 35: //SCRIPT_COMMAND_MOVEMENT
					$movementType = $tc_waypoint_script->datalong;
					if ($movementType == 2) { // WAYPOINT_MOTION_TYPE
						$path_id = $tc_waypoint_script->dataint;
						throw new ImportException("NYI SCRIPT_COMMAND_MOVEMENT (35) with WAYPOINT_MOTION_TYPE");
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
		if (count($tcResults) != count($sunResults))
			return false;
		
		$sunResults = array_values($sunResults); //this is to reset array keys
		$i = 0;
		foreach($tcResults as $tcResult) {
			if ($tcResult != $sunResults[$i++])
				return false;
		}
		return true;
	}
	
	function GetTimesUsedWaypoints($path_id)
	{
        $this->LoadTable("creature_addon");
        
		assert($path_id > 0);
		$results = FindAll($this->sunStore->creature_addon, "path_id", $path_id);
		return count($results);
	}
	
	function DeleteWaypoints($path_id)
	{
        $this->LoadTable("waypoint_info");
        $this->LoadTable("waypoint_data");
        
		fwrite($this->file, "DELETE FROM waypoint_data WHERE id = {$path_id};" . PHP_EOL);
		fwrite($this->file, "DELETE FROM waypoint_info WHERE id = {$path_id};" . PHP_EOL);
				
		RemoveAny($this->sunStore->waypoint_info, "id", $path_id);
		RemoveAny($this->sunStore->waypoint_data, "id", $path_id);
	}
	
	function ReplaceWaypoints(int $guid, bool $updatePosition = true)
	{
        $this->LoadTable("creature");
        $this->LoadTable("creature_addon");
        
		if (!array_key_exists($guid, $this->tcStore->creature_addon)) {
			LogError("Trying to replace waypoints for creature {$guid}, but creature has no creature_addon on trinity");
			return;
		}
		$tc_creature_addon = &$this->tcStore->creature_addon[$guid];
		if ($tc_creature_addon->path_id == 0) {
			LogError("Trying to replace waypoints for creature {$guid}, but creature has no path_id on trinity");
			return;
		}
		
		if (array_key_exists($guid, $this->sunStore->creature_addon) && $this->sunStore->creature_addon[$guid]->path_id) 
		{
			$usedTimes = $this->GetTimesUsedWaypoints($this->sunStore->creature_addon[$guid]->path_id);
			if ($usedTimes == 1) {
				fwrite($this->file, "UPDATE creature_addon SET path_id = NULL WHERE spawnID = {$guid};" . PHP_EOL);
				$this->DeleteWaypoints($this->sunStore->creature_addon[$guid]->path_id);
			} else if ($usedTimes > 1)
				fwrite($file, "-- Not deleting waypoint path {$this->sunStore->creature_addon[$guid]->path_id} because it's still used by another creature" . PHP_EOL);
		}
		
		//will often be equal to tc path id, unless it's not free
		$sun_path_id = $this->ImportWaypoints($guid, $tc_creature_addon->path_id);
		
		if (array_key_exists($guid, $this->sunStore->creature_addon)) {
			$this->sunStore->creature_addon[$guid]->path_id = $sun_path_id;
			fwrite($this->file, "UPDATE creature_addon SET path_id = {$sun_path_id} WHERE spawnID = {$guid};" . PHP_EOL);
		} else {
			$sun_creature_addon = new stdClass;
			$sun_creature_addon->spawnID = $guid;
			$sun_creature_addon->path_id = $sun_path_id;
			$this->sunStore->creature_addon[$guid] = $sun_creature_addon;
			fwrite($this->file, WriteObject($this->conn, "creature_addon", $sun_creature_addon));
		}
		
		if ($updatePosition)
		{
			if (   $this->sunStore->creature[$guid]->position_x != $this->tcStore->creature[$guid]->position_x
			    || $this->sunStore->creature[$guid]->position_y != $this->tcStore->creature[$guid]->position_y
				|| $this->sunStore->creature[$guid]->position_z != $this->tcStore->creature[$guid]->position_z)
			{
				$this->sunStore->creature[$guid]->position_x = $this->tcStore->creature[$guid]->position_x;
				$this->sunStore->creature[$guid]->position_y = $this->tcStore->creature[$guid]->position_y;
				$this->sunStore->creature[$guid]->position_z = $this->tcStore->creature[$guid]->position_z;
				$this->sunStore->creature[$guid]->orientation = $this->tcStore->creature[$guid]->orientation;
				fwrite($this->file, "UPDATE creature SET position_x = {$this->tcStore->creature[$guid]->position_x}, position_y = {$this->tcStore->creature[$guid]->position_y}, position_z = {$this->tcStore->creature[$guid]->position_z}, orientation = {$this->tcStore->creature[$guid]->orientation} WHERE spawnID = {$guid};" . PHP_EOL);
			}
		}
	}
	
	//return new path_id
	function ImportWaypoints(int $guid, int $tc_path_id, bool $includeMovementTypeUpdate = true) : int
	{
        $this->LoadTable("creature");
        $this->LoadTable("waypoint_data");
        $this->LoadTable("waypoint_info");
        
		$results = FindAll($this->tcStore->waypoint_data, "id", $tc_path_id);
		if (empty($results))
		{
			$msg = "Tried to import waypoint_data with path_id {$tc_path_id} but no such path exists";
			throw new ImportException($msg);
		}
		
		if (CheckAlreadyImported($tc_path_id)) {
			LogDebug("Path {$tc_path_id} is already imported");
			return $tc_path_id;
		}
		
		$sunResults = FindAll($this->sunStore->waypoint_data, "id", $tc_path_id);
		if ($this->SunHasSameWaypoints($results, $sunResults)) {
			LogDebug("Path {$tc_path_id} is already present and the same");
			return $tc_path_id;
		}
		
		$sun_path_id = $tc_path_id;
		if (count($sunResults) > 0) {
			//we have a path with this id, but no the same...
			$sun_path_id = GetHighest($this->sunStore->waypoint_data, "id") + 1;
		}
		
		$waypoint_info = new stdClass;
		$waypoint_info->id = $sun_path_id;
		$this->sunStore->waypoint_info[$sun_path_id] = $waypoint_info;
		fwrite($this->file, WriteObject($this->conn, "waypoint_info", $waypoint_info));
		
		foreach($results as $tc_waypoint) {
			$sun_waypoint = $tc_waypoint; //copy
			$sun_waypoint->id = $sun_path_id;
			if ($tc_action = $tc_waypoint->action)
				$sun_waypoint->action = $this->ImportWaypointScripts($tc_action);
			else
				$sun_waypoint->action = 'NULL';
			
			array_push($this->sunStore->waypoint_data, $sun_waypoint);
			fwrite($this->file, WriteObject($this->conn, "waypoint_data", $sun_waypoint));
		}
		
		if ($includeMovementTypeUpdate) {
			$this->sunStore->creature[$guid]->MovementType = 2;
			fwrite($this->file, "UPDATE creature SET MovementType = 2 WHERE spawnID = {$guid};" . PHP_EOL);
		}
		
		return $sun_path_id;
	}

	function ImportSpawnGroup(int $guid, bool $creature) //else gameobject
	{
		if (CheckAlreadyImported($guid + $creature << 31))
			return;
		
        $this->LoadTable("spawn_group");
        
		$results = FindAll($this->tcStore->spawn_group, "spawnId", $guid);
		foreach($results as $result) {
			if ($creature) {
				if ($result->spawnType != 0) //creature type
					continue;
			} else {
				if ($result->spawnType != 1) //gob type
					continue;
			}
				
			$groupId = $result->groupId;
			if (!ConvertSpawnGroup($groupId, $guid)) //may change groupId
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
        $this->LoadTable("creature_formations");
        
		if (!array_key_exists($guid, $this->tcStore->creature_formations))
			return;
		
		$tc_formation = $this->tcStore->creature_formations[$guid];
		$leaderGUID = $tc_formation->leaderGUID;
		if (!array_key_exists($leaderGUID, $this->tcStore->creature_formations))
			return;
		
		if (array_key_exists($guid, $this->sunStore->creature_formations)) {
			$sun_formation = $this->sunStore->creature_formations[$guid];
			if ($sun_formation != $tc_formation) {
				//a formation already exists for this creature but is not the same...
				fwrite($this->file, "DELETE FROM creature_formations WHERE memberGUID = {$guid};" . PHP_EOL);
				unset($this->sunStore->creature_formations[$guid]);
			} else {
				LogDebug("Formation for creature {$guid} already in db");
				return;
			}
		}
		
		if (CheckAlreadyImported($leaderGUID))
			return;
		
		$results = FindAll($this->tcStore->creature_formations, "leaderGUID", $leaderGUID);
		foreach($results as $tc_formation) {
				
			if (!array_key_exists($tc_formation->memberGUID, $this->sunStore->creature)) {
				//we don't have that leader yet
				LogDebug("Trying to import formation for creature {$tc_formation->memberGUID}, but the creature isn't in our db yet. Importing it now");
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
			
			if ($sun_formation->leaderGUID == $sun_formation->memberGUID)
				$sun_formation->leaderGUID = "NULL"; //special on SUN as well (only do that for the WriteObject, not for the store)
			
			$this->delayedFormationsImports .= "DELETE FROM creature_formations WHERE memberGUID = {$sun_formation->memberGUID};" . PHP_EOL;
			$this->delayedFormationsImports .= WriteObject($this->conn, "creature_formations", $sun_formation);
		}
	}

	function ImportPool(int $guid, bool $creature) //else gameobject
	{
        $this->LoadTable("pool_creature");
        $this->LoadTable("pool_gameobject");
        $this->LoadTable("pool_template");
        
		$tc_pool_entry = null;
		if ($creature) {
			if (!array_key_exists($guid, $this->tcStore->pool_creature))
				return;
			
			$tc_pool_entry = $this->tcStore->pool_creature[$guid]->pool_entry;
		} else {
			if (!array_key_exists($guid, $this->tcStore->pool_gameobject))
				return;
			
			$tc_pool_entry = $this->tcStore->pool_gameobject[$guid]->pool_entry;
		}
		
		if (!array_key_exists($tc_pool_entry, $this->tcStore->pool_template)) {
			echo "ERROR: TC has " . $creature ? "creature" : "gob" . " {$guid} which part of pool {$pool_entry}, but this pool does not exists" . PHP_EOL;
			return;
		}
		
		//Handle pool template
		if (array_key_exists($tc_pool_entry, $this->sunStore->pool_template)) { 
			if ($this->sunStore->pool_template[$tc_pool_entry]->description != $this->tcStore->pool_template[$tc_pool_entry]->description)
			{ //we have that pool id but not the same pool
				echo "WARNING: Imported " . $creature ? "creature" : "gob" . " {$guid} is part of pool {$tc_pool_entry} but a pool with this entry (but different description already exists). Need manual fix." . PHP_EOL;
				return;
			}
			else { //we have that pool id and same pool, no need to import template
				//LogDebug("Imported " . $creature ? "creature" : "gob" . " {$guid} is part of pool {$tc_pool_entry} which already exists");
			}
		}
		else { 
			//if sun doesn't have that pool
			$sun_pool_template = $this->tcStore->pool_template[$tc_pool_entry];
			$this->sunStore->pool_template[$tc_pool_entry] = $sun_pool_template;
			fwrite($this->file, WriteObject($this->conn, "pool_template", $sun_pool_template));
		}
		
		//and finally add to pool
		if ($creature) {
			$pool_creature = $this->tcStore->pool_creature[$guid];
			$this->sunStore->pool_creature[$guid] = $pool_creature;
			fwrite($this->file, WriteObject($this->conn, "pool_creature", $pool_creature));
		} else {
			$pool_gameobject = $this->tcStore->pool_gameobject[$guid];
			$this->sunStore->pool_gameobject[$guid] = $pool_gameobject;
			fwrite($this->file, WriteObject($this->conn, "pool_gameobject", $pool_gameobject));
		}
	}

	function HasSunModelInTemplate($creature_id, $model_id)
	{
        $this->LoadTable("creature_template");
        
		//check if template for this creature has this modelid. Also check the modelids other gender.
		$sun_results = FindAll($this->sunStore->creature_template, "entry", $creature_id);
		if (!empty($sun_results)) {
			foreach($sun_results as $result) {
				$sun_models = [
					$result->modelid1, $result->modelid2, $result->modelid3, $result->modelid4
				];
				$sun_models_other_gender = [];
				foreach($sun_models as $sun_model) {
					if (array_key_exists($sun_model, $this->sunStore->creature_model_info)) {
						if ($other_gender_model = $this->sunStore->creature_model_info[$sun_model]->modelid_other_gender) {
							array_push($sun_models_other_gender, $other_gender_model);
						}
					}
				}
				array_merge ($sun_models, $sun_models_other_gender);
				if (!in_array($model_id, $sun_models)) {
					//echo "Not the same modelid id {$creature_id}, checking modelid: {$model_id}" . PHP_EOL;
					return false;
				}
			}
		}
		return true;
	}
	
	//don't forget to call HandleFormations after this
	function ImportTCCreature(int $guid, int $patch_min = 0, int $patch_max = 10)
	{
		if (CheckAlreadyImported($guid))
			return;
		
        $this->LoadTable("creature");
        $this->LoadTable("creature_addon");
        $this->LoadTable("creature_entry");
        
		if (array_key_exists($guid, $this->sunStore->creature)) 
			return;
		
		$tc_creature = &$this->tcStore->creature[$guid];
		$tc_creature_addon = null;
		if (array_key_exists($guid, $this->tcStore->creature_addon)) {
			$tc_creature_addon = &$this->tcStore->creature_addon[$guid];
		}
		
		if (IsTLKCreature($tc_creature->id)) {
			ImportCreatureTemplate($tc_creature->id);
			if ($patch_min < 5)
				$patch_min = 5;
		}
		
		//create creature
		$sun_creature = new stdClass;
		$sun_creature->spawnID = $guid;
		$sun_creature->map = $tc_creature->map;
		$sun_creature->spawnMask = $tc_creature->spawnMask;
		$keep_model_id = true;
		if ($tc_creature->modelid) {
			if ($this->HasSunModelInTemplate($tc_creature->id, $tc_creature->modelid)) {
				LogDebug("Has LK modelid {$tc_creature->modelid} already in creature template, set to 0.");
				$keep_model_id = false;
			}
			
			if ($keep_model_id) {
				$model_info = array_key_exists($tc_creature->modelid, $this->sunStore->creature_model_info) ? $this->sunStore->creature_model_info[$tc_creature->modelid] : null;
				if ($model_info) {
					if ($model_info->patch > 4) {
						//this is a LK model, what do to here? Just set model to 0 for now.
						LogDebug("Has LK modelid {$tc_creature->modelid}, set to 0.");
						$keep_model_id = 0;
					}
				} else
					throw new ImportException("Non existing model id {$tc_creature->modelid}?");
			}
		}
		$sun_creature->modelid = $keep_model_id ? ($tc_creature->modelid ? $tc_creature->modelid : "NULL") : "NULL";
		$sun_creature->equipment_id = $tc_creature->equipment_id; //import equip ID?
		$sun_creature->position_x = $tc_creature->position_x;
		$sun_creature->position_y = $tc_creature->position_y;
		$sun_creature->position_z = $tc_creature->position_z;
		$sun_creature->orientation= $tc_creature->orientation;
		$sun_creature->spawntimesecsmin = $tc_creature->spawntimesecs;
		$sun_creature->spawntimesecsmax = $tc_creature->spawntimesecs;
		$sun_creature->spawndist = $tc_creature->spawndist;
		$sun_creature->currentwaypoint = $tc_creature->currentwaypoint;
		$sun_creature->curhealth = $tc_creature->curhealth;
		$sun_creature->curmana = $tc_creature->curmana;
		$sun_creature->MovementType = $tc_creature->MovementType;
		$sun_creature->unit_flags = $tc_creature->unit_flags;
		$sun_creature->pool_id = 0;
		if (IsTLKMap($sun_creature->map))
			$patch_min = 5;
		$sun_creature->patch_min = $patch_min;
		$sun_creature->patch_max = $patch_max;
		
		$this->sunStore->creature[$guid] = $sun_creature;
		fwrite($this->file, WriteObject($this->conn, "creature", $sun_creature));
		
		//create creature_entry
		$sun_creature_entry = new stdClass; //anonymous object
		$sun_creature_entry->spawnID = $guid;
		$sun_creature_entry->entry = $tc_creature->id;
		
		array_push($this->sunStore->creature_entry, $sun_creature_entry);
		fwrite($this->file, WriteObject($this->conn, "creature_entry", $sun_creature_entry));
		
		//create creature_addon
		if ($tc_creature_addon) {
			$path_id = $tc_creature_addon->path_id;
			if ($path_id) {
				$path_id = $this->ImportWaypoints($guid, $path_id, false); 
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
		if (array_key_exists($guid, $this->tcStore->game_event_creature)) {
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
		if (CheckAlreadyImported($creature_id))
			return;
			
        $this->LoadTable("creature");
        $this->LoadTable("creature_entry");
        
		$results = FindAll($this->tcStore->creature, "id", $creature_id);
		if (empty($results))
			throw new ImportException("Failed to find any TC creature with id {$creature_id}");
						
		$tc_guids = [];
		foreach($results as $tc_creature) {
			array_push($tc_guids, $tc_creature->guid);
			if (!array_key_exists($tc_creature->guid, $this->sunStore->creature)) {
				$sun_creature_entries = FindAll($this->sunStore->creature_entry, "spawnID", $tc_creature->guid);
				if (!empty($sun_creature_entries)) 
					throw new ImportException("Error in sun DB... there is a creature_entry without matching creature for spawnID {$tc_creature->guid}");

				$this->ImportTCCreature($tc_creature->guid, $patch_min, $patch_max);
			}
		}
		$this->DeleteSunCreatures($creature_id, $tc_guids);
		$this->HandleFormations();
	}
	
	function CreateReplaceMap(int $map_id, int $patch_min = 0, int $patch_max = 10)
	{
		if (CheckAlreadyImported($map_id))
			return;
			
        $this->LoadTable("creature");
        $this->LoadTable("gameobject");
        
		//handle creatures
		$results = FindAll($this->tcStore->creature, "map", $map_id);
		if (empty($results)) 
			throw new ImportException("Failed to find any TC creature in map {$map_id}");
		
		$tc_guids = [];
		foreach($results as $tc_creature) {
			array_push($tc_guids, $tc_creature->guid);
			if (!array_key_exists($tc_creature->guid, $this->sunStore->creature))
				$this->ImportTCCreature($tc_creature->guid, $patch_min, $patch_max);
		}
		$this->DeleteSunCreaturesInMap($map_id, $tc_guids);
		$this->HandleFormations();
		
		//handle gameobjects
		$results = FindAll($this->tcStore->gameobject, "map", $map_id);
		if (empty($results))
			throw new ImportException("Failed to find any TC gameobject in map {$map_id}");

		$tc_guids = [];
		foreach($results as $tc_gob) {
			array_push($tc_guids, $tc_gob->guid);
			if (!array_key_exists($tc_gob->guid, $this->sunStore->gameobject))
				$this->ImportTCGameObject($tc_gob->guid, $patch_min, $patch_max);
		}
		$this->DeleteSunGameObjectsInMap($creature_id, $tc_guids);
	}

	//Write formations stored in $this->delayedFormationsImports
	function HandleFormations()
	{
		if (!$this->delayedFormationsImports)
			return;
		
		LogDebug("Formations");
		fwrite($this->file, $this->delayedFormationsImports);
		$this->delayedFormationsImports = "";
	}
	
	function ImportGameObjectTemplate($id)
	{
        $this->LoadTable("gameobject_template");
        
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
		$sun_gameobject_template->AIName = ""; //$tc_gameobject_template->AIName;
		$sun_gameobject_template->ScriptName = ""; //$tc_gameobject_template->ScriptName;
		
		if (IsTLKGameObject($id))
			$sun_gameobject_template->patch = 5;
		
		if ($tc_gameobject_template->AIName != "") 
			echo "WARNING: Importing gameobject template {$id} which has AIName {$tc_gameobject_template->AIName}" . PHP_EOL;
		
		if ($tc_gameobject_template->ScriptName != "")
			echo "WARNING: Importing gameobject template {$id} which has ScriptName {$tc_gameobject_template->ScriptName}" . PHP_EOL;
		
		$this->sunStore->gameobject_template[$sun_gameobject_template->entry] = $sun_gameobject_template;
		fwrite($this->file, WriteObject($this->conn, "gameobject_template", $sun_gameobject_template));
	}
	
	function ImportTCGameObject(int $guid, int $patch_min = 0, int $patch_max = 10)
	{
		if (CheckAlreadyImported($guid))
			return;
		
        $this->LoadTable("gameobject");
        $this->LoadTable("gameobject_template");
        $this->LoadTable("game_event_gameobject");
        
		if (array_key_exists($guid, $this->sunStore->gameobject))
			return;
		
		$tc_gameobject = &$this->tcStore->gameobject[$guid];
		
		$tlk_gameobject = !array_key_exists($tc_gameobject->id, $this->sunStore->gameobject_template); //TODO not valid anymore? since we have TLK objects
		if ($tlk_gameobject) {
			ImportGameObjectTemplate($tc_gameobject->id);
			if (IsTLKGameObject($tc_gameobject->id) && $patch_min < 5)
				$patch_min = 5;
		}
		
		//create gameobject
		$sun_gameobject = new stdClass;
		$sun_gameobject->guid             = $guid;
		$sun_gameobject->id               = $tc_gameobject->id;
		$sun_gameobject->map              = $tc_gameobject->map;
		$sun_gameobject->spawnMask        = $tc_gameobject->spawnMask;
		$sun_gameobject->position_x       = $tc_gameobject->position_x;
		$sun_gameobject->position_y       = $tc_gameobject->position_y;
		$sun_gameobject->position_z       = $tc_gameobject->position_z;
		$sun_gameobject->rotation0        = $tc_gameobject->rotation0;
		$sun_gameobject->rotation1        = $tc_gameobject->rotation1;
		$sun_gameobject->rotation2        = $tc_gameobject->rotation2;
		$sun_gameobject->rotation3        = $tc_gameobject->rotation3;
		$sun_gameobject->spawntimesecsmin = $tc_gameobject->spawntimesecs;
		$sun_gameobject->spawntimesecsmax = $tc_gameobject->spawntimesecs;
		$sun_gameobject->animprogress     = $tc_gameobject->animprogress;
		$sun_gameobject->state            = $tc_gameobject->state;
		$sun_gameobject->ScriptName       = $tc_gameobject->ScriptName;
		if (IsTLKMap($sun_gameobject->map))
			$patch_min = 5;
		$sun_gameobject->patch_min     = $patch_min;
		$sun_gameobject->patch_max     = $patch_max;
		
		$this->sunStore->gameobject[$guid] = $sun_gameobject;
		fwrite($this->file, WriteObject($this->conn, "gameobject", $sun_gameobject));
		
		//game event gameobject
		if (array_key_exists($guid, $this->tcStore->game_event_gameobject)) {
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
		if (CheckAlreadyImported($spawn_id))
			return;
		
        $this->LoadTable("smart_scripts");
        
		$sql = "CALL DeleteGameObject({$spawn_id});" . PHP_EOL;
		fwrite($this->file, $sql);
				
		//warn smart scripts references removal
		$results = FindAll($this->sunStore->smart_scripts, "entryorguid", -$spawn_id);
		foreach($results as $result) {
			if ($result->source_type != SmartSourceType::gameobject)
				continue;
			
			echo "WARNING: Deleting a gameobject (guid: {$spawn_id}) with a per guid SmartScripts ({$result->entryorguid}, {$result->id}). Smart scripts ref has been left as is." . PHP_EOL;
		}
		
		$results = FindAll($this->sunStore->smart_scripts, "target_param1", $spawn_id);
		foreach($results as $result) {
			if ($result->target_type != SmartTarget::GAMEOBJECT_GUID)
				continue;
			
			echo "WARNING: Deleting gameobject (guid: {$spawn_id}) targeted by a smartscript ({$result->entryorguid}, {$result->id}). Smart scripts ref has been left as is." . PHP_EOL;
		}
	}

	function DeleteSunGameObjects(int $gob_id, array $not_in)
	{
		if (CheckAlreadyImported($gob_id))
			return;
		
        $this->LoadTable("gameobject");
        
		$results = FindAll($this->sunStore->gameobject, "id", $gob_id);
		foreach($results as $result) {
			if (!in_array($result->guid, $not_in))
				$this->DeleteSunGameObjectSpawn($result->guid);
		}
	}

	function CreateReplaceAllGameObject(int $gob_id, int $patch_min = 0, int $patch_max = 10)
	{
		if (CheckAlreadyImported($gob_id))
			return;
			
        $this->LoadTable("gameobject");
        
		$results = FindAll($this->tcStore->gameobject, "id", $gob_id);
		if (empty($results)) 
			throw new ImportException("Failed to find any TC gameobject with id {$gob_id}");
						
		$tc_guids = [];
		foreach($results as $tc_gob) {
			array_push($tc_guids, $tc_gob->guid);
			if (!array_key_exists($tc_gob->guid, $this->sunStore->gameobject))
				$this->ImportTCGameObject($tc_gob->guid, $patch_min, $patch_max);
		}
		$this->DeleteSunGameObjects($gob_id, $tc_guids);
	}
};
