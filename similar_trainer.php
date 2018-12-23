<?php

include_once(__DIR__ . '/lib/common.php');

function ComputeHash(&$my_spells)
{
	foreach($my_spells as &$spell)
		$spell->TrainerId = 0;
		
	return md5(serialize($my_spells));
}

function Compare()
{
	global $sunStore, $debug;
	
	$debug = false;
	
	$trainer_spell_hashes = [];
	echo "Computing hashes".PHP_EOL;
	foreach($sunStore->trainer as $trainer) {
		if($debug && $trainer->Id != 219523 && $trainer->Id != 209322)
			continue;
		
		$my_spells = FindAll($sunStore->trainer_spell, "TrainerId", $trainer->Id);
		if(empty($my_spells)) {
			echo "No my trainer spells {$trainer->Id}" .PHP_EOL;
			continue;
			//assert(false);
			//exit(1);
		}
		$trainer_spell_hashes[$trainer->Id] = ComputeHash($my_spells);
	}

	echo "Done." . PHP_EOL;
	$useTrainers = [];
	$results_count = 0;
	
	foreach($sunStore->trainer as $trainer) {
		if($debug && $trainer->Id != 219523 && $trainer->Id != 209322)
			continue;
		
		if($trainer->Id < 202100)
			continue; //exclude LK trainers
		
		foreach($sunStore->trainer as $other_trainer) {
			if($trainer->Id == $other_trainer->Id)
				continue; //don't compare ourselves
			
			if($other_trainer->Id < 202100)
				continue; //exclude LK trainers
			
			if($debug && $other_trainer->Id != 219523 && $other_trainer->Id != 209322)
				continue;
		
			if($trainer->Type != $other_trainer->Type)
				continue;
			
			if($trainer->Requirement != $other_trainer->Requirement)
				continue;
			
			if($trainer->Greeting != $other_trainer->Greeting)
				continue;
			
			if($trainer->Greeting != $other_trainer->Greeting)
				continue;
			
			//comparable trainer, now compare spells!
			
			if($trainer_spell_hashes[$trainer->Id] == $trainer_spell_hashes[$other_trainer->Id]) {
				if(CheckAlreadyImported($trainer->Id))
					continue;
			
				if(CheckAlreadyImported($other_trainer->Id)) 
					continue;
				
				echo "-- {$trainer->Id} | {$other_trainer->Id} are identical" . PHP_EOL;
				echo "DELETE FROM trainer_spell WHERE TrainerId = {$other_trainer->Id};" . PHP_EOL;
				echo "DELETE FROM trainer WHERE Id = {$other_trainer->Id};" . PHP_EOL;
				echo "UPDATE creature_default_trainer SET TrainerId = {$trainer->Id} WHERE TrainerId = {$other_trainer->Id};" . PHP_EOL;
				array_push($useTrainers, $trainer->Id);
				assert(!in_array($other_trainer->Id, $useTrainers));
				$results_count++;
			}
		}
	}
	
	echo "-- Found {$results_count} results " .PHP_EOL;
}

Compare();
