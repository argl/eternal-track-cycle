<!DOCTYPE html>
<?


$link = mysql_connect("127.0.0.1", "USERNAME", "PASSWORD") or die("no db connection: " . mysql_error());
mysql_select_db("varjanta_results") or die("could not select carousel db");

function format_time($secs) {
  $secs = floatval($secs);
  if ($secs > 0.0) {
    $usecs = $secs - floor($secs);
    $hours = floor($secs / (60.0*60.0));
    $rest = $secs % (60.0*60.0);
    $minutes = floor($rest / (60.0));
    $rest = $rest % (60.0);
    $seconds = floor($rest);
    $hours = $hours == 0.0 ? "" : sprintf("%02d:", $hours);
    $usecs = preg_replace('/^0\./', "", sprintf("%.3f", $usecs));
    return sprintf("%s%02d:%02d.%s", $hours, $minutes, $seconds, $usecs);
  } else {
    return "-";
  }
}

function mysql_get_list($query) {
  $res = mysql_query($query);
  if (!$res) {die("query failed: ".mysql_error());}
  $ret = Array();
  while($row = mysql_fetch_object($res)) {
    array_push($ret, $row);
  }
  return $ret;
}

function get_latest_league() {
  $query = sprintf("select * from leagues order by created_at desc limit 1");
  $res = mysql_query($query);
  if (!$res || mysql_num_rows($res) == 0) {die( "no league in db");}
  $row = mysql_fetch_object($res);
  return $row;
}

function get_league($league_id) {
  $query = sprintf("select * from leagues where id=%d",
    intval($league_id));
  $res = mysql_query($query);
  if (!$res || mysql_num_rows($res) == 0) {die( "no league");}
  $row = mysql_fetch_object($res);
  return $row;
}

function get_result_for_race_and_driver($race_id, $driver_id) {
  $query = sprintf("select * from league_results where driver_id=%d and race_id=%d",
    intval($driver_id), 
    intval($race_id));
  $res = mysql_query($query);
  if (!$res) {die( "query failed: ".mysql_error());}
  $row = mysql_fetch_object($res);
  return $row;
}

############

# find either a given league (by query param league_id)
# or find the latest league (by created_At)
$league_id = intval($_GET['league_id']);
if ($league_id == 0) {
  $league = get_latest_league();
} else {
  $league = get_league($league_id);
}

$all_leagues = mysql_get_list("select * from leagues order by created_at desc");

$races = mysql_get_list("select * from league_races where league_id=".$league->id." order by race_time asc");

# get the current charts
$billboard = mysql_get_list("
select 
	d.user_id, d.username, sum(rs.points) as sum_points, count(rs.id) as num_races
from 
	cf_users d
	join league_results rs on d.user_id=rs.driver_id
	join league_races r on rs.race_id=r.id
where
	r.league_id=$league->id
group by
	d.user_id
order by 
	sum_points desc");


?>
<html>
<head>
  <title>League Results</title>
  <link rel="stylesheet" href="css/blueprint/screen.css" type="text/css" media="screen, projection">
  <link rel="stylesheet" href="css/blueprint/print.css" type="text/css" media="print"> 
  <!--[if lt IE 8]>
    <link rel="stylesheet" href="css/blueprint/ie.css" type="text/css" media="screen, projection">
  <![endif]-->
  <style type="text/css" media="screen">
    table.results {
      border-width: 0px;
      border-style: none;
      border-color: gray;
      border-collapse: collapse;
      background-color: white;
    }
    table.results th {
      border-width: 2px;
      padding: 2px;
      border-style: solid;
      border-color: white;
      background-color: #fd6;
    }
    table.results td {
      border-width: 2px;
      padding: 2px;
      border-style: solid;
      border-color: white;
      background-color: #fd6;
      vertical-align: top;
    }
    table.results tbody td.right {
      text-align: right;
    }
    table.results thead td {
      text-align: center;
    }
    ul {
      list-style-type: square;
      padding: 0;
    }
  </style>
  <script type="text/javascript" charset="utf-8" src="javascripts/jquery.js"></script>
</head>
<body>
  <div class="container">
    <div class="span-24 last">
      <div class="span-10">
        <h1>League Stats</h1>
        <h2>
          <?= $league->name ?>
        </h2>
      </div>
    </div>
    
    <div class="span-4">
        <ul>
          <? foreach ($all_leagues as $l) { ?>
            <li><a href="?league_id=<?= $l->id ?>"><?= $l->name ?></a></li>
          <? } ?>
        </ul>
    </div>
    
    <div class="span-20 last">
        <h2 class="">League: <?= $league->name ?></h2>
        <h3 class="">Description: <?= $league->description ?></h3> 
        <h3 class="">Mod: <?= $league->rfm_name ?></h3> 

        <table class="results">
          <thead>
            <tr>
              <td>Pos</td>
              <td>Driver</td>
              <? foreach ($races as $race) { ?>
                <td><?= $race->track ?></td>
              <? } ?>
              <td class="loud"><b>Total</b></td>
            </tr>
          </thead>
          <tbody>
            <? $pos = 1 ?>
            <? foreach ($billboard as $bb) { ?>
              <tr>
                <td><?= $pos ?></td>
                <td><?= $bb->username ?></td>
                <? foreach ($races as $race) { ?>
                  <? $result = get_result_for_race_and_driver($race->id, $bb->user_id) ?>
                  <td class="right">
                    <? if($result) { ?>
                      <b><?= $result->points ?></b> <br />
                      <span class="small">Position: <?= $result->position ?></span>
                      <span class="small">Laps: <?= $result->laps ?></span><br />
                      <span class="small">Grid Position: <?= $result->grid_position ?></span>
                      <span class="small">Pit stops: <?= $result->pit_stops ?></span><br />
                      <span class="small">Best lap: <?= format_time($result->best_lap) ?></span>
                      <span class="small">Status: <?= $result->finish_status  ?></span>
                    <? } else { ?>
                      -
                    <? } ?>
                  </td>
                <? } ?>
                <td class="loud right"><b><?= $bb->sum_points ?></b></td>
                <? $pos++ ?>
              </tr>
            <? } ?>
          </tbody>
        </table>
    </div>
    

    <div class="span-24 last">
        The footer
    </div>
  </div>
</body>
</html>