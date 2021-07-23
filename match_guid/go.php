<?php

$sunWorld = "world";
$tcWorld = "trinityworld";

try {
    // Connect
    $conn = new PDO("mysql:host=localhost;dbname=$sunWorld", "root", "canard");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected successfully\n"; 

    // Creatures Count
    $stmt = $conn->query("SELECT COUNT(*) as count FROM $sunWorld.gameobject;");
    $stmt->setFetchMode(PDO::FETCH_ASSOC);
    $sunSpawnIDCount = $stmt->fetch()['count'];
    echo "SUN go spawnID count: {$sunSpawnIDCount}\n";

    // $sunSpawnIDCount = 100;

    $file = fopen(__DIR__."/go.sql", "w");
    if (!$file)
        die("Couldn't open go.txt");

    $tcGuids = [];
    $sunGuids = [];
    $sql = "";

    // Creatures
    $batchSize = 10000;
    for ($offset = 0; $offset < $sunSpawnIDCount; $offset += $batchSize) {
        $stmt = $conn->query(
        "SELECT 
            g.guid as sunGuid,
            tc.guid as tcGuid,
            pm.spawnId as pm,
			tc.position_x as posX, tc.position_y as posY, tc.position_z as posZ, tc.orientation as Ori, tc.rotation0 as Rot0, tc.rotation1 as Rot1, tc.rotation2 as Rot2, tc.rotation3 as Rot3,
			(ABS(g.position_x - tc.position_x) + ABS(g.position_z - tc.position_z) + ABS(g.position_z - tc.position_z)) as distance
        FROM $sunWorld.gameobject g
        LEFT JOIN $tcWorld.gameobject tc ON g.guid > 1000000 AND g.id = tc.id AND g.position_x - 5 < tc.position_x AND g.position_x + 5 > tc.position_x AND g.position_y - 5 < tc.position_y AND g.position_y + 5 > tc.position_y AND g.position_z - 5 < tc.position_z AND g.position_z + 5 > tc.position_z AND g.map = tc.map
		LEFT JOIN $sunWorld.gameobject g2 ON g2.guid = tc.guid
        LEFT JOIN $sunWorld.pool_members pm ON pm.type = 1 AND pm.spawnId = g.guid
		WHERE g2.guid IS NULL AND tc.guid IS NOT NULL
		ORDER BY distance
        LIMIT $batchSize OFFSET $offset");
        $stmt->setFetchMode(PDO::FETCH_ASSOC);

        foreach($stmt->fetchAll() as $k => $v) {
            if ($v['tcGuid'] && in_array($v['tcGuid'], $tcGuids))
                continue;

            if ($v['sunGuid'] && in_array($v['sunGuid'], $sunGuids))
                continue;
            
            // Update the spawnID to match TC's
            if ($v['sunGuid'] != $v['tcGuid'] && $v['tcGuid']) {
                $sql .= "UPDATE gameobject SET guid = {$v['tcGuid']} WHERE guid = {$v['sunGuid']};\n";
                
                if ($v['c1'])
                    $sql .= "UPDATE conditions SET ConditionValue3 = {$v['tcGuid']} WHERE ConditionValue3 = {$v['sunGuid']} 0 AND ConditionTypeOrReference = 31;\n";

                if ($v['c3'])
                    $sql .= "UPDATE conditions SET SourceEntry = -{$v['tcGuid']} WHERE SourceEntry = -{$v['sunGuid']} AND SourceTypeOrReferenceId = 22;\n";

                if ($v['pm'])
                    $sql .= "UPDATE pool_members SET spawnId = {$v['tcGuid']} WHERE spawnId = {$v['sunGuid']} AND type = 1;\n";

                if ($v['s1'])
                    $sql .= "UPDATE smart_scripts SET entryorguid = -{$v['tcGuid']} WHERE entryorguid = -{$v['sunGuid']} AND source_type = 0;\n";

                if ($v['s2'])
                    $sql .= "UPDATE smart_scripts SET target_param1 = {$v['tcGuid']} WHERE target_param1 = {$v['sunGuid']} AND target_type = 10;\n";
                
                if ($v['sg'])
                    $sql .= "UPDATE spawn_group SET spawnID = {$v['tcGuid']} WHERE spawnID = {$v['sunGuid']} AND spawnType = 1;\n";

                if ($v['gs'])
                    $sql .= "UPDATE gameobject_scripts SET datalong = {$v['tcGuid']} WHERE datalong = {$v['sunGuid']} AND command IN (9,11,12);\n";

                if ($v['es'])
                    $sql .= "UPDATE event_scripts SET datalong = {$v['tcGuid']} WHERE datalong = {$v['sunGuid']} AND command IN (9,11,12);\n";
                    
                if ($v['qes'])
                    $sql .= "UPDATE quest_end_scripts SET datalong = {$v['tcGuid']} WHERE datalong = {$v['sunGuid']} AND command IN (9,11,12);\n";

                if ($v['qss'])
                    $sql .= "UPDATE quest_start_scripts SET datalong = {$v['tcGuid']} WHERE datalong = {$v['sunGuid']} AND command IN (9,11,12);\n";
                
                // if ($v['qsst'])
                    // $sql .= "UPDATE quest_start_scripts_tmp SET datalong = {$v['tcGuid']} WHERE datalong = {$v['sunGuid']} AND command IN (9,11,12);\n";
                    
                if ($v['ssc'])
                    $sql .= "UPDATE spell_scripts SET datalong = {$v['tcGuid']} WHERE datalong = {$v['sunGuid']} AND command IN (9,11,12);\n";
                
                if ($v['ws'])
                    $sql .= "UPDATE waypoint_scripts SET datalong = {$v['tcGuid']} WHERE datalong = {$v['sunGuid']} AND command IN (9,11,12);\n";
                
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

