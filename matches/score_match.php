<?php
session_start();


include "../db.php";

function redirect_self($match_id){
    header("Location: score_match.php?match_id=".$match_id);
    exit;
}

$match_id = $_GET['match_id'] ?? 0;
if($match_id == 0){ echo "Invalid match."; exit; }

// reset session if this is a different match
if(!isset($_SESSION['match_id']) || $_SESSION['match_id'] != $match_id){
    $_SESSION = [];
    $_SESSION['match_id'] = $match_id;
}

/* -------------------------------------------------------
   New bowler after over
------------------------------------------------------- */
if(isset($_POST['new_bowler'])){
    $_SESSION['bowler']      = $_POST['new_bowler'];
    $_SESSION['bowler_over'] = $_POST['current_over'];
    $_SESSION['over_end']    = 0;
    redirect_self($match_id);
}
/* -------------------------------------------------------
   Load match row — includes total_overs, innings, target
------------------------------------------------------- */
$match_row = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT m.*, t1.team_name AS team1_name, t2.team_name AS team2_name
     FROM matches m
     JOIN teams t1 ON t1.team_id = m.team1_id
     JOIN teams t2 ON t2.team_id = m.team2_id
     WHERE m.match_id='$match_id'"
));
if(!$match_row){ echo "Match not found."; exit; }

$team1        = $match_row['team1_id'];
$team2        = $match_row['team2_id'];
$team1_name   = $match_row['team1_name'];
$team2_name   = $match_row['team2_name'];
$total_overs  = $match_row['total_overs'];   // use DB value, not hardcoded 20
$innings      = $match_row['innings'];
$target       = $match_row['target'] ?? 0;   // from DB, not session

/* batting team = team1 in innings 1, team2 in innings 2 */
$batting_team  = ($innings == 1) ? $team1 : $team2;
$bowling_team  = ($innings == 1) ? $team2 : $team1;
$batting_name  = ($innings == 1) ? $team1_name : $team2_name;
$bowling_name  = ($innings == 1) ? $team2_name : $team1_name;

/* -------------------------------------------------------
   UI mode
------------------------------------------------------- */
if(isset($_POST['mode'])){
    $_SESSION['mode'] = $_POST['mode'];
}
if(!isset($_SESSION['mode'])){
    $_SESSION['mode'] = "normal";
}

/* -------------------------------------------------------
   Set target — save to DB, not session
------------------------------------------------------- */
if(isset($_POST['set_target'])){
    $t = (int)$_POST['set_target'];
    mysqli_query($conn,
        "UPDATE matches SET target='$t', innings=2 WHERE match_id='$match_id'"
    );
    unset($_SESSION['striker']);
    unset($_SESSION['non_striker']);
    unset($_SESSION['bowler']);
    unset($_SESSION['over_end']);
    unset($_SESSION['bowler_over']);
    unset($_SESSION['wicket']);
    unset($_SESSION['free_hit']);
    $_SESSION['mode'] = "normal";
    redirect_self($match_id);
}

/* -------------------------------------------------------
   Store initial batsmen + bowler
------------------------------------------------------- */
if(isset($_POST['striker'])){
    if($_POST['striker'] == $_POST['non_striker']){
        $selection_error = "Striker and Non-Striker cannot be the same player!";
    } else {
        $_SESSION['striker']     = $_POST['striker'];
        $_SESSION['non_striker'] = $_POST['non_striker'];
        $_SESSION['bowler']      = $_POST['bowler'];
        redirect_self($match_id);
    }
}

/* -------------------------------------------------------
   Find current over + ball number from DB
------------------------------------------------------- */
$last_ball = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT * FROM balls
     WHERE match_id='$match_id' AND innings='$innings'
     ORDER BY ball_id DESC LIMIT 1"
));

$over = 1;
$ball = 1;

if($last_ball){
    $over  = $last_ball['over_number'];
    $ball  = $last_ball['ball_number'];
    $extra = $last_ball['extra_type'] ?? null;

    if($extra != "wide" && $extra != "noball"){
        $ball++;
    }
    if($ball > 6){
        $over++;
        $ball = 1;
    }
}

/* -------------------------------------------------------
   Detect over end
------------------------------------------------------- */
if($ball == 1 && $over > 1){
    $bowler_over = $_SESSION['bowler_over'] ?? 0;
    if($bowler_over < $over){
        $_SESSION['over_end'] = 1;
    } else {
        $_SESSION['over_end'] = 0;
    }
}


/* -------------------------------------------------------
   New batsman after wicket
------------------------------------------------------- */
if(isset($_POST['replace_striker'])){
    if(isset($_SESSION['out_player']) && $_SESSION['out_player'] == "non_striker"){
        $_SESSION['non_striker'] = $_POST['new_striker'];
    } else {
        $_SESSION['striker'] = $_POST['new_striker'];
    }
    $_SESSION['wicket'] = 0;
    unset($_SESSION['out_player']);
    redirect_self($match_id);
}

