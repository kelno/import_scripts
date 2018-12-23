# import_scripts

```
ALTER TABLE `trinityworld`.`creature_template`   
  ADD COLUMN `import` ENUM('IGNORE','GOSSIP','SMART') NULL AFTER `entry`;
```

  
**creature_import.php**:   
Old script, don't use

**gossips_select.php**:  
 Interactive UI, help to choose which gossip to import

**gossips_import.php**:  
Import process depending on creature_template.`import` column
Generates `gossips.sql`
