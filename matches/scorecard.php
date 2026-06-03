<?php
require_once $_SERVER['DOCUMENT_ROOT']."/cricstat/auth.php";
require_once $_SERVER['DOCUMENT_ROOT']."/cricstat/db.php";

$match_id = $_GET['match_id'] ?? 0;
if($match_id == 0){ echo "Invalid match."; exit; }

$match_row = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT m.*,
            t1.team_name AS team1_name,
            t2.team_name AS team2_name
     FROM matches m
     JOIN teams t1 ON t1.team_id = m.team1_id
     JOIN teams t2 ON t2.team_id = m.team2_id
     WHERE m.match_id='$match_id'"
));
if(!$match_row){ echo "Match not found."; exit; }

$team1_name  = $match_row['team1_name'];
$team2_name  = $match_row['team2_name'];
$result      = $match_row['result'] ?? "In Progress";
$total_overs = $match_row['total_overs'];

function innings_scorecard($conn, $match_id, $innings, $batting_name, $bowling_name){

    $totals = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT
            SUM(runs) AS total_runs,
            SUM(wicket) AS total_wickets,
            SUM(CASE WHEN extra_type IS NULL OR extra_type NOT IN ('wide','noball') THEN 1 ELSE 0 END) AS legal_balls,
            SUM(CASE WHEN extra_type='wide'   THEN runs ELSE 0 END) AS wides,
            SUM(CASE WHEN extra_type='noball' THEN runs_extra ELSE 0 END) AS noballs,
            SUM(CASE WHEN extra_type='bye'    THEN runs ELSE 0 END) AS byes,
            SUM(CASE WHEN extra_type='legbye' THEN runs ELSE 0 END) AS legbyes
         FROM balls
         WHERE match_id='$match_id' AND innings='$innings'"
    ));

    $total_runs    = $totals['total_runs']    ?? 0;
    $total_wickets = $totals['total_wickets'] ?? 0;
    $legal_balls   = $totals['legal_balls']   ?? 0;
    $wides         = $totals['wides']         ?? 0;
    $noballs       = $totals['noballs']       ?? 0;
    $byes          = $totals['byes']          ?? 0;
    $legbyes       = $totals['legbyes']       ?? 0;
    $total_extras  = $wides + $noballs + $byes + $legbyes;
    $overs_full    = floor($legal_balls / 6);
    $overs_part    = $legal_balls % 6;

    echo "<div class='card' style='margin-bottom:1.5rem;'>";

    // innings header
    echo "<div style='display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:0.5rem;margin-bottom:1rem;'>";
    echo "<div>";
    echo "<div class='section-title' style='margin-bottom:0.25rem;'>".$batting_name." Innings</div>";
    echo "<div class='score-display'>".$total_runs."<span class='wickets'>/".$total_wickets."</span></div>";
    echo "<div class='overs-display'>".$overs_full.".".$overs_part." overs</div>";
    echo "</div>";
    echo "<div style='text-align:right;font-size:0.85rem;color:var(--text-secondary);'>";
    echo "Extras: ".$total_extras."<br>";
    echo "<span style='font-size:0.8rem;color:var(--text-muted);'>";
    echo "WD ".$wides." · NB ".$noballs." · B ".$byes." · LB ".$legbyes;
    echo "</span></div>";
    echo "</div>";

    // batting table
    echo "<div class='section-title'>Batting</div>";
    echo "<div class='table-wrap' style='margin-bottom:1rem;'>";
    echo "<table><thead><tr>
            <th>Batsman</th><th>Dismissal</th>
            <th>R</th><th>B</th><th>4s</th><th>6s</th><th>SR</th>
          </tr></thead><tbody>";

    $batting = mysqli_query($conn,
        "SELECT p.player_name, b.batsman_id,
                SUM(b.runs_batsman) AS runs,
                SUM(CASE WHEN b.extra_type IS NULL OR b.extra_type NOT IN ('wide','noball') THEN 1 ELSE 0 END) AS balls,
                SUM(CASE WHEN b.runs_batsman=4 AND b.extra_type IS NULL THEN 1 ELSE 0 END) AS fours,
                SUM(CASE WHEN b.runs_batsman=6 AND b.extra_type IS NULL THEN 1 ELSE 0 END) AS sixes,
                MAX(b.wicket) AS is_out,
                MAX(CASE WHEN b.wicket=1 THEN b.wicket_type ELSE NULL END) AS how_out
         FROM balls b JOIN players p ON p.player_id=b.batsman_id
         WHERE b.match_id='$match_id' AND b.innings='$innings'
         GROUP BY b.batsman_id ORDER BY MAX(b.ball_id) ASC"
    );

    while($r = mysqli_fetch_assoc($batting)){
        $sr      = $r['balls'] > 0 ? number_format(($r['runs']/$r['balls'])*100, 1) : "0.0";
        $how_out = $r['is_out'] ? ucfirst($r['how_out'] ?? 'out') : "<span style='color:var(--green);'>not out</span>";
        echo "<tr>";
        echo "<td class='td-name'>".$r['player_name']."</td>";
        echo "<td style='font-size:0.85rem;color:var(--text-secondary);'>".$how_out."</td>";
        echo "<td class='td-score'>".$r['runs']."</td>";
        echo "<td>".$r['balls']."</td>";
        echo "<td>".$r['fours']."</td>";
        echo "<td>".$r['sixes']."</td>";
        echo "<td>".$sr."</td>";
        echo "</tr>";
    }
    echo "</tbody></table></div>";

    // fall of wickets
    $fow = mysqli_query($conn,
        "SELECT b.ball_id, b.over_number, b.ball_number, p.player_name
         FROM balls b JOIN players p ON p.player_id=b.batsman_id
         WHERE b.match_id='$match_id' AND b.innings='$innings' AND b.wicket=1
         ORDER BY b.ball_id ASC"
    );
    if(mysqli_num_rows($fow) > 0){
        echo "<div class='section-title'>Fall of Wickets</div>";
        echo "<div style='font-size:0.85rem;color:var(--text-secondary);margin-bottom:1rem;'>";
        $wno = 0; $fow_parts = [];
        while($row = mysqli_fetch_assoc($fow)){
            $wno++;
            $sc = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT SUM(runs) AS total FROM balls
                 WHERE match_id='$match_id' AND innings='$innings'
                 AND ball_id <= '".$row['ball_id']."'"
            ))['total'] ?? 0;
            $fow_parts[] = $sc."/".$wno." (".$row['player_name'].", ".$row['over_number'].".".$row['ball_number'].")";
        }
        echo implode(" &nbsp;|&nbsp; ", $fow_parts);
        echo "</div>";
    }

    // bowling table
    echo "<div class='section-title'>Bowling</div>";
    echo "<div class='table-wrap'>";
    echo "<table><thead><tr>
            <th>Bowler</th><th>O</th><th>R</th><th>W</th>
            <th>Econ</th><th>WD</th><th>NB</th>
          </tr></thead><tbody>";

    $bowling = mysqli_query($conn,
        "SELECT p.player_name,
                SUM(CASE WHEN b.extra_type IS NULL OR b.extra_type NOT IN ('wide','noball') THEN 1 ELSE 0 END) AS balls,
                SUM(CASE WHEN b.extra_type IN ('bye','legbye') THEN 0 ELSE b.runs END) AS runs,
                SUM(b.wicket) AS wickets,
                SUM(CASE WHEN b.extra_type='wide'   THEN 1 ELSE 0 END) AS wides,
                SUM(CASE WHEN b.extra_type='noball' THEN 1 ELSE 0 END) AS noballs
         FROM balls b JOIN players p ON p.player_id=b.bowler_id
         WHERE b.match_id='$match_id' AND b.innings='$innings'
         GROUP BY b.bowler_id ORDER BY MAX(b.ball_id) ASC"
    );

    while($r = mysqli_fetch_assoc($bowling)){
        $ov   = floor($r['balls']/6).".".($r['balls']%6);
        $econ = $r['balls'] > 0
            ? number_format(($r['runs']/($r['balls']/6)), 2)
            : "0.00";
        echo "<tr>";
        echo "<td class='td-name'>".$r['player_name']."</td>";
        echo "<td>".$ov."</td>";
        echo "<td>".$r['runs']."</td>";
        echo "<td class='td-highlight'>".$r['wickets']."</td>";
        echo "<td>".$econ."</td>";
        echo "<td style='color:var(--amber);'>".$r['wides']."</td>";
        echo "<td style='color:var(--amber);'>".$r['noballs']."</td>";
        echo "</tr>";
    }
    echo "</tbody></table></div>";
    echo "</div>";
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Scorecard</title>
<link rel="stylesheet" href="/cricstat/assets/css/style.css">
</head>
<body>

<nav class="navbar">
    <a href="/cricstat/index.php" class="navbar-brand">Cric<span>Stat</span></a>
    <div class="navbar-links">
        <a href="/cricstat/index.php">Home</a>
    </div>
</nav>

<div class="container">

<div style="margin-bottom:1.5rem;">
    <h2 style="margin:0;"><?php echo $team1_name." vs ".$team2_name; ?></h2>
    <p style="margin:0.25rem 0 0;"><?php echo $total_overs; ?> overs match</p>
</div>

<div class="result-banner" style="margin-bottom:1.5rem;">
    <h2><?php echo $result; ?></h2>
</div>

<?php
innings_scorecard($conn, $match_id, 1, $team1_name, $team2_name);

$innings2_check = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS cnt FROM balls WHERE match_id='$match_id' AND innings=2"
));
if($innings2_check['cnt'] > 0){
    innings_scorecard($conn, $match_id, 2, $team2_name, $team1_name);
}
?>

<div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
    <a href="../tournaments/points_table.php?tournament_id=<?php echo $match_row['tournament_id']; ?>"
        class="btn btn-primary">Points Table</a>
    <a href="/cricstat/index.php" class="btn btn-secondary">← Home</a>
</div>

</div>
</body>
</html>