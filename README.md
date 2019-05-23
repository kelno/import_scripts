# import_scripts

```
ALTER TABLE `trinityworld`.`creature_template`   
  ADD COLUMN `import` ENUM('IGNORE','GOSSIP','SMART') NULL AFTER `entry`;
  
ALTER TABLE `trinityworld`.`creature`   
  ADD COLUMN `import` ENUM('IMPORT','REPLACE_ALL','REPLACE_ALL_LK','LK_ONLY','MOVE_UNIQUE_IMPORT_WP','UPDATE_SPAWNID','IMPORT_WP', 'IGNORE', 'IMPORT_MAP') CHARSET utf8 COLLATE utf8_general_ci NULL;
  
ALTER TABLE `trinityworld`.`gameobject`   
  ADD COLUMN `import` ENUM('REPLACE_ALL','IMPORT', 'IGNORE') CHARSET utf8 COLLATE utf8_general_ci NULL;
  
```

  
**creature_import.php**:   
 Import from TC creature table

**gossips_select.php**:  
 Interactive UI, help to choose which gossip to import

**gossips_import.php**:  
 Import from TC creature_template table
 
**gob_import.php**:  
 Import from TC gameobject table
 
**creature_import_old.php**:   
 Old version of creature_import.php
 
**convert_game_event**:  
 Old script to match sun game events to tc game events

 