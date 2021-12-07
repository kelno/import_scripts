<?php

include_once(__DIR__ . '/lib/helpers.php');
include_once(__DIR__ . '/lib/common.php');
include_once(__DIR__ . '/config.php');

$output_filename = "newpools_2.sql";
$file = fopen($output_filename, "w");
if (!$file)
	die("Couldn't open {$output_filename}");

$start = microtime(true);

$debug = true;
$converter = new DBConverter($file, $debug);

$conn = new PDO("mysql:host=localhost", $login, $password);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$query = "SELECT poolSpawnId, g.position_x, g.position_y, g.spawnId, ge.entry, pm.chance
FROM ${sunWorld}.pool_members pm
JOIN ${sunWorld}.gameobject g ON g.spawnId = pm.spawnId
JOIN ${sunWorld}.gameobject_entry ge ON ge.spawnId = g.spawnId
WHERE TYPE = 1
ORDER BY poolSpawnId";

class PoolMember
{
	function __construct($s, $e, $c, $x, $y)
	{
        $this->spawn_id = $s;
        $this->entry = $e;
        $this->chance = $c;
        $this->x = $x;
        $this->y = $y;
    }
    
    public $spawn_id = null;
    public $entry = null;
    public $chance = null;
    public $x = null;
    public $y = null;
}

class Coord
{
	function __construct($x, $y)
	{
        $this->x = $x;
        $this->y = $y;
    }
    
    public $x = null;
    public $y = null;
}

// MAIN
fwrite($file, "-- Converting more pools to gameobject_entry" . PHP_EOL);
$stmt = $conn->query($query);
$stmt->setFetchMode(PDO::FETCH_NUM);

$group_coordinates = array(); // key is pool spawn id
$all_groups = array(); // key is pool spawn id

// Extract data
foreach($stmt->fetchAll() as $v) {
    $pool_member = new PoolMember($v[3], $v[4], $v[5], $v[1], $v[2]);
    $pool_id = $v[0];
	
    if (!array_key_exists($pool_id, $group_coordinates))
        $group_coordinates[$pool_id] = new Coord($pool_member->x, $pool_member->y);

    if (!array_key_exists($pool_id, $all_groups))
        $all_groups[$pool_id] = array();

    array_push($all_groups[$pool_id], $pool_member);
}

// Now remove groups not sharing the same coordinate
foreach ($all_groups as $pool_id => &$arr) {
    // only one object in pool? We don't cover this case
    if (sizeof($arr) == 1) {
        unset($all_groups[$pool_id]);
        continue;
    }
        
    $x = $group_coordinates[$pool_id]->x;
    $y = $group_coordinates[$pool_id]->y;

    $delete = false;
    foreach ($arr as &$member) {
        if ($member->x != $x || $member->y != $y) {
            $delete = true;
            break;
        }
    }
    if ($delete)
        unset($all_groups[$pool_id]);
}

// Transform remaining groups
foreach ($all_groups as $pool_id => &$arr) {
    fwrite($file, "-- Converting ${pool_id}" . PHP_EOL);
    fwrite($file, "DELETE FROM pool_members WHERE type = 1 AND poolSpawnId = ${pool_id};" . PHP_EOL);
    fwrite($file, "DELETE FROM pool_template WHERE entry = ${pool_id};" . PHP_EOL);
    $first_spawn_id_of_group = null;
    foreach ($arr as &$member) {
        $spawn_id = $member->spawn_id;
        if ($first_spawn_id_of_group === null) {
            $first_spawn_id_of_group = $spawn_id;
            fwrite($file, "DELETE FROM gameobject_entry WHERE spawnID = $spawn_id;" . PHP_EOL);
        }
        else
            fwrite($file, "CALL DeleteGameObject($spawn_id);" . PHP_EOL);
        
        fwrite($file, "INSERT INTO gameobject_entry (spawnID, entry, chance) VALUES ($first_spawn_id_of_group, {$member->entry}, {$member->chance});" . PHP_EOL . PHP_EOL);
    }
    fwrite($file, PHP_EOL);
}

fclose($file);

$duration = microtime(true) - $start;
$duration = number_format($duration, 4);
echo PHP_EOL . "Finished in {$duration}s" . PHP_EOL;	