<?php
require_once $_SERVER['DOCUMENT_ROOT']."/cricstat/db.php";

$tournament_id = $_GET['tournament_id'] ?? 0;

if($tournament_id == 0){
    $tournaments = mysqli_query($conn, "SELECT * FROM tournaments ORDER BY tournament_id DESC");
    ?>
    <!DOCTYPE html>
    <html>
    <head>
    <title>Points Table</title>
    <link rel="stylesheet" href="/cricstat/assets/css/style.css">
    </head>
    <body>
    <nav class="navbar">
        <a href="/cricstat/index.php" class="navbar-brand">Cric<span>Stat</span></a>
        <div class="navbar-links"><a href="/cricstat/index.php">Home</a></div>
    </nav>
    <div class="container-sm">
    <h2>Points Table</h2>
    <p>Select a tournament:</p>
    <?php while($t = mysqli_fetch_assoc($tournaments)){ ?>
    <a href="points_table.php?tournament_id=<?php echo $t['tournament_id']; ?>"
        class="card" style="display:block;text-decoration:none;margin-bottom:0.75rem;">
        <h3 style="margin:0;color:var(--text-primary);"><?php echo $t['tournament_name']; ?></h3>
    </a>
    <?php } ?>
    <br><a href="/cricstat/index.php" class="btn btn-secondary">← Home</a>
    </div>
    </body>
    </html>
    <?php
    exit;
}

$tournament = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT tournament_name FROM tournaments WHERE tournament_id='$tournament_id'"
));
if(!$tournament){ echo "Tournament not found."; exit; }

$matches_query = mysqli_query($conn,
    "SELECT m.*,
            t1.team_name AS team1_name,
            t2.team_name AS team2_name
     FROM matches m
     JOIN teams t1 ON t1.team_id = m.team1_id
     JOIN teams t2 ON t2.team_id = m.team2_id
     WHERE m.tournament_id='$tournament_id'
     ORDER BY m.match_id ASC"
);

$table = [];
$teams_init = mysqli_query($conn,
    "SELECT * FROM teams WHERE tournament_id='$tournament_id'"
);
while($team = mysqli_fetch_assoc($teams_init)){
    $table[$team['team_id']] = [
        'name'   => $team['team_name'],
        'played' => 0,
        'won'    => 0,
        'lost'   => 0,
        'tied'   => 0,
        'points' => 0,
        'nrr'    => 0.00,
    ];
}

$matches_list = [];
while($m = mysqli_fetch_assoc($matches_query)){
    $matches_list[] = $m;
    $t1 = $m['team1_id'];
    $t2 = $m['team2_id'];
    if(!$m['result']) continue;

    if(isset($table[$t1])) $table[$t1]['played']++;
    if(isset($table[$t2])) $table[$t2]['played']++;

    if($m['result'] == "Match Tied!"){
        // tie — 1 point each
        if(isset($table[$t1])){
            $table[$t1]['tied']++;
            $table[$t1]['points'] += 1;
        }
        if(isset($table[$t2])){
            $table[$t2]['tied']++;
            $table[$t2]['points'] += 1;
        }
    } else {
        $winner = $m['winning_team'] ?? null;
        if($winner){
            $loser = ($winner == $t1) ? $t2 : $t1;
            if(isset($table[$winner])){
                $table[$winner]['won']++;
                $table[$winner]['points'] += 3; // win = 3 pts
            }
            if(isset($table[$loser])){
                $table[$loser]['lost']++;
                // loss = 0 pts
            }
        }
    }
}

foreach($table as $tid => $row){
    $scored = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT
            SUM(b.runs) AS runs,
            SUM(CASE WHEN b.extra_type IS NULL OR b.extra_type NOT IN ('wide','noball')
                THEN 1 ELSE 0 END) AS balls
         FROM balls b
         JOIN matches m ON m.match_id = b.match_id
         WHERE m.tournament_id='$tournament_id'
         AND ((m.team1_id='$tid' AND b.innings=1) OR (m.team2_id='$tid' AND b.innings=2))"
    ));
    $conceded = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT
            SUM(b.runs) AS runs,
            SUM(CASE WHEN b.extra_type IS NULL OR b.extra_type NOT IN ('wide','noball')
                THEN 1 ELSE 0 END) AS balls
         FROM balls b
         JOIN matches m ON m.match_id = b.match_id
         WHERE m.tournament_id='$tournament_id'
         AND ((m.team1_id='$tid' AND b.innings=2) OR (m.team2_id='$tid' AND b.innings=1))"
    ));
    $runs_scored   = $scored['runs']    ?? 0;
    $balls_faced   = $scored['balls']   ?? 0;
    $runs_conceded = $conceded['runs']  ?? 0;
    $balls_bowled  = $conceded['balls'] ?? 0;
    $overs_faced   = $balls_faced  > 0 ? $balls_faced  / 6 : 0;
    $overs_bowled  = $balls_bowled > 0 ? $balls_bowled / 6 : 0;
    $rpo_scored    = $overs_faced  > 0 ? $runs_scored   / $overs_faced  : 0;
    $rpo_conceded  = $overs_bowled > 0 ? $runs_conceded / $overs_bowled : 0;
    $table[$tid]['nrr'] = $rpo_scored - $rpo_conceded;
}

