<?php
include_once 'psl-config.php';

error_reporting(E_ALL);
ini_set('display_errors', TRUE);
ini_set('display_startup_errors', TRUE);

function sec_session_start() {
    $session_name = 'SC_secure';   // Set a custom session name
    $secure = SECURE;
    // This stops JavaScript being able to access the session id.
    $httponly = true;
    // Forces sessions to only use cookies.
    if (ini_set('session.use_only_cookies', 1) === FALSE) {
       	header("Location: ../error.php?err=Could not initiate a safe session (ini_set)");
    	exit();
    }
    // Gets current cookies params.
    $cookieParams = session_get_cookie_params();
    session_set_cookie_params($cookieParams["lifetime"], $cookieParams["path"], $cookieParams["domain"], $secure, $httponly);
    // Sets the session name to the one set above.
    session_name($session_name);
    session_start();            // Start the PHP session
    session_regenerate_id(true);    // regenerated the session, delete the old one.
}

function login($username, $password, $userType, $mysqli) {
    // Using prepared statements means that SQL injection is not possible. 
    if ($userType == "player") {
	$user = "puname";
    } else {
	$user = "cuname";
    }

    if ($stmt = $mysqli->prepare("SELECT " . $user . ", pw FROM " . $userType . " WHERE " . $user . "  = ? LIMIT 1")) {
        $stmt->bind_param('s', $username);  // Bind "$username" to parameter.
        $stmt->execute();    // Execute the prepared query.
        $stmt->store_result();
 
        // get variables from result.
        $stmt->bind_result($db_username, $db_password);
        $stmt->fetch();

        if ($stmt->num_rows == 1) {
            // If user exists, check if the password in the database 
            // matches the password the user submitted.
            if ($db_password == $password) {
                // Password is correct!
                // Get the user-agent string of the user.
                $user_browser = $_SERVER['HTTP_USER_AGENT'];
                // XSS protection as we might print this value
                $username = preg_replace("/[^a-zA-Z0-9_\-]+/", "", $username);
                $_SESSION['username'] = $username;
                $_SESSION['login_string'] = hash('sha512', $password . $user_browser);
		$_SESSION['usertype'] = $userType;
                // Login successful.
                return true;
            } else {
                // Password is not correct
                // We record this attempt in the database
                $now = time();
                $mysqli->query("INSERT INTO login_attempts(user_id, time) VALUES ('111', '$now')");
                return false;
            }
        } else {
            // No user exists.
            return false;
        }
    }
}

	function checkbrute($user_id, $mysqli) {
		// Get timestamp of current time
		$now = time();

		// All login attempts are counted from the past 2 hours.
		$valid_attempts = $now - (2 * 60 * 60);

		if ($stmt = $mysqli->prepare("SELECT time FROM login_attempts WHERE user_id = ? AND time > '$valid_attempts'")) {
			$stmt->bind_param('i', $user_id);

			// Execute the prepared query.
			$stmt->execute();
			$stmt->store_result();

			// If there have been more than 5 failed logins
			if ($stmt->num_rows > 5) {
				return true;
			} else {
				return false;
			}
		}
	}

	function login_check($mysqli) {


		// Check if all session variables are set
		if (isset($_SESSION['username'], $_SESSION['login_string'])) {
			return true;
			$login_string = $_SESSION['login_string'];
			$username = $_SESSION['username'];

			// Get the user-agent string of the user.
			$user_browser = $_SERVER['HTTP_USER_AGENT'];

			if ($stmt = $mysqli->prepare("SELECT pw FROM player WHERE puname = ? LIMIT 1")) {
				// Bind "$user_id" to parameter.
				$stmt->bind_param('i', $username);
				$stmt->execute();   // Execute the prepared query.
				$stmt->store_result();

				if ($stmt->num_rows == 1) {
					// If the user exists get variables from result.
					$stmt->bind_result($password);
					$stmt->fetch();
					$login_check = hash('sha512', $password . $user_browser);

					if ($login_check == $login_string) {
						// Logged In!!!!
						return true;
					} else {
						// Not logged in
						return false;
					}
				} else {
					// Not logged in
					return false;
				}
			} else {
				// Not logged in 
				return false;
			}
		} else {
			// Not logged in 
			return false;
		}
	}

	function esc_url($url) {
		if ('' == $url) {
			return $url;
		}

		$url = preg_replace('|[^a-z0-9-~+_.?#=!&;,/:%@$\|*\'()\\x80-\\xff]|i', '', $url);

		$strip = array('%0d', '%0a', '%0D', '%0A');
		$url = (string) $url;

		$count = 1;
		while ($count) {
			$url = str_replace($strip, '', $url, $count);
		}

		$url = str_replace(';//', '://', $url);

		$url = htmlentities($url);

		$url = str_replace('&amp;', '&#038;', $url);
		$url = str_replace("'", '&#039;', $url);

		if ($url[0] !== '/') {
			// We're only interested in relative links from $_SERVER['PHP_SELF']
			return '';
		} else {
			return $url;
		}
	}

	function getCoach($username, $mysqli) {
	    if ($stmt = $mysqli->prepare("SELECT cuname FROM player WHERE puname  = ? LIMIT 1")) {
		$stmt->bind_param('s', $username);  // Bind "$username" to parameter.
		$stmt->execute();    // Execute the prepared query.
		$stmt->store_result();

		// get variables from result.
		$stmt->bind_result($coach);
		$stmt->fetch();

		if ($coach ==  "" || $coach == "No Coach")
		    return "No Coach Set";
		 else
		    return $coach;
	    }
	    else
		return "Failed to connect to server";
	}

	function displayMyPlayers($mysqli) {
	    $username = $_SESSION['username'];
	    $query = "SELECT puname FROM player WHERE cuname= ?";

	    if ($stmt = $mysqli->prepare($query)) {
		$stmt->bind_param('s', $username);  // Bind "$username" to parameter.
		$stmt->execute();    // Execute the prepared query.
		$stmt->store_result();

		// get variables from result.
		$stmt->bind_result($player);
		$result = "<table><tr><td><b><u>Player Username:</b></u></td></tr>";
		while ($stmt->fetch()) {
		    $result = $result . "<tr><td><a href='player.php?player=" . $player . "'>" . $player . "</a></td></tr>"; 
		}
		$result = $result . "</table>";
	        return $result;
	    }
	}

	function getMyRoutines($mysqli) {
	    $username = $_SESSION['username'];
	    $usertype = $_SESSION['usertype'];
	    if ($usertype == "coach")
		$usertype = "C";
	    else
		$usertype = "P";
	    $query = "SELECT rname, type FROM routine WHERE username= ? and usertype= ?";

	    if ($stmt = $mysqli->prepare($query)) {
		$stmt->bind_param('ss', $username, $usertype);  // Bind "$username" and "$usertype" to parameters
		$stmt->execute();    // Execute the prepared query.
		$stmt->store_result();

		// get variables from result.
		$stmt->bind_result($routine, $type);
		$result = "";
		while ($stmt->fetch()) {
		    if ($type == "C")
			$type = "Chase";
		    elseif ($type == "H")
			$type = "Higher 2 Rows";
		    elseif ($type == "L")
			$type = "Lowest Row";
		   else
			$type = "Unknown";
		    $result = $result . "<tr><td>" . $routine . "</td><td>" . $type . "</td></tr>"; 
		}

	        return $result;
	    }
	}

	function saveRoutine($rname, $desc, $diff, $rnds, $tmr, $tmb, $type, $grnd, $mysqli) {
    	    $username = $_SESSION['username'];
	    $usertype = $_SESSION['usertype'];
	    $lock = "0";

	    if ($usertype == "coach")
		$usertype = "C";
	    else
		$usertype = "P";

	    $query = "INSERT INTO routine(`rname`, `descr`, `difficulty`, `rounds`, `timer`, `timebased`, `type`, `lock`, `username`, `usertype`, 'ground') VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

	    $stmt = $mysqli->prepare($query);
	    if ($stmt) {
		$stmt->bind_param('ssssssssss', $rname, $desc, $diff, $rnds, $tmr, $tmb, $type, $lock, $username, $usertype);  // Bind Vars to params
		$stmt->execute();    // Execute the prepared query.

	        return true;
	    } else
		return false;
	}

	function checkPrivacyStatus($user, $mysqli) {
	    // Check if player's privacy is set to public
	    $query = "SELECT privacy FROM player WHERE puname= ? LIMIT 1";
	    if ($p_stmt = $mysqli->prepare($query)) {
		$p_stmt->bind_param('s', $user);
		$p_stmt->execute();
		$p_stmt->bind_result($privacy);
        	$p_stmt->fetch();
		if ($privacy == 1) {
		    // Set to public
		    return true;
		} else {
		    // Set to private
		    return false;
		}
	    } else {
		//cannot connect
		return false;
	    }
	}

	function playerInfo($user, $mysqli) {
	    // Check if player's privacy is set to public
	    $query = "SELECT cuname, fname, lname, dbirth, pos FROM player WHERE puname= ? LIMIT 1";
	    if ($p_stmt = $mysqli->prepare($query)) {
		$p_stmt->bind_param('s', $user);
		$p_stmt->execute();
		$p_stmt->bind_result($coach, $fname, $lname, $dob, $pos);
        	$p_stmt->fetch();
		$ret = "";
		if ($coach == "") {
		    $coach == "Not Set";
		}
		$ret = $ret . "<tr><td>Coach:</td><td>" . $coach . "</td></tr>";
		if ($fname == "") {
		    $fname == "Not Set";
		}
		$ret = $ret . "<tr><td>First Name:</td><td>" . $fname . "</td></tr>";
		if ($lname == "") {
		    $lname == "Not Set";
		}
		$ret = $ret . "<tr><td>Last Name:</td><td>" . $lname . "</td></tr>";
		if ($dob == "") {
		    $dob == "Not Set";
		}
		$ret = $ret . "<tr><td>Birthday:</td><td>" . $dob . "</td></tr>";
		if ($pos == "") {
		    $pos == "Not Set";
		}
		$ret = $ret . "<tr><td>Position:</td><td>" . $pos . "</td></tr>";
		return $ret;
	    } else {
		//cannot connect
		return "Error Connecting to Server";
	    }
	}


	function getAccuracyChart($username, $mysqli) {
	    $query = "SELECT dateTime, shotsOnTarget, numShots FROM statistic WHERE puname= ?";
	    if ($stmt = $mysqli->prepare($query)) {
		$stmt->bind_param('s', $username);
		$stmt->execute();
		$stmt->bind_result($date, $shotsOT, $shots);
		$data = "[";
		while ($stmt->fetch()) {
		    if ($data != "[")
			$data = $data . ", ";
		    $yr = substr($date, 0, 4);
		    $mn = intval(substr($date, 5,2)) - 1;
		    $dy = substr($date, 8, 2);
		    $hr = substr($date, 11, 2);
		    $mt = substr($date, 14, 2);
		    $date = $yr . ", " . $mn . ", " . $dy . ", " . $hr . ", " . $mt;
		    $data = $data . "[new Date(" . $date . "), " . (intval($shotsOT)/intval($shots)*100) . "]"; //[new Date(yyyy, mm 0-11 , d), 5]
		}

		$data = $data . "]";

		return $data;
	    }
	    return "error";
	}

	function getForceChart($username, $mysqli) {
	    $query = "SELECT dateTime, frce FROM statistic WHERE puname= ?";
	    if ($stmt = $mysqli->prepare($query)) {
		$stmt->bind_param('s', $username);
		$stmt->execute();
		$stmt->bind_result($date, $force);
		$data = "[";
		while ($stmt->fetch()) {
		    if ($data != "[")
			$data = $data . ", ";
		    $yr = substr($date, 0, 4);
		    $mn = intval(substr($date, 5,2)) - 1;
		    $dy = substr($date, 8, 2);
		    $hr = substr($date, 11, 2);
		    $mt = substr($date, 14, 2);
		    $date = $yr . ", " . $mn . ", " . $dy . ", " . $hr . ", " . $mt;
		    $data = $data . "[new Date(" . $date . "), " . $force . "]";
		}

		$data = $data . "]";

		return $data;
	    }
	    return "error";
	}
