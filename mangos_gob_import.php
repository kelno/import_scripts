<?php

include_once(__DIR__ . '/config.php');
include_once(__DIR__ . '/lib/common.php');
include_once(__DIR__ . '/lib/helpers.php');

$output_filename = "gameobject_mangos.sql";
$file = fopen($output_filename, "w");
if (!$file)
	die("Couldn't open {$output_filename}");

$conn = new PDO("mysql:host=localhost", $login, $password);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// STEP 1 - Deleting all our veins and plant (& related pools)
fwrite($file, "-- STEP 1 - Deleting our stuff" . PHP_EOL);

$query = "SELECT g.guid
FROM ${sunWorld}.gameobject g
JOIN ${sunWorld}.gameobject_template gt ON gt.entry = g.id AND gt.data0 != 0
JOIN ${dbc}.db_lock_8606 dbc ON dbc.m_ID = gt.data0 AND dbc.m_Index_1 IN (2, 3)
WHERE gt.type = 3";

$stmt = $conn->query($query);
$stmt->setFetchMode(PDO::FETCH_ASSOC);

foreach($stmt->fetchAll() as $v) {
	$guid = $v['guid'];
	$sql = "CALL DeleteGameObject(${guid});" . PHP_EOL;
	fwrite($file, $sql);
}

fwrite($file, PHP_EOL);
$sql = "-- Deleting now empty pool_templates
DELETE pt FROM pool_template pt
LEFT JOIN pool_members pm ON pm.poolSpawnId = pt.entry
WHERE pt.entry IS NULL;" . PHP_EOL;
fwrite($file, $sql);
fwrite($file, PHP_EOL);

// STEP 2 - Importing gameobjects
fwrite($file, PHP_EOL . "-- STEP 2 - Importing gameobjects" . PHP_EOL);

$stmt = $conn->query("SELECT MAX(guid) AS highest FROM ${sunWorld}.gameobject;");
$stmt->setFetchMode(PDO::FETCH_ASSOC);

$firstFreeGuid = $stmt->fetch()["highest"] + 1;

$query = "SELECT gt.name, g.guid, gt.entry, g.map, g.position_x, g.position_y, g.position_z, g.orientation, g.rotation0, g.rotation1, g.rotation2, g.rotation3, g.spawntimesecsmin, g.spawntimesecsmax
FROM ${cmangosWorld}.gameobject g
JOIN ${cmangosWorld}.gameobject_template gt ON gt.entry = g.id AND gt.data0 != 0
JOIN ${dbc}.db_lock_8606 dbc ON dbc.m_ID = gt.data0 AND dbc.m_Index_1 IN (2, 3)
WHERE gt.type = 3";
$stmt = $conn->query($query);
$stmt->setFetchMode(PDO::FETCH_ASSOC);

$cmangos_guids = array();
fwrite($file, "REPLACE INTO gameobject (guid, id, map, position_x, position_y, position_z, orientation, rotation0, rotation1, rotation2, rotation3, spawntimesecsmin, spawntimesecsmax) VALUES " . PHP_EOL);
$sql = "";
foreach($stmt->fetchAll() as $v) {
	$cmangos_guid =  $v['guid'];
	$guid = $firstFreeGuid++;
	array_push($cmangos_guids, array($cmangos_guid, $guid));
	$entry = $v['entry'];
	
	$sql .= "(${guid}, ${v['entry']}, ${v['map']}, ${v['position_x']}, ${v['position_y']}, ${v['position_z']}, ${v['orientation']}, ${v['rotation0']}, ${v['rotation1']}, ${v['rotation2']}, ${v['rotation3']}, ${v['spawntimesecsmin']}, ${v['spawntimesecsmax']})," . PHP_EOL;
	
}
$sql = rtrim($sql, PHP_EOL);
$sql = rtrim($sql, ",");
fwrite($file, $sql . ';' . PHP_EOL . PHP_EOL);

// STEP 3 - Importing pools
fwrite($file, PHP_EOL . "-- STEP 3 - Import pools" . PHP_EOL);
$pools_members = array();

foreach($cmangos_guids as $pair)
{
	$cmangos_guid = $pair[0];
	$sun_guid = $pair[1];
	
	$stmt = $conn->query("SELECT * FROM ${cmangosWorld}.pool_gameobject WHERE guid = ${cmangos_guid}");
	$stmt->setFetchMode(PDO::FETCH_ASSOC);
	foreach($stmt->fetchAll() as $v) {
		$pool_entry = $v["pool_entry"];
		$chance = $v["chance"];
		$description = $v["description"];
		
		if (!array_key_exists($pool_entry, $pools_members))
			$pools_members[$pool_entry] = array();
		
		$pool_line = new stdClass; //anonymous object
		$pool_line->mangosguid = $cmangos_guid;
		$pool_line->sunguid = $sun_guid;
		$pool_line->chance = $chance;
		$pool_line->description = $description . " (cmangos gob guid: ${cmangos_guid})";
		
		array_push($pools_members[$pool_entry], $pool_line);
	}
}