usort($table, function($a, $b){
    if($b['points'] != $a['points']) return $b['points'] - $a['points'];
    return $b['nrr'] <=> $a['nrr'];
});
?>
<!DOCTYPE html>
<html>
<head>
<title>Points Table</title>
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
    <h2 style="margin:0;">Points Table</h2>
    <p style="margin:0.25rem 0 0;"><?php echo $tournament['tournament_name']; ?></p>
</div>

<!-- POINTS LEGEND -->
<div class="alert alert-info" style="font-size:0.85rem;margin-bottom:1rem;">
    Win = 3 pts &nbsp;·&nbsp; Tie = 1 pt &nbsp;·&nbsp; Loss = 0 pts
</div>

<!-- POINTS TABLE -->
<div class="card" style="padding:0;overflow:hidden;margin-bottom:1.5rem;">
<div class="table-wrap">
<table>
<thead>
<tr>
    <th>#</th>
    <th>Team</th>
    <th>P</th>
    <th>W</th>
    <th>L</th>
    <th>T</th>
    <th>Pts</th>
    <th>NRR</th>
</tr>
</thead>
<tbody>
<?php
$rank = 1;
foreach($table as $row){
    $rank_class = $rank == 1 ? "rank-1" : ($rank == 2 ? "rank-2" : "");
    $nrr_color  = $row['nrr'] >= 0 ? "var(--green)" : "var(--red)";
?>
<tr class="<?php echo $rank_class; ?>">
    <td><?php echo $rank++; ?></td>
    <td class="td-name"><?php echo $row['name']; ?></td>
    <td><?php echo $row['played']; ?></td>
    <td class="td-highlight"><?php echo $row['won']; ?></td>
    <td><?php echo $row['lost']; ?></td>
    <td style="color:var(--amber);"><?php echo $row['tied']; ?></td>
    <td><strong><?php echo $row['points']; ?></strong></td>
    <td style="color:<?php echo $nrr_color; ?>">
        <?php echo ($row['nrr'] >= 0 ? "+" : "").number_format($row['nrr'], 3); ?>
    </td>
</tr>
<?php } ?>
</tbody>
</table>
</div>
</div>

<!-- MATCH RESULTS -->
<h3 style="margin-bottom:1rem;">Match Results</h3>
<?php if(empty($matches_list)){ ?>
    <div class="alert alert-info">No matches played yet.</div>
<?php } else { ?>
<div class="card" style="padding:0;overflow:hidden;margin-bottom:1.5rem;">
<div class="table-wrap">
<table>
<thead>
<tr>
    <th>Match</th>
    <th>Result</th>
    <th>Status</th>
    <th></th>
</tr>
</thead>
<tbody>
<?php foreach($matches_list as $m){ ?>
<tr>
    <td class="td-name"><?php echo $m['team1_name']." vs ".$m['team2_name']; ?></td>
    <td style="font-size:0.85rem;color:var(--text-secondary);">
        <?php echo $m['result'] ?? "—"; ?>
    </td>
    <td>
        <?php if($m['result']){ ?>
        <span class="badge badge-green">Completed</span>
        <?php } else { ?>
        <span class="badge badge-amber">In Progress</span>
        <?php } ?>
    </td>
    <td>
        <?php if($m['result']){ ?>
        <a href="../matches/scorecard.php?match_id=<?php echo $m['match_id']; ?>"
            class="btn btn-secondary" style="font-size:0.8rem;padding:0.3rem 0.7rem;">Scorecard</a>
        <?php } else { ?>
        <a href="../matches/score_match.php?match_id=<?php echo $m['match_id']; ?>"
            class="btn btn-primary" style="font-size:0.8rem;padding:0.3rem 0.7rem;">Continue</a>
        <?php } ?>
    </td>
</tr>
<?php } ?>
</tbody>
</table>
</div>
</div>
<?php } ?>

<div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
    <a href="../matches/create_match.php?tournament_id=<?php echo $tournament_id; ?>"
        class="btn btn-primary">+ New Match</a>
    <a href="/cricstat/index.php" class="btn btn-secondary">← Home</a>
</div>

</div>
</body>
</html>