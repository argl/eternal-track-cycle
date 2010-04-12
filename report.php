<?

$link = mysql_connect("127.0.0.1", "USERNAME", "PASSWORD") or die("no db connection: " . mysql_error());

mysql_select_db("carousel") or die("could not select carousel db");

function find_or_create_event($season_id, $track, $mod) {
  $query = sprintf("SELECT * FROM events where `track`='%s' and `mod`='%s'",
    mysql_real_escape_string($track),
    mysql_real_escape_string($mod));
  $res = mysql_query($query);
  if (mysql_num_rows ($res) < 1) {
    # create event
    $query = sprintf("insert into events (`track`, `mod`, `season_id`) values ('%s', '%s', %d)",
      mysql_real_escape_string($track),
      mysql_real_escape_string($mod),
      intval($season_id));
    $res2 = mysql_query($query);
    if (!$res2) {die("insert query failed: ".mysql_error());}
    $event_id =  mysql_insert_id();
  } else {
    $row = mysql_fetch_object($res);
    $event_id = $row->id;
  }
  return $event_id;
}

function find_or_create_season($name) {
  $query = sprintf("SELECT * FROM seasons where `name`='%s'",
    mysql_real_escape_string($name));
  $res = mysql_query($query);
  if (mysql_num_rows ($res) < 1) {
    # create season
    echo "creating season ".$name."\n";
    $query = sprintf("insert into seasons (`name`) values ('%s')",
      mysql_real_escape_string($name));
    $res2 = mysql_query($query);
    if (!$res2) {die("insert query failed: ".mysql_error());}
    $season_id =  mysql_insert_id();
  } else {
    $row = mysql_fetch_object($res);
    $season_id = $row->id;
  }
  return $season_id;
}

function find_or_create_driver($driver) {
  $query = sprintf("SELECT * FROM drivers where `name`='%s'",
    mysql_real_escape_string($driver));
  $res = mysql_query($query);
  if (mysql_num_rows ($res) < 1) {
    # create driver
    $query = sprintf("insert into drivers (`name`) values ('%s')",
      mysql_real_escape_string($driver));
    $res2 = mysql_query($query);
    if (!$res2) {die("insert query failed: ".mysql_error());}
    $driver_id =  mysql_insert_id();
  } else {
    $row = mysql_fetch_object($res);
    $driver_id = $row->id;
  }
  return $driver_id;
}

function create_race($event_id, $timestring, $laps) {
  $query = sprintf("SELECT * FROM races where `event_id`=%d and `race_time`='%s'",
    $event_id,
    mysql_real_escape_string($timestring));
  $res = mysql_query($query);
  if (mysql_num_rows ($res) > 0) {
    $row = mysql_fetch_object($res);
    echo "re-importing race\n";
    $query = sprintf("delete FROM results where `race_id`=%d", $row->id);
    mysql_query($query);
    return $row->id;
  }
  $query = sprintf("insert into races (`event_id`, `race_time`, `laps`) values (%d, '%s', %d)",
    $event_id,
    mysql_real_escape_string($timestring));
  $res2 = mysql_query($query);
  if (!$res2) {die( "insert query failed: ".mysql_error());}
  $race_id = mysql_insert_id();
  return $race_id;
}

function create_result($driver_id, $race_id, $best_lap, $race_time, $finish_status, $laps_completed) {
  $query = sprintf("insert into results (`race_id`, `driver_id`, `race_time`, `finish_status`, `best_lap`, `laps_completed`) values (%d, %d, %f, '%s', %f, %d)",
    $race_id,
    $driver_id,
    $race_time,
    mysql_real_escape_string($finish_status),
    $best_lap,
    intval($laps_completed));
  $res = mysql_query($query);
  if (!$res) {die( "insert query failed: ".mysql_error());}
  $result_id = mysql_insert_id();
  return $result_id;
}

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
  $xmlstr = trim(file_get_contents('php://input'));
  $xmlstr = iconv('UTF-8', 'UTF-8//IGNORE', $xmlstr);
  $xml = new SimpleXMLElement($xmlstr);
  $res = $xml->xpath("/rFactorXML/RaceResults/Race");
  if (sizeof($res) == 1) {
    $race = $res[0];
    $race_laps = $race->Laps;
    $most_laps = $race->MostLapsCompleted;
    if (intval($most_laps) < intval($race_laps)) {
      echo "race not finished";
      exit;
    }
    # find/create event
    $track = $xml->RaceResults->TrackCourse;
    $mod = $xml->RaceResults->Mod;
    $season_id = find_or_create_season($_GET["season"]);
    echo "season_id: ".$season_id." ".$_GET["season"]."\n";
    $event_id = find_or_create_event($season_id, $track, $mod);
    echo "event_id: ".$event_id."\n";
    $timestring = $race->TimeString;
    $laps = intval($race->Laps);
    echo "laps:";
    $race_id = create_race($event_id, $timestring, $laps);
    echo "race_id: ".$race_id."\n";
    foreach ($race->Driver as $driver) {
      $driver_id = find_or_create_driver($driver->Name);
      $result_id = create_result($driver_id, $race_id, $driver->BestLapTime, $driver->FinishTime, $driver->FinishStatus, $driver->Laps);
    }
    
  }
}

?>
