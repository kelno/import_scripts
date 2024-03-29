<?php

/*TODO:
- Convert most tables to table key system in DBStore.
- make smart_scripts ported for tlk be patch 5 if the creature is from TLK
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
function LogError(string $msg)
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
	public $broadcast_text = null;
	public $conditions = null;
	public $creature = null;
	public $creature_addon = null;
	public $creature_entry = null;
	public $creature_equip_template = null;
	public $creature_formations = null;
	public $creature_loot_template = null;
	public $creature_model_info = null;
	public $creature_summon_groups = null;
	public $creature_template = null;
	public $creature_template_addon = null;
	public $creature_template_movement = null;
	public $creature_template_resistance = null;
	public $creature_template_spell = null;
	public $creature_text = null;
	public $game_event = null;
	public $game_event_creature = null;
	public $game_event_gameobject = null;
	public $gameobject = null;
	public $gameobject_addon = null;
	public $gameobject_entry = null;
	public $gameobject_loot_template = null;
	public $gameobject_template = null; 
	public $gameobject_template_addon = null; 
	public $gossip_menu = null;
	public $gossip_menu_option = null;
	public $gossip_text = null;
	public $item_template = null;
	public $npc_text = null;
	public $page_text = null;
	public $pickpocketing_loot_template = null;
	public $points_of_interest = null;
	public $pool_members = null;
	public $pool_template = null;
	public $reference_loot_template = null;
	public $skinning_loot_template = null;
	public $smart_scripts = null;
	public $spawn_group = null;
	public $spell_template = null;
	public $trainer = null;
	public $trainer_spell = null;
	public $waypoint_info = null;
	public $waypoint_data = null;
	public $waypoint_scripts = null;
	public $waypoints = null;
	
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
    /* Load table with this format: 
    table = [
        main_key = [ Object, Object, ... ]
    ]
    */
    function LoadTableWithKey(&$conn, string $table_name, string $main_key_name)
    {
        $stmt = $conn->query("SELECT * FROM {$this->databaseName}.{$table_name}");
		$stmt->setFetchMode(PDO::FETCH_OBJ);
		foreach($stmt->fetchAll() as &$v)
        {
            $key = $v->$main_key_name;
            if (!array_key_exists($key, $this->$table_name))
                $this->$table_name[$key] = [];
            
            array_push($this->$table_name[$key], $v);
        }
    }
    
    function LoadTable(&$conn, string $table_name) : bool
    {
        global $loadTableInfos;
        
        if ($this->$table_name !== null)
            return false; //already loaded
        
        if (!isset($loadTableInfos[$table_name]))
			throw new ImportException("Could not find load table info for table {$table_name}");
        
        $this->$table_name = []; // prepare member array to fill
        $key = $this->loadmode == LoadMode::sunstrider ? $loadTableInfos[$table_name]->sunKey : $loadTableInfos[$table_name]->tcKey;
        $disabled = $this->loadmode == LoadMode::sunstrider ? $loadTableInfos[$table_name]->disableSun : $loadTableInfos[$table_name]->disableTC;
        
        if ($disabled)
            return false;
        
		$this->LoadTableWithKey($conn, $table_name, $key);
        return true;
    }
    
    function &FindAllByField(string $table_name, $field_name, &$field_value) : array
    {
		$results = [];
		
		if ($this->$table_name === null)
			return $results;

		foreach ($this->$table_name as &$value_array)
			foreach ($value_array as &$value)
				if ($value->$field_name == $field_value)
					array_push($results, $value);
		
        return $results;
	}

    function &FindAll(string $table_name, $main_key_value) : array
    {
		$results = [];

         if ($this->$table_name === null)
            return $results;

		if (!array_key_exists($main_key_value, $this->$table_name))
			return $results;
		
		return $this->$table_name[$main_key_value];
    }
    
	function Exists(string $table_name, $main_key_value, string $keyname = null, $value = null) : bool
	{
		if ($this->$table_name === null)
			return false;

		if (!array_key_exists($main_key_value, $this->$table_name))
			return false;

		$table_by_main_key = &$this->$table_name[$main_key_value]; // [ Object, Object, ... ]
		if (empty($table_by_main_key))
			return false;

		if ($keyname !== null && $value !== null)
		{
			foreach ($table_by_main_key as &$row)
				if ($row->$keyname == $value)
					return true;
		}
		else
			return true;

		return false;
	}

	// if keyname & value are specified, further filter result with those
    function &FindFirst(string $table_name, $main_key_value, string $keyname = null, $value = null)
    {
		$null = null;
        if ($this->$table_name === null)
            return $null;

		if (!array_key_exists($main_key_value, $this->$table_name))
			return $null;

		$table_by_main_key = &$this->$table_name[$main_key_value]; // [ Object, Object, ... ]
		if (empty($table_by_main_key))
			return $null;

		if ($keyname !== null && $value !== null)
		{
			foreach ($table_by_main_key as &$row)
				if ($row->$keyname == $value)
					return $row;
		}
		else
			return $table_by_main_key[0]; // return first element, whatever it is

        return $null;
    }
    
    function Insert(string $table_name, &$object, &$main_key_value)
    {
        assert($this->$table_name !== null);
		assert($main_key_value !== null);
		if (!array_key_exists($main_key_value, $this->$table_name))
			$this->$table_name[$main_key_value] = [];
			
		array_push($this->$table_name[$main_key_value], $object);
    }

	function Remove(string $table_name, &$main_key_value)
    {
        assert($this->$table_name !== null);
		unset($this->$table_name[$main_key_value]);
    }

	function RemoveOnCondition(string $table_name, &$main_key_value, $other_field_name, $other_field_value)
    {
        assert($this->$table_name !== null);
		
		if (array_key_exists($main_key_value, $this->$table_name)) {
			$array = &$this->$table_name[$main_key_value];
			foreach ($array as $key => &$value)
				if ($value->$other_field_name == $other_field_value)
					unset($array[$key]);
		}
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
        $this->sunKey = $sun;
        $this->tcKey = $tc;
        $this->disableSun = $disableSun;
        $this->disableTC = $disableTC;
    }
}

$loadTableInfos = [];
$loadTableInfos["broadcast_text"] = new LoadTableInfo("ID", "ID");
$loadTableInfos["creature"] = new LoadTableInfo("spawnID", "guid"); 
$loadTableInfos["creature_addon"] = new LoadTableInfo("spawnID", "guid", false, false);
$loadTableInfos["creature_entry"] = new LoadTableInfo("spawnID", null, false, true);
$loadTableInfos["creature_equip_template"] = new LoadTableInfo("CreatureID", "CreatureID");
$loadTableInfos["conditions"] = new LoadTableInfo("SourceEntry", "SourceEntry");
$loadTableInfos["creature_formations"] = new LoadTableInfo("memberGUID", "memberGUID");
$loadTableInfos["creature_loot_template"] = new LoadTableInfo("Entry", "Entry");
$loadTableInfos["creature_model_info"] = new LoadTableInfo("modelid", "DisplayID");
$loadTableInfos["creature_summon_groups"] = new LoadTableInfo("summonerId", "summonerId");
$loadTableInfos["creature_template"] = new LoadTableInfo("entry", "entry");
$loadTableInfos["creature_template_addon"] = new LoadTableInfo("entry", "entry");
$loadTableInfos["creature_template_movement"] = new LoadTableInfo("CreatureId", "CreatureId");
$loadTableInfos["creature_template_resistance"] = new LoadTableInfo("CreatureID", "CreatureID");
$loadTableInfos["creature_template_spell"] = new LoadTableInfo("CreatureID", "CreatureID");
$loadTableInfos["creature_text"] = new LoadTableInfo("CreatureID", "CreatureID");
$loadTableInfos["game_event"] = new LoadTableInfo("entry", "eventEntry");
$loadTableInfos["game_event_creature"] = new LoadTableInfo("guid", "guid");
$loadTableInfos["game_event_gameobject"] = new LoadTableInfo("guid", "guid");
$loadTableInfos["gameobject"] = new LoadTableInfo("spawnID", "guid");
$loadTableInfos["gameobject_addon"] = new LoadTableInfo("spawnID", "guid");
$loadTableInfos["gameobject_entry"] = new LoadTableInfo("spawnID", null, false, true);
$loadTableInfos["gameobject_loot_template"] = new LoadTableInfo("Entry", "Entry");
$loadTableInfos["gameobject_template"] = new LoadTableInfo("entry", "entry");
$loadTableInfos["gameobject_template_addon"] = new LoadTableInfo("entry", "entry");
$loadTableInfos["gossip_menu"] = new LoadTableInfo("MenuID", "MenuID");
$loadTableInfos["gossip_menu_option"] = new LoadTableInfo("MenuID", "MenuID");
$loadTableInfos["gossip_text"] = new LoadTableInfo("ID", null, false, true); // npc_text on TC
$loadTableInfos["item_template"] = new LoadTableInfo("entry", "entry");
$loadTableInfos["npc_text"] = new LoadTableInfo(null, "ID", true, false); // gossip_text on Sun
$loadTableInfos["page_text"] = new LoadTableInfo("ID", "ID");
$loadTableInfos["pickpocketing_loot_template"] = new LoadTableInfo("Entry", "Entry");
$loadTableInfos["points_of_interest"] = new LoadTableInfo("ID", "ID");
$loadTableInfos["pool_members"] = new LoadTableInfo("spawnId", "spawnId");
$loadTableInfos["pool_template"] = new LoadTableInfo("entry", "entry");
$loadTableInfos["reference_loot_template"] = new LoadTableInfo("Entry", "Entry");
$loadTableInfos["skinning_loot_template"] = new LoadTableInfo("Entry", "Entry");
$loadTableInfos["smart_scripts"] = new LoadTableInfo("entryorguid", "entryorguid");
$loadTableInfos["spawn_group"] = new LoadTableInfo("spawnId", "spawnId");
$loadTableInfos["spell_template"] = new LoadTableInfo("entry", null, false, true);
$loadTableInfos["trainer"] = new LoadTableInfo("Id", "Id");
$loadTableInfos["trainer_spell"] = new LoadTableInfo("TrainerId", "TrainerId");
$loadTableInfos["waypoints"] = new LoadTableInfo("entry", "entry");
$loadTableInfos["waypoint_data"] = new LoadTableInfo("id", "id");
$loadTableInfos["waypoint_info"] = new LoadTableInfo("id", null, false, true);
$loadTableInfos["waypoint_scripts"] = new LoadTableInfo("id", "id");

class DBConverter
{
	public $conn;
	public $file;
	
	public $tcStore;
	public $sunStore;
	
	//Import referenced smartai even if it already has a scriptname
	private $smart_import_contagious = false;
	//always import those regardless of smart_import_contagious
	private $force_import = [ "npc_obsidia", "npc_kalaran_windblade", "mobs_spitelashes" ];
	
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
    
    function LoadTable(string $table_name)
    {
        $loaded = $this->sunStore->LoadTable($this->conn, $table_name);
        $loaded = $this->tcStore->LoadTable($this->conn, $table_name) || $loaded;
        
        if ($loaded)
            echo "Loaded tables {$table_name}." . PHP_EOL;
    }
	
	/* This test pass if:
	- sunContainer does not contain key with this value
	- sunContainer does contain key with value but has the same as tcContainer
	Else, crash everything
	*/
	function CheckExistsBroadcast($value)
	{
        $this->LoadTable("broadcast_text");
        
		$key_name = "ID";
		$sun_text = $this->sunStore->FindFirst("broadcast_text", $value);
		if ($sun_text === null)
			return;
		
		$tc_text = $this->tcStore->FindFirst("broadcast_text", $value);
		if ($tc_text === null)
			throw new ImportException("Checked for broadcast existence but TC has no value?");
		
        // ignore lines returns and spaces differences
        $sun_text_trim = preg_replace('/\s+/', '', trim($sun_text->Text));
        $sun_text_trim = preg_replace('/\s+/', '', trim($tc_text->Text));
        
		$sun_text_trim = substr($sun_text_trim, 0, 255);
		$sun_text_trim = substr($sun_text_trim, 0, 255);
        
		if (levenshtein($sun_text_trim, $sun_text_trim) > 2) { //allow very similar strings

			// var_dump($sun_text_trim);
			// var_dump($sun_text_trim);
			LogError("TC and SUN containers have different results for table broadcast_text and value {$value}");
		}
		
		//OK
		return;
	}

	function CheckBroadcast(int $broadcast_id)
	{
        $this->LoadTable("broadcast_text");
        
		if ($this->tcStore->Exists("broadcast_text", $broadcast_id) === null)
			throw new ImportException("BroadcastText $broadcast_id does not exists in TC db");

		if ($this->sunStore->Exists("broadcast_text", $broadcast_id) === null)
			throw new ImportException("BroadcastText $broadcast_id does not exists in Sun db");

		$this->CheckExistsBroadcast($broadcast_id);
	}

