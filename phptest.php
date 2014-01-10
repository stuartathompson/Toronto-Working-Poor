<?php
$selected = false;
function GoogleClientLogin($username, $password, $service) {
	// Check that we have all the parameters
	if(!$username || !$password || !$service) {
		throw new Exception("You must provide a username, password, and service when creating a new GoogleClientLogin.");
	}
	
	// Set up the post body
	$body = "accountType=GOOGLE &Email=$username&Passwd=$password&service=$service";
	
	// Set up the cURL
	$c = curl_init ("https://www.google.com/accounts/ClientLogin");
	curl_setopt($c, CURLOPT_POST, true);
	curl_setopt($c, CURLOPT_POSTFIELDS, $body);
	curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
	$response = curl_exec($c);
	
	// Parse the response to obtain just the Auth token
	// Basically, we remove everything before the "Auth="
	return preg_replace("/[\s\S]*Auth=/", "", $response);
}

class FusionTable {
	var $token;
	
	function FusionTable($token) {
		if (!$token) {
			throw new Exception("You must provide a token when creating a new FusionTable.");		
		}
		$this->token = $token;
	}
	
	function query($query) {
		if(!$query) {
			throw new Exception("query method requires a query.");
		}
		// Check to see if we have a query that will retrieve data
		if(preg_match("/^select|^show tables|^describe/i", $query)) {
			$request_url = "http://tables.googlelabs.com/api/query?sql=" . urlencode($query);
			$c = curl_init ($request_url);
			curl_setopt($c, CURLOPT_HTTPHEADER, array("Authorization: GoogleLogin auth=" . $this->token));
			curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
			
			// Place the lines of the output into an array
			$results = preg_split("/\n/", curl_exec ($c));
			
			// If we got an error, raise it
			if(curl_getinfo($c, CURLINFO_HTTP_CODE) != 200) {
				return $this->output_error($results);
			}

			// Drop the last (empty) array value
			array_pop($results);
			
			// Parse the output
			return $this->parse_output($results);
		}
		// Otherwise we are going to be updating the table, so we need to the POST method
		else if(preg_match("/^update|^delete|^insert/i", $query)) {
			// Set up the cURL
			$body = "sql=" . urlencode($query);
			$c = curl_init ("http://tables.googlelabs.com/api/query");
			curl_setopt($c, CURLOPT_POST, true);
			curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($c, CURLOPT_HTTPHEADER, array(
				"Content-length: " . strlen($body),
				"Content-type: application/x-www-form-urlencoded",
				"Authorization: GoogleLogin auth=" . $this->token . " "		// I don't know why, but unless I add extra characters after the token, I get this error: Syntax error near line 1:1: unexpected token: null
			));
			curl_setopt($c, CURLOPT_POSTFIELDS, $body);
			
			// Place the lines of the output into an array
			$results = preg_split("/\n/", curl_exec ($c));
			
			// If we got an error, raise it
			if(curl_getinfo($c, CURLINFO_HTTP_CODE) != 200) {
				return $this->output_error($results);
			}

			// Drop the last (empty) array value
			array_pop($results);
			
			return $this->parse_output($results);
		}
		else {
			throw new Exception("Unknown SQL query submitted.");
		}
	}
	
	private function parse_output($results) {
		$headers = false;
		$output = array();
		$i = 0;
		foreach($results as $row) {
		$i++;
		if($i == 2){
			$theNewRow = $row;
		}
		// Get the headers
			if(!$headers) {
				$headers = $this->parse_row($row);
			}
			else {
				// Create a new row for the array
				$newrow = array();
				$values = $this->parse_row($row);
				
				// Build an associative array, using the headers for the association
				foreach($headers as $index => $header) {
					$newrow[$header] = $values[$index];
				}
				
				// Add the new array to the output array
				array_push($output, $newrow);
			}
		}
		// Return the output
		return $output;
	}
	
	private function parse_row($row) {
		// Split the comma delimted row
		$cells = preg_split("/,/", $row);
		
		// Go through each cell and see if we encounter a double quote
		foreach($cells as $index => $value) {
			// When we encounter a double quote at the start of a cell, we've got a quoted string
			if(preg_match("/^\"/", $value)) {
				// Concatenate the value with the next cell and remove the double quotes
				$cells[$index] = preg_replace("/^\"|\"$/", "", $cells[$index] . $cells[$index+1]);
				
				// Drop the next cell from the array
				array_splice($cells, $index+1, 1);
			}
		}
		return $cells;
	}
	
	private function output_error($err) {
		$err = implode("", $err);
		
		// Remove everything outside of the H1 tag
		$err = preg_replace("/[\s\S]*<H1>|<\/H1>[\s\S]*/i", "", $err);
		
		// Return the error
		return $err;
		
		// Eventually we'll just throw the error rather than return the error output
		throw new Exception($err);
	}
}


// USAGE EXAMPLE

// First, get the token.
// The GoogleClientLogin function will provide the token, given a Google Account email address, password, and service
// (in this case, fusiontables)
$token = GoogleClientLogin("stuartathompson@gmail.com", "cl0U*M5sy", "fusiontables"); 

// Create a new instance of FusionTable, passing in the token generated by GoogleClientLogin
$ft = new FusionTable($token); 

// Have fun!  Use the FusionTable->query method to run queries.
// It will automatically take care of using the GET or POST method, depending on the type of query
// The output is an array of associative arrays.
// The associative arrays use the csv headers for the keys, and the values are the values in the csv columns
//$output = $ft->query("SELECT * FROM 2433934 WHERE LAT='LAT'");
// lat=&lon=&desc=&safe=&road=&cras=&ageo=&acct=&traf=&roads=&year
$theMethod = $_GET['method'];
$theTable = $_GET['table'];
$theLat = $_GET['lat'];
$theLon = $_GET['lon'];
$theName = $_GET['name'];
$thePhone = $_GET['phone'];
$theEmail = $_GET['email'];
$theAge = $_GET['age'];
$theDesc = $_GET['desc'];

if($theMethod == 'save'){
	// This is the first time we're saving this session
	$thequerystring = "INSERT INTO " . $theTable . " ('lat', 'lon', 'name', 'phone', 'email', 'age', 'desc', 'approved') VALUES ('" . $theLat . "', '" . $theLon . "', '" . $theName . "', '" . $thePhone . "', '" . $theEmail . "', '" . $theAge . "', '" . $theDesc . "', '0')";
	echo $thequerystring;
} else {
	// This is the second time we're saving - a resave
	$thequerystring = "SELECT ROWID FROM " . $theTable . " WHERE ID = '" . $theKey . "'";
}
$ft->query($thequerystring);
if($theMethod != 'save'){
foreach($output as $row){
	foreach($row as $ro){
		$thequerystring = "UPDATE " . $theTable . " SET geometry = '" . $theGeom . "' WHERE ROWID = '" . $ro . "'";
		$ft->query($thequerystring);
	}
}
}
?>