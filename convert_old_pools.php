<?php

include_once(__DIR__ . '/lib/helpers.php');
include_once(__DIR__ . '/lib/common.php');
include_once(__DIR__ . '/config.php');

$output_filename = "convert_old_pools.sql";
$file = fopen($output_filename, "w");
if (!$file)
	die("Couldn't open {$output_filename}");

$start = microtime(true);

$debug = true;

$conn = new PDO("mysql:host=localhost", $login, $password);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$query = "SELECT c.spawnID, c.map, c.deprecated_pool_id, cf.groupAI
FROM ${sunWorld}.creature c
LEFT JOIN ${sunWorld}.creature_formations cf ON cf.memberGUID = c.spawnID
WHERE deprecated_pool_id != 0 
ORDER BY map, deprecated_pool_id;";

// MAIN
$stmt = $conn->query($query);
$stmt->setFetchMode(PDO::FETCH_ASSOC);

$targetGroupAI = 0x3; // FLAG_MEMBERS_ASSIST_LEADER+FLAG_LEADER_ASSISTS_MEMBER

$currentlyProcessedPool = 0;
$currentlyProcessedMap = 0;

$currentLeaderSpawnId = 0;

fwrite($file, "-- Converting old deprecated pool to creature formations" . PHP_EOL);
foreach($stmt->fetchAll() as $v) {
	$spawnId = $v['spawnID'];
	$map = $v['map'];
	$poolId = $v["deprecated_pool_id"];
	$groupAI = $v["groupAI"]; // = null if not existing
	
	if ($groupAI !== null)
	{
		fwrite($file, "-- {$spawnId} already has a creature formation" . PHP_EOL);
		if (!($groupAI & $groupAI))
			fwrite($file, "UPDATE creature_formations SET groupAI = groupAI | ${targetGroupAI} WHERE memberGUID = {$spawnId};" . PHP_EOL);
	}
	else
	{
	// First creature found will be the leader
	// This logic relies on the ordering in the SQL query
	if ($currentlyProcessedPool != $poolId || $currentlyProcessedMap != $map)
	{
		fwrite($file, PHP_EOL . "-- New formation with leader ${spawnId}" . PHP_EOL);
		assert($poolId > $currentlyProcessedPool);
		
		$currentlyProcessedPool = $poolId;
		$currentlyProcessedMap = $map;
		$currentLeaderSpawnId = $spawnId;
		
		fwrite($file, "REPLACE INTO creature_formations (leaderGUID, memberGUID, dist, angle, groupAI) VALUES (NULL, ${spawnId}, 0, 0, ${targetGroupAI});" . PHP_EOL);
	}
	else
	{
		fwrite($file, "-- Formation member ${spawnId}" . PHP_EOL);
		assert($currentLeaderSpawnId);
		fwrite($file, "REPLACE INTO creature_formations (leaderGUID, memberGUID, dist, angle, groupAI) VALUES (${currentLeaderSpawnId}, ${spawnId}, 0, 0, ${targetGroupAI});" . PHP_EOL);
	}
	}
	
	fwrite($file, "UPDATE creature SET deprecated_pool_id = 0 WHERE spawnID = {$spawnId};" . PHP_EOL);
}

fclose($file);

$duration = microtime(true) - $start;
$duration = number_format($duration, 4);
echo PHP_EOL . "Finished in {$duration}s" . PHP_EOL;	