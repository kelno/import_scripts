<?php

include_once(__DIR__ . '/lib/common.php');
include_once(__DIR__ . '/lib/smartai.php');

$conn = new PDO("mysql:host=localhost", $login, $password);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$file = null;
$converter = new DBConverter($file, false);

function PrintOptions($menu_id, $sun)
{
	global $converter;
	
    $converter->LoadTable("gossip_menu_option");
    
	$subMenusIds = [];
	$results = [];
	if($sun)
		$results = FindAll($converter->sunStore->gossip_menu_option, "MenuID", $menu_id);
	else
		$results = FindAll($converter->tcStore->gossip_menu_option, "MenuID", $menu_id);
	
	if(empty($results))
		return [];
	
	echo "Options:" . PHP_EOL;
	foreach($results as $option) {
		$optionID = $option->OptionID;
		$optionText = $option->OptionText;
		$actionMenuID = $option->ActionMenuID;
		echo "ID {$optionID} - '{$optionText}' - Menu {$actionMenuID} " . PHP_EOL;
			
		if($actionMenuID)
			array_push($subMenusIds, $option->ActionMenuID);
		
		PrintOptionConditions($menu_id, $optionID, $sun);
	}
	return $subMenusIds;
}

function PrintText($text_id, $sun)
{
	global $converter;
	
    $converter->LoadTable("gossip_text");
    $converter->LoadTable("npc_text");
    
	if($sun)
		$text = $converter->sunStore->gossip_text[$text_id];
	else
		$text = $converter->tcStore->npc_text[$text_id];
		
	echo "text0_0: {$text->text0_0}" . PHP_EOL;
	if($text->text0_1 != "" && $text->text0_0 != $text->text0_1)
		echo "text0_1: {$text->text0_1}" . PHP_EOL;
}

function PrintCondition(&$condition)
{
	$conditionName = GetConditionName($condition->ConditionTypeOrReference);
	echo "{$conditionName} - Value1 {$condition->ConditionValue1} - Value2 {$condition->ConditionValue2}" . PHP_EOL;
}

function PrintMenuConditions($menu_id, $text_id, $sun)
{
	global $converter;
	
    $converter->LoadTable("conditions");
    
	static $CONDITION_SOURCE_TYPE_GOSSIP_MENU = 14;
	
	foreach(($sun ? $converter->sunStore->conditions : $converter->tcStore->conditions) as $condition) {
		if($condition->SourceTypeOrReferenceId != $CONDITION_SOURCE_TYPE_GOSSIP_MENU)
		   continue;
		   
		if($condition->SourceGroup != $menu_id || $condition->SourceEntry != $text_id)
		   continue;
	   
	    PrintCondition($condition);
	}
}

function PrintOptionConditions($menu_id, $option_id, $sun)
{
	global $converter;
	
    $converter->LoadTable("conditions");
    
	static $CONDITION_SOURCE_TYPE_GOSSIP_MENU_OPTION = 15;
	
	foreach(($sun ? $converter->sunStore->conditions : $converter->tcStore->conditions) as $condition) {
		if($condition->SourceTypeOrReferenceId != $CONDITION_SOURCE_TYPE_GOSSIP_MENU_OPTION)
		   continue;
		   
		if($condition->SourceGroup != $menu_id || $condition->SourceEntry != $option_id)
		   continue;
	   
	    PrintCondition($condition);
	}
}

$alreadyPrinted = [ ];
function PrintMenu($menu_id, bool $sun, bool $callFromMain = false)
{
	global $converter, $alreadyPrinted;
	
    $converter->LoadTable("gossip_menu");
        
	if($callFromMain)
		$alreadyPrinted = [ ]; //reset
	
	if(in_array($menu_id, $alreadyPrinted))  //avoid infinite loops
		return;
		
	array_push($alreadyPrinted, $menu_id);
	
	$results = [];
	if($sun)
		$results = FindAll($converter->sunStore->gossip_menu, "MenuID", $menu_id);
	else
		$results = FindAll($converter->tcStore->gossip_menu,  "MenuID", $menu_id);
	
	if(empty($results))
	{
		echo "ERROR: No menu found for id {$menu_id}" . PHP_EOL;
		exit(1);
	}
	
	foreach($results as $menu) {
		$text_id = $menu->TextID;
		echo "Menu {$menu_id} - TextID: {$text_id}" . PHP_EOL;
		PrintText($text_id, $sun);
		PrintMenuConditions($menu_id, $text_id, $sun);
	}
	
	$subMenusIds = PrintOptions($menu_id, $sun);
		
	foreach($subMenusIds as $subMenuId) {
		echo PHP_EOL;
		PrintMenu($subMenuId, $sun);
	}
	echo PHP_EOL;
}