/* -------------------------------------------------------
   Helper: insert a ball with all columns filled correctly
   $rb = runs_batsman, $re = runs_extra, $wkt = 0/1
   $wkt_type = null or wicket type string
------------------------------------------------------- */
function insert_ball($conn, $match_id, $innings, $over, $ball,
                     $batsman, $bowler,
                     $rb, $re, $extra_type,
                     $wkt, $wkt_type,
                     $striker_before, $non_striker_before, $bowler_before)
{
    $runs = $rb + $re;
    $et   = $extra_type ? "'$extra_type'" : "NULL";
    $wt   = $wkt_type   ? "'$wkt_type'"  : "NULL";
    mysqli_query($conn,
        "INSERT INTO balls
        (match_id, innings, over_number, ball_number,
         batsman_id, bowler_id,
         runs_batsman, runs_extra, runs,
         extra_type, wicket, wicket_type,
         striker_before, non_striker_before, bowler_before)
        VALUES
        ('$match_id','$innings','$over','$ball',
         '$batsman','$bowler',
         '$rb','$re','$runs',
         $et,'$wkt',$wt,
         '$striker_before','$non_striker_before','$bowler_before')"
    );
}

function swap_strike(){
    $temp = $_SESSION['striker'];
    $_SESSION['striker']     = $_SESSION['non_striker'];
    $_SESSION['non_striker'] = $temp;
}

/* -------------------------------------------------------
   WIDE
------------------------------------------------------- */
if(isset($_POST['wide'])){
    $total  = (int)$_POST['wide'];   // e.g. 1 = plain wide, 2 = wide+1 run
    $rb     = 0;
    $re     = $total;                // entire wide goes to extras
    $running = $total - 1;           // running runs (excluding the penalty 1)

    insert_ball($conn, $match_id, $innings, $over, $ball,
                $_SESSION['striker'], $_SESSION['bowler'],
                $rb, $re, 'wide', 0, null,
                $_SESSION['striker'], $_SESSION['non_striker'],$_SESSION['bowler']);

    if($running % 2 == 1) swap_strike();
    $_SESSION['mode'] = "normal";
    redirect_self($match_id);
}

/* -------------------------------------------------------
   NO BALL
------------------------------------------------------- */
if(isset($_POST['noball'])){
    $total      = (int)$_POST['noball'];  // e.g. 1=NB only, 2=NB+1
    $rb         = $total - 1;             // batsman runs
    $re         = 1;                      // no-ball penalty

    insert_ball($conn, $match_id, $innings, $over, $ball,
                $_SESSION['striker'], $_SESSION['bowler'],
                $rb, $re, 'noball', 0, null,
                $_SESSION['striker'], $_SESSION['non_striker'],$_SESSION['bowler']);

    if($total % 2 == 1) swap_strike();
    $_SESSION['free_hit'] = 1;
    $_SESSION['mode']     = "normal";
    redirect_self($match_id);
}

/* -------------------------------------------------------
   BYE
------------------------------------------------------- */
if(isset($_POST['bye'])){
    $runs = (int)$_POST['bye'];

    insert_ball($conn, $match_id, $innings, $over, $ball,
                $_SESSION['striker'], $_SESSION['bowler'],
                0, $runs, 'bye', 0, null,
                $_SESSION['striker'], $_SESSION['non_striker'],$_SESSION['bowler']);

    if($runs % 2 == 1) swap_strike();
    $_SESSION['mode'] = "normal";
    redirect_self($match_id);
}

/* -------------------------------------------------------
   LEG BYE
------------------------------------------------------- */
if(isset($_POST['legbye'])){
    $runs = (int)$_POST['legbye'];

    insert_ball($conn, $match_id, $innings, $over, $ball,
                $_SESSION['striker'], $_SESSION['bowler'],
                0, $runs, 'legbye', 0, null,
                $_SESSION['striker'], $_SESSION['non_striker'],$_SESSION['bowler']);

    if($runs % 2 == 1) swap_strike();
    $_SESSION['mode'] = "normal";
    redirect_self($match_id);
}

/* -------------------------------------------------------
   WICKET TYPE (bowled, caught, lbw, stumped, hitwicket)
------------------------------------------------------- */
if(isset($_POST['wicket_type'])){
    if(!empty($_SESSION['free_hit']) && $_POST['wicket_type'] != "runout"){
        // on free hit only runout is allowed — ignore and redirect
        $_SESSION['mode'] = "normal";
        redirect_self($match_id);
    }

    $type = $_POST['wicket_type'];

    insert_ball($conn, $match_id, $innings, $over, $ball,
                $_SESSION['striker'], $_SESSION['bowler'],
                0, 0, null, 1, $type,
                $_SESSION['striker'], $_SESSION['non_striker'],$_SESSION['bowler']);

    $_SESSION['wicket'] = 1;
    $_SESSION['mode']   = "normal";
    unset($_SESSION['free_hit']);
    redirect_self($match_id);
}

/* -------------------------------------------------------
   RUN OUT
------------------------------------------------------- */
if(isset($_POST['runout_player'])){
    $who        = $_POST['runout_player'];
    $out_batsman = ($who == "striker") ? $_SESSION['striker'] : $_SESSION['non_striker'];

    insert_ball($conn, $match_id, $innings, $over, $ball,
                $out_batsman, $_SESSION['bowler'],
                0, 0, null, 1, 'runout',
                $_SESSION['striker'], $_SESSION['non_striker'],$_SESSION['bowler']);

    $_SESSION['wicket']     = 1;
    $_SESSION['out_player'] = $who;
    $_SESSION['mode']       = "normal";
    redirect_self($match_id);
}

