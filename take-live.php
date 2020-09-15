<?php
	//This should be run from each servers on master branch, as a 5 minute repeat cron job.
	//
	//It should be run from the /home/ubuntu/config-cluster-0001
	//
	//   */5 * * * *     /usr/bin/php /somepath/config-cluster-0001/take-live.php
	//
	// Depending on the status of "productionSlowChecks", we can reduce the rate
	// of checking to once every hour.
	//
	//New clusters should have a new project config-cluster-0002, config-cluster-0003 etc. or some
	//other nomenclature e.g. by country code.
	
 
 

	chdir(__DIR__);
	if(strpos(__DIR__, "staging") !== false) {
		$staging = true;
	} else {
		$staging = false;
	}
	
	
	
	//Get the .config file
	$data = file_get_contents ("./config/config.json");
    $config = json_decode($data, true);
	
	//Loop through and get the productionSlowChecks for this type of server
	$slow_checks = false;

		if($config['productionSlowChecks'] == true) {
			$slow_checks = true;
		}
	

	
	
	if($staging == true) {
		//Get the staging branch if our current dir includes 'staging' ie. /var/www/html/atomjump_staging/
		if($slow_checks == true) {
			//Only check every hour or so (random 1 in 12 check every 5 minutes)
			if(rand(1,12) == 1){
				$git_response = shell_exec("sudo git fetch origin; sudo git diff origin/staging");
			} else {
				$git_response = "";		//A blank response, so do nothing			
			}
		} else {
			//Do a git check every 5 mins
			$git_response = shell_exec("sudo git fetch origin; sudo git diff origin/staging");
		}
	} else {
		//Every other kind of server
		if($slow_checks == true) {
	
			//Only check every hour or so (random 1 in 12 check every 5 minutes)
			if(rand(1,12) == 1){
				echo "Git fetching\n";
				$git_response = shell_exec("sudo git fetch origin; sudo git diff origin/master");
			} else {
				echo "Holding on doing a git update.\n";
				$git_response = "";		//A blank response, so do nothing
			}
		} else {
			//Do a git check every 5 mins
			echo "Git fetching\n";
			$git_response = shell_exec("sudo git fetch origin; sudo git diff origin/master");
		}
		
		
	}	
	
	echo "Git response:" . $git_response . "\n";
	
	if((strpos($git_response, "+++") !== false) ||
	   (strpos($git_response, "---") !== false)) {
		
		echo "Config changes started\n";
		
		
		
		if($staging == true) {
			exec("sudo git fetch origin staging; sudo git reset --hard FETCH_HEAD; sudo git clean -df"); 
		} else {
			exec("sudo git fetch origin master; sudo git reset --hard FETCH_HEAD; sudo git clean -df"); 
		}
		//see http://stackoverflow.com/questions/9589814/how-do-i-force-git-pull-to-overwrite-everything-on-every-pull
		
		echo "New files git fetched.\n";
		
		if(file_exists("config-override.json")) {
			exec("sudo cp config-override.json ..");			//Always update.
		}
		
		
		if(strpos($git_response, "--- a/hosts") !== false) {
			
				echo "Copying new host file...\n";
				//We have a change to the host file itself. 
				//This likely happens if we have switched over servers.
		
				$config_to_use = "hosts";
			
		
				echo "Copying " . $config_to_use . " to /etc/hosts";
				exec("sudo cp " . $config_to_use . " /etc/hosts");
				
		}
		
		if(strpos($git_response, "--- a/haproxy") !== false) {
			
				echo "Copying new haproxy file...\n";
				//We have a change to the haproxy file itself. Hard restart of the haproxy server.
				//This likely happens if we have switched over servers.
				exec("sudo cp haproxy.cfg /etc/haproxy");
		
		
				echo "Restarting haproxy...\n";
				//We have a change to the haproxy file itself. Hard restart of the haproxy server.
				//This likely happens if we have switched over servers.
				exec("sudo /usr/sbin/service haproxy restart");
				
		}



		
	} else {
		echo "All clear. No updates.\n";
	}

?>