$stmt = $conn->query("SELECT MAX(entry) AS highest FROM ${sunWorld}.pool_template;");
$stmt->setFetchMode(PDO::FETCH_ASSOC);
$firstFreePoolId = $stmt->fetch()["highest"] + 1;

$pool_list = array(); //index: cmangos - value: sun
$pool_correspondancy = array();
foreach($pools_members as $pool_id => $members)
{
	$sql = "SELECT * FROM ${cmangosWorld}.pool_template WHERE entry = ${pool_id}";
	$stmt = $conn->query($sql);
	$stmt->setFetchMode(PDO::FETCH_ASSOC);
	$v = $stmt->fetch();
	$sun_pool_id = $firstFreePoolId++;
	
	$pool_correspondancy[$pool_id] = $sun_pool_id;
	
	$description = $v["description"] . " (cmangos pool: ${pool_id})";
	fwrite($file, "-- Pool ${sun_pool_id} (cmangos pool ${pool_id})" . PHP_EOL);
	fwrite($file, "REPLACE INTO pool_template (entry, max_limit, description) VALUES ({$sun_pool_id}, ${v["max_limit"]}, \"${description}\");" . PHP_EOL);
	fwrite($file, "REPLACE INTO pool_members (type, spawnId, poolSpawnId, chance, description) VALUES " . PHP_EOL);
	$sql = "";
	foreach($members as $member)
	{
		$sunguid = $member->sunguid;
		$chance = $member->chance;
		$desc = $member->description;
		
		$sql .= "(1, ${sunguid}, ${sun_pool_id}, ${chance}, \"${desc}\")," . PHP_EOL;
	}
	$sql = rtrim($sql, PHP_EOL);
	$sql = rtrim($sql, ',');
	fwrite($file, $sql . ';' . PHP_EOL);
}

// Step 4 - Porting pools of pool

fwrite($file, PHP_EOL . "-- STEP 4 - pools of pool" . PHP_EOL);

$mother_pools = array();
foreach($pool_correspondancy as $cmangos_pool_id => $sun_pool_id)
{
	$sql = "SELECT * FROM ${cmangosWorld}.pool_pool WHERE pool_id = ${cmangos_pool_id}";
	$stmt = $conn->query($sql);
	$stmt->setFetchMode(PDO::FETCH_ASSOC);
	foreach($stmt->fetchAll() as $v) {
		$mother_pool = $v["mother_pool"];
		$chance = $v["chance"];
		$description = $v["description"] . " (cmangos pool: ${mother_pool})";
		
		if (!array_key_exists($mother_pool, $mother_pools))
			$mother_pools[$mother_pool] = array();
		
		$pool_line = new stdClass; //anonymous object
		$pool_line->sunpoolid = $sun_pool_id;
		$pool_line->chance = $chance;
		$pool_line->description = $description;
		
		array_push($mother_pools[$mother_pool], $pool_line);
	}
}

foreach($mother_pools as $pool_id => $members)
{
	$sql = "SELECT * FROM ${cmangosWorld}.pool_template WHERE entry = ${pool_id}";
	$stmt = $conn->query($sql);
	$stmt->setFetchMode(PDO::FETCH_ASSOC);
	$v = $stmt->fetch();
	$sun_pool_id = $firstFreePoolId++;
	
	fwrite($file, "-- Pool ${sun_pool_id} (cmangos pool ${pool_id})" . PHP_EOL);
	fwrite($file, "REPLACE INTO pool_template (entry, max_limit, description) VALUES ({$sun_pool_id}, ${v["max_limit"]}, \"${v["description"]}\");" . PHP_EOL);
	fwrite($file, "REPLACE INTO pool_members (type, spawnId, poolSpawnId, chance, description) VALUES " . PHP_EOL);
	$sql = "";
	foreach($members as $member)
	{
		$sunpoolid = $member->sunpoolid;
		$chance = $member->chance;
		$desc = $member->description;
		
		$sql .= "(2, ${sunpoolid}, ${sun_pool_id}, ${chance}, \"${desc}\")," . PHP_EOL;
	}
	$sql = rtrim($sql, PHP_EOL);
	$sql = rtrim($sql, ',');
	fwrite($file, $sql . ';' . PHP_EOL);
}

fclose($file);
echo "Done" . PHP_EOL;