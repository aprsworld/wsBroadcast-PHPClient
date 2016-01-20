#!/usr/bin/php -q
<?

/*
 * Shamelessly stolen from danielnorton.com/2013site/nerd/code/php/isdef.html
 */
// Be silent about E_NOTICE errors, but make a note if we see one
function _isdef_error_handler( $errno, $errstr, $errfile, $errline, $errcontext ) {
  $GLOBALS['_isdef_error_detected'] = TRUE;
  return TRUE;
}

// enable the error handler
function isdef_begin() {
  if (empty($GLOBALS['_isdef_error_handler_enabled'])) {
    $GLOBALS['_isdef_error_handler_enabled'] = TRUE;
    set_error_handler("_isdef_error_handler",E_NOTICE);
  }
  _isdef_reset();
}

// reset the error handler
function _isdef_reset() {
  unset($GLOBALS['_isdef_error_detected']);
}

// disable the error handler
function isdef_end() {
  if (!empty($GLOBALS['_isdef_error_handler_enabled'])) {
    restore_error_handler();
    unset($GLOBALS['_isdef_error_handler_enabled']);
  }
}

// see if the variable is defined by seeing if an error was detected
function isdef($var) {
  if (!empty($GLOBALS['_isdef_error_detected'])) {
    _isdef_reset();
    return FALSE;
  }
  return TRUE;
}
/*
 * !Shamlessly Stolen
 */

function wsb_url_encode ($url, $uri) {

	// Append trailing / to url, if needed.
	if (substr($url, -1) != '/') {
		$url = $url . '/';
	}

	// split uri into keys
	$keys = explode('/', $uri);
	if ($keys[0] == '') {
		array_shift($keys);
	}

	// encode keys
	$keys = array_map(rawurlencode, $keys);

	// return complete url
	return $url . implode('/', $keys);
}

function wsb_value_set ($url, $uri, $value, $expire = NULL) {

	// Format the Update Packet
	$data = array();
	$data['wsb_update'] = 0;
	if ($expire >= 0) {
		$data['expire'] = $expire;
	}
	$data['data'] = $value;

	// Encode actual request
	$json = json_encode($data /*, JSON_PRESERVE_ZERO_FRACTION*/);
	if ($json == FALSE) {
		return new Exception('JSON Encoding Error', 666);
	}

	// Make request
	$sh = 'curl --fail -s --header "Content-Type: application/json" --header "Accept: application/json" --data-binary ' . escapeshellarg($json) . ' '. escapeshellarg(wsb_url_encode($url, $uri));
	exec($sh, $resp, $ret);

	// Was there an error
	if ($ret != 0) {
		return new Exception('HTTP Connection Error', $ret);
	}

	// Parse the results
	$value = json_decode($resp[0], true);
	if (json_last_error() != JSON_ERROR_NONE) {
		return new Exception('JSON Parse Error', 3);
	}
	if ($value['error'] || !isdef($value['data'])) {
		return new Exception('Server Error', 21);
	}

	// Successfully Updated
	return $value;
}

function wsb_value_get ($url, $uri) {

	// Make request
	$sh = 'curl --fail -s --header "Accept: application/json" ' . escapeshellarg(wsb_url_encode($url, $uri));
	exec($sh, $resp, $ret);

	if ($ret != 0) {
		return new Exception('HTTP Connection Error', $ret);
	}

	// Parse the results
	$value = json_decode($resp[0], true);
	if (json_last_error() != JSON_ERROR_NONE) {
		print $resp[0];
		return new Exception('JSON Parse Error', 3);
	}

	// Return the value
	return $value;
}

// This is a CLI Invokation
if (count(get_included_files()) == 1) {
ini_set("display_errors", "stderr");
if ($argc < 3 || ($argv[2] != 'get' && $argv[2] != 'set')) {
	error_log('Invalid Arguments!');
	error_log('Syntax: `'.$argv[0].' url set uri value [expire]`');
	error_log('Syntax: `' . $argv[0] . ' url get uri`');
	error_log('For more info use a url and set or get with no more arguments');
	exit(127);
}
if ($argv[2] == 'set') {
	if ($argc < 5 || $argc > 6) {
		error_log('Invalid Arguments!');
		error_log('Syntax: `'.$argv[0].' url set uri value [expire]`');
		error_log('Where url is the base data url such as http://localhost/.data/');
		error_log('Where uri is the uri specifying the object to set.');
		error_log('Where value is a valid JSON object to set.');
		error_log('Where, if specified, expire is an interger or 0.');
		exit(127);
	}
	if ($argc > 5) {
		$expire = $argv[5];
	} else {
		$expire = NULL;
	}

	// Get actual data and validate
	$value = json_decode($argv[4], true);
	if ($value == FALSE) {
		if ($argv[4] == 'null') {
			$value = NULL;
		} else {
			print "Assuming Value is a String\n";
			$value = $argv[4];
		}
	}

	if (substr($argv[3], -1) != '/' && is_array($value)) {
		error_log('Error: URI must end in / to send an object.');
		exit(126);
	}

	$ret = wsb_value_set($argv[1], $argv[3], $value, $expire);
	if ($ret instanceof Exception) {
		error_log('Error: ' . $ret->getMessage());
		exit($ret->getCode());
	}
	print JSON_encode($ret) . "\n";
	exit(0);
} else if ($argv[2] == 'get') {
	if ($argc > 4 || $argc < 3) {
		error_log('Invalid Arguments!');
		error_log('Syntax: `' . $argv[0] . ' url get [uri]`');
		error_log('Where url is the base data url such as http://localhost/.data/');
		error_log('Where uri, if specified, is the uri specifying the object to get.');
		exit(127);
	}
	if ($argc >= 4) {
		$uri = $argv[3];
	} else {
		$uri = '';
	}
	$ret = wsb_value_get($argv[1], $uri);
	if ($ret instanceof Exception) {
		error_log('Error: ' . $ret->getMessage());
		exit($ret->getCode());
	}
	if (is_array($ret) && strlen($uri) > 0 && substr($uri, -1) != '/') {
		error_log('Error: Attempted to retrieve primative but got object!');
		exit(126);
	}
	print JSON_encode($ret) . "\n";
	exit(0);
}
}
?>
