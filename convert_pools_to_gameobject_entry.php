<?php

include_once(__DIR__ . '/lib/helpers.php');
include_once(__DIR__ . '/lib/common.php');
include_once(__DIR__ . '/config.php');

$output_filename = "newpools.sql";
$file = fopen($output_filename, "w");
if (!$file)
	die("Couldn't open {$output_filename}");

$start = microtime(true);

$debug = true;
$converter = new DBConverter($file, $debug);

$conn = new PDO("mysql:host=localhost", $login, $password);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$query = "SELECT pm.*, ge.entry, pm2.poolSpawnId AS masterPool
FROM ${sunWorld}.pool_members pm
JOIN ${sunWorld}.gameobject_entry ge ON ge.spawnId = pm.spawnId
JOIN ${sunWorld}.pool_members pm2 ON pm2.type = 2 AND pm2.spawnId = pm.poolSpawnId
WHERE pm.type = 1
ORDER BY pm.poolspawnId";

// MAIN
$stmt = $conn->query($query);
$stmt->setFetchMode(PDO::FETCH_ASSOC);

$currentlyProcessedPool = 0;
$currentSpawnId = 0;
$lastType = 0;

fwrite($file, "-- Converting pools of pools to gameobject_entry" . PHP_EOL);
foreach($stmt->fetchAll() as $v) {
	$spawnId = $v['spawnId'];
	$type = $v['type'];
	$entry = $v["entry"];
	$chance = $v["chance"];
	$pool = $v["poolSpawnId"];
	$masterPool = $v["masterPool"];
	
	if ($currentlyProcessedPool != $pool)
	{
		fwrite($file, PHP_EOL . PHP_EOL);
		assert($pool > $currentlyProcessedPool);
		$currentlyProcessedPool = $pool;
		$currentSpawnId = $spawnId;
		$lastType = $type;
		
		fwrite($file, "DELETE FROM pool_members WHERE type = 1 AND spawnId = {$spawnId};" . PHP_EOL);
		fwrite($file, "DELETE FROM pool_template WHERE entry = ${pool};" . PHP_EOL);
		fwrite($file, "INSERT INTO pool_members (type, spawnId, poolSpawnId, chance, description) VALUES (1, ${currentSpawnId}, ${masterPool}, ${chance}, 'Autoconvert pools of pools to gameobject_entry');" . PHP_EOL);
	}
	else
	{
		assert($currentSpawnId);
		assert($type = $lastType);
		fwrite($file, "DELETE FROM pool_members WHERE type = 1 AND spawnId = {$spawnId};" . PHP_EOL);
		fwrite($file, "CALL DeleteGameObject(${spawnId});" . PHP_EOL);
		fwrite($file, "REPLACE INTO gameobject_entry (spawnID, entry, chance) VALUES (${currentSpawnId}, ${entry}, ${chance});" . PHP_EOL);
	}
}

fclose($file);

$duration = microtime(true) - $start;
$duration = number_format($duration, 4);
echo PHP_EOL . "Finished in {$duration}s" . PHP_EOL;	