	//overrideCheckSourceEntry is for menus that changed id, condition must apply to that new menu
	function SunHasCondition(&$tc_condition, $overrideCheckSourceEntry = null)
	{
        $this->LoadTable("conditions");
        
		$check_source_entry = $overrideCheckSourceEntry ? $overrideCheckSourceEntry : $tc_condition->SourceEntry;
		$sun_conditions = &$this->sunStore->FindAll("conditions", $check_source_entry);
		
		foreach($sun_conditions as &$sun_condition) {
			if (   $tc_condition->SourceTypeOrReferenceId == $sun_condition->SourceTypeOrReferenceId
			    && $tc_condition->SourceGroup == $sun_condition->SourceGroup
			    && $tc_condition->SourceId == $sun_condition->SourceId
			    && $tc_condition->ElseGroup == $sun_condition->ElseGroup
			    && $tc_condition->ConditionTypeOrReference == $sun_condition->ConditionTypeOrReference
			    && $tc_condition->ConditionTarget == $sun_condition->ConditionTarget
			    && $tc_condition->ConditionValue1 == $sun_condition->ConditionValue1
			    && $tc_condition->ConditionValue2 == $sun_condition->ConditionValue2
			    && $tc_condition->ConditionValue3 == $sun_condition->ConditionValue3
			    && $tc_condition->NegativeCondition == $sun_condition->NegativeCondition
			    && $tc_condition->ErrorType == $sun_condition->ErrorType
			    && $tc_condition->ErrorTextId == $sun_condition->ErrorTextId
			    && $tc_condition->ScriptName == $sun_condition->ScriptName)
			return true;
		}
		return false;
	}
    
	function CreateMenuConditions(int $tc_menu_id, int $sun_menu_id, int $tc_text_id, int $sun_text_id)
	{
		static $CONDITION_SOURCE_TYPE_GOSSIP_MENU = 14;
		 
        $this->LoadTable("conditions");

		$this->timelol("CMO", true);
		$this->timelol("CMO");
		
		$tc_conditions = &$this->tcStore->FindAll("conditions", $tc_text_id);

		foreach($tc_conditions as &$tc_condition) {
			if ($tc_condition->SourceGroup != $tc_menu_id) 
			   continue;

			if ($this->SunHasCondition($tc_condition, $sun_menu_id)) {
				LogDebug("Sun db already has this condition (tc menu {$tc_menu_id}, sun menu {$sun_menu_id})");
				continue;
			}
			
			$this->timelol("CMO");
			$sun_condition = $tc_condition; //copy
			$sun_condition->SourceGroup = $sun_menu_id;
			$sun_condition->SourceEntry = $sun_text_id;
			$sun_condition->Comment = "(autoimported) " . $tc_condition->Comment;
			$sun_condition->Comment = str_replace($tc_text_id, $sun_text_id, $sun_condition->Comment);
			$sun_condition->Comment = str_replace($tc_menu_id, $sun_menu_id, $sun_condition->Comment);
							
			fwrite($this->file, WriteObject($this->conn, "conditions", $sun_condition));
		}
		$this->timelol("CMO");
	}

	function CreateMenuOptionsConditions(int $tc_menu_id, int $sun_menu_id)
	{
		static $CONDITION_SOURCE_TYPE_GOSSIP_MENU_OPTION = 15;
		
        $this->LoadTable("conditions");
        
		foreach($this->tcStore->conditions as &$condition_arr) {
			foreach($condition_arr as &$tc_condition) {
				if ($tc_condition->SourceTypeOrReferenceId != $CONDITION_SOURCE_TYPE_GOSSIP_MENU_OPTION)
					continue;
				
				if ($tc_condition->SourceGroup != $tc_menu_id) //SourceGroup = menu_id / SourceEntry = option_id
					continue;
			
				if ($this->SunHasCondition($tc_condition, $sun_menu_id)) {
					LogDebug("Sun db already has this condition (tc menu {$tc_menu_id}, sun menu {$sun_menu_id})");
					continue;
				}
				
				$sun_condition = $tc_condition; //copy
				$sun_condition->SourceGroup = $sun_menu_id;
				$sun_condition->Comment = "(autoimported) " . $tc_condition->Comment;
				$sun_condition->Comment = str_replace($tc_menu_id, $sun_menu_id, $sun_condition->Comment);
				
				$this->sunStore->Insert("conditions", $sun_condition, $sun_condition->SourceEntry);
				fwrite($this->file, WriteObject($this->conn, "conditions", $sun_condition));
			}
		}
	}

	private $reused_sun_texts = [];
	private $moved_tc_texts = [];

	function CreateText(int $tc_text_id) : int
	{
		if (array_key_exists($tc_text_id, $this->moved_tc_texts)) {
			LogDebug("Text {$tc_text_id} is already imported as " . $this->moved_tc_texts[$tc_text_id]);
			return $this->moved_tc_texts[$tc_text_id];
		}
		
		if (CheckAlreadyImported($tc_text_id)) {
			LogDebug("Text {$tc_text_id} is already imported");
			return $tc_text_id;
		}
		
        $this->LoadTable("gossip_text");
        $this->LoadTable("npc_text");
        
		LogDebug("Importing text {$tc_text_id}");
		
		$tc_text = &$this->tcStore->FindFirst("npc_text", $tc_text_id);
		if ($tc_text === null) {
			LogError("TextId {$tc_text_id} does not exists in TC db?");
			assert(false);
			exit(1);
		}
		
		$sun_text_id = $tc_text_id;
		$sun_text = &$this->sunStore->FindFirst("gossip_text", $tc_text_id);
		if ($sun_text !== null) {
			if ($sun_text->text0_0 == $tc_text->text0_0 && $sun_text->text0_1 == $tc_text->text0_1) {
				array_push($this->reused_sun_texts, $tc_text_id);
				LogDebug("Text {$tc_text_id} already present in Sun DB"); //same text, stop here
				return $tc_text_id;
			}
			$sun_text_id = max(array_keys($this->sunStore->gossip_text)) + 1;
			
			$this->moved_tc_texts[$tc_text_id] = $sun_text_id;
		}
		
		//convert TC table to Sun table here
		$sun_text = new stdClass;
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
			
			for($j = 0; $j < 3; $j++) {
				$sunFieldName = 'em' . $i . '_' . $j;
				$tcFieldName = 'Emote' . $i . '_' . $j;
				$sun_text->$sunFieldName = $tc_text->$tcFieldName;
			}
		}
		
        $this->sunStore->Insert("gossip_text", $sun_text, $sun_text_id);
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
        
		$results = &$this->tcStore->FindAll("points_of_interest", $poi_id);
		if (count($results) != 1) {
			echo "TC points_of_interest has 0 or > 1 PoI for id {$poi_id}" . PHP_EOL;
			assert(false);
			exit(1);
		}
		
		$tc_poi = $results[0];
		
		//we assume if we have a poi with this id, it's already the same
		$results = &$this->sunStore->FindAll("points_of_interest", $poi_id);
		if (count($results) > 0) {
			LogDebug("POI {$poi_id} already present in sun db");
			return;
		}
		
		$sun_poi = $tc_poi; //simple copy
		$sun_poi->Icon = ConvertPoIIcon($tc_poi->Icon);
		fwrite($this->file, WriteObject($this->conn, "points_of_interest", $sun_poi));
        $this->sunStore->Insert("points_of_interest", $sun_poi, $poi_id);
		
		if ($tc_poi->Icon != $sun_poi->Icon) {
			$sun_poi_tlk = $tc_poi;
			$sun_poi_tlk->patch = 5; //LK patch
			fwrite($this->file, WriteObject($this->conn, "points_of_interest", $sun_poi_tlk));
            $this->sunStore->Insert("points_of_interest", $sun_poi_tlk, $poi_id);
		} 
	}

	//returns given id on error as well
	function ImportPageText(int $tc_page_text_id) : int
	{
		if (CheckAlreadyImported($tc_page_text_id))
			return $tc_page_text_id;

		$this->LoadTable("page_text");

		$tc_text = &$this->tcStore->FindFirst("page_text", $tc_page_text_id);
		if (!$tc_text) {
			LogError("Trying to import unknown page text $tc_page_text_id");
			return $tc_page_text_id;
		}
		$existing_sun_text = &$this->sunStore->FindFirst("page_text", $tc_page_text_id);
		if ($existing_sun_text && $existing_sun_text->Text == $tc_text->Text) {
			LogDebug("Text $tc_page_text_id already existing with the same text");
			return $tc_page_text_id;
		}

		$text_id = $existing_sun_text === null ? $tc_page_text_id : max(array_keys($this->sunStore->page_text))+ 1;
		$sun_text = $tc_text; // just copy
		$sun_text->ID = $text_id;
		unset($sun_text->VerifiedBuild);

		$sql = "DELETE FROM page_text WHERE ID = {$text_id};" . PHP_EOL;
		fwrite($this->file, $sql);

		$this->sunStore->Insert("page_text", $sun_text, $text_id);
		fwrite($this->file, WriteObject($this->conn, "page_text", $sun_text)); 

		return $text_id;
	}
	
	function CreateMenuOptions(int $tc_menu_id, int $sun_menu_id)
	{
		if (CheckAlreadyImported($tc_menu_id)) {
			LogDebug("Menu options for {$tc_menu_id} are already imported");
			return;
		}
		
        $this->LoadTable("gossip_menu_option");
        
		$results = &$this->tcStore->FindAll("gossip_menu_option", $tc_menu_id);
		if (empty($results))
			return; //no menu options found, this is a normal case
		
		foreach($results as &$tc_option) {
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
			
			//DELETE?
			
            $this->sunStore->Insert("gossip_menu_option", $sun_option, $sun_menu_id);
			fwrite($this->file, WriteObject($this->conn, "gossip_menu_option", $sun_option)); 
		}
	}

	function DeleteSunMenu(int $sun_menu_id)
	{
		if (CheckAlreadyImported($sun_menu_id))
			return;
		
        $this->LoadTable("creature_template");
        $this->LoadTable("gossip_menu");
        
		//only delete if only one menu is found
		$results = &$this->sunStore->FindAllByField("creature_template", "gossip_menu_id", $sun_menu_id);
		if (empty($results)) {
			echo "ERROR: Trying to delete non existing sun menu {$sun_menu_id}" . PHP_EOL;
			assert(false);
			exit(1);
		}
		
		if (sizeof($results) > 1)
			return; //more than one ref to this menu, skip
		
		$sql = "DELETE FROM gossip_menu WHERE MenuID = {$sun_menu_id};" . PHP_EOL;
		$this->sunStore->Remove("gossip_menu", $sun_menu_id);
		
		//currently bugged, because we reuse text that are the same
		/*
		$results2 = FindAll($this->sunStore->gossip_menu, "MenuID", $sun_menu_id);
		foreach($results2 as $sun_menu) {
			$text_id = $sun_menu->text_id;
			if (array_key_exists($text_id, $this->reused_sun_texts))
				continue; //we use it!
			$results3 = FindAll($this->sunStore->gossip_text, "ID", $text_id);	
			if (sizeof($results3) > 1 || array_key_exists($text_id, $this->reused_sun_texts))
				continue; //more than one ref to this text, skip
				
			$sql .= "DELETE FROM gossip_text WHERE ID = {$text_id};" . PHP_EOL;
		}*/
		fwrite($this->file, $sql);
	}

	private $converted_tc_menus = [];

	// return sun menu
	function CreateMenu(int $tc_menu_id) : ?int
	{
		if (array_key_exists($tc_menu_id, $this->converted_tc_menus))
		{
			LogDebug("Menu {$tc_menu_id} is already imported as " . $this->converted_tc_menus[$tc_menu_id]);
			return $this->converted_tc_menus[$tc_menu_id];
		}
		
		if (CheckAlreadyImported($tc_menu_id)) {
			LogDebug("Menu {$tc_menu_id} is already imported");
			return $tc_menu_id;
		}
		
        // preloading those table to exclude that from the performance check
        $this->LoadTable("broadcast_text");
        $this->LoadTable("gossip_menu");
        $this->LoadTable("npc_text");
        $this->LoadTable("gossip_text");
        $this->LoadTable("conditions");
        
		$this->timelol("CM", true);
		$this->timelol("CM");
		
		$results = &$this->tcStore->FindAll("gossip_menu", $tc_menu_id);
		if (empty($results))
		{
			LogError("Failed to find TC menu {$tc_menu_id}");
			return null;
		}
		
		$this->timelol("CM");
		
		$sun_menu_id = null;
		if ($this->sunStore->Exists("gossip_menu", $tc_menu_id)) {
			//would be very complicated to compare menus... just import a new one
			$sun_menu_id = max(array_keys($this->sunStore->gossip_menu))+ 1;
			$this->converted_tc_menus[$tc_menu_id] = $sun_menu_id;
			LogDebug("Importing menu {$tc_menu_id} as {$sun_menu_id}");
		}
		else
			$sun_menu_id = $tc_menu_id;
		
		$this->timelol("CM");
		
		foreach($results as &$tc_menu) {
			$tc_text_id = $tc_menu->TextID;
			
			LogDebug("Importing tc menu {$tc_menu_id} (text {$tc_text_id}) into sun menu {$sun_menu_id}");
			
			$sun_text_id = $this->CreateText($tc_text_id);
			assert($sun_text_id != '' && $sun_text_id > 0);
			
			$sun_menu = new stdClass; //anonymous object
			$sun_menu->MenuID = $sun_menu_id;
			$sun_menu->TextID = $sun_text_id;
				
            $this->sunStore->Insert("gossip_menu", $sun_menu, $sun_menu_id);
			$this->CreateMenuConditions($tc_menu_id, $sun_menu_id, $tc_text_id, $sun_text_id);
			fwrite($this->file, WriteObject($this->conn, "gossip_menu", $sun_menu)); 
		}
		$this->timelol("CM");
		$this->CreateMenuOptions($tc_menu_id, $sun_menu_id);
		$this->timelol("CM");
		$this->CreateMenuOptionsConditions($tc_menu_id, $sun_menu_id);

		$this->timelol("CM");
		return $sun_menu_id;
	}

	function SetMenuId(int $entry, int $sun_menu_id, bool $set_gossip_flag)
	{
		$npcflag = $set_gossip_flag ? "npcflag = (npcflag | 1), " : "";
		$sql = "UPDATE creature_template SET {$npcflag}gossip_menu_id = {$sun_menu_id} WHERE entry = {$entry};" . PHP_EOL;
		fwrite($this->file, $sql);
	}

	function &FindAllSmart(int $sun, int $entryorguid, int $source_type) : array
	{
        $this->LoadTable("smart_scripts");
        
		$results = [];
		
		$this->timelol("FA", true);
		$this->timelol("FA");
		
		if ($sun) {
			$results = &FindAll($this->sunStore->FindAll("smart_scripts", $entryorguid), "source_type", $source_type);
		}
		else {
			$results = &FindAll($this->tcStore->FindAll("smart_scripts", $entryorguid), "source_type", $source_type);
		}
		$this->timelol("FA");
		
		return $results;
	}

	function DeleteAllSmart(int $entryorguid, int $source_type)
	{
        $this->LoadTable("smart_scripts");
        
		$this->timelol("DeleteAllSmart", true);
		$this->timelol("DeleteAllSmart");
		
		if (CheckAlreadyImported($entryorguid + $source_type << 28)) //max entry is 30.501.000 (smaller number with 28 bits shift is 268.435.456)
			return;
		
		$sql = "DELETE FROM smart_scripts WHERE entryorguid = {$entryorguid} AND source_type = {$source_type};" . PHP_EOL;
		fwrite($this->file, $sql);
		
		$this->timelol("DeleteAllSmart");
		
		$results = $this->FindAllSmart(true, $entryorguid, $source_type);
		foreach($results as $sun_smart) {
			switch($sun_smart->action_type)
			{
				case SmartAction::CALL_TIMED_ACTIONLIST:
					$this->DeleteAllSmart($sun_smart->action_param1, SmartSourceType::timedactionlist);
					break;
			}
		}

		$this->timelol("DeleteAllSmart");
		// Unset in store
		if (array_key_exists($entryorguid, $this->sunStore->smart_scripts)) {
			foreach($this->sunStore->smart_scripts[$entryorguid] as $key => &$row)
				if ($row->source_type == $source_type)
					unset($this->sunStore->smart_scripts[$entryorguid][$key]);
		}

		$this->timelol("DeleteAllSmart");
	}

	function CreateCreatureText(int $tc_entry)
	{
		if (CheckAlreadyImported($tc_entry)) {
			LogDebug("Creature text {$tc_entry} is already imported");
			return;
		}
        $this->LoadTable("creature_text");

		$this->timelol("CCT", true);
		$this->timelol("CCT");

		$results = &$this->tcStore->FindAll("creature_text", $tc_entry);
		if (empty($results)) 
			throw new ImportException("ERROR: Could not find TC creature_text for creature id {$tc_entry}");

		$sql = "DELETE FROM creature_text WHERE CreatureID = {$tc_entry};" . PHP_EOL;
		fwrite($this->file, $sql);

		$this->timelol("CCT");
		foreach($results as &$text_entry) {
			if ($text_entry->BroadcastTextId)
				$this->CheckBroadcast($text_entry->BroadcastTextId);

            $this->sunStore->Insert("creature_text", $text_entry, $tc_entry);
			fwrite($this->file, WriteObject($this->conn, "creature_text", $text_entry)); 
		}
		$this->timelol("CCT");
	}

	function CreateSmartConditions(int $tc_entry, int $source_type)
	{
		if (CheckAlreadyImported($tc_entry + $source_type << 28)) { //max entry is 30.501.000 (smaller number with 28 bits shift is 268.435.456)
			LogDebug("Smart condition {$tc_entry} {$source_type} is already imported");
			return;
		}
		
        $this->LoadTable("conditions");
        
		static $CONDITION_SOURCE_TYPE_SMART_EVENT = 22;

		$tc_conditions = &$this->tcStore->FindAll("conditions", $tc_entry);
		foreach($tc_conditions as &$tc_condition) {
			if ($tc_condition->SourceTypeOrReferenceId != $CONDITION_SOURCE_TYPE_SMART_EVENT)
				continue;
			
			//SourceGroup == id, but we import all
			
			if ($tc_condition->SourceId != $source_type) 
				continue;
		
			if ($this->SunHasCondition($tc_condition)) {
				LogDebug("Sun db already has this condition (entry: {$tc_entry})");
				continue;
			}
			$sun_condition = $tc_condition; // copy
			$sun_condition->Comment = "(autoimported) " . $tc_condition->Comment;
			
			$this->sunStore->Insert("conditions", $sun_condition, $sun_condition->SourceEntry);
			fwrite($this->file, WriteObject($this->conn, "conditions", $sun_condition));		
		}
	}

	function ImportSummonGroup(int $npcEntry, int $group_id)
	{
		if (CheckAlreadyImported($npcEntry + $group_id << 28)) {
			LogDebug("Summon group for npc {$npcEntry} group {$group_id} is already imported");
			return;
		}

        $this->LoadTable("creature_summon_groups");

		// could be improved, currently we just port everything and ignore group
		$tc_results = &$this->tcStore->FindAll("creature_summon_groups", $npcEntry);
		if (empty($tc_results))
			throw new ImportException("Could not find TC summon group {$npcEntry}");

		$sun_results = &$this->sunStore->FindAll("creature_summon_groups", $npcEntry);
		if ($tc_results == $sun_results) {
			LogDebug("Summon group for npc {$npcEntry} group {$group_id} is already existing and identical");
			return;
		}

		$sql = "DELETE FROM creature_summon_groups WHERE summonerId = {$npcEntry};" . PHP_EOL;
		fwrite($this->file, $sql);
		$this->sunStore->Remove("creature_summon_groups", $npcEntry);

		foreach($tc_results as &$tc_group) {
            $this->sunStore->Insert("creature_summon_groups", $tc_group, $npcEntry);
			fwrite($this->file, WriteObject($this->conn, "creature_summon_groups", $tc_group)); 

			$entry = $tc_group->entry;
			$dummy = 0;
			try {
				$this->ImportReferencedCreatureSmart($entry, $dummy, false);
			} catch (ImportException $e) {
				LogException($e, "Summon group {$npcEntry} group {$group_id}: Failed to import smart for summoned creature: {$e->getMessage()}");
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

		$results = &$this->tcStore->FindAll("waypoints", $path_id);
		if (empty($results)) 
			throw new ImportException("Could not find TC waypoints {$path_id}");

        if (CheckIdenticalExtended($this->sunStore->waypoints, $this->tcStore->waypoints, $path_id, "pointid")) {
			LogDebug("Smart Waypoints {$path_id} are already the same on sun");
            return;
        }
		$sun_results = &$this->sunStore->FindAll("waypoints", $path_id);
		if (!empty($sun_results)) {
            echo "TC and SUN table have different smart waypoints for path_id {$path_id}, overwritting with TC ones.". PHP_EOL;
			$sql = "DELETE FROM waypoints WHERE entry = {$path_id};" . PHP_EOL;
			fwrite($this->file, $sql);
			$this->sunStore->Remove("waypoints", $path_id);
		}

		foreach($results as &$tc_waypoint) {
            $this->sunStore->Insert("waypoints", $tc_waypoint, $path_id);
			fwrite($this->file, WriteObject($this->conn, "waypoints", $tc_waypoint)); 
		}
	}
    
	private $converted_loot_template = [];
    
    // returns new sun id
    function ImportLootTemplate(string $table_name, int $tc_loot_id) : ?int
    {
        //echo "ImportLootTemplate $table_name $tc_loot_id" . PHP_EOL;
        if (!array_key_exists($table_name, $this->converted_loot_template))
            $this->converted_loot_template[$table_name] = [];
        
        if (array_key_exists($tc_loot_id, $this->converted_loot_template[$table_name]))
        {
            $newId = $this->converted_loot_template[$table_name][$tc_loot_id];
			LogDebug("{$table_name} {$tc_loot_id} is already imported as " . $newId);
            
			return $newId;
		}
		
        $this->LoadTable($table_name);
        
        $sun_id = $tc_loot_id;
        
        if ($this->sunStore->Exists($table_name, $tc_loot_id)) {
            // Do we have an already existing loot at this id with same values?
            if (CheckIdenticalExtended($this->sunStore->$table_name, $this->tcStore->$table_name, $tc_loot_id, "Item")) {
                LogDebug("{$table_name} {$tc_loot_id}: found already identical loot in our DB at this ID, skipping");
                // we assume referenced loot template are also the same if they have the same ids
                return $tc_loot_id;
            } else {
                // generate new id instead
                $sun_id = max(array_keys(($this->sunStore->$table_name))) + 1;
            }
        }
        
        $tc_loot_results = &$this->tcStore->FindAll($table_name, $tc_loot_id);
        if (empty($tc_loot_results)) {
            LogError("Non existing loot for {$table_name} id {$tc_loot_id}");
            return null;
        }
        //print_r($tc_loot_results);
        
		fwrite($this->file, "DELETE FROM {$table_name} WHERE Entry = {$sun_id};" . PHP_EOL);
        foreach($tc_loot_results as &$tc_loot) {
            $sun_loot = $tc_loot; // start with a copy
            $sun_loot->Entry = $sun_id;
            
            //LogDebug("importing TC {$table_name} id {$tc_loot_id} as {$sun_id} with ref {$tc_loot->Reference}");
            // Create Reference if any
            $sun_loot->Reference = $tc_loot->Reference ? $this->ImportLootTemplate("reference_loot_template", $tc_loot->Reference) : 0;
            if ($sun_loot->Reference === null)
				$sun_loot->Reference = 0; // field can't be null

            $this->sunStore->Insert($table_name, $sun_loot, $sun_id);
			BatchWrite($this->file, $this->conn, $table_name, $sun_loot);
			//fwrite($this->file, WriteObject($this->conn, $table_name, $sun_loot)); 
		}
        
		FlushWrite($this->file, $this->conn);

        $this->converted_loot_template[$table_name][$tc_loot_id] = $sun_id;
        return $sun_id;
    }

    // returns converted flag
    function ConvertFlagsExtraTCtoSun(int $tc_flags) : int
    {
        // first 8 flags are the same
        $sun_flags = $tc_flags & 0xFF; 

        if ($tc_flags & TCCreatureFlagsExtra::CREATURE_FLAG_EXTRA_NO_TAUNT)
            $sun_flags |= SunCreatureFlagsExtra::CREATURE_FLAG_EXTRA_NO_TAUNT;
        if ($tc_flags & TCCreatureFlagsExtra::CREATURE_FLAG_EXTRA_GHOST_VISIBILITY)
            $sun_flags |= SunCreatureFlagsExtra::CREATURE_FLAG_EXTRA_GHOST_VISIBILITY;
        if ($tc_flags & TCCreatureFlagsExtra::CREATURE_FLAG_EXTRA_GUARD)
            $sun_flags |= SunCreatureFlagsExtra::CREATURE_FLAG_EXTRA_GUARD;
        if ($tc_flags & TCCreatureFlagsExtra::CREATURE_FLAG_EXTRA_NO_CRIT)
            $sun_flags |= SunCreatureFlagsExtra::CREATURE_FLAG_EXTRA_NO_CRIT;
        if ($tc_flags & TCCreatureFlagsExtra::CREATURE_FLAG_EXTRA_ALL_DIMINISH)
            $sun_flags |= SunCreatureFlagsExtra::CREATURE_FLAG_EXTRA_ALL_DIMINISH;
        if ($tc_flags & TCCreatureFlagsExtra::CREATURE_FLAG_EXTRA_NO_PLAYER_DAMAGE_REQ)
            $sun_flags |= SunCreatureFlagsExtra::CREATURE_FLAG_EXTRA_NO_PLAYER_DAMAGE_REQ;

        //others have no equivalence as of writing (23/07/2021)

        return $sun_flags;
    }

	function DeleteLKCreatureEntry(string $table_name, string $key_column_name, string $patch_column_name = null, int $creature_id)
	{
        $this->LoadTable($table_name);
		if ($patch_column_name === null)
			$this->sunStore->Remove($table_name, $creature_id);
		else
			$this->sunStore->RemoveOnCondition($table_name, $creature_id, $patch_column_name, 5);

		$sql = "DELETE FROM {$table_name} WHERE {$key_column_name} = {$creature_id}" . ($patch_column_name !== null ? " AND {$patch_column_name} = 5" : "") . ";" . PHP_EOL;
		fwrite($this->file, $sql); 
	}

    function ImportCreatureTemplateAddon(int $creature_id)
    {
		if (CheckAlreadyImported($creature_id))
			return;
        
        $this->LoadTable("creature_template_addon");
        
		$tc_creature_template_addon = &$this->tcStore->FindFirst("creature_template_addon", $creature_id);
		if ($tc_creature_template_addon === null)
            return;
        
		$sun_result = $this->sunStore->FindFirst("creature_template_addon", $creature_id); // ignore patch here, most of the time we just want to use the same data for both TBC and TLK
		if (!empty($sun_result)) {
			if (CheckIdenticalObject($sun_result, $tc_creature_template_addon))
			{
				LogDebug("Found identical creature_template_addon for {$creature_id}.");
				return; // already identical
			}
			throw new ImportException("Trying to import already existing creature template addon {$creature_id}");
		}

        // copy TC one and make some arrangements
        $sun_creature_template_addon = $tc_creature_template_addon;
		$sun_creature_template_addon->patch = 5;
        $stand_state = $tc_creature_template_addon->bytes1 & 0xF; // first byte is stand state
        $sun_creature_template_addon->standState = $stand_state;
        unset($sun_creature_template_addon->bytes1);
        unset($sun_creature_template_addon->bytes2);
        unset($sun_creature_template_addon->MountCreatureID);

        if ($sun_creature_template_addon->path_id)
            $sun_creature_template_addon->path_id = $this->ImportWaypoints(0, $tc_creature_template_addon->path_id, false);
        else
            $sun_creature_template_addon->path_id = null;
        
		$this->DeleteLKCreatureEntry("creature_template_addon", "entry", "patch", $creature_id);
        $this->sunStore->Insert("creature_template_addon", $sun_creature_template_addon, $creature_id);
		fwrite($this->file, WriteObject($this->conn, "creature_template_addon", $sun_creature_template_addon)); 
    }

    function ImportCreatureTemplateMovement(int $creature_id)
    {
		if (CheckAlreadyImported($creature_id))
			return;

        $this->LoadTable("creature_template_movement");

		$creature_template_movement = $this->tcStore->FindFirst("creature_template_movement", $creature_id);
		if ($creature_template_movement == null)
            return;

		$sun_result = $this->sunStore->FindFirst("creature_template_movement", $creature_id);
		if ($sun_result !== null) {
            if (CheckIdenticalObject($sun_result, $creature_template_movement)) {
                LogDebug("creature_template_movement for creature {$creature_id} is already in db and identical.");
                return;
            } else
				throw new ImportException("Trying to import already existing creature template movement {$creature_id}");
				// Got diverging data, need a patch column for this table then? Or manual fix. Change DeleteLKCreatureEntry line below if adding a patch column to the table.
        }

        // copy TC one and make some arrangements
        unset($creature_template_movement->InteractionPauseTimer);
        
		$this->DeleteLKCreatureEntry("creature_template_movement", "CreatureId", null, $creature_id);
        $this->sunStore->Insert("creature_template_movement", $creature_template_movement, $creature_id);
		fwrite($this->file, WriteObject($this->conn, "creature_template_movement", $creature_template_movement)); 
    }

    function ImportCreatureTemplateResistance(int $creature_id)
    {
		if (CheckAlreadyImported($creature_id))
			return;

        $this->LoadTable("creature_template_resistance");
        
        $tc_results = &$this->tcStore->FindAll("creature_template_resistance", $creature_id);
		if (empty($tc_results))
            return;
        
		$sun_results = &$this->sunStore->FindAll("creature_template_resistance", $creature_id);
		if (!empty($sun_results)) {
			if (_CheckIdentical($sun_results, $tc_results, "School")) {
				LogDebug("creature_template_resistance for creature {$creature_id} is already in db and identical.");
				return;
			} else {
				throw new ImportException("Trying to import already existing creature template resistance {$creature_id}");
				// Got diverging data, what should be do there? Just import for patch 5 and not care?
			}
        }

		$this->DeleteLKCreatureEntry("creature_template_resistance", "CreatureID", "patch", $creature_id);
		foreach ($tc_results as $creature_template_resistance) {
			// we copy TC one and make some arrangements
			$creature_template_resistance->patch = 5;
			unset($creature_template_resistance->VerifiedBuild);

            $this->sunStore->Insert("creature_template_resistance", $creature_template_resistance, $creature_id);
			BatchWrite($this->file, $this->conn, "creature_template_resistance", $creature_template_resistance);
		}
		FlushWrite($this->file, $this->conn);
    }
    
    function ImportCreatureTemplateSpell(int $creature_id)
    {
		if (CheckAlreadyImported($creature_id))
			return;
        
        $this->LoadTable("creature_template_spell");
        
        $tc_results = &$this->tcStore->FindAll("creature_template_spell", $creature_id);
		if (empty($tc_results))
            return;
        
		$sun_results = &$this->sunStore->FindAll("creature_template_spell", $creature_id);
		if (!empty($sun_results))
		{
			if (_CheckIdentical($sun_results, $tc_results, "Index"))
			{
				LogDebug("creature_template_spell for creature {$creature_id} is already in db and identical.");
				return;
			}
			else
				throw new ImportException("Trying to import already existing creature template spells {$creature_id}");
				// Got diverging data, what should be do there? Just import for patch 5 and not care?
		}

		$this->DeleteLKCreatureEntry("creature_template_spell", "CreatureID", "patch", $creature_id);
		foreach($tc_results as $creature_template_spell)
		{
			// copy TC one and make some arrangements
			$creature_template_spell->patch = 5;
			unset($creature_template_spell->VerifiedBuild);
			
            $this->sunStore->Insert("creature_template_spell", $creature_template_spell, $creature_id);
			BatchWrite($this->file, $this->conn, "creature_template_spell", $creature_template_spell);
		}
		FlushWrite($this->file, $this->conn);
    }

	// used to check what's freshly imported and avoid some circular import issue
	private $just_imported_creatures = [];

	function ImportCreatureTemplate(int $creature_id, bool $force = false)
	{
		if (CheckAlreadyImported($creature_id))
			return;

		array_push($this->just_imported_creatures, $creature_id);
        $this->LoadTable("creature_template");

		$tc_creature_template = &$this->tcStore->FindFirst("creature_template", $creature_id);
		if ($tc_creature_template === null)
			throw new ImportException("Trying to import non existing TLK creature template {$creature_id}");

		$sun_results = &$this->sunStore->FindAll("creature_template", $creature_id);
		if (!empty($sun_results) && !$force)
			throw new ImportException("Trying to import already existing creature template {$creature_id}");

        // use TC entry copy and make some arrangements
		$creature_template = $tc_creature_template;
		$creature_template->patch = 5;
        unset($creature_template->import);
        unset($creature_template->movementId);
        unset($creature_template->RegenHealth);
        unset($creature_template->VerifiedBuild);

        $creature_template->flags_extra = $this->ConvertFlagsExtraTCtoSun($creature_template->flags_extra);
        $creature_template->lootid = $creature_template->lootid ? $this->ImportLootTemplate("creature_loot_template", $creature_template->lootid) : null;
        $creature_template->pickpocketloot = $creature_template->pickpocketloot ? $this->ImportLootTemplate("pickpocketing_loot_template", $creature_template->pickpocketloot) : null;
        $creature_template->skinloot = $creature_template->skinloot ? $this->ImportLootTemplate("skinning_loot_template", $creature_template->skinloot) : null;
        $creature_template->gossip_menu_id = $creature_template->gossip_menu_id ? $this->CreateMenu($creature_template->gossip_menu_id) : null;

		$this->DeleteLKCreatureEntry("creature_template", "entry", "patch", $creature_id);
        $this->sunStore->Insert("creature_template", $creature_template, $creature_id);
		fwrite($this->file, WriteObject($this->conn, "creature_template", $creature_template)); 
		
        $this->ImportCreatureTemplateAddon($creature_id);
        $this->ImportCreatureTemplateMovement($creature_id);
        $this->ImportCreatureTemplateResistance($creature_id);
        $this->ImportCreatureTemplateSpell($creature_id);
        
        $this->ImportCreatureSmartAI($creature_id);

		//Todo: npc spell click spells... already done manually so we won't do it here but it was missing
	}

	function IsIdenticalSmartAI(int $entry, bool $creature) : bool
	{
		$sun_results = &$this->sunStore->FindAll("smart_scripts", $entry);
		$tc_results = &$this->tcStore->FindAll("smart_scripts", $entry);
		return CheckIdentical($sun_results, $tc_results, "source_type", $creature ? SmartSourceType::creature : SmartSourceType::gameobject, "id");
	}

	function ImportGameObjectSmartAI(int $gob_id)
	{
		if (CheckAlreadyImported($gob_id))
			return;
        
        $this->LoadTable("gameobject_template");
        
		$tc_gameobject_template = $this->tcStore->FindFirst("gameobject_template", $gob_id);
        if ($tc_gameobject_template->AIName != "SmartAI")
            return;

		$this->LoadTable("smart_scripts");
		if ($this->IsIdenticalSmartAI($gob_id, false)) {
			//echo "Already identical, skipping" . PHP_EOL;
			LogDebug("SmartAI for gob {$gob_id} is already in db and identical.");
			return;
		}

		$this->CreateSmartAI(SmartSourceType::gameobject, $gob_id, SmartSourceType::gameobject, $gob_id, false);
	}
	
    function ImportCreatureSmartAI(int $creature_id)
    {
		if (CheckAlreadyImported($creature_id))
			return;
        
        $this->LoadTable("creature_template");
        
		$tc_creature_template = $this->tcStore->FindFirst("creature_template", $creature_id);
        if ($tc_creature_template->AIName != "SmartAI")
            return;
        
        $this->LoadTable("smart_scripts");
		if ($this->IsIdenticalSmartAI($creature_id, true)) {
			//echo "Already identical, skipping" . PHP_EOL;
			LogDebug("SmartAI for creature {$creature_id} is already in db and identical.");
			return;
		}
        
		$this->CreateSmartAI(SmartSourceType::creature, $creature_id, SmartSourceType::creature, $creature_id, false);
    }

    function ImportReferencedCreatureSmart(int $creature_id, int& $patch, bool $has_to_be_smart) 
	{
		if (CheckAlreadyImported($creature_id))
			return;

        $this->LoadTable("creature_template");
        $this->LoadTable("smart_scripts");

		$tc_creature_template = $this->tcStore->FindFirst("creature_template", $creature_id);
		if ($tc_creature_template === null)
			throw new ImportException("Has a reference on a non existing creature {$creature_id}");

		if ($tc_creature_template->AIName != "SmartAI") {
			if ($has_to_be_smart)
				throw new ImportException("Has a reference on a non Smart creature {$creature_id}");
			else 
				return;
		}

		$this->timelol("CIC", true);
		$this->timelol("CIC");
		if ($this->IsIdenticalSmartAI($creature_id, true)) {
			//echo "Already identical, skipping" . PHP_EOL;
			LogDebug("SmartAI for creature {$creature_id} is already in db and identical.");
			return;
		}

		$this->timelol("CIC");

		if (in_array($creature_id, $this->just_imported_creatures))
			return;

		echo "Importing SmartAI for referenced summon/target creature id {$creature_id}, "; //... continue this line later
		$sun_results = &$this->sunStore->FindAll("creature_template", $creature_id);
		if (!empty($sun_results)) {
            foreach($sun_results as $sun_result) {
                $sun_ai_name = $sun_result->AIName;
                $sun_script_name = $sun_result->ScriptName;

                if ($sun_ai_name == "" && $sun_script_name == "")
                    echo "it currently has no script on sunstrider." . PHP_EOL;
                else {
                    if ($this->smart_import_contagious || in_array($sun_script_name, $this->force_import)) {
                        echo "it currently had AIName '{$sun_ai_name}' and ScriptName '{$sun_script_name}'." . PHP_EOL;
						// then just proceed
                    } else {
                        echo PHP_EOL;
						if ($this->IsIdenticalSmartAI($creature_id, true))
							return;
						else
                        	throw new ImportException("This would replace a creature (id: {$creature_id}) which already has a script ({$sun_ai_name}/{$sun_script_name}), no import.", false);
                    }
                }
            }
		}
        else
		{
            echo "the creature currently does not exists in sunstrider DB. Importing it now" . PHP_EOL;
			$this->ImportCreatureTemplate($creature_id);
			return; // smart will be imported already
		}

		$this->CreateSmartAI(SmartSourceType::creature, $creature_id, SmartSourceType::creature);
		$this->timelol("CIC");
	}

	function ImportReferencedGameObjectSmart(int $gob_id, int& $patch, bool $has_to_be_smart)
	{
		if (CheckAlreadyImported($gob_id))
			return;

        $this->LoadTable("gameobject_template");

		$tc_gob_template = &$this->tcStore->FindFirst("gameobject_template", $gob_id);
		if ($tc_gob_template === null)
			throw new ImportException("Smart reference a non existing gameobject {$gob_id}");

		if ($tc_gob_template->AIName != "SmartAI")
			throw new ImportException("Smart reference a non Smart gameobject {$gob_id}");

		if ($this->IsIdenticalSmartAI($gob_id, false)) {
			//echo "Already identical, skipping" . PHP_EOL;
			LogDebug("SmartAI for gob {$gob_id} is already in db and identical.");
			return;
		}

		$sun_results = &FindAll($this->sunStore->FindAll("gameobject_template", $gob_id), "patch", 0);
		if (empty($sun_results)) {
			LogWarning("Targeted gob entry {$gob_id} doesn't exists in our database");
			$patch = 5;
			return;
		}

		echo "Smart TC {$from_entry} targets gameobject id {$gob_id}, also importing it, "; //... continue this line later

		$sun_gob_template = $this->sunStore->FindFirst("gameobject_template", $creature_id);
		$sun_ai_name = $sun_gob_template ->AIName;
		$sun_script_name = $sun_gob_template ->ScriptName;

		if ($sun_ai_name == "" && $sun_script_name == "")
			echo "it currently has no script." . PHP_EOL;
		else 
			echo "It currently had AIName '{$sun_ai_name}' and ScriptName '{$sun_script_name}." . PHP_EOL;

		$this->CreateSmartAI(SmartSourceType::gameobject, $gob_id, SmartSourceType::gameobject);
	}

	function timelol($id, bool $reset = false, int $limit = 1)
	{
		global $debug;
		if (!$debug)
			return;
        
		static $start = [];
        if ($reset)
        {
            unset($start[$id]);
            return;
        }
		if (array_key_exists($id, $start))
		{
			$duration = microtime(true) - $start[$id];
			if ($duration > $limit) {
				echo "{$id} - Duration: {$duration}s" . PHP_EOL;
				assert(false);
			}
		}
		$start[$id] = microtime(true);
	}

	function CheckImportCreature(int $spawn_id)
	{
        $this->LoadTable("creature");

		if (!$this->tcStore->Exists("creature",  $spawn_id))
			throw new ImportException("Smart TC trying to target a non existing creature guid {$spawn_id}... this is a tc db error. Ignoring.", false);

		if ($this->sunStore->Exists("creature", $spawn_id))
			return; //already present

		LogWarning("Trying to use creature spawnID {$spawn_id} existing only on TC, importing it now.");
		$this->ImportTCCreature($spawn_id, 5, 10);
		$this->HandleFormations();
	}

	function CheckImportGameObject(int $spawn_id)
	{
        $this->LoadTable("gameobject");
        $this->LoadTable("gameobject_template");
        
		if (!$this->tcStore->Exists("gameobject", $spawn_id))
			throw new ImportException("Smart TC trying to target a non existing gob guid {$spawn_id} on their own db... this is a tc db error. Ignoring.");

		if ($this->sunStore->Exists("gameobject", $spawn_id))
			return; //already present
		
		LogWarning("Trying to use target a gob spawnID {$spawn_id} existing only on TC, importing it now.");
		$this->ImportTCGameObject($spawn_id, 5, 10);
	}

	function ImportSmartForTarget(&$sun_smart_entry, int $original_type, int $original_entry, int& $patch) : array
	{
		$this->LoadTable("creature");
		$this->LoadTable("gameobject");
        
		$creature_id = 0;
		$gameobject_id = 0;

		switch($sun_smart_entry->target_type)
		{
			case SmartTarget::NONE:
			case SmartTarget::SELF:
				break;
			case SmartTarget::CREATURE_DISTANCE:
			case SmartTarget::CLOSEST_CREATURE:
			case SmartTarget::CREATURE_RANGE:
				if ($sun_smart_entry->target_param1)
					$creature_id = intval($sun_smart_entry->target_param1);
				else if ($sun_smart_entry->target_type != SmartTarget::CREATURE_RANGE)
					throw new ImportException("Target {$sun_smart_entry->target_type} of smart {$sun_smart_entry->entryorguid} id {$sun_smart_entry->id} has no target entry. Wut?");
				break;
			case SmartTarget::CREATURE_GUID:
				$spawn_id = $sun_smart_entry->target_param1;
				//$creature_id = $sun_smart_entry->target_param2;
				$results = &$this->tcStore->FindAll("creature", $spawn_id);
				if (empty($results)) 
					throw new ImportException("Could not find tc creature with guid {$spawn_id} for target CREATURE_GUID");
					
				$creature_id = $results[0]->id;
				try {
					$this->CheckImportCreature($spawn_id);
				} catch (ImportException $e) {
					throw new ImportException("importing creature spawn_id {$spawn_id} with error: {$e->getMessage()}", $e->_error);
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
				break;
			case SmartTarget::GAMEOBJECT_RANGE:
			case SmartTarget::GAMEOBJECT_DISTANCE:
			case SmartTarget::CLOSEST_GAMEOBJECT:
				$gameobject_id = $sun_smart_entry->target_param1;
				break;
			case SmartTarget::GAMEOBJECT_GUID:
				$spawn_id = $sun_smart_entry->target_param1;
				$tc_gob = $this->tcStore->FindFirst("gameobject", $spawn_id);
				if ($tc_gob === null)
					throw new ImportException("SCould not find TC gameobject with spawn_id {$spawn_id} for target GAMEOBJECT_GUID. This is a TC error.");

				$gameobject_id = $tc_gob->id;
				try {
					$this->CheckImportGameObject($spawn_id);
				} catch (ImportException $e) {
					throw new ImportException("importing gameobject spawn_id {$spawn_id} with error: {$e->getMessage()}", $e->_error);
				}
				break;
			default:
				throw new ImportException("Target {$sun_smart_entry->target_type} NYI for Smart TC {$sun_smart_entry->entryorguid} {$sun_smart_entry->id}");
		}

		if ($creature_id)
		{
			try {
				$this->ImportReferencedCreatureSmart($creature_id, $patch, false);
			} catch (ImportException $e) {
				throw new ImportException("importing creature smart {$creature_id} with error: {$e->getMessage()}", $e->_error);
			}
			return array(SmartSourceType::creature, $creature_id);
		}
		else if ($gameobject_id)
		{
			try {
				$this->ImportReferencedGameObjectSmart($gameobject_id, $patch, false);
			} catch (ImportException $e) {
				throw new ImportException("importing gob smart {$gameobject_id} with error: {$e->getMessage()}", $e->_error);
			}
			return array(SmartSourceType::gameobject, $gameobject_id);
		}
		else
			return array($original_type, $original_entry);
	}

	function ImportSmartActionList(int $action_list_entry, int $original_type, int $original_entry, &$sun_smart_entry, int& $patch)
	{
		// try to get the creature/gob the action list will be run on
		list($target_type, $target_entry) = $this->ImportSmartForTarget($sun_smart_entry, $original_type, $original_entry, $patch);
		$this->CreateSmartAI(SmartSourceType::timedactionlist, $action_list_entry, $target_type, $target_entry); 	
	}
	
	// original_entry: if we're in an action list, the creature/gob it was called from
	function CreateSmartAI(int $source_type, int $tc_entry, int $original_type, int $original_entry = 0, bool $update_script_name = true)
	{
		if (CheckAlreadyImported($tc_entry + $source_type << 28)) { //max entry is 30'501'000 (smaller number with 28 bits shift is 268'435'456)
			LogDebug("SmartAI {$tc_entry} {$source_type} is already imported");
			return;
		}
		
        $this->LoadTable("smart_scripts");
        $this->LoadTable("spell_template");
        $this->LoadTable("creature_text");
        $this->LoadTable("creature");
        $this->LoadTable("broadcast_text");
        $this->LoadTable("game_event");
        
		$this->timelol("CreateSmartAI", true);
		$this->timelol("CreateSmartAI");
		
        if ($update_script_name) {
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
        }
		$this->DeleteAllSmart($tc_entry, $source_type);
		
		$this->timelol("CreateSmartAI");
		
		$results = $this->FindAllSmart(false, $tc_entry, $source_type);
		if (empty($results))
			throw new ImportException("Failed to find TC SmartAI with entry {$tc_entry} and type {$source_type}");
		
		$this->timelol("CreateSmartAI");
		
		foreach($results as &$smart_entry) {
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
							continue 2;
						}
					}
					break;
				case SmartEvent::GOSSIP_SELECT:
					if ($tc_menu_id = $smart_entry->event_param1) {
						try {
							$sun_menu_id = $this->CreateMenu($tc_menu_id);
						} catch (ImportException $e) {
							LogException($e, "Failed to create menu for entry {$original_entry} {$tc_entry} id {$sun_smart_entry->id}: {$e->getMessage()}");
							continue 2;
						}
						$sun_smart_entry->event_param1 = $sun_menu_id;
					}
					break;
				default:
					break;
			}
			
			//echo $smart_entry->event_type . PHP_EOL;
			$this->timelol("CreateSmartAI");
		
			switch($sun_smart_entry->action_type)
			{
				case SmartAction::TALK:
				case SmartAction::SIMPLE_TALK:
					assert($source_type == SmartSourceType::creature || $source_type == SmartSourceType::timedactionlist);
					
					$creature_id = $original_entry ? $original_entry : $tc_entry;
					$use_talk_target = $sun_smart_entry->action_type == SmartAction::TALK && $sun_smart_entry->action_param3;
                    // by default, this action will make it so the target is the one talking. if use_talk_target is set, we are the one talking and we don't need to worry about it. But if the target is another creature, we should try to import it.
					if (!$use_talk_target)
					{
						switch($sun_smart_entry->target_type)
						{
						case SmartTarget::SELF:
						case SmartTarget::CLOSEST_PLAYER:
						case SmartTarget::PLAYER_DISTANCE:
						case SmartTarget::PLAYER_RANGE:
							break; // myself or a player... nothing to look for.
						case SmartTarget::CLOSEST_ENEMY:
						case SmartTarget::CLOSEST_FRIENDLY:
						case SmartTarget::POSITION:
							//doesnt make any sense to use here... this is very probably a TC error
							LogWarning("Entry {$original_entry} {$tc_entry} id {$sun_smart_entry->id} uses target {$sun_smart_entry->target_type} without use_talk_target... Makes no sense.");
							break; //then import normally like in the use_talk_target case
						default:
							try {
								list($dummy, $creature_id) = $this->ImportSmartForTarget($sun_smart_entry, SmartSourceType::creature, $creature_id, $sun_smart_entry->patch_min);
							} catch (ImportException $e) {
								LogException($e, "Failed to import smart target type {$sun_smart_entry->target_type} for original entry {$original_entry} current entry {$tc_entry} id {$sun_smart_entry->id}: {$e->getMessage()}");
                                continue 3;
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
							//continue 2; // continue importing, even if it's not working at least we'll see the event and that some talk was intended there
						}
					}
					break;
				case SmartAction::CAST:
					$spell_id = $sun_smart_entry->action_param1;
					//check if spell exists for TBC
					if (!$this->sunStore->Exists("spell_template", $spell_id)) {
						$sun_smart_entry->patch_min = 5;
					}
					break;
				case SmartAction::CALL_TIMED_ACTIONLIST:
					try {
						$this->ImportSmartActionList($sun_smart_entry->action_param1, $original_type, $original_entry, $sun_smart_entry, $sun_smart_entry->patch_min);
					} catch (ImportException $e) {
						LogException($e, "Failed to import actionlist for entry {$original_entry} {$tc_entry} id {$sun_smart_entry->id}: {$e->getMessage()}");
						continue 2;
					}
					break;
				case SmartAction::CALL_RANDOM_TIMED_ACTIONLIST:			
					assert($source_type == SmartSourceType::creature || $source_type == SmartSourceType::timedactionlist); //we only handle creature action list atm
					$SMART_AI_MAX_ACTION_PARAM = 6;
					for($i = 1; $i <= $SMART_AI_MAX_ACTION_PARAM; $i++) {
						$fieldName = "action_param" . $i;
						if ($action_list = $sun_smart_entry->$fieldName) {
							try {
								$this->ImportSmartActionList($action_list , $original_type, $original_entry, $sun_smart_entry, $sun_smart_entry->patch_min);
							} catch (ImportException $e) {
								LogException($e, "Failed to import actionlist for entry {$original_entry} {$tc_entry} id {$sun_smart_entry->id}: {$e->getMessage()}");
								continue 3;
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
							$this->ImportSmartActionList($i, $original_type, $original_entry, $sun_smart_entry, $sun_smart_entry->patch_min);
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
						continue 2;
					}
					$sun_smart_entry->action_param1 = $sun_menu_id;
					break;
				case SmartAction::WP_START:
					$path_id = $sun_smart_entry->action_param2;
					try {
						$this->CreateSmartWaypoints($path_id);
					} catch (ImportException $e) {
						LogException($e, "Failed to create waypoints for entry {$original_entry} {$tc_entry} and id {$sun_smart_entry->id}: {$e->getMessage()}");
						continue 2;
					}
					break;
				case SmartAction::SUMMON_CREATURE:
					$summonID = $sun_smart_entry->action_param1;
					//echo "SmartAI {$tc_entry} ${source_type} does summon a creature {$summonID}" . PHP_EOL;
					try {
						$this->ImportReferencedCreatureSmart($summonID, $sun_smart_entry->patch_min, false);
					} catch (ImportException $e) {
						LogException($e, "Smart TC {$tc_entry} {$sun_smart_entry->id}: Failed to import smart for summoned creature: {$e->getMessage()}");
						continue 2;
					}
					break;
				case SmartAction::LOAD_EQUIPMENT:
				case SmartAction::SPAWN_SPAWNGROUP:
				case SmartAction::DESPAWN_SPAWNGROUP:
					throw new ImportException("NYI {$tc_entry} action {$sun_smart_entry->action_type}");
				case SmartAction::GAME_EVENT_STOP:
				case SmartAction::GAME_EVENT_START:
                    $event_id = $sun_smart_entry->action_param1;
					$sun_game_event = $this->sunStore->FindFirst("game_event", $sun_smart_entry->action_param1);
                    if ($sun_game_event === null)
                    {
                        LogError("Smart TC {$tc_entry} {$sun_smart_entry->id} has unknown event {$event_id}");
                        //continue 2;
                    }
                    break;
				case SmartAction::START_CLOSEST_WAYPOINT:
                    $wp_ids = array($sun_smart_entry->action_param1, $sun_smart_entry->action_param3, $sun_smart_entry->action_param4, $sun_smart_entry->action_param5, $sun_smart_entry->action_param6);
                    foreach($wp_ids as $wp_id) {
                        if ($wp_id != 0) {
                            try {
                                $this->CreateSmartWaypoints($wp_id);
                            } catch (ImportException $e) {
                                LogException($e, "Failed to create waypoints for entry {$original_entry} {$tc_entry} id {$sun_smart_entry->id}: {$e->getMessage()}");
                                continue;
                            }
                        }
                    }
                    break;
				case SmartAction::RESPAWN_BY_SPAWNID:
					$shouldKeep = false;
					$spawnType = MapSpawnType::from($sun_smart_entry->action_param1);
					$spawn_id = $sun_smart_entry->action_param2;
					try {
						if ($spawnType == MapSpawnType::creature)
							$this->CheckImportCreature($spawn_id);
						else if ($spawnType == MapSpawnType::gameobject)
							$this->CheckImportGameObject($spawn_id);
						else
						{
							LogError("Smart TC {$tc_entry} {$sun_smart_entry->id}: NYI spawnType {$spawnType}");
							continue 2;
						}
					} catch (ImportException $e) {
						LogException($e, "Smart TC {$tc_entry} {$sun_smart_entry->id}: Failed to import smart for summoned creature: {$e->getMessage()}");
						continue 2;
					}
					break;
				case SmartAction::SUMMON_CREATURE_GROUP:
					$this->ImportSummonGroup($original_entry ? $original_entry : $tc_entry, $sun_smart_entry->action_param1);
					break;
				case SmartAction::SET_INST_DATA:
				case SmartAction::SET_INST_DATA64:
					//ignore those
					continue 2;
				case SmartAction::SET_DATA:
					//special handling below
					break;
				default:
					break;
			}
			
			//echo "action type " . $sun_smart_entry->action_type . PHP_EOL;
			$this->timelol("CreateSmartAI");
			
			if (!IsActionIgnoreTarget($sun_smart_entry->action_type))
			{
				switch($sun_smart_entry->target_type)
				{
					case SmartTarget::CREATURE_GUID:
						//creature must exists in sun db
						$spawn_id = $sun_smart_entry->target_param1;
						try {
							$this->CheckImportCreature($spawn_id);
						} catch (ImportException $e) {
							LogWarning("Smart TC {$tc_entry} {$sun_smart_entry->id}: Failed to import smart for targeted creature spawn_id {$spawn_id}: {$e->getMessage()}");
							continue 2;
						}
						break;
					case SmartTarget::GAMEOBJECT_GUID:
						//gameobject must exists in sun db
						$spawn_id = $sun_smart_entry->target_param1;
						try {
							$this->CheckImportGameObject($spawn_id);
						} catch (ImportException $e) {
							LogWarning("Smart TC {$tc_entry} {$sun_smart_entry->id}: Failed to import smart for targeted gameobject spawn_id {$spawn_id}: {$e->getMessage()}");
							continue 2;
						}
					default:
						break;
				}
			}
			
			$this->timelol("CreateSmartAI", false, 4); // allow for gob loading
			 
			if ($sun_smart_entry->action_type == SmartAction::SET_DATA) {
				try {
					$this->ImportSmartForTarget($sun_smart_entry, $original_type, $original_entry, $sun_smart_entry->patch_min);  //will import creature/gob if missing
				} catch (ImportException $e) {
					LogException($e, "Smart TC {$tc_entry} {$sun_smart_entry->id}: Failed to import smart for targeted creature/gob spawn_id: {$e->getMessage()}");
					continue;
				}
			}
			
			$this->timelol("CreateSmartAI");
			
            $this->sunStore->Insert("smart_scripts", $sun_smart_entry, $tc_entry);
			BatchWrite($this->file, $this->conn, "smart_scripts", $sun_smart_entry);
			
			$this->timelol("CreateSmartAI");
		}
		
		FlushWrite($this->file, $this->conn);
		$this->CreateSmartConditions($tc_entry, $source_type);
		
		$this->timelol("CreateSmartAI");
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
		$results = &$this->sunStore->FindAll("smart_scripts", -$spawn_id);
		foreach($results as &$result) {
			if ($result->source_type != SmartSourceType::creature)
				continue;
			
			echo "WARNING: Deleting a creature (guid: {$spawn_id}) with a per guid SmartScripts ({$result->entryorguid}, {$result->id}). Smart scripts ref has been left as is." . PHP_EOL;
		}
		
		$results = &$this->sunStore->FindAllByField("smart_scripts", "target_param1", $spawn_id);
		foreach($results as &$result) {
			if ($result->target_type != SmartTarget::CREATURE_GUID)
				continue;
			
			echo "WARNING: Deleting creature (guid: {$spawn_id}) targeted by a smartscript ({$result->entryorguid}, {$result->id}). Smart scripts ref has been left as is." . PHP_EOL;
		}
		
        $results = &$this->sunStore->FindAll("creature_addon", $spawn_id);
		foreach($results as &$result) {
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
        
		$results = $this->sunStore->FindAll("creature_entry", $creature_id);
		foreach($results as &$result) {
			if (!in_array($result->spawnID, $not_in))
				$this->DeleteSunCreatureSpawn($result->spawnID);
		}
	}

	function DeleteSunGameObjectsInMap(int $map_id, array $not_in)
	{
		if (CheckAlreadyImported($map_id))
			return;
		
        $this->LoadTable("gameobject");
        
		$results = &$this->sunStore->FindAllByField("gameobject", "map", $map_id);
		foreach($results as &$result) {
			if (!in_array($result->spawnID, $not_in))
				$this->DeleteSunGameObjectSpawn($result->guid);
		}
	}
	
	function DeleteSunCreaturesInMap(int $map_id, array $not_in)
	{
		if (CheckAlreadyImported($map_id))
			return;
		
        $this->LoadTable("creature");
        
		$results = &$this->sunStore->FindAllByField("creature", "map", $map_id);
		foreach($results as &$result) {
			if (!in_array($result->spawnID, $not_in))
				$this->DeleteSunCreatureSpawn($result->spawnID);
		}
	}
	
	function SunHasSameWaypointsScripts(array &$tc_results, array $sun_results) : bool
	{
		// should use CheckIdentical instead? Need to test
		if (count($tc_results) != count($sun_results))
			return false;
		
		$sun_results = array_values($sun_results); //this is to reset array keys
		$i = 0;
		foreach($tc_results as &$tcResult) {
			if ($tcResult != $sun_results[$i++])
				return false;
		}
		return true;
	}
	
	function ImportWaypointScripts(int $action_id) : int
	{
		if (CheckAlreadyImported($action_id))
			return $action_id;
		
        $this->LoadTable("waypoint_scripts");
        $this->LoadTable("creature_equip_template");
        
		$results = &$this->tcStore->FindAll("waypoint_scripts", $action_id);
		if (empty($results))
			throw new ImportException("ERROR: Tried to import waypoint_scripts with id {$action_id} but no such entry exists");
		
		$sun_results = &$this->sunStore->FindAll("waypoint_scripts", $action_id);
		if ($this->SunHasSameWaypointsScripts($results, $sun_results)) {
			LogDebug("Waypoint scripts with id {$action_id} are already present and identical");
			return $action_id;
		}
		
		$sun_action_id = $action_id;
		if (count($sun_results) > 0) {
			//we have a path with this id, but no the same...
			$sun_action_id = max(array_keys($this->sunStore->waypoint_scripts)) + 1;
		}
		
		foreach($results as &$tc_waypoint_script) {
			$sun_waypoint_script = $tc_waypoint_script; //copy
			$sun_waypoint_script->id = $sun_action_id;
			unset($sun_waypoint_script->guid); //let db generate a new one here
			switch($tc_waypoint_script->command)
			{
				case 0: //SCRIPT_COMMAND_TALK:
					//we already get the same broadcast_text tables, so nothing to change here!
					break;
				case 31: //SCRIPT_COMMAND_EQUIP
                    $equipment_id = $tc_waypoint_script->datalong;
                    if (!$this->sunStore->Exists("creature_equip_template", $equipment_id))
                        LogError("Waypoint scripts action id {$action_id} has SCRIPT_COMMAND_EQUIP using unknown equipment {$equipment_id}");
                    break;
				case 35: //SCRIPT_COMMAND_MOVEMENT
					$movement_type = $tc_waypoint_script->datalong;
					if ($movement_type == 2) { // WAYPOINT_MOTION_TYPE
						$path_id = $tc_waypoint_script->dataint;
						throw new ImportException("NYI SCRIPT_COMMAND_MOVEMENT (35) with WAYPOINT_MOTION_TYPE");
					}
					break;
				default:
					break;
			}
				
            $this->sunStore->Insert("waypoint_scripts", $sun_waypoint_script, $action_id);
			BatchWrite($this->file, $this->conn, "waypoint_scripts", $sun_waypoint_script);
		}
		
		FlushWrite($this->file, $this->conn);
		
		return $sun_action_id;
	}

	//not sure this is working
	function SunHasSameWaypoints(array &$tc_results, array $sun_results) : bool
	{
		// should use CheckIdentical instead? Need to test
		if (count($tc_results) != count($sun_results))
			return false;
		
		$sun_results = array_values($sun_results); //this is to reset array keys
		$i = 0;
		foreach($tc_results as &$tcResult) {
			if ($tcResult != $sun_results[$i++])
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
				
		$this->sunStore->Remove("waypoint_info", $path_id);
		$this->sunStore->Remove("waypoint_data", $path_id, "id");
	}
	
	function ReplaceWaypoints(int $spawn_id, bool $update_position = true)
	{
        $this->LoadTable("creature");
        $this->LoadTable("creature_addon");
        
        $tc_creature_addon = $this->tcStore->FindFirst("creature_addon", $spawn_id);
		if ($tc_creature_addon === null) {
			LogError("Trying to replace waypoints for creature {$spawn_id}, but creature has no creature_addon on trinity");
			return;
		}
		if ($tc_creature_addon->path_id == 0) {
			LogError("Trying to replace waypoints for creature {$spawn_id}, but creature has no path_id on trinity");
			return;
		}
		
        $sun_creature_addon = $this->sunStore->FindFirst("creature_addon", $spawn_id);
		if ($sun_creature_addon !== null && $sun_creature_addon->path_id) 
		{
			$usedTimes = $this->GetTimesUsedWaypoints($sun_creature_addon->path_id);
			if ($usedTimes == 1) {
				fwrite($this->file, "UPDATE creature_addon SET path_id = NULL WHERE spawnID = {$spawn_id};" . PHP_EOL);
				$this->DeleteWaypoints($sun_creature_addon->path_id);
			} else if ($usedTimes > 1)
				fwrite($file, "-- Not deleting waypoint path {$sun_creature_addon->path_id} because it's still used by another creature" . PHP_EOL);
		}
		
		//will often be equal to tc path id, unless it's not free
		$sun_path_id = $this->ImportWaypoints($spawn_id, $tc_creature_addon->path_id);
		if ($sun_path_id === 0)
        {
			LogError("Trying to replace waypoints for creature {$spawn_id}, but creature has wrong path on trinity");
            return;
        }

        $sun_creature_addon = $this->sunStore->FindFirst("creature_addon", $spawn_id);
		if ($sun_creature_addon !== null) {
			$sun_creature_addon->path_id = $sun_path_id;
			fwrite($this->file, "UPDATE creature_addon SET path_id = {$sun_path_id} WHERE spawnID = {$spawn_id};" . PHP_EOL);
		} else {
			$sun_creature_addon = new stdClass;
			$sun_creature_addon->spawnID = $spawn_id;
			$sun_creature_addon->path_id = $sun_path_id; 
            $this->sunStore->Insert("creature_addon", $sun_creature_addon, $spawn_id);
			fwrite($this->file, WriteObject($this->conn, "creature_addon", $sun_creature_addon));
		}
		
		if ($update_position)
		{
			$tc_creature = $this->tcStore->FindFirst("creature", $spawn_id);
			foreach($this->sunStore->creature as &$creature) 
			{
				if (  $creature->position_x != $tc_creature->position_x
				||    $creature->position_y != $tc_creature->position_y
				||    $creature->position_z != $tc_creature->position_z)
				{
					$creature->position_x  = $tc_creature->position_x;
					$creature->position_y  = $tc_creature->position_y;
					$creature->position_z  = $tc_creature->position_z;
					$creature->orientation = $tc_creature->orientation;
					fwrite($this->file, "UPDATE creature SET position_x = {$tc_creature->position_x}, position_y = {$tc_creature->position_y}, position_z = {$tc_creature->position_z}, orientation = {$tc_creature->orientation} WHERE spawnID = {$spawn_id};" . PHP_EOL);
				}
			}
		}
	}
	
	// returns new path_id
	function ImportWaypoints(int $spawn_id, int $tc_path_id, bool $include_movement_type_update = true) : int
	{
        $this->LoadTable("creature");
        $this->LoadTable("waypoint_data");
        $this->LoadTable("waypoint_info");
        
		$results = &$this->tcStore->FindAll("waypoint_data", $tc_path_id);
		if (empty($results))
		{
			LogError("Tried to import waypoint_data with path_id {$tc_path_id} but no such path exists. This is likely a TC db error.");
            return 0;
			//throw new ImportException($msg);
		}
		
		if (CheckAlreadyImported($tc_path_id)) {
			LogDebug("Path {$tc_path_id} is already imported");
			return $tc_path_id;
		}
		
		$sun_results = &$this->sunStore->FindAll("waypoint_data", $tc_path_id);
		if ($this->SunHasSameWaypoints($results, $sun_results)) {
			LogDebug("Path {$tc_path_id} is already present and the same");
			return $tc_path_id;
		}
		
		$sun_path_id = $tc_path_id;
		if (count($sun_results) > 0) {
			//we have a path with this id, but no the same...
			$sun_path_id = max(array_keys($this->sunStore->waypoint_data)) + 1;
		}
		
		$waypoint_info = new stdClass;
		$waypoint_info->id = $sun_path_id;

		$this->sunStore->Insert("waypoint_info", $waypoint_info, $sun_path_id);
		fwrite($this->file, WriteObject($this->conn, "waypoint_info", $waypoint_info));
		
		foreach($results as &$tc_waypoint) {
			$sun_waypoint = $tc_waypoint; //copy
			$sun_waypoint->id = $sun_path_id;
			if ($tc_action = $tc_waypoint->action)
				$sun_waypoint->action = $this->ImportWaypointScripts($tc_action);
			else
				$sun_waypoint->action = 'NULL';
			
            $this->sunStore->Insert("waypoint_data", $sun_waypoint, $sun_path_id);
			BatchWrite($this->file, $this->conn, "waypoint_data", $sun_waypoint);
		}
		FlushWrite($this->file, $this->conn);
		
		if ($include_movement_type_update && $spawn_id != 0) {
			//&FindAll would work here?
			foreach($this->sunStore->creature as &$creatures) 
				foreach($creatures as &$creature) 
					if ($creature->spawnID == $spawn_id)
						$creature->MovementType = 2;

			fwrite($this->file, "UPDATE creature SET MovementType = 2 WHERE spawnID = {$spawn_id};" . PHP_EOL);
		}
		
		return $sun_path_id;
	}

	function ImportSpawnGroup(int $spawn_id, bool $creature) //else gameobject
	{
		if (CheckAlreadyImported($spawn_id + $creature << 31))
			return;

        $this->LoadTable("spawn_group");
        
		$results = &$this->tcStore->FindAll("spawn_group", $spawn_id);
		foreach($results as &$result) {
			$spawn_type = MapSpawnType::from($result->spawnType);
			if ($creature) {
				if ($spawn_type != MapSpawnType::creature)
					continue;
			} else {
				if ($spawn_type != MapSpawnType::gameobject)
					continue;
			}
				
			$groupId = $result->groupId;
			$sun_spawn_group = $result; //copy
			$sun_spawn_group->groupId = $groupId;
			 
			$this->sunStore->RemoveOnCondition("spawn_group", $spawn_id, "spawnType", $result->spawnType);
			fwrite($this->file, "DELETE FROM spawn_group WHERE spawnId = {$spawn_id} AND spawnType = {$result->spawnType};" . PHP_EOL);

            $this->sunStore->Insert("spawn_group", $sun_spawn_group, $spawn_id);
			fwrite($this->file, WriteObject($this->conn, "spawn_group", $sun_spawn_group));
		}
	}

	private $delayed_formations_imports = "";
	
	function ImportFormation(int $guid)
	{
        $this->LoadTable("creature_formations");
        
		$tc_formation = $this->tcStore->FindFirst("creature_formations", $guid);
		if ($tc_formation === null)
			return;
		
		$leaderGUID = $tc_formation->leaderGUID;
		if (!$this->tcStore->Exists("creature_formations", $leaderGUID)) {
			LogError("Leader of formation {$leaderGUID} not found in tc for guid {$guid}");
			return;
		}
		
		$sun_formation = $this->sunStore->FindFirst("creature_formations", $guid);
		if ($sun_formation !== null) {
			if ($sun_formation != $tc_formation) {
				//a formation already exists for this creature but is not the same...
				fwrite($this->file, "DELETE FROM creature_formations WHERE memberGUID = {$guid};" . PHP_EOL);
				$this->sunStore->Remove("creature_formations", $guid);
			} else {
				LogDebug("Formation for creature {$guid} already in db");
				return;
			}
		}
		
		if (CheckAlreadyImported($leaderGUID))
			return;
		
		$results = &$this->tcStore->FindAllByField("creature_formations", "leaderGUID", $leaderGUID);
		foreach($results as &$tc_formation) {
			if ($this->sunStore->Exists("creature", $tc_formation->memberGUID) === null) {
				//we don't have that leader yet
				LogDebug("Trying to import formation for creature {$tc_formation->memberGUID}, but the creature isn't in our db yet. Importing it now");
				$this->ImportTCCreature($tc_formation->memberGUID);
				//this call won't import the formation because of the CheckAlreadyImported before
			}
			
			$sun_formation = new stdClass; 
			$sun_formation->leaderGUID = $leaderGUID;
			$sun_formation->memberGUID = $tc_formation->memberGUID;
			$sun_formation->dist = $tc_formation->dist;
			$sun_formation->groupAI = 515; // = FULL_SUPPORT = fixed value because we don't use the same AI system than TC
			$sun_formation->angle = $tc_formation->angle;
			
            $this->sunStore->Insert("creature_formations", $sun_formation, $sun_formation->memberGUID);
			
			if ($sun_formation->leaderGUID == $sun_formation->memberGUID)
				$sun_formation->leaderGUID = "NULL"; //special on SUN as well (only do that for the WriteObject, not for the store)
			
			$this->delayed_formations_imports .= "DELETE FROM creature_formations WHERE memberGUID = {$sun_formation->memberGUID};" . PHP_EOL;
			$this->delayed_formations_imports .= WriteObject($this->conn, "creature_formations", $sun_formation);
		}
	}

	function ImportPool(int $guid, bool $creature) //else gameobject
	{
        $this->LoadTable("pool_members");
        $this->LoadTable("pool_template");
        
		$tc_pool_entry = null;
		//$sun_results = FindAll($this->sunStore->creature_template, "entry", $creature_id);
        $tc_results = &$this->tcStore->FindAll("pool_members", $guid);
        $tc_pool_member = FindFirst($tc_results, "type", $creature ? 0 : 1);
        if ($tc_pool_member === null)
            return;
        
        $tc_pool_entry = $tc_pool_member->poolSpawnId;

		$tc_pool_template = &$this->tcStore->FindFirst("pool_template", $tc_pool_entry);
		if ($tc_pool_template === null) {
			echo "ERROR: TC has " . $creature ? "creature" : "gob" . " {$guid} which part of pool {$pool_entry}, but this pool does not exists" . PHP_EOL;
			return;
		}
		
		// Handle pool template
		$sun_pool_template = &$this->sunStore->FindFirst("pool_template", $tc_pool_entry);
		if ($sun_pool_template !== null) { 
			if ($sun_pool_template->description != $tc_pool_template->description)
			{ //we have that pool id but not the same pool
				LogError("WARNING: Imported " . $creature ? "creature" : "gob" . " {$guid} is part of pool {$tc_pool_entry} but a pool with this entry (but different description already exists). Need manual fix.");
				return;
			}
			else { //we have that pool id and same pool, no need to import template
				//LogDebug("Imported " . $creature ? "creature" : "gob" . " {$guid} is part of pool {$tc_pool_entry} which already exists");
			}
		}
		else { 
			//if sun doesn't have that pool
			$sun_pool_template = $tc_pool_template; // copy
            $sun_pool_template->max_limit_percent = 0;
            
			$this->sunStore->Insert("pool_template", $sun_pool_template, $tc_pool_entry);
			fwrite($this->file, WriteObject($this->conn, "pool_template", $sun_pool_template));
		}
		
		//and finally add to pool
        $sun_pool_member = $tc_pool_member;
		$this->sunStore->Insert("pool_members", $sun_pool_member, $guid);
        fwrite($this->file, WriteObject($this->conn, "pool_members", $sun_pool_member));
	}

	//don't forget to call HandleFormations after this
	function ImportTCCreature(int $spawn_id, int $patch_min = 0, int $patch_max = 10)
	{
		if (CheckAlreadyImported($spawn_id))
			return;
		
        $this->LoadTable("creature");
        $this->LoadTable("creature_addon");
        $this->LoadTable("creature_entry");
        $this->LoadTable("game_event_creature");
        
		if ($this->sunStore->Exists("creature", $spawn_id))
			return;
		
		$tc_creature = $this->tcStore->FindFirst("creature", $spawn_id);

		if (IsTLKCreature($tc_creature->id))
			if ($patch_min < 5)
				$patch_min = 5;
		
		//create creature
		$sun_creature = new stdClass;
		$sun_creature->spawnID = $spawn_id;
		$sun_creature->map = $tc_creature->map;
		$sun_creature->spawnMask = $tc_creature->spawnMask;
		$sun_creature->phaseMask = $tc_creature->phaseMask;
        // for now just let the core filter out the bad models. creature_model_info needs to be filled with all the TC values already
		$sun_creature->modelid = $tc_creature->modelid ? $tc_creature->modelid : null;
		$sun_creature->position_x = $tc_creature->position_x;
		$sun_creature->position_y = $tc_creature->position_y;
		$sun_creature->position_z = $tc_creature->position_z;
		$sun_creature->orientation = $tc_creature->orientation;
		$sun_creature->spawntimesecsmin = $tc_creature->spawntimesecs;
		$sun_creature->spawntimesecsmax = $tc_creature->spawntimesecs;
		$sun_creature->wander_distance = $tc_creature->wander_distance;
		$sun_creature->currentwaypoint = $tc_creature->currentwaypoint;
		$sun_creature->curhealth = $tc_creature->curhealth;
		$sun_creature->curmana = $tc_creature->curmana;
		$sun_creature->MovementType = $tc_creature->MovementType;
		$sun_creature->unit_flags = $tc_creature->unit_flags;

		if (IsTLKMap($sun_creature->map))
			$patch_min = 5;
        
		$sun_creature->patch_min = $patch_min;
		$sun_creature->patch_max = $patch_max;
		
        $this->sunStore->Insert("creature", $sun_creature, $spawn_id);
		fwrite($this->file, WriteObject($this->conn, "creature", $sun_creature));

		//create creature_entry
		$sun_creature_entry = new stdClass; //anonymous object
		$sun_creature_entry->spawnID = $spawn_id;
		$sun_creature_entry->entry = $tc_creature->id;
		
        $this->sunStore->Insert("creature_entry", $sun_creature_entry, $spawn_id);
		fwrite($this->file, WriteObject($this->conn, "creature_entry", $sun_creature_entry));
		
		//create creature_addon
        $tc_creature_addon = $this->tcStore->FindFirst("creature_addon", $spawn_id);
		if ($tc_creature_addon) {
			$path_id = $tc_creature_addon->path_id;
			if ($path_id) 
				$path_id = $this->ImportWaypoints($spawn_id, $path_id, false); 

            if (!$path_id)
				$path_id = null;
                
			$sun_creature_addon = new stdClass;
			$sun_creature_addon->spawnID = $spawn_id;
			$sun_creature_addon->path_id = $path_id;
			$sun_creature_addon->mount = $tc_creature_addon->mount;
            $sun_creature_addon->standState = $tc_creature_addon->bytes1 & 0xFF; // first 8 bytes of bytes 1 are stand state
            // other bytes fields are not handled in this table for sunstrider
			$sun_creature_addon->emote = $tc_creature_addon->emote;
			$sun_creature_addon->auras = $tc_creature_addon->auras ? $tc_creature_addon->auras : 'NULL';
			//could be improved here: check auras with spell_template, some are TLK only
			
            $this->sunStore->Insert("creature_addon", $sun_creature_addon, $spawn_id);
			fwrite($this->file, WriteObject($this->conn, "creature_addon", $sun_creature_addon));
		}
		
		// game event creature
		$tc_gec = $this->tcStore->FindFirst("game_event_creature", $spawn_id);
		if ($tc_gec !== null) {
			$sun_gec = new stdClass;
            //TODO: tc event might not exists... but if it exists we assume it's the right one, we made some id sync for that before
			$sun_gec->eventEntry = $tc_gec->eventEntry;
			$sun_gec->guid = $spawn_id;
            $this->sunStore->Insert("game_event_creature", $sun_gec, $spawn_id);
			fwrite($this->file, WriteObject($this->conn, "game_event_creature", $sun_gec));
		}

		$this->ImportSpawnGroup($spawn_id, true);
		$this->ImportFormation($spawn_id);
		$this->ImportPool($spawn_id, true);
	}

	function CreateReplaceAllCreature(int $creature_id, int $patch_min = 0, int $patch_max = 10)
	{
		if (CheckAlreadyImported($creature_id))
			return;

        $this->LoadTable("creature");
        $this->LoadTable("creature_entry");

		$results = &$this->tcStore->FindAllByField("creature", "id", $creature_id);
		if (empty($results))
			throw new ImportException("Failed to find any TC creature with id {$creature_id}");
						
		$tc_guids = [];
		foreach($results as &$tc_creature) {
			array_push($tc_guids, $tc_creature->guid);
			if (!$this->sunStore->Exists("creature", $tc_creature->guid)) {
				$sun_creature_entries = $this->sunStore->FindFirst("creature_entry", $tc_creature->guid);
				if (!empty($sun_creature_entries))
					throw new ImportException("Error in sun DB... there is a creature_entry without matching creature for spawn_id {$tc_creature->guid}");

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
		$results = &$this->tcStore->FindAllByField("creature", "map", $map_id);
		$tc_guids = [];
		foreach($results as &$tc_creature) {
			array_push($tc_guids, $tc_creature->guid);
			if (!$this->sunStore->Exists("creature", $tc_creature->guid))
				$this->ImportTCCreature($tc_creature->guid, $patch_min, $patch_max);
			else
				LogDebug("Sun db already has creature {$tc_creature->guid}");
		}
		$this->DeleteSunCreaturesInMap($map_id, $tc_guids);
		$this->HandleFormations();

		//handle gameobjects
		$results = &$this->tcStore->FindAllByField("gameobject", "map", $map_id);
		$tc_guids = [];
		foreach($results as &$tc_gob) {
			array_push($tc_guids, $tc_gob->guid);
			if (!$this->sunStore->FindFirst("gameobject", $tc_gob->guid))
				$this->ImportTCGameObject($tc_gob->guid, $patch_min, $patch_max);
			else
				LogDebug("Sun db already has gameobject {$tc_gob->guid}");
		}
		$this->DeleteSunGameObjectsInMap($map_id, $tc_guids);
	}

	//Write formations stored in $this->delayed_formations_imports
	function HandleFormations()
	{
		if (!$this->delayed_formations_imports)
			return;
		
		LogDebug("Formations");
		fwrite($this->file, $this->delayed_formations_imports);
		$this->delayed_formations_imports = "";
	}
	
	function ImportGameObjectTemplateAddon($gob_id)
	{
		if (CheckAlreadyImported($gob_id))
			return;
		
		$this->LoadTable("gameobject_template_addon");
		
		$tc_gameobject_template_addon = &$this->tcStore->FindFirst("gameobject_template_addon", $gob_id);
		if ($tc_gameobject_template_addon === null)
			return;
		
		$sun_result = $this->sunStore->FindFirst("gameobject_template_addon", $gob_id); // ignore patch here, most of the time we just want to use the same data for both TBC and TLK
		if (!empty($sun_result)) {
			if (CheckIdenticalObject($sun_result, $tc_gameobject_template_addon))
			{
				LogDebug("Found identical gameobject_template_addon for {$gob_id}.");
				return; // already identical
			}
			throw new ImportException("Trying to import already existing gameobject template addon {$gob_id}");
		}

		// just copy one and make some arrangements
		$sun_gameobject_template_addon = $tc_gameobject_template_addon;

		$this->sunStore->Remove("gameobject_template_addon", $gob_id);
		$sql = "DELETE FROM gameobject_template_addon WHERE entry = {$gob_id};" . PHP_EOL;
		fwrite($this->file, $sql); 

		$this->sunStore->Insert("gameobject_template_addon", $sun_gameobject_template_addon, $gob_id);
		fwrite($this->file, WriteObject($this->conn, "gameobject_template_addon", $sun_gameobject_template_addon)); 
	}
	
	function ImportGameObjectTemplate($gob_id)
	{
        $this->LoadTable("gameobject_template");
        
		$tc_gameobject_template = &$this->tcStore->FindFirst("gameobject_template", $gob_id);
		assert($tc_gameobject_template !== null);
		
		$sun_gameobject_template = new stdClass;
		$sun_gameobject_template->entry = $gob_id;
		$sun_gameobject_template->patch = 5;
		$sun_gameobject_template->type = $tc_gameobject_template->type;
		$sun_gameobject_template->displayId = $tc_gameobject_template->displayId;
		$sun_gameobject_template->name = $tc_gameobject_template->name;
		$sun_gameobject_template->castBarCaption = $tc_gameobject_template->castBarCaption;
		$sun_gameobject_template->size = $tc_gameobject_template->size;
		for($i = 0; $i < 24; $i++) {
			$sun_field_name = 'data' . $i;
			$tc_field_name = 'Data' . $i;
			$sun_gameobject_template->$sun_field_name = $tc_gameobject_template->$tc_field_name;
		}
		
		// Import more things depending on data
		switch(GameobjectTypes::from($tc_gameobject_template->type)) {
			case GameobjectTypes::DOOR:
			case GameobjectTypes::BUTTON:
				break;
			case GameobjectTypes::QUESTGIVER:
				$menu_id = &$sun_gameobject_template->data3;
				if ($menu_id)
					$menu_id = $this->CreateMenu($menu_id);
				break;
			case GameobjectTypes::CHEST:
				$loot_id = &$sun_gameobject_template->data1;
				if ($loot_id)
					$loot_id = $this->ImportLootTemplate("gameobject_loot_template", $loot_id);
				break;
			case GameobjectTypes::BINDER:
			case GameobjectTypes::GENERIC:
			case GameobjectTypes::TRAP:
			case GameobjectTypes::CHAIR:
			case GameobjectTypes::SPELL_FOCUS:
				break;
			case GameobjectTypes::TEXT:
				$page_id = &$sun_gameobject_template->data0;
				if ($page_id)
					$page_id = $this->ImportPageText($page_id);
				break;
			case GameobjectTypes::GOOBER:
				$page_id = &$sun_gameobject_template->data7;
				if ($page_id)
					$page_id = $this->ImportPageText($page_id);
				$menu_id = &$sun_gameobject_template->data19;
				if ($menu_id)
					$menu_id = $this->CreateMenu($menu_id);
				break;
			case GameobjectTypes::TRANSPORT:
			case GameobjectTypes::AREADAMAGE:
			case GameobjectTypes::CAMERA:
			case GameobjectTypes::MAP_OBJECT:
			case GameobjectTypes::MO_TRANSPORT:
			case GameobjectTypes::DUEL_ARBITER:
				break;
			case GameobjectTypes::FISHINGNODE: // has loot id but nothing new to import for TLK
			case GameobjectTypes::SUMMONING_RITUAL:
			case GameobjectTypes::MAILBOX:
			case GameobjectTypes::AUCTIONHOUSE:
			case GameobjectTypes::GUARDPOST:
			case GameobjectTypes::SPELLCASTER:
			case GameobjectTypes::MEETINGSTONE:
			case GameobjectTypes::FLAGSTAND:
			case GameobjectTypes::FISHINGHOLE:
			case GameobjectTypes::FLAGDROP:
			case GameobjectTypes::MINI_GAME:
			case GameobjectTypes::LOTTERY_KIOSK:
			case GameobjectTypes::CAPTURE_POINT:
			case GameobjectTypes::AURA_GENERATOR:
			case GameobjectTypes::DUNGEON_DIFFICULTY:
			case GameobjectTypes::BARBER_CHAIR:
			case GameobjectTypes::DESTRUCTIBLE_BUILDING:
			case GameobjectTypes::GUILD_BANK:
			case GameobjectTypes::TRAPDOOR:
				break;
		}

		$sun_gameobject_template->AIName = $tc_gameobject_template->AIName;
		$sun_gameobject_template->ScriptName = $tc_gameobject_template->ScriptName; // if there is anything there it will be an error at loading later when starting core, but that's good.
		
		$isTLKGameObject = FindFirst($this->sunStore->FindAll("gameobject_template", $tc_gameobject_template->entry), "patch", 0) !== null;
		if ($isTLKGameObject)
			$sun_gameobject_template->patch = 5;
		
		if ($tc_gameobject_template->ScriptName != "")
			echo "WARNING: Importing gameobject template {$gob_id} which has ScriptName {$tc_gameobject_template->ScriptName}" . PHP_EOL;
		
		$this->sunStore->Remove("gameobject_template", $sun_gameobject_template->entry);
		$sql = "DELETE FROM gameobject_template WHERE entry = {$sun_gameobject_template->entry} AND patch = 5;" . PHP_EOL;
		fwrite($this->file, $sql);

		$this->sunStore->Insert("gameobject_template", $sun_gameobject_template, $sun_gameobject_template->entry);
		fwrite($this->file, WriteObject($this->conn, "gameobject_template", $sun_gameobject_template));

        $this->ImportGameObjectTemplateAddon($gob_id);

        $this->ImportGameObjectSmartAI($gob_id);
	}
	
	function ImportTCGameObject(int $spawn_id, int $patch_min = 0, int $patch_max = 10)
	{
		if (CheckAlreadyImported($spawn_id))
			return;
		
        $this->LoadTable("gameobject");
        $this->LoadTable("gameobject_addon");
        $this->LoadTable("gameobject_entry");
        $this->LoadTable("gameobject_template");
        $this->LoadTable("game_event_gameobject");
        
		if ($this->sunStore->Exists("gameobject", $spawn_id))
			return;
		
		$tc_gameobject = &$this->tcStore->FindFirst("gameobject", $spawn_id);
		if ($tc_gameobject === null)
			throw new ImportException("Failed to find any TC gameobject with spawnID {$spawn_id}");
		
		// import template if missing
		$sun_templates = &$this->sunStore->FindAll("gameobject_template", $tc_gameobject->id);
		if (empty($sun_templates))
			$this->ImportGameObjectTemplate($tc_gameobject->id);

		// if we can't find a template with patch 0, this is a tlk object
		if (FindFirst($sun_templates, "patch", 0) === null)
			$patch_min = 5; // assume it's a TLK object then

		//create gameobject
		$sun_gameobject = new stdClass;
		$sun_gameobject->spawnID          = $spawn_id;
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
		$sun_gameobject->ScriptName       = $tc_gameobject->ScriptName; // always empty currently
		if (IsTLKMap($sun_gameobject->map))
			$patch_min = 5;

		$sun_gameobject->patch_min     = $patch_min;
		$sun_gameobject->patch_max     = $patch_max;
		
        $this->sunStore->Insert("gameobject", $sun_gameobject, $spawn_id);
		fwrite($this->file, WriteObject($this->conn, "gameobject", $sun_gameobject));
		
		// create gameobject_entry
		$sun_gameobject_entry = new stdClass;
		$sun_gameobject_entry->spawnID = $spawn_id;
		$sun_gameobject_entry->entry   = $tc_gameobject->id;

        $this->sunStore->Insert("gameobject_entry", $sun_gameobject_entry, $spawn_id);
		fwrite($this->file, WriteObject($this->conn, "gameobject_entry", $sun_gameobject_entry));

		// game event gameobject
		$tc_geg = $this->tcStore->FindFirst("game_event_gameobject", $spawn_id);
		if ($tc_geg !== null) {
			$sun_geg = new stdClass;
			$sun_geg->eventEntry = $tc_geg->eventEntry;
			$sun_geg->guid = $spawn_id;
            $this->sunStore->Insert("game_event_gameobject", $sun_geg, $spawn_id);
			fwrite($this->file, WriteObject($this->conn, "game_event_gameobject", $sun_geg));
		}
		
		$this->ImportSpawnGroup($spawn_id, false);
		$this->ImportPool($spawn_id, false);
	}
	
	function DeleteSunGameObjectSpawn(int $spawn_id)
	{
		if (CheckAlreadyImported($spawn_id))
			return;
		
        $this->LoadTable("smart_scripts");
        
		$sql = "CALL DeleteGameObject({$spawn_id});" . PHP_EOL;
		fwrite($this->file, $sql);
				
		// warn smart scripts references removal
		$sun_results = &$this->sunStore->FindAll("smart_scripts", -$spawn_id);
		foreach($sun_results as &$result) {
			if ($result->source_type != SmartSourceType::gameobject)
				continue;
			
			echo "WARNING: Deleting a gameobject (guid: {$spawn_id}) with a per guid SmartScripts ({$result->entryorguid}, {$result->id}). Smart scripts ref has been left as is." . PHP_EOL;
		}
		
		$results = &$this->sunStore->FindAllByField("smart_scripts", "target_param1", $spawn_id);
		foreach($results as &$result) {
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
        
		$results = &$this->sunStore->FindAllByField("gameobject", "id", $gob_id);
		foreach($results as &$result) {
			if (!in_array($result->guid, $not_in))
				$this->DeleteSunGameObjectSpawn($result->guid);
		}
	}

	function CreateReplaceAllGameObject(int $gob_id, int $patch_min = 0, int $patch_max = 10)
	{
		if (CheckAlreadyImported($gob_id))
			return;
			
        $this->LoadTable("gameobject");
        
		$results = &$this->tcStore->FindAllByField("gameobject", "id", $gob_id);
		if (empty($results)) 
			throw new ImportException("Failed to find any TC gameobject with id {$gob_id}");
						
		$tc_guids = [];
		foreach($results as &$tc_gob) {
			array_push($tc_guids, $tc_gob->guid);
			if (!$this->sunStore->Exists("gameobject", $tc_gob->guid))
				$this->ImportTCGameObject($tc_gob->guid, $patch_min, $patch_max);
		}
		$this->DeleteSunGameObjects($gob_id, $tc_guids);
	}
};