/* -------------------------------------------------------
   UNDO LAST BALL
------------------------------------------------------- */
if(isset($_POST['undo'])){
    $last = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM balls
         WHERE match_id='$match_id' AND innings='$innings'
         ORDER BY ball_id DESC LIMIT 1"
    ));
    if($last){
        $_SESSION['striker']     = $last['striker_before'];
        $_SESSION['non_striker'] = $last['non_striker_before'];
        $_SESSION['bowler']      = $last['bowler_before'];
        $_SESSION['bowler_over'] = $last['over_number'];
        mysqli_query($conn, "DELETE FROM balls WHERE ball_id='".$last['ball_id']."'");
        unset($_SESSION['free_hit']);
        unset($_SESSION['wicket']);
        unset($_SESSION['over_end']);
    }
    redirect_self($match_id);
}

/* -------------------------------------------------------
   NORMAL RUN
------------------------------------------------------- */
if(isset($_POST['run'])){
    $runs   = (int)$_POST['run'];
    $bowler = $_SESSION['bowler'] ?? 0;

    insert_ball($conn, $match_id, $innings, $over, $ball,
                $_SESSION['striker'], $bowler,
                $runs, 0, null, 0, null,
                $_SESSION['striker'], $_SESSION['non_striker'],$_SESSION['bowler']);

    if($runs == 1 || $runs == 3) swap_strike();

    if($ball == 6){
        swap_strike();  // end of over strike swap
        $_SESSION['over_end'] = 1;
    }

    unset($_SESSION['free_hit']);
    redirect_self($match_id);
}

/* -------------------------------------------------------
   Calculate live stats from DB
------------------------------------------------------- */
$stats_row = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT
        SUM(runs) AS total_runs,
        SUM(wicket) AS total_wickets,
        SUM(CASE WHEN extra_type IS NULL
                 OR extra_type NOT IN ('wide','noball')
             THEN 1 ELSE 0 END) AS legal_balls,
        SUM(CASE WHEN extra_type='wide'   THEN runs ELSE 0 END) AS wides,
        SUM(CASE WHEN extra_type='noball' THEN runs_extra ELSE 0 END) AS noballs,
        SUM(CASE WHEN extra_type='bye'    THEN runs ELSE 0 END) AS byes,
        SUM(CASE WHEN extra_type='legbye' THEN runs ELSE 0 END) AS legbyes
     FROM balls
     WHERE match_id='$match_id' AND innings='$innings'"
));

$total_runs    = $stats_row['total_runs']    ?? 0;
$total_wickets = $stats_row['total_wickets'] ?? 0;
$legal_balls   = $stats_row['legal_balls']   ?? 0;
$wides         = $stats_row['wides']         ?? 0;
$noballs       = $stats_row['noballs']       ?? 0;
$byes          = $stats_row['byes']          ?? 0;
$legbyes       = $stats_row['legbyes']       ?? 0;
$total_extras  = $wides + $noballs + $byes + $legbyes;

$overs_full    = floor($legal_balls / 6);
$overs_part    = $legal_balls % 6;
$overs_decimal = $legal_balls / 6;

$crr = $overs_decimal > 0 ? $total_runs / $overs_decimal : 0;

/* -------------------------------------------------------
   Check match end conditions
------------------------------------------------------- */
$match_over = false;
$result_msg = "";

// all out
if($total_wickets >= 10){
    $match_over = true;
    if($innings == 1){
        $result_msg = "Innings over! ".$batting_name." scored ".$total_runs.". Set target now.";
    } else {
        if($total_runs == $target - 1){
    $result_msg = "Match Tied!";
    $winning_team_id = 0;
} elseif($total_runs >= $target){
    $result_msg = $batting_name." won!";
} else {
    $result_msg = $bowling_name." won by ".($target - $total_runs - 1)." runs!";
}
    }
}

// overs complete
if($overs_full >= $total_overs && !$match_over){
    $match_over = true;
    if($innings == 1){
        $result_msg = "Innings over! ".$batting_name." scored ".$total_runs.". Set target now.";
    } else {
        if($total_runs == $target - 1){
    $result_msg = "Match Tied!";
    $winning_team_id = 0;
} elseif($total_runs >= $target){
    $result_msg = $batting_name." won!";
} else {
    $result_msg = $bowling_name." won by ".($target - $total_runs - 1)." runs!";
}
    }
}

// target chased
if($innings == 2 && $target > 0 && $total_runs >= $target && !$match_over){
    $match_over = true;
    $squad_size = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT tr.squad_size
         FROM teams t
         JOIN tournaments tr ON tr.tournament_id = t.tournament_id
         WHERE t.team_id='$batting_team'"
    ))['squad_size'] ?? 10;
    $wkts_left  = ($squad_size - 1) - $total_wickets;
    $result_msg = $batting_name." won by ".$wkts_left." wickets!";
}

