<?

# READ THIS

# before starting, you have to add a league record, containing the name, the rfm name, the group_id and the server name (and probably a description)
# example:
#	name: F1-75 League
# rfm_name: F1-75_LE.rfm
# server_name: Varjanta.com LEAGUE
# description: 6 Laps qualy, 1xTyre Wear, 1xFuel Usage, Black Flag, 50% Damage, 60min Race
# group_id: 30

# group_id is needed to integrate with the existing board data
# for example, look up the group in cf_groups and put in the id in the group_id field of the league record

# make sure drivers are member of the group, otherwise they will get kicked out of the final results

# edit this for your settings:
$link = mysql_connect("127.0.0.1", "andi", "bla") or die("no db connection: " . mysql_error());
mysql_select_db("varjanta_results") or die("could not select varjanta_results db");


$points = Array();
$points[1] = 25;
$points[2] = 20;
$points[3] = 16;
$points[4] = 13;
$points[5] = 11;
$points[6] = 10;
$points[7] = 9;
$points[8] = 8;
$points[9] = 7;
$points[10] = 6;
$points[11] = 5;
$points[12] = 4;
$points[13] = 3;
$points[14] = 2;
$points[15] = 1;


function mysql_get_list($query) {
  $res = mysql_query($query);
  if (!$res) {die("query failed: ".mysql_error());}
  $ret = Array();
  while($row = mysql_fetch_object($res)) {
    array_push($ret, $row);
  }
  return $ret;
}


function find_league_by_server_and_rfm($server, $rfm) {
  $league = null;
  $query = sprintf("SELECT * FROM leagues where server_name='%s' and rfm_name='%s' order by created_at desc limit 1",
    mysql_real_escape_string($server),
    mysql_real_escape_string($rfm));
  $res = mysql_query($query);
  if (mysql_num_rows($res) == 1) {
    $league = mysql_fetch_object($res);
  }
  return $league;
}

function check_existence_of_registered_driver($driver_name, $league) {
  $query = sprintf("SELECT cfu.*, p.pf_rfactorname as rfname 
      FROM cf_user_group cfug 
        join cf_users cfu on cfug.user_id=cfu.user_id 
        join leagues l on cfug.group_id=l.group_id 
        join cf_profile_fields_data p on p.user_id=cfu.user_id
      where p.pf_rfactorname='%s' and l.id=%d limit 1",
    mysql_real_escape_string($driver_name),
    $league->id);
  #echo "$query\n";
  $res = mysql_query($query);
  if (!$res) {die( "query failed: ".mysql_error());}
  if (mysql_num_rows($res) == 1) {
    $driver = mysql_fetch_object($res);
  }
  return $driver;
}

function create_race($track, $mod, $league, $race_time) {
  # check if this is a re-import
  # delete race + results first
  $query = sprintf("select id from league_races where track='%s' and rfm_name='%s' and league_id=%d and race_time='%s'",
    mysql_real_escape_string($track),
    mysql_real_escape_string($mod),
    $league['id'],
    mysql_real_escape_string($race_time));
  $res = mysql_query($query);
  if (!$res) {die( "query $query failed: ".mysql_error());}
  if (mysql_num_rows($res) > 0) {
    echo "re-import, deleteing old race + results\n";
    $row = mysql_fetch_object($res);
    $old_id = $row->id;
    $query = "delete from league_races where id=$old_id";
    $res = mysql_query($query);
    if (!$res) {die( "query failed: ".mysql_error());}
    $query = "delete from league_results where race_id=$old_id";
    $res = mysql_query($query);
    if (!$res) {die( "query failed: ".mysql_error());}
  }
  
  $query = sprintf("insert into league_races (track, rfm_name, league_id, race_time) values ('%s', '%s', %d, '%s')",
    mysql_real_escape_string($track),
    mysql_real_escape_string($mod),
    $league['id'],
    mysql_real_escape_string($race_time));
  $res = mysql_query($query);
  if (!$res) {die( "insert query failed: ".mysql_error());}
  $result_id = mysql_insert_id();
  return $result_id;
}

