# import_scripts

```
ALTER TABLE `trinityworld`.`creature_template`   
  ADD COLUMN `import` ENUM('IGNORE','GOSSIP','SMART') NULL AFTER `entry`;
  
ALTER TABLE `trinityworld`.`creature`   
  ADD COLUMN `import` ENUM('IMPORT','REPLACE_ALL','REPLACE_ALL_LK','LK_ONLY','MOVE_UNIQUE_IMPORT_WP','UPDATE_SPAWNID','IMPORT_WP', 'IGNORE') CHARSET utf8 COLLATE utf8_general_ci NULL;
```

  
**creature_import.php**:   
Old script, don't use

**gossips_select.php**:  
 Interactive UI, help to choose which gossip to import

**gossips_import.php**:  
Import process depending on creature_template.`import` column
Generates `gossips.sql`