// save result to DB if just ended
if($match_over && $result_msg && $innings == 2 && !$match_row['winning_team']){
    $safe_result = mysqli_real_escape_string($conn, $result_msg);

    // determine winning team id
    if($result_msg == "Match Tied!"){
        $winning_team_id = 0;
    } elseif($total_runs >= $target){
        $winning_team_id = $batting_team;
    } else {
        $winning_team_id = $bowling_team;
    }

    mysqli_query($conn,
        "UPDATE matches
         SET result='$safe_result', winning_team='$winning_team_id'
         WHERE match_id='$match_id'"
    );
}

?>
<!DOCTYPE html>
<html>
<head>
<title>Live Score — <?php echo $team1_name." vs ".$team2_name; ?></title>
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

<div style="margin-bottom:1rem;">
    <h3 style="color:var(--text-secondary);font-size:1rem;font-weight:400;margin-bottom:0.25rem;">
        Innings <?php echo $innings; ?> &nbsp;·&nbsp; <?php echo $batting_name; ?> batting
    </h3>
    <h2 style="margin:0;"><?php echo $team1_name; ?> vs <?php echo $team2_name; ?></h2>
</div>

<!-- LIVE SCORE CARD -->
<div class="card card-accent" style="margin-bottom:1rem;">
    <div style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:0.5rem;">
        <div>
            <div class="score-display">
                <?php echo $total_runs; ?><span class="wickets">/<?php echo $total_wickets; ?></span>
            </div>
            <div class="overs-display">
                <?php echo $overs_full.".".$overs_part; ?> / <?php echo $total_overs; ?> overs
            </div>
        </div>
        <div style="text-align:right;">
            <div class="crr-display">CRR: <?php echo number_format($crr, 2); ?></div>
            <div style="font-size:0.85rem;color:var(--text-muted);margin-top:0.25rem;">
                Extras: <?php echo $total_extras; ?>
                <span style="font-size:0.8rem;">
                    (WD <?php echo $wides; ?> · NB <?php echo $noballs; ?> · B <?php echo $byes; ?> · LB <?php echo $legbyes; ?>)
                </span>
            </div>
        </div>
    </div>

    <?php if($innings == 2 && $target > 0){
        $runs_needed   = $target - $total_runs;
        $balls_total   = $total_overs * 6;
        $balls_left    = $balls_total - $legal_balls;
        $overs_left    = $balls_left / 6;
        $rrr           = $overs_left > 0 ? $runs_needed / $overs_left : 0;
        $balls_left_ov = floor($balls_left / 6);
        $balls_left_bl = $balls_left % 6;
    ?>
    <div class="target-banner" style="margin-top:0.75rem;">
        Target: <?php echo $target; ?> &nbsp;·&nbsp;
        Need <?php echo $runs_needed; ?> in <?php echo $balls_left_ov.".".$balls_left_bl; ?> overs (<?php echo $balls_left; ?> balls)
        &nbsp;·&nbsp; RRR: <?php echo number_format($rrr, 2); ?>
    </div>
    <?php } ?>
</div>

<!-- BALL TIMELINE -->
<?php
$timeline = mysqli_query($conn,
    "SELECT runs, runs_batsman, extra_type, wicket FROM balls
     WHERE match_id='$match_id' AND innings='$innings'
     ORDER BY ball_id DESC LIMIT 12"
);
$tl_balls = [];
while($t2 = mysqli_fetch_assoc($timeline)) $tl_balls[] = $t2;
$tl_balls = array_reverse($tl_balls);
if(count($tl_balls) > 0){ ?>
<div class="card" style="padding:0.75rem 1rem;margin-bottom:1rem;">
    <div class="section-title" style="margin-bottom:0.5rem;">Last Balls</div>
    <div class="ball-timeline">
    <?php foreach($tl_balls as $t2){
        if($t2['wicket'] == 1){
            $cls = "ball wicket"; $val = "W";
        } elseif($t2['extra_type']=='wide'){
            $cls = "ball wide";
            $val = "WD".($t2['runs']>1 ? "+".($t2['runs']-1) : "");
        } elseif($t2['extra_type']=='noball'){
            $cls = "ball noball";
            $val = "NB".($t2['runs_batsman']>0 ? "+".$t2['runs_batsman'] : "");
        } elseif($t2['extra_type']=='bye'){
            $cls = "ball"; $val = "B".$t2['runs'];
        } elseif($t2['extra_type']=='legbye'){
            $cls = "ball"; $val = "LB".$t2['runs'];
        } elseif($t2['runs_batsman'] == 4){
            $cls = "ball four"; $val = "4";
        } elseif($t2['runs_batsman'] == 6){
            $cls = "ball six"; $val = "6";
        } elseif($t2['runs_batsman'] == 0){
            $cls = "ball dot"; $val = "·";
        } else {
            $cls = "ball"; $val = $t2['runs_batsman'];
        }
        echo "<div class='$cls'>$val</div>";
    } ?>
    </div>
</div>
<?php } ?>