function create_result($race_id, $driver_id, $finish_time, $finish_status, $best_lap_time, $pitstops, $position, $grid_pos, $laps) {
  $query = sprintf("insert into league_results (`race_id`, `driver_id`, `race_time`, `finish_status`, `best_lap`, `pit_stops`, position, grid_position, laps, points) values (%d, %d, %f, '%s', %f, %d, %d, %d, %d, %d)",
      $race_id,
      $driver_id,
      $finish_time,
      mysql_real_escape_string($finish_status),
      $best_lap_time,
      intval($pitstops),
      intval($position),
      intval($grid_pos),
      intval($laps),
      0
      );
  #echo "$query\n";
  $res = mysql_query($query);
  if (!$res) {die( "insert query failed: ".mysql_error());}
  $result_id = mysql_insert_id();
  return $result_id;
}

function distribute_points($race_id) {
  global $points;
  $query = sprintf("select * from league_results where race_id=%d order and finish_status='Finished Normally' by position asc", $race_id);
  $results = mysql_get_list($query);
  $ct = 1;
  foreach ($results as $result) {
    $query = sprintf("update league_results set position=%d, points=%d where id=%d", $ct, intval($points[$ct]), $result->id);
    $res = mysql_query($query);
    if (!$res) {die( "update query $query failed: ".mysql_error());}
    $ct++;
  }
}

###############################################

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
  $xmlstr = trim(file_get_contents('php://input'));
  $xmlstr = iconv('UTF-8', 'UTF-8//IGNORE', $xmlstr);
  $xml = new SimpleXMLElement($xmlstr);
  
  $mod =  $xml->RaceResults->Mod;
  $server_name = $xml->RaceResults->ServerName;
  $league = find_league_by_server_and_rfm($server_name, $mod);
  if($league == null) {
    echo "league with server $server_name and mod $mod not found";
    exit;
  }
  
  # ok there seems to be a league record for us, onwards
  $race = $xml->RaceResults->Race;

  # check if the race was finished
  $race_laps = intval($xml->RaceResults->RaceLaps);
  $race_time = intval($xml->RaceResults->RaceTime);
  if($race_laps > 0) {
    if(intval($race->Laps) < $race_laps) {
      echo "race not finished (not enough laps)";
      exit;
    }
  } else {
    if(intval($race->Minutes) < $race_time) {
      echo "race not finished (did not last long enough)";
      exit;
    }
  }
  
  # ok we have a valid race, store it
  $track = $xml->RaceResults->TrackCourse;
  $race_time = $race->TimeString;
  
  #print_r($league);
  $race_id = create_race($track, $mod, $league->id, $race_time);
  
  # insert results
  foreach ($race->Driver as $driver_data) {
    $driver_name = $driver_data->Name;
    if ($driver = check_existence_of_registered_driver($driver_name, $league)) {
      echo "valid driver: $driver->rfname ($driver->username)\n";
      # create result for driver
      create_result($race_id, $driver->user_id, $driver_data->FinishTime, $driver_data->FinishStatus, $driver_data->BestLapTime, $driver_data->Pitstops, $driver_data->Position, $driver_data->GridPos, $driver_data->Laps);
    } else {
      echo "driver $driver_data->Name does not exist or is not registered for league $league->name\n";
    }
  }
  
  # distribute points
  distribute_points($race_id);
  
  // $res = $xml->xpath("/rFactorXML/RaceResults/Race");
  // if (sizeof($res) == 1) {
  //   $race = $res[0];
  //   $race_laps = $race->Laps;
  //   $most_laps = $race->MostLapsCompleted;
  //   if (intval($most_laps) < intval($race_laps)) {
  //     echo "race not finished";
  //     exit;
  //   }
  //   
  //   # find/create event
  //   $track = $xml->RaceResults->TrackCourse;
  //   $mod = $xml->RaceResults->Mod;
  //   $season_id = find_or_create_season($_GET["season"]);
  //   echo "season_id: ".$season_id." ".$_GET["season"]."\n";
  //   $event_id = find_or_create_event($season_id, $track, $mod);
  //   echo "event_id: ".$event_id."\n";
  //   $timestring = $race->TimeString;
  //   $laps = intval($race->Laps);
  //   echo "laps:";
  //   $race_id = create_race($event_id, $timestring, $laps);
  //   echo "race_id: ".$race_id."\n";
  //   foreach ($race->Driver as $driver) {
  //     $driver_id = find_or_create_driver($driver->Name);
  //     $result_id = create_result($driver_id, $race_id, $driver->BestLapTime, $driver->FinishTime, $driver->FinishStatus, $driver->Laps);
  //   }
  //   
  // }
}

?>
