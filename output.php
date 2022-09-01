<?php
// ///////////////////////////////////////////////////////
// Initialize variables

$config 	= array();
$scodes		= array();
$capcodes	= array();

// ///////////////////////////////////////////////////////
// Assign API url

$config 	= ['discord'=> ['webhook' => 'https://discord.com/api/webhooks/..............................'] ];

// ///////////////////////////////////////////////////////
// Capcodes are collections of disciplines and/or regions

if (file_exists("capcodes.db")) {
	$fp = @fopen("capcodes.db", 'r');
	if ($fp) {
		$capcodes = explode("\n", fread($fp, filesize("capcodes.db"))); 
		print("Loading cap codes [Total: ".count($capcodes)."]\n");
	}
	fclose($fp);
} else { die("No capcode database found"); }

// ///////////////////////////////////////////////////////
// Shortnames are abbreviations used in messages

if (file_exists("shortnames.db")) {
	$fp = @fopen("shortnames.db", 'r');
	if ($fp) {
		$scodes = explode("\n", fread($fp, filesize("shortnames.db")));
		print("Loading abbreviation codes [Total: ".count($scodes)."]\n");
	}
	fclose($fp);
} else { die("No shortcode database found"); }

// ///////////////////////////////////////////////////////
// Finally we setup our listener on the stdinput, we also
// listen for the '--q' phrase, which will exit the loop.

while(!feof(STDIN)){
	$line 		= trim(fgets(STDIN));
	if (strlen($line)>0) {
		if ($line=="--q") { die("Caught exit signal by stdin..."); }
		print("[".date("Y.m.d-H.i.s")."] -> ".strlen($line)."bytes received.\n");
		print($line."\n");
		$payload	= CreateMessage($capcodes,$scodes,$line);
		$result		= SendMessage($payload,$config);
	} else {
		print("Skipping...\n");
	}
	print("---------------------------------------------------------------------------------\n");
}


// ///////////////////////////////////////////////////////
// Parse received data
function CreateMessage($db,$sc,$payload) {
	// Initialize array
	$time	 	= date("Y-m-d H:i:s");
	$data[]		= array();
	$data		= explode("|", $payload);

	// Only proceed if we have all data
	if (count($data)>=7) {

	// Write to out database
	WriteDB(trim($data[4]),trim($data[6]));

	return json_encode([
		"username" => "P2000",
		"tts" => false,
		"embeds" => [	[
				"title" => "Transmission",
				"type" => "rich",
				"color" => hexdec("3366ff"),
				"footer" => ["text" => "Barret © 2022 • 169,650MHz • FLEX"],
				"thumbnail" => ["url" => "https://i.imgur.com/yy3praF.png"],
				"fields" => [
		                [
	                    	"name" => "📟 Protocol",
	                    	"value" => $data[2],
	                    	"inline" => true
	                	],
				[
	                    	"name" => "🎞 Frame",
	                    	"value" => $data[3],
	                    	"inline" => true
	                	],
				[
	                    	"name" => "⌚ Time",
	                    	"value" => $time,
	                    	"inline" => true
	                	],
				[
	                    	"name" => "Capcodes",
	                    	"value" => Summarize($db,trim($data[4])),
	                    	"inline" => false
	                	],
				[
	                    	"name" => "Message",
	                    	"value" => $data[6],
	                    	"inline" => false
	                	],
				[
	                    	"name" => "Details",
	                    	"value" => ScanAbbr($sc,trim($data[6])),
	                    	"inline" => false
				]
            		]]]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	}
}

// ///////////////////////////////////////////////////////
// Abbreviations (shortcodes) code lookup parser
function ScanAbbr($db,$input) {
	$results = "";
	if (strlen($input)>0) {
		preg_match_all('~[\w\:\-\.-]+~',$input,$matches,PREG_SET_ORDER);
		// Make sure we have matches
		if (count($matches)>0) {
			foreach ($matches as $abr) {
				foreach ($abr as $word) {
					foreach ($db as &$code) {
						// match words against known codes
						$column = array();
						$column = explode(",",$code);
						if (count($column)>1) {
							if ($column[0]==$word) {
								$results .= trim($column[1])."\n";
							}
						}
					}
				}
			}
		}
	}
	// Dont return with null
	if (strlen($results) > 0) { return $results; }
	return "Geen";
}

// ///////////////////////////////////////////////////////
// Capcode lookup parser
function Summarize($db,$input) {
	if (strlen($input)>0) {
		$results  = "";
		$codes 	  = array();
		$codes    = explode(" ",$input);
		foreach ($codes  as &$v1) {
			foreach ($db as &$v2) {
				$c = substr($v2, 0,9);
				if ($v1==$c) { 
					$v2 = str_replace(",", " ", $v2);
					$v2 = str_replace("  ", " ", $v2);
					$results .= trim(substr($v2,9))."\n"; 
				
				}
			}
		}
		// Dont return with null
		if (strlen($results) > 0) { return $results; }
		return $input;
		}
	return "Geen";
}

// ///////////////////////////////////////////////////////
// Write to database
function WriteDB($capcodes,$message) {
	if (!file_exists('captured.db')) {
		print("Creating new database\n");
		$db = new SQLite3('captured.db');
		$db->exec("CREATE TABLE broadcasts(id INTEGER PRIMARY KEY, time DATE, capcodes TEXT, data TEXT)");
	} else {
		$db = new SQLite3('captured.db');
	}

	// Insert data
	$db->exec("INSERT INTO broadcasts(time, capcodes, data) VALUES(date('now'), '".$capcodes."', '".$message."')");

	// Verify it
	$state = 0;
	$res = $db->query('SELECT * FROM broadcasts ORDER BY id DESC LIMIT 1;');
	while ($row = $res->fetchArray()) {
		$state = 1;
 		print("{$row['id']} = {$row['capcodes']} {$row['data']} \n");
	}
	// Did we really add the entry?
	if ($state=0) { print("Message not saved in database!\n"); } else { print("Message stored in database.\n"); }
	$db->close();
} 

// ///////////////////////////////////////////////////////
// Hand off data to discord API server
function SendMessage($payload,$config) {
	if (strlen($payload) >= 0) {
	$ch = curl_init($config['discord']['webhook']);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$response = curl_exec( $ch );
	curl_close( $ch );
	return $response;
	}
}
?>