<!-- COMPACT BATSMAN + BOWLER -->
<?php
$striker_id     = $_SESSION['striker']     ?? 0;
$non_striker_id = $_SESSION['non_striker'] ?? 0;
$bowler_id      = $_SESSION['bowler']      ?? 0;
if($striker_id || $bowler_id){ ?>
<div class="score-bar">
    <?php foreach([$striker_id, $non_striker_id] as $id){
        if($id == 0) continue;
        $br = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT SUM(runs_batsman) AS runs,
                    SUM(CASE WHEN extra_type IS NULL OR extra_type NOT IN ('wide','noball') THEN 1 ELSE 0 END) AS balls
             FROM balls WHERE match_id='$match_id' AND innings='$innings' AND batsman_id='$id'"
        ));
        $pname = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT player_name FROM players WHERE player_id='$id'"
        ))['player_name'];
        $is_striker = ($id == $striker_id);
    ?>
    <div class="score-bar-item" onclick="toggleBatting()">
        <div class="player-name"><?php echo $pname.($is_striker ? " *" : ""); ?></div>
        <div class="player-score"><?php echo ($br['runs']??0)." (".($br['balls']??0).")"; ?></div>
    </div>
    <?php } ?>

    <?php if($bowler_id){
        $bwr = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT
                SUM(CASE WHEN extra_type IS NULL OR extra_type NOT IN ('wide','noball') THEN 1 ELSE 0 END) AS balls,
                SUM(CASE WHEN extra_type IN ('bye','legbye') THEN 0 ELSE runs END) AS runs,
                SUM(wicket) AS wickets
             FROM balls WHERE match_id='$match_id' AND innings='$innings' AND bowler_id='$bowler_id'"
        ));
        $bname = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT player_name FROM players WHERE player_id='$bowler_id'"
        ))['player_name'];
        $bo = floor($bwr['balls']/6); $bb = $bwr['balls']%6;
    ?>
    <div class="score-bar-item" onclick="toggleBowling()">
        <div class="player-name"><?php echo $bname; ?></div>
        <div class="player-score"><?php echo $bo.".".$bb." - ".($bwr['runs']??0)." - ".($bwr['wickets']??0); ?></div>
    </div>
    <?php } ?>
</div>
<?php } ?>

<!-- FALL OF WICKETS -->
<?php
$fow = mysqli_query($conn,
    "SELECT b.ball_id, b.over_number, b.ball_number, p.player_name
     FROM balls b
     JOIN players p ON p.player_id = b.batsman_id
     WHERE b.match_id='$match_id' AND b.innings='$innings' AND b.wicket=1
     ORDER BY b.ball_id ASC"
);
if(mysqli_num_rows($fow) > 0){
    echo "<div class='card' style='padding:0.75rem 1rem;margin-bottom:1rem;'>";
    echo "<div class='section-title' style='margin-bottom:0.4rem;'>Fall of Wickets</div>";
    echo "<div style='font-size:0.85rem;color:var(--text-secondary);'>";
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
    echo "</div></div>";
}
?>

<!-- FULL BATTING SCORECARD (hidden) -->
<div id="batting_card" style="display:none;margin-bottom:1rem;">
<div class="card">
<div class="section-title">Batting</div>
<div class="table-wrap">
<table>
<thead><tr><th>Batsman</th><th>R</th><th>B</th><th>4s</th><th>6s</th><th>SR</th></tr></thead>
<tbody>
<?php
$bat_stats = mysqli_query($conn,
    "SELECT p.player_name,
            SUM(b.runs_batsman) AS runs,
            SUM(CASE WHEN b.extra_type IS NULL OR b.extra_type NOT IN ('wide','noball') THEN 1 ELSE 0 END) AS balls,
            SUM(CASE WHEN b.runs_batsman=4 AND b.extra_type IS NULL THEN 1 ELSE 0 END) AS fours,
            SUM(CASE WHEN b.runs_batsman=6 AND b.extra_type IS NULL THEN 1 ELSE 0 END) AS sixes,
            MAX(b.wicket) AS is_out
     FROM balls b JOIN players p ON p.player_id=b.batsman_id
     WHERE b.match_id='$match_id' AND b.innings='$innings'
     GROUP BY b.batsman_id ORDER BY MAX(b.ball_id) ASC"
);
while($r = mysqli_fetch_assoc($bat_stats)){
    $sr  = $r['balls'] > 0 ? number_format(($r['runs']/$r['balls'])*100,1) : "0.0";
    $out = $r['is_out'] ? "" : "*";
    echo "<tr><td class='td-name'>".$r['player_name'].$out."</td><td class='td-score'>".$r['runs']."</td><td>".$r['balls']."</td><td>".$r['fours']."</td><td>".$r['sixes']."</td><td>".$sr."</td></tr>";
}
?>
</tbody>
</table>
</div>
</div>
</div>

