<!DOCTYPE html>
<?


$link = mysql_connect("127.0.0.1", "USERNAME", "PASSWORD") or die("no db connection: " . mysql_error());
mysql_select_db("carousel") or die("could not select carousel db");

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

$seasons = mysql_get_list("select * from seasons order by id desc");
$season = $seasons[0];
$events = mysql_get_list("select * from events where season_id=".$season->id);
$drivers = mysql_get_list("
  select distinct d.*
  from races r
    join events e on r.event_id = e.id 
    join results rs on r.id=rs.race_id 
    join drivers d on rs.driver_id = d.id
  where 
    e.season_id=3
    and rs.finish_status = 'Finished Normally'");

$stats = Array();
foreach ($events as $event) {
  # get races for the event
  $races = mysql_get_list("select * from races where event_id=".$event->id);
  foreach ($races as $race) {
    $results = mysql_get_list("select * from results where finish_status='Finished Normally' and race_id=".$race->id);
    foreach($results as $result) {
      $driver = mysql_get_list("select * from drivers where id=".$result->driver_id);
      $driver = $driver[0];
      if (!$stats[$driver->name]) {
        $stats[$driver->name] = Array();
      }
      if ($result->laps_completed >= $race->laps) {
        if ($stats[$driver->name][$event->id]) {
          if ($stats[$driver->name][$event->id]['race_time'] > $result->race_time) {
            $stats[$driver->name][$event->id]['result_id'] = $result->id;
            $stats[$driver->name][$event->id]['race_time'] = $result->race_time;
            $stats[$driver->name][$event->id]['race_date'] = $race->race_time;
          }
          if ($stats[$driver->name][$event->id]['best_lap'] > $result->best_lap) {
            $stats[$driver->name][$event->id]['best_lap'] = $result->best_lap;
            $stats[$driver->name][$event->id]['best_date'] = $race->race_time;
          }
        } else {
          $stats[$driver->name][$event->id] = Array();
          $stats[$driver->name][$event->id]['race_time'] = $result->race_time;
          $stats[$driver->name][$event->id]['result_id'] = $result->id;
          $stats[$driver->name][$event->id]['best_lap'] = $result->best_lap;
          $stats[$driver->name][$event->id]['race_date'] = $race->race_time;
          $stats[$driver->name][$event->id]['best_date'] = $race->race_time;
        }
      }
    }
  }
}

foreach ($stats as $driver_name => $driver_stats) {
  $sum = 0.0;
  foreach ($driver_stats as $event_id => $driver_stat) {
    $sum += $driver_stat['race_time'];
  }
  $stats[$driver_name]["total"] = $sum;
}

# sort
foreach ($stats as $driver_name => $driver_stats) {
    $total[$driver_name]  = $driver_stats['total'];
    $race_num[$driver_name] = count($driver_stats);
    #print "".$driver_name.": ".count($driver_stats);
}

array_multisort($race_num, SORT_DESC, $total, SORT_ASC, $stats);

?>
<html>
<head>
  <title>The Eternal Track Cycle</title>
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
  </style>
  <script type="text/javascript" charset="utf-8" src="javascripts/jquery.js"></script>
</head>
<body>
  <div class="container">
    <div class="span-24 last">
      <div class="span-10">
        <h1>The Eternal Track Cycle</h1>
        <p>
          An rFactor race format prototype in the spirit of the glorious race@rfc times. 
          Each season runs for 2 weeks, and includes 5 tracks which run in a cycle. 5 minute qualifying, 10 laps race.
          Winner is the one with the fastest aggregated times achieved on each of the tracks. 
          There is no limit on the number of times you can try during the season, so a bad race isn't the end of the world.
        </p>
      </div>

      <div class="span-14 last">
        <img src="images/carousel.jpg" width="530" height="196" />
      </div>
    </div>
    <div class="span-4">
        <ul>
          <? foreach ($seasons as $s) { ?>
            <li><a href="#" data-remote="true" data-link="stats.php?season_id=<?= $s->id ?>"><?= $s->name ?></a></li>
          <? } ?>
        </ul>
    </div>
    <div class="span-20 last">
        <h2 class="">Season: <?= $season->name ?></h2>
        <h3 class="">Mod: <?= $events[0]->mod ?></h3> 
        <p>If you want to try, hop onto the server named <span class="highlight">backmarkers.etc</span>
        after installing the <a href="http://league.varjanta.com/downloads/file.php?id=507">mod</a> and the <a href="http://n2backmarkers.s3.amazonaws.com/etc_formula_armaroli_track_pack.7z">tracks</a></p>
        

        <table class="results">
          <thead>
            <tr>
              <td>Pos</td>
              <td>Driver</td>
              <? foreach ($events as $event) { ?>
                <td><?= $event->track ?></td>
              <? } ?>
              <td class="loud"><b>Total</b></td>
            </tr>
          </thead>
          <tbody>
            <? $pos = 1 ?>
            <? foreach ($stats as $driver_name => $driverstats) { ?>
              <tr>
                <td><?= $pos ?></td>
                <td><?= $driver_name ?></td>
                <? foreach ($events as $event) { ?>
                  <td class="right">
                    <?= format_time($driverstats[$event->id]['race_time']) ?> <br />
                    <span class="small"><?= $driverstats[$event->id]['race_date'] ?></span><br />
                    <span class="small">BL: <?= format_time($driverstats[$event->id]['best_lap']) ?></span><br />
                    <span class="small"><?= $driverstats[$event->id]['best_date'] ?></span>
                  </td>
                <? } ?>
                <td class="loud right"><b><?= format_time($driverstats['total']) ?></b></td>
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