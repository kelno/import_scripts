<?php

error_reporting(E_STRICT);
include_once(__DIR__ . '/lib/helpers.php');
include_once(__DIR__ . '/lib/common.php');
include_once(__DIR__ . '/config.php');

$output_filename = "name_loot_references.sql";
$file = fopen($output_filename, "w");
if (!$file)
	die("Couldn't open {$output_filename}");

$converter = new DBConverter($file);

$conn = new PDO("mysql:host=localhost", $login, $password);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$query = "SELECT Reference, Comment FROM {$sunWorld}.creature_loot_template WHERE Comment IS NOT NULL UNION SELECT Reference, Comment FROM {$sunWorld}.reference_loot_template WHERE Comment IS NOT NULL UNION SELECT Reference, Comment FROM {$sunWorld}.gameobject_loot_template WHERE Comment IS NOT NULL";
$stmt = $conn->query($query);
$stmt->setFetchMode(PDO::FETCH_ASSOC);
$knownNames = [];
foreach($stmt->fetchAll() as $v) {
	$knownNames[$v['Reference']] = $v['Comment'];
}

/*
$query = "SELECT Entry, Item FROM {$sunWorld}.creature_loot_template WHERE Reference = 0";
$stmt = $conn->query($query);
$stmt->setFetchMode(PDO::FETCH_ASSOC);
$itemsInRef = [];
foreach($stmt->fetchAll() as $v) {
	if (array_key_exists([$v['Entry'], $itemsInRef))
		array_push($itemsInRef[$v['Entry']], $v['Item']);
	else
		$itemsInRef[$v['Entry']] = array($v['Item']);
}*/

function TryGetExistingName($searchRefId)
{
	global $conn, $knownNames;
	foreach($knownNames as $refId => $name) {
		if ($refId == $searchRefId)
			return $name;
	}
	
	return null;
}

function GetQualityName($id)
{
	switch($id)
	{
		case 0:  return "Grey";
		case 1:  return "White";
		case 2:  return "Green";
		case 3:  return "Blue";
		case 4:  return "Purple";
		case 5:  return "Orange";
		case 6:  return "Light Yellow";
		default: return "Unknown quality";
		
	}
}

function TryDeducingName($refId)
{
	global $conn, $converter;
	$results = FindAll($converter->sunStore->reference_loot_template, "Entry", $refId);
	$lvl_min = null;
	$lvl_max = null;
	$quality = null;
	$count = 0;
	$is_plans = true;
	$reqSkill_min = 0;
	$reqSkill_max = 0;
	foreach($results as $result)
	{
		$itemId = $result->Item;
		if (!$itemId || $result->Reference)
			continue;
		
		if (!array_key_exists($itemId, $converter->sunStore->item_template))
		{
			echo "Failed to find item ${itemId} for reference ${refId}" . PHP_EOL;
			break;
		}
		$currentReqLevel = $converter->sunStore->item_template[$itemId]->RequiredLevel;
		$currentSkillRank = $converter->sunStore->item_template[$itemId]->RequiredSkillRank;
		$currentQuality = $converter->sunStore->item_template[$itemId]->Quality;
		if ($quality !== null && $quality != $currentQuality)
		{
			echo "Non matching quality ${currentQuality} with already found quality ${quality} for item ${itemId} for reference ${refId}" . PHP_EOL;
			$quality = null;
			break;
		}
		$quality = $currentQuality;
		if ($lvl_min === null || $lvl_min > $currentReqLevel)
			if ($currentReqLevel)
				$lvl_min = $currentReqLevel;
		if ($lvl_max === null || $lvl_max < $currentReqLevel)
			if ($currentReqLevel)
				$lvl_max = $currentReqLevel;
			
		if ($is_plans && $converter->sunStore->item_template[$itemId]->class != 9) // ITEM_CLASS_RECIPE
			$is_plans = false;
			
			
		if ($reqSkill_min === null || $reqSkill_min < $currentSkillRank)
			if ($currentSkillRank)
				$reqSkill_min = $currentSkillRank;
			
		if ($reqSkill_max === null || $reqSkill_max < $currentSkillRank)
			if ($currentSkillRank)
				$reqSkill_max = $currentSkillRank;
			
		$count++;
	}
		
	if ($quality && $lvl_min && $lvl_max)
	{
		$str = GetQualityName($quality) . " items ({$count}) lvl " . $lvl_min;
		if ($lvl_min != $lvl_max)
			$str .= "-" . $lvl_max;
		return $str;
	}
	else if ($is_plans && $reqSkill_min && $reqSkill_max)
	{
		$qualityStr = $quality !== null ? GetQualityName($quality) . " " : "";
		$str = $qualityStr . "plans ({$count})";
		if ($lvl_min != $lvl_max)
			$str .= "-" . $lvl_max;
		return $str;
	}
	else
		echo "no find for {$refId}" . PHP_EOL;
	
	return null;
}

$query = "SELECT Reference FROM {$sunWorld}.creature_loot_template WHERE Reference > 0 AND Comment IS NULL UNION SELECT Reference FROM {$sunWorld}.reference_loot_template WHERE Reference > 0 AND COMMENT IS NULL UNION SELECT Reference FROM {$sunWorld}.gameobject_loot_template WHERE Reference > 0 AND COMMENT IS NULL ORDER BY Reference"; 
$stmt = $conn->query($query);
$stmt->setFetchMode(PDO::FETCH_ASSOC);

$refs = [];

foreach($stmt->fetchAll() as $v) {
	$refId = $v['Reference'];
	$refs[$refId] = TryGetExistingName($refId);
	if ($refs[$refId] == null)
		$refs[$refId] = TryDeducingName($refId);
}

foreach($refs as $refId => $name)
{
	if ($name == null)
	{
		fwrite($file, "-- Failed to find a name for ref {$refId}" . PHP_EOL);
		continue;
	}
	
	$sql = "-- Reference ${refId}" . PHP_EOL .
           "UPDATE creature_loot_template SET Comment = '{$name}' WHERE Reference = {$refId};" . PHP_EOL .
	       "UPDATE reference_loot_template SET Comment = '{$name}' WHERE Reference = {$refId};" . PHP_EOL .
		   "UPDATE gameobject_loot_template SET Comment = '{$name}' WHERE Reference = {$refId};" . PHP_EOL;
	fwrite($file, $sql);
}
 
fclose($file);
echo "Done." . PHP_EOL;

