#
 import_scripts

A collection of scripts to import 

**convert_game_event.php**:  
One time script to convert match sunstrider events id to TC ones, or move the ones not matching out of TC range.

**convert_old_pools.php**:  
Convert old sunstrider creature.pool_id column to creature_formations system.

**convert_pools_to_gameobject_entry.php**:  
Clear up some pools by using sunstrider gameobject_entry table instead. Was mostly used to clear up the pool system of the resource nodes?

**creature_import.php**:   
Import from TC creature table

```SQL
ALTER TABLE `trinityworld`.`creature`   
  ADD COLUMN `import` ENUM('IMPORT','REPLACE_ALL','REPLACE_ALL_LK','LK_ONLY','MOVE_UNIQUE_IMPORT_WP','UPDATE_SPAWNID','IMPORT_WP', 'IGNORE', 'IMPORT_MAP') CHARSET utf8 COLLATE utf8_general_ci NULL;
```

**creature_import_old.php**:   
Old version of creature_import.php, not using the common library. Probably the first script written.
 
 **creature_template_import.php**:  
Import all missing TC creature_template to sunstrider db.

**gob_import.php**:  
Import from TC gameobject table

 ```SQL
ALTER TABLE `trinityworld`.`gameobject`   
  ADD COLUMN `import` ENUM('REPLACE_ALL','IMPORT', 'IGNORE') CHARSET utf8 COLLATE utf8_general_ci NULL;
```

**gossips_import.php**:  
Import from TC gossip menus and related tables
 
```SQL
ALTER TABLE `trinityworld`.`creature_template`   
  ADD COLUMN `import` ENUM('IGNORE','GOSSIP','SMART') NULL AFTER `entry`;
```

**gossips_select.php**:  
Interactive UI, help to choose which gossip to import from TC for creature template having no gossip in sunstrider but gossips in TC.

**mangos_fishing_import.php**:  
Import some fishies from mangos.

**mangos_gob_import.php**:  
Import resources nodes? from mangos.

**name_loot_references.php**:  
Update lots of *_loot_template Comments for readabilty.

**similar_trainer.php**:  
Cleanup duplicated trainer data where trainers data exactly matches.
