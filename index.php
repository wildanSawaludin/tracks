<?php
session_start();

$loggedIn = false;

require_once("lib.php");

if (isset($_GET["logout"])) {
	$_SESSION = null;
	header("Location: .");
}

if (isset($_POST["secret"]) && $_POST["secret"] == SECRET) {
	$_SESSION["login"] = true;
}

$editTrackTags = "";
if (isset($_SESSION["login"]) && $_SESSION["login"]) {
	$loggedIn = true;
	$login = "[<a href=\"?logout\">logout</a>]";
	$editTrackTags = "[<a href=\"editTrackTags.php\">edit track tags</a>]";
} else {
	$login = <<<LOGIN
<form name="login" action="" method="post">
	<label for="secret">Login</label>
	<input type="password" name="secret" size=5>
	<input type="submit" value="login">
</form>
LOGIN;
}

if (!isset($_SESSION["trackFlagFilter"])) {
	$_SESSION["trackFlagFilter"] = array();
}

$filter = "";
if (isset($_POST["trackTags"])) {
	foreach ($_POST["trackTags"] as $id => $flag) {
		$filter[] = $id;
	}
	$_SESSION["trackFlagFilter"] = $filter;
} elseif (isset($_POST["changeFilter"])) {
	$_SESSION["trackFlagFilter"] = array();
}

if((count($_SESSION["trackFlagFilter"]) == 0)) {
	$req = db_query("select t.id as trackID, t.trackName as trackName, t.trackDescr as trackDescr, UNIX_TIMESTAMP(t.trackDate) as trackDate, GROUP_CONCAT(DISTINCT tt.trackTag ORDER BY tt.trackTag SEPARATOR ', ') as tags  FROM gps.tracks t JOIN gps.trackTagsLink ttl ON ttl.tracksID = t.id JOIN gps.trackTags tt ON tt.id = ttl.trackTagID GROUP BY t.id ORDER BY trackDate");
} elseif ((count($_SESSION["trackFlagFilter"]) == 1)) {
	$filters = implode (", ", $_SESSION["trackFlagFilter"]);
	$req = db_query("select t.id as trackID, t.trackName as trackName, t.trackDescr as trackDescr, UNIX_TIMESTAMP(t.trackDate) as trackDate, GROUP_CONCAT(DISTINCT tt.trackTag ORDER BY tt.trackTag SEPARATOR ', ') as tags  FROM gps.tracks t JOIN gps.trackTagsLink ttl ON ttl.tracksID = t.id JOIN gps.trackTags tt ON tt.id = ttl.trackTagID WHERE tt.id = $filters GROUP BY t.id ORDER BY trackDate");
} elseif (count($_SESSION["trackFlagFilter"]) >= 2) {
	$filters = implode (", ", $_SESSION["trackFlagFilter"]);
	$req = db_query("select t.id as trackID, t.trackName as trackName, t.trackDescr as trackDescr, UNIX_TIMESTAMP(t.trackDate) as trackDate, GROUP_CONCAT(DISTINCT tt.trackTag ORDER BY tt.trackTag SEPARATOR ', ') as tags  FROM gps.tracks t JOIN gps.trackTagsLink ttl ON ttl.tracksID = t.id JOIN gps.trackTags tt ON tt.id = ttl.trackTagID WHERE tt.id IN ($filters) GROUP BY t.id HAVING COUNT(DISTINCT tt.id) = 2 ORDER BY trackDate");
}

if (mysql_num_rows($req) == 0) {
	$tracks = "<h2>No matches...</h2>";
} else {
	$tracks = <<< TRACKS
<form action="map.php" method="post">
	<table>
		<tr>
			<th>Map</th>
			<th>Date</th>
			<th>Trail</th>
			<th>Tags</th>
			<th>Edit</th>
		</tr>
TRACKS;
	while ($row = mysql_fetch_assoc($req)) {
		$trackID = $row["trackID"];
		$trackName = $row["trackName"];
		$trackDate = date("c", $row["trackDate"]);
		$trackDescr = $row["trackDescr"];
		$tags = $row["tags"];
		$edit = "";
		if ($loggedIn) {
			$edit = "[<a href=\"editTrack.php?trackID=$trackID\">edit</a>]";
		} 
		$tracks .= <<< TRACKS
		<tr>
			<td><input name="tracks[$trackID]" type="checkbox"></td>
			<td>$trackDate</td>
			<td>$trackName</a></td>
			<td>$tags</td>
			<td>$edit</td>
		</tr>
TRACKS;
	}
	$tracks .= <<< TRACKS
	</table>
	<input type="submit" value="map!">
</form>
TRACKS;
}

$trackTags = <<<HTML
<form name="filterByTrackTag" action="" method="post">
	<input type="hidden" name="changeFilter">
	<ul>
HTML;


$req = db_query("SELECT id, trackTag FROM gps.trackTags");
while ($row = mysql_fetch_assoc($req)) {
	$trackTag = $row["trackTag"];
	$id = $row["id"];
	$checked = "";
	if (in_array($id, $_SESSION["trackFlagFilter"])) {
		$checked = "CHECKED";
	}
	$trackTags .= <<< HTML
<li><input type="checkbox" name="trackTags[$id]" $checked>$trackTag</li>
HTML;
}

$trackTags .= "</ul><input type=\"submit\" value=\"filter\"></form>";


echo <<<CONTENT
<!DOCTYPE html>
<html>
	<head>
		<meta name="viewport" content="initial-scale=1.0, user-scalable=no" />
		<meta http-equiv="content-type" content="text/html; charset=UTF-8"/>
		<title>Hikes and things!</title>
		<link href="default.css" rel="stylesheet" type="text/css">
		$analytics
	</head>
	<body>
		<div>$login $editTrackTags</div>
		<h2>Tracks</h2>
		<h3>Check the box next to each map you would like to see, then click the "map!" button.</h3>
		$tracks
		<hr>
		<h2>Filter tracks by tags</h2>
		
		$trackTags
		<hr>
		<h2>Upload a new gpx track</h2>
		<form name="importFile" enctype="multipart/form-data" action="importGPX.php" method="POST">
			<input type="hidden" name="MAX_FILE_SIZE" value="10485760" />
			<input name="userfile" type="file" /><br>
			<input type="submit" value="Send File" />
		</form>
	</body>
</html>
CONTENT;
?>