<!-- FULL BOWLING SCORECARD (hidden) -->
<div id="bowling_card" style="display:none;margin-bottom:1rem;">
<div class="card">
<div class="section-title">Bowling</div>
<div class="table-wrap">
<table>
<thead><tr><th>Bowler</th><th>O</th><th>R</th><th>W</th><th>Econ</th></tr></thead>
<tbody>
<?php
$bowl_stats = mysqli_query($conn,
    "SELECT p.player_name,
            SUM(CASE WHEN b.extra_type IS NULL OR b.extra_type NOT IN ('wide','noball') THEN 1 ELSE 0 END) AS balls,
            SUM(CASE WHEN b.extra_type IN ('bye','legbye') THEN 0 ELSE b.runs END) AS runs,
            SUM(b.wicket) AS wickets
     FROM balls b JOIN players p ON p.player_id=b.bowler_id
     WHERE b.match_id='$match_id' AND b.innings='$innings'
     GROUP BY b.bowler_id"
);
while($r = mysqli_fetch_assoc($bowl_stats)){
    $bo = floor($r['balls']/6).".".$r['balls']%6;
    $ec = $r['balls']>0 ? number_format(($r['runs']/($r['balls']/6)),2) : "0.00";
    echo "<tr><td class='td-name'>".$r['player_name']."</td><td>".$bo."</td><td>".$r['runs']."</td><td class='td-highlight'>".$r['wickets']."</td><td>".$ec."</td></tr>";
}
?>
</tbody>
</table>
</div>
</div>
</div>

