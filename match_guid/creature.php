<?php

$sunWorld = "world";
$tcWorld = "trinityworld";

try {
    // Connect
    $conn = new PDO("mysql:host=localhost;dbname=$sunWorld", "root", "pass");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected successfully\n"; 

    // Creatures Count
    $stmt = $conn->query("SELECT COUNT(*) as count FROM $sunWorld.creature_entry;");
    $stmt->setFetchMode(PDO::FETCH_ASSOC);
    $sunSpawnIDCount = $stmt->fetch()['count'];
    echo "SUN spawnID count: {$sunSpawnIDCount}";

    // $sunSpawnIDCount = 100;

    $file = fopen(__DIR__."/creature.sql", "w");
    if (!$file)
        die("Couldn't open creature.txt");

    $tcGuids = [];
    $sunGuids = [];
    $sql = "";

    // Creatures
    $batchSize = 10000;
    for ($offset = 0; $offset < $sunSpawnIDCount; $offset += $batchSize) {
		$query =  "SELECT sc.spawnID as sunGuid, tc.guid as tcGuid, c.id as c1, c3.id as c3, ss.entryorguid as s1, ss2.entryorguid as s2, sg.spawnID as sg, tc.position_x as posX, tc.position_y as posY, tc.position_z as posZ, tc.orientation as Ori, (ABS(sc.position_x - tc.position_x) + ABS(sc.position_z - tc.position_z) + ABS(sc.position_z - tc.position_z)) as distance
        FROM $sunWorld.creature_entry ce
        JOIN $sunWorld.creature sc ON ce.spawnID > 1000000 AND ce.spawnID = sc.spawnID
        LEFT JOIN $tcWorld.creature tc ON ce.entry = tc.id AND sc.position_x - 5 < tc.position_x AND sc.position_x + 5 > tc.position_x AND sc.position_y - 5 < tc.position_y AND sc.position_y + 5 > tc.position_y AND sc.position_z - 5 < tc.position_z AND sc.position_z + 5 > tc.position_z AND sc.map = tc.map
		LEFT JOIN $sunWorld.creature sc2 ON sc2.spawnID = tc.guid
        LEFT JOIN $sunWorld.conditions c ON c.ConditionValue3 = ce.spawnID AND c.ConditionTypeOrReference = 31
        LEFT JOIN $sunWorld.conditions c3 ON c3.SourceEntry = -ce.spawnID AND c3.ConditionTypeOrReference = 22
        LEFT JOIN $sunWorld.smart_scripts ss ON ss.entryorguid = -ce.spawnID AND ss.source_type = 0
        LEFT JOIN $sunWorld.smart_scripts ss2 ON ss2.target_param1 = ce.spawnID AND ss2.target_type = 10
        LEFT JOIN $sunWorld.spawn_group sg ON sg.spawnID = ce.spawnID AND sg.spawnType = 0
		WHERE sc2.spawnId IS NULL AND tc.guid IS NOT NULL
		ORDER BY distance
        LIMIT $batchSize OFFSET $offset";
		//echo $query . PHP_EOL;
        $stmt = $conn->query($query);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);

        foreach($stmt->fetchAll() as $k => $v) {
            if ($v['tcGuid'] && in_array($v['tcGuid'], $tcGuids))
                continue;

            if ($v['sunGuid'] && in_array($v['sunGuid'], $sunGuids))
                continue;
    
            // Update the spawnID to match TC's
            if ($v['sunGuid'] != $v['tcGuid'] && $v['tcGuid']) {
                $sql .= "UPDATE creature_entry SET spawnID = {$v['tcGuid']} WHERE spawnID = {$v['sunGuid']};\n";
                $sql .= "UPDATE creature SET position_x = {$v['posX']}, position_y = {$v['posY']}, position_z = {$v['posZ']}, orientation = {$v['Ori']}  WHERE spawnID = {$v['sunGuid']};\n";
				
                if ($v['c1'])
                    $sql .= "UPDATE conditions SET ConditionValue3 = {$v['tcGuid']} WHERE ConditionValue3 = {$v['sunGuid']} AND ConditionTypeOrReference = 31;\n";

                if ($v['c3'])
                    $sql .= "UPDATE conditions SET SourceEntry = -{$v['tcGuid']} WHERE SourceEntry = -{$v['sunGuid']} AND SourceTypeOrReferenceId = 22;\n";

                if ($v['s1'])
                    $sql .= "UPDATE smart_scripts SET entryorguid = -{$v['tcGuid']} WHERE entryorguid = -{$v['sunGuid']} AND source_type = 0;\n";

                if ($v['s2'])
                    $sql .= "UPDATE smart_scripts SET target_param1 = {$v['tcGuid']} WHERE target_param1 = {$v['sunGuid']} AND target_type = 10;\n";

                if ($v['sg'])
                    $sql .= "UPDATE spawn_group SET spawnID = {$v['tcGuid']} WHERE spawnID = {$v['sunGuid']} AND spawnType = 0;\n";

                array_push($tcGuids, $v['tcGuid']);
                array_push($sunGuids, $v['sunGuid']);
            }
        }
    }
    fwrite($file, $sql);
    fclose($file);

} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}