function SetImport($creatureId, $import)
{
	global $conn, $tcWorld;
	
	$query = "UPDATE {$tcWorld}.creature_template SET import = '{$import}' WHERE entry = {$creatureId};";
	$stmt = $conn->query($query);
	return;
}

// MAIN
$query = "SELECT tc.entry, tc.gossip_menu_id, tc.name, tc.npcflag, sun.gossip_menu_id as sunmenuid, sun.ScriptName as sunScriptName, sun.AIName as sunAIName, tc.ScriptName as tcScriptName, tc.AIName as tcAIName
FROM {$tcWorld}.creature_template tc 
JOIN {$sunWorld}.creature_template sun ON sun.entry = tc.entry
WHERE tc.gossip_menu_id != 0 AND tc.import IS NULL AND sun.gossip_menu_id IS NULL
ORDER BY tc.gossip_menu_id ";
//
//echo $query . PHP_EOL;
$stmt = $conn->query($query);
$stmt->setFetchMode(PDO::FETCH_ASSOC);

$separator = "---------------------------------------" .PHP_EOL;

echo "READY TO WORK" . PHP_EOL . PHP_EOL;
$auto_import = false;

foreach($stmt->fetchAll() as $v) {
	echo "Creature {$v['entry']} - {$v['name']}:" . PHP_EOL . PHP_EOL;
	
	if($auto_import) {
		SetImport($v['entry'], "GOSSIP"); 
		continue;
	}
	PrintMenu($v['gossip_menu_id'], false, true);
	if($v['tcScriptName'] != "")
		echo "WARNING: TC creature has a ScriptName: {$v['tcScriptName']}" . PHP_EOL;
	if($v['tcAIName'] != "")
	{
		echo "WARNING: TC creature has an AIName: {$v['tcAIName']}" . PHP_EOL;
		if($v['tcAIName'] == "SmartAI") {
			$results = $converter->FindAllSmart(false, $v['entry'], SmartSourceType::creature);
			if(empty($results))
				echo "!!! But there is no smart data for this creature..." .PHP_EOL;
		}
	}
	echo "-- VERSUS -- " . PHP_EOL;
	if($sunMenuId = $v['sunmenuid']) {
		PrintMenu($sunMenuId, true, true);
	} else {
		echo "Nada". PHP_EOL;
	}
	if($v['sunScriptName'] != "")
		echo "WARNING: Sun creature has a ScriptName: {$v['sunScriptName']}" . PHP_EOL;
	if($v['sunAIName'] != "")
		echo "WARNING: Sun creature has an AIName: {$v['sunAIName']}" . PHP_EOL;
	
	$line = null;
	$skip = false;
	while($line == null && $skip == false)
	{
		$skip = false;
		echo PHP_EOL;
		$line = readline("Import? ([Y]es/[S]martAI/[N]o/[L]ater): ");
		switch($line)
		{
			case "Y": 
				SetImport($v['entry'], "GOSSIP"); 
				echo "Set to importing." . PHP_EOL;
				echo $separator;
				break;
			case "S": 
				SetImport($v['entry'], "SMART"); 
				echo "Set to importing." . PHP_EOL;
				if(empty($converter->FindAllSmart(false, $v['entry'], SmartSourceType::creature))) {
					echo "WARNING: Did not find any TC smartAI for this creature. If you intended to just import gossip:" . PHP_EOL;
					echo "UPDATE {$tcWorld}.creature_template SET import = 'GOSSIP' WHERE entry = {$v['entry']};" . PHP_EOL;
				}
				echo $separator;
				break;
			case "N": 
				SetImport($v['entry'], "IGNORE"); 
				echo "Set to ignoring." . PHP_EOL;
				echo $separator;
				break;
			case "L": 
				$skip = true; 
				echo "Skipping this one." . PHP_EOL;
				echo $separator;
				break;
			default:
				break; //invalid input, just restart loop
		}
	}
	echo PHP_EOL . PHP_EOL;
}

echo "Finished!" . PHP_EOL;