<!-- MATCH OVER -->
<?php if($match_over){ ?>
<div class="result-banner">
    <h2><?php echo $result_msg; ?></h2>
    <?php if($innings == 1 && ($total_wickets >= 10 || $overs_full >= $total_overs)){ ?>
    <form method="POST" action="score_match.php?match_id=<?php echo $match_id; ?>" style="margin-top:1rem;">
        <div style="display:flex;align-items:center;justify-content:center;gap:0.75rem;flex-wrap:wrap;">
            <label style="color:var(--text-secondary);font-size:0.9rem;">Set Target:</label>
            <input type="number" name="set_target" value="<?php echo $total_runs + 1; ?>"
                class="form-control" style="width:100px;">
            <button type="submit" class="btn btn-primary">Start 2nd Innings</button>
        </div>
    </form>
    <form method="POST" action="score_match.php?match_id=<?php echo $match_id; ?>" style="margin-top:0.75rem;">
        <button name="undo" value="1" class="btn btn-undo"
            onclick="return confirm('Undo last ball?')">↩ Undo Last Ball</button>
    </form>
    <?php } else { ?>
    <div style="display:flex;gap:0.75rem;justify-content:center;flex-wrap:wrap;margin-top:1rem;">
        <form method="POST" action="score_match.php?match_id=<?php echo $match_id; ?>">
            <button name="undo" value="1" class="btn btn-undo"
                onclick="return confirm('Undo last ball?')">↩ Undo Last Ball</button>
        </form>
        <a href="../matches/scorecard.php?match_id=<?php echo $match_id; ?>" class="btn btn-primary">View Scorecard</a>
        <a href="../tournaments/points_table.php?tournament_id=<?php echo $match_row['tournament_id']; ?>" class="btn btn-secondary">Points Table</a>
    </div>
    <?php } ?>
</div>

<?php } else { ?>

<!-- INITIAL SELECTION -->
<?php if(!isset($_SESSION['striker'])){ ?>
<div class="card">
<h3>Select Opening Batsmen &amp; Bowler</h3>
<?php if(isset($selection_error)){ ?>
<div class="alert alert-error"><?php echo $selection_error; ?></div>
<?php } ?>
<form method="POST">
<div class="form-group">
    <label>Striker</label>
    <select name="striker" class="form-control">
    <?php
    $t1p = mysqli_query($conn, "SELECT * FROM players WHERE team_id='$batting_team'");
    while($p = mysqli_fetch_assoc($t1p)){
        echo '<option value="'.$p['player_id'].'">'.$p['player_name'].'</option>';
    }
    ?>
    </select>
</div>
<div class="form-group">
    <label>Non-Striker</label>
    <select name="non_striker" class="form-control">
    <?php
    $t1p2 = mysqli_query($conn, "SELECT * FROM players WHERE team_id='$batting_team'");
    $first = true;
    while($p = mysqli_fetch_assoc($t1p2)){
        $sel = $first ? "" : "selected";
        $first = false;
        echo '<option value="'.$p['player_id'].'" '.$sel.'>'.$p['player_name'].'</option>';
    }
    ?>
    </select>
</div>
<div class="form-group">
    <label>Bowler</label>
    <select name="bowler" class="form-control">
    <?php
    $t2p = mysqli_query($conn, "SELECT * FROM players WHERE team_id='$bowling_team'");
    while($p = mysqli_fetch_assoc($t2p)){
        echo '<option value="'.$p['player_id'].'">'.$p['player_name'].'</option>';
    }
    ?>
    </select>
</div>
<button type="submit" class="btn btn-primary">Start Scoring</button>
</form>
</div>

<!-- NEW BOWLER AFTER OVER -->
<?php } elseif(!empty($_SESSION['over_end']) && $_SESSION['over_end'] == 1){ ?>
<div class="card">
<h3>Over <?php echo $over - 1; ?> Complete</h3>
<p>Select bowler for over <?php echo $over; ?></p>
<form method="POST" action="score_match.php?match_id=<?php echo $match_id; ?>">
<input type="hidden" name="current_over" value="<?php echo $over; ?>">
<div class="form-group">
    <label>New Bowler</label>
    <select name="new_bowler" class="form-control">
    <?php
    $cb  = $_SESSION['bowler'];
    $t2p = mysqli_query($conn, "SELECT * FROM players WHERE team_id='$bowling_team' AND player_id!='$cb'");
    while($p = mysqli_fetch_assoc($t2p)){
        echo '<option value="'.$p['player_id'].'">'.$p['player_name'].'</option>';
    }
    ?>
    </select>
</div>
<button type="submit" class="btn btn-primary">Start Next Over</button>
</form>
<form method="POST" action="score_match.php?match_id=<?php echo $match_id; ?>" style="margin-top:0.75rem;">
    <button name="undo" value="1" class="btn btn-undo"
        onclick="return confirm('Undo last ball?')">↩ Undo Last Ball</button>
</form>
</div>

<!-- NEW BATSMAN AFTER WICKET -->
<?php } elseif(!empty($_SESSION['wicket']) && $_SESSION['wicket'] == 1){ ?>
<?php
$out_players = [];
$oq = mysqli_query($conn, "SELECT batsman_id FROM balls WHERE match_id='$match_id' AND innings='$innings' AND wicket=1");
while($r = mysqli_fetch_assoc($oq)) $out_players[] = $r['batsman_id'];
$ns = $_SESSION['non_striker'];
$available = [];
$avail = mysqli_query($conn, "SELECT * FROM players WHERE team_id='$batting_team'");
while($p = mysqli_fetch_assoc($avail)){
    if($p['player_id'] != $ns && !in_array($p['player_id'], $out_players)){
        $available[] = $p;
    }
}
if(count($available) == 0){
    if($innings == 1){ ?>
    <div class="result-banner">
        <h2 style="color:var(--red);">All Out!</h2>
        <p><?php echo $batting_name; ?> scored <?php echo $total_runs; ?></p>
        <form method="POST" action="score_match.php?match_id=<?php echo $match_id; ?>" style="margin-top:1rem;">
            <div style="display:flex;align-items:center;justify-content:center;gap:0.75rem;flex-wrap:wrap;">
                <label style="color:var(--text-secondary);font-size:0.9rem;">Set Target:</label>
                <input type="number" name="set_target" value="<?php echo $total_runs + 1; ?>"
                    class="form-control" style="width:100px;">
                <button type="submit" class="btn btn-primary">Start 2nd Innings</button>
            </div>
        </form>
        <form method="POST" action="score_match.php?match_id=<?php echo $match_id; ?>" style="margin-top:0.75rem;">
            <button name="undo" value="1" class="btn btn-undo"
                onclick="return confirm('Undo last ball?')">↩ Undo Last Ball</button>
        </form>
    </div>
    <?php } else { ?>
    <div class="result-banner">
        <h2 style="color:var(--red);">All Out!</h2>
        <?php
        if($total_runs >= $target) echo "<h2 style='color:var(--green);'>".$batting_name." won!</h2>";
        elseif($total_runs == $target - 1) echo "<h2 style='color:var(--amber);'>Match Tied!</h2>";
        else echo "<h2 style='color:var(--green);'>".$bowling_name." won by ".($target-$total_runs-1)." runs!</h2>";
        ?>
        <div style="display:flex;gap:0.75rem;justify-content:center;flex-wrap:wrap;margin-top:1rem;">
            <form method="POST" action="score_match.php?match_id=<?php echo $match_id; ?>">
                <button name="undo" value="1" class="btn btn-undo"
                    onclick="return confirm('Undo last ball?')">↩ Undo Last Ball</button>
            </form>
            <a href="../matches/scorecard.php?match_id=<?php echo $match_id; ?>" class="btn btn-primary">View Scorecard</a>
            <a href="../tournaments/points_table.php?tournament_id=<?php echo $match_row['tournament_id']; ?>" class="btn btn-secondary">Points Table</a>
        </div>
    </div>
    <?php }
} else { ?>
<div class="card">
<h3 style="color:var(--red);">Wicket! Select New Batsman</h3>
<form method="POST">
<div class="form-group">
    <label>New Batsman</label>
    <select name="new_striker" class="form-control">
    <?php foreach($available as $p){ ?>
        <option value="<?php echo $p['player_id']; ?>"><?php echo $p['player_name']; ?></option>
    <?php } ?>
    </select>
</div>
<button type="submit" name="replace_striker" class="btn btn-primary">Send In</button>
</form>
</div>
<?php } ?>

<!-- SCORING BUTTONS -->
<?php } else {
$sid   = $_SESSION['striker'];
$nid   = $_SESSION['non_striker'];
$sname = mysqli_fetch_assoc(mysqli_query($conn,"SELECT player_name FROM players WHERE player_id='$sid'"))['player_name'];
$nname = mysqli_fetch_assoc(mysqli_query($conn,"SELECT player_name FROM players WHERE player_id='$nid'"))['player_name'];
?>

<?php if(!empty($_SESSION['free_hit'])){ ?>
<div style="text-align:center;margin-bottom:0.75rem;">
    <span class="freehit-badge">⚡ Free Hit</span>
</div>
<?php } ?>

<div class="card" >
<div class="card">
<div style="margin-bottom:0.75rem;">
    <span style="font-family:var(--font-head);font-size:1.1rem;font-weight:600;">
        🏏 <?php echo $sname; ?>*
    </span>
    <span style="color:var(--text-muted);margin:0 0.5rem;">vs</span>
    <span style="color:var(--text-secondary);"><?php echo $nname; ?></span>
</div>

<form method="POST">

<?php if($_SESSION['mode'] == "normal"){ ?>
<div class="section-title">Runs</div>
<div class="run-buttons" style="margin-bottom:1rem;">
    <button name="run" value="0" class="btn-run">0</button>
    <button name="run" value="1" class="btn-run">1</button>
    <button name="run" value="2" class="btn-run">2</button>
    <button name="run" value="3" class="btn-run">3</button>
    <button name="run" value="4" class="btn-run four">4</button>
    <button name="run" value="6" class="btn-run six">6</button>
    <button name="undo" value="1" class="btn-undo"
        onclick="return confirm('Undo last ball?')"
        style="border-radius:var(--radius);padding:0 1rem;height:56px;">↩</button>
</div>
<div class="section-title">Extras</div>
<div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1rem;">
    <button name="mode" value="wide"   class="btn-extra">WD</button>
    <button name="mode" value="noball" class="btn-extra">NB</button>
    <button name="mode" value="bye"    class="btn-extra">Bye</button>
    <button name="mode" value="legbye" class="btn-extra">Leg Bye</button>
</div>
<div class="section-title">Wicket</div>
<button name="mode" value="wicket" class="btn-wicket">W Wicket</button>
<?php } ?>

<?php if($_SESSION['mode'] == "wide"){ ?>
<div class="section-title">Wide — how many runs?</div>
<div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1rem;">
    <button name="wide" value="1" class="btn-extra">WD+0</button>
    <button name="wide" value="2" class="btn-extra">WD+1</button>
    <button name="wide" value="3" class="btn-extra">WD+2</button>
    <button name="wide" value="4" class="btn-extra">WD+3</button>
    <button name="wide" value="5" class="btn-extra">WD+4</button>
</div>
<button name="mode" value="normal" class="btn btn-secondary">Cancel</button>
<?php } ?>

<?php if($_SESSION['mode'] == "noball"){ ?>
<div class="section-title">No Ball — batsman runs?</div>
<div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1rem;">
    <button name="noball" value="1" class="btn-extra">NB+0</button>
    <button name="noball" value="2" class="btn-extra">NB+1</button>
    <button name="noball" value="3" class="btn-extra">NB+2</button>
    <button name="noball" value="4" class="btn-extra">NB+3</button>
    <button name="noball" value="5" class="btn-extra">NB+4</button>
    <button name="noball" value="7" class="btn-extra">NB+6</button>
</div>
<button name="mode" value="normal" class="btn btn-secondary">Cancel</button>
<?php } ?>

<?php if($_SESSION['mode'] == "bye"){ ?>
<div class="section-title">Byes</div>
<div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1rem;">
    <button name="bye" value="1" class="btn-extra">1</button>
    <button name="bye" value="2" class="btn-extra">2</button>
    <button name="bye" value="3" class="btn-extra">3</button>
    <button name="bye" value="4" class="btn-extra">4</button>
</div>
<button name="mode" value="normal" class="btn btn-secondary">Cancel</button>
<?php } ?>

<?php if($_SESSION['mode'] == "legbye"){ ?>
<div class="section-title">Leg Byes</div>
<div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1rem;">
    <button name="legbye" value="1" class="btn-extra">1</button>
    <button name="legbye" value="2" class="btn-extra">2</button>
    <button name="legbye" value="3" class="btn-extra">3</button>
    <button name="legbye" value="4" class="btn-extra">4</button>
</div>
<button name="mode" value="normal" class="btn btn-secondary">Cancel</button>
<?php } ?>

<?php if($_SESSION['mode'] == "wicket"){ ?>
<div class="section-title">Wicket Type</div>
<div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1rem;">
    <button name="wicket_type" value="bowled"    class="btn-wicket-type">Bowled</button>
    <button name="wicket_type" value="caught"    class="btn-wicket-type">Caught</button>
    <button name="wicket_type" value="lbw"       class="btn-wicket-type">LBW</button>
    <button name="wicket_type" value="stumped"   class="btn-wicket-type">Stumped</button>
    <button name="wicket_type" value="hitwicket" class="btn-wicket-type">Hit Wicket</button>
    <button name="mode" value="runout_select"    class="btn-wicket-type">Run Out</button>
</div>
<button name="mode" value="normal" class="btn btn-secondary">Cancel</button>
<?php } ?>

<?php if($_SESSION['mode'] == "runout_select"){ ?>
<div class="section-title">Who is Run Out?</div>
<div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1rem;">
    <button name="runout_player" value="striker"     class="btn-wicket-type">Striker (<?php echo $sname; ?>)</button>
    <button name="runout_player" value="non_striker" class="btn-wicket-type">Non-Striker (<?php echo $nname; ?>)</button>
</div>
<button name="mode" value="normal" class="btn btn-secondary">Cancel</button>
<?php } ?>

</form>
</div>
<?php } ?>
<?php } ?>

</div>

<script>
function toggleBatting(){
    var x = document.getElementById("batting_card");
    x.style.display = x.style.display === "none" ? "block" : "none";
}
function toggleBowling(){
    var x = document.getElementById("bowling_card");
    x.style.display = x.style.display === "none" ? "block" : "none";
}


</script>

</body>
</html>