<?php
/**
 * Robert McDermot
 * Date: 1/28/14
 */

//according to spec, schedule.txt must exist and will be in correct form
file_exists("schedule.txt") or die("ERROR: schedule.txt is not present or cannot be read.  Script cannot continue.");

date_default_timezone_set("America/New_York");

if(isset($_POST["action"]))
	$action = $_POST["action"];
else
	$action = "none";

//check for existing cookie containing usernames
if(isset($_COOKIE["usernames"]))
	$usernames = unserialize($_COOKIE["usernames"]);
else
	$usernames = [];

if($action == "newUser")
{
	$newUsername = $_POST["username"];
	if($newUsername == "")
		$newUsername = "[blank]";
	//if we just entered a new user, add to the array
	array_push($usernames,$newUsername);
	//then put the array back into a cookie
	setcookie("usernames",serialize($usernames),time()+(365 * 24 * 60 * 60));
	if((file_exists("users.txt") && substr(file_get_contents("users.txt"),-1) == "\n") || !file_exists("users.txt"))
		$userString = $newUsername."^";
	else
		$userString = "\n".$newUsername."^";
	foreach($_POST as $key => $value)
	{
		if(strpos($key, "check") !== FALSE)
			$userString .= str_replace('check','',$key)."|";
	}
	$userString = substr($userString,0,-1);
	$handle = fopen("users.txt","a");
	flock($handle,LOCK_EX);
	fwrite($handle,$userString);
	flock($handle,LOCK_UN);
	fclose($handle);
}

if(strpos($action,"editUser") !== FALSE)
{
	$newUsername = $_POST["username"];
	if($newUsername == "")
		$newUsername = "[blank]";
	//replace username in cookie
	$oldName = explode("-",$action)[1];
	foreach($usernames as $key => $name)
	{
		if($name == $oldName)
			$usernames[$key] = $newUsername;
	}
	setcookie("usernames",serialize($usernames),time()+(365 * 24 * 60 * 60));

	//replace user info in file
	$newFileContents = "";
	$handle = fopen("users.txt","r");
	flock($handle,LOCK_EX);
	while(($line = fgets($handle)) !== FALSE)
	{
		if(strpos($line, $oldName) !== FALSE)
		{
			$userString = $newUsername."^";
			foreach($_POST as $key => $value)
			{
				if(strpos($key, "check") !== FALSE)
					$userString .= str_replace('check','',$key)."|";
			}
			$userString = substr($userString,0,-1);
			$newFileContents .= $userString."\n";
		}
		else
			$newFileContents .= $line;
	}
	$handle = fopen("users.txt","w");
	//remove newline if it is the last character
	fwrite($handle,trim($newFileContents));
	flock($handle,LOCK_UN);
	fclose($handle);
}
?>
<!DOCTYPE html>
<html>
<head>
	<title>Schedule Decider</title>

	<link type="text/css" rel="stylesheet" href="style.css">
</head>
<body>
<table border="1"">
<tr>
	<th>User</th>
	<th>Action</th>
	<?php
	//read in the schedule file and create the table headers
	$handle = fopen("schedule.txt","r");
	$numColumns = 0;
	$formattedDates = [];
	while(($line = fgets($handle)) !== false)
	{
		$date_times = explode("^",$line);
		$times = explode("|",$date_times[1]);
		foreach($times as $time)
		{
			$totals[$numColumns] = 0;
			//combine each time with the associated day and trim the newline character from the last time
			$date = DateTime::createFromFormat("Y-m-d H:i",$date_times[0] . " " . trim($time));
			$dateFormatted = $date->format("l"."<b\\r/>"."m/d/y"."<b\\r/>"."h:iA");
			$formattedDates[$numColumns] = $dateFormatted;
			echo("<th>$dateFormatted</th>");
			$numColumns++;
		}
	}
	fclose($handle);
	?>
</tr>
<?php
if(file_exists("users.txt"))
{
	//read in the users and fill in the table rows
	$handle = fopen("users.txt","r");
	while(($line = fgets($handle)) !== false)
	{
		echo("<tr>");
		$name_columns = explode("^",$line);
		if(strpos($action, "edit-") !== FALSE && $name_columns[0] == explode("-",$action)[1])
		{
			$name = $name_columns[0];
			echo("<td><form method='post' action='index.php'><input type='text' value='$name' name='username'></td>");
		}
		else
			echo("<td>$name_columns[0]</td>");

		if(strpos($action, "edit-") !== FALSE && $name_columns[0] == explode("-",$action)[1])
		{
			$value = "editUser-$name_columns[0]";
			echo("<td><form method='post' action='index.php'><input type='submit' value='submit'><input type='text' value='$value' hidden='true' name='action'></td>");
		}
		else if(in_array($name_columns[0],$usernames))
		{
			$editValue = "edit-$name_columns[0]";
			echo("<td><form method='post' action='index.php'><input type='submit' value='edit'><input type='text' value='$editValue' hidden='true' name='action'></form></td>");
		}
		else
			echo("<td></td>");

		if(count($name_columns) == 2)
		{
			if(strlen($name_columns[1]) == 0) //no times selected
				$columns = [];
			else
				$columns = explode("|",$name_columns[1]);
		}
		else
			$columns = [];

		for($i=0; $i<$numColumns; $i++)
		{
			if(in_array($i,$columns))
			{
				if(strpos($action, "edit-") !== FALSE && $name_columns[0] == explode("-",$action)[1])
				{
					$name = "check$i";
					echo("<td><input type='checkbox' checked='true' name=$name></td>");
				}
				else
					echo("<td>&#10003;</td>");
				if(isset($totals[$i]))
					$totals[$i]++;
				else
					$totals[$i] = 1;
			}
			else
			{
				if(strpos($action, "edit-") !== FALSE && $name_columns[0] == explode("-",$action)[1])
				{
					$name = "check$i";
					echo("<td><input type='checkbox' name=$name></td>");
				}
				else
					echo("<td></td>");
				if(!isset($totals[$i]))
					$totals[$i] = 0;
			}
		}
		if(strpos($action, "edit-") !== FALSE)
			echo("</form>");
		echo("</tr>");
	}
	fclose($handle);
}

if($action != "new")
{
	echo("<td></td>");
	echo("<td><form method='post' action='index.php'><input type='submit' value='new'><input type='text' value='new' hidden='true' name='action'></form></td>");
	foreach($totals as $i)
	{
		echo("<td></td>");
	}
}
else
{
	echo("<td><form method='post' action='index.php'><input type='text' name='username'><input type='text' value='newUser' hidden='true' name='action'></td>");
	echo("<td><input type='submit' value='submit'></td>");
	for($i=0; $i<count($totals); $i++)
	{
		$name = "check$i";
		echo("<td><input type='checkbox' name='$name'></td>");
	}
	echo("</form>");
}

echo("<tr><td><b>Totals</b></td><td></td>");
$highest = 0;
$highestIndex = -1;
foreach($totals as $key => $i)
{
	if($i > $highest)
	{
		$highest = $i;
		$highestIndex = $key;
	}
	echo("<td>$i</td>");
}
?>
</table>
<?php
if($highestIndex != -1)
{
	$answer = preg_replace('#<[^>]+>#', ' ',$formattedDates[$highestIndex]);
	echo("<div id='Answer'>You should meet on <b>$answer</b>.</div>");
}
else
	echo("<div id='Answer'>Sorry, there are no good times for you to meet.</div>");
?>
</body>
</html>