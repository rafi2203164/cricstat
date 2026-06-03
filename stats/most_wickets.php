<?php
require_once $_SERVER['DOCUMENT_ROOT']."/cricstat/auth.php";
require_once $_SERVER['DOCUMENT_ROOT']."/cricstat/db.php";

$tournament_id = $_GET['tournament_id'] ?? 0;

if($tournament_id == 0){
    $tournaments = mysqli_query($conn, "SELECT * FROM tournaments ORDER BY tournament_id DESC");
    ?>
    <!DOCTYPE html>
    <html>
    <head>
    <title>Most Wickets</title>
    <link rel="stylesheet" href="/cricstat/assets/css/style.css">
    </head>
    <body>
    <nav class="navbar">
        <a href="/cricstat/index.php" class="navbar-brand">Cric<span>Stat</span></a>
        <div class="navbar-links"><a href="/cricstat/index.php">Home</a></div>
    </nav>
    <div class="container-sm">
    <h2>Most Wickets</h2>
    <p>Select a tournament:</p>
    <?php while($t = mysqli_fetch_assoc($tournaments)){ ?>
    <a href="most_wickets.php?tournament_id=<?php echo $t['tournament_id']; ?>"
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

$result = mysqli_query($conn,
    "SELECT
        p.player_name,
        t.team_name,
        SUM(b.wicket) AS wickets,
        SUM(CASE WHEN b.extra_type IS NULL OR b.extra_type NOT IN ('wide','noball')
            THEN 1 ELSE 0 END) AS balls,
        SUM(CASE WHEN b.extra_type IN ('bye','legbye') THEN 0 ELSE b.runs END) AS runs,
        COUNT(DISTINCT b.match_id) AS matches,
        SUM(CASE WHEN b.extra_type='wide'   THEN 1 ELSE 0 END) AS wides,
        SUM(CASE WHEN b.extra_type='noball' THEN 1 ELSE 0 END) AS noballs
     FROM balls b
     JOIN players p ON p.player_id = b.bowler_id
     JOIN teams t ON t.team_id = p.team_id
     JOIN matches m ON m.match_id = b.match_id
     WHERE m.tournament_id='$tournament_id'
     GROUP BY b.bowler_id
     ORDER BY wickets DESC, runs ASC"
);
?>
<!DOCTYPE html>
<html>
<head>
<title>Most Wickets</title>
<link rel="stylesheet" href="/cricstat/assets/css/style.css">
</head>
<body>

<nav class="navbar">
    <a href="/cricstat/index.php" class="navbar-brand">Cric<span>Stat</span></a>
    <div class="navbar-links">
        <a href="most_runs.php?tournament_id=<?php echo $tournament_id; ?>">Most Runs</a>
        <a href="/cricstat/index.php">Home</a>
    </div>
</nav>

<div class="container">

<div style="margin-bottom:1.5rem;">
    <h2 style="margin:0;">Most Wickets</h2>
    <p style="margin:0.25rem 0 0;"><?php echo $tournament['tournament_name']; ?></p>
</div>

<div class="card" style="padding:0;overflow:hidden;">
<div class="table-wrap">
<table>
<thead>
<tr>
    <th>#</th>
    <th>Player</th>
    <th>Team</th>
    <th>M</th>
    <th>W</th>
    <th>Overs</th>
    <th>Runs</th>
    <th>Econ</th>
    <th>Avg</th>
    <th>WD</th>
    <th>NB</th>
</tr>
</thead>
<tbody>
<?php
$rank = 1;
while($row = mysqli_fetch_assoc($result)){
    $ov   = floor($row['balls']/6).".".($row['balls']%6);
    $econ = $row['balls'] > 0
        ? number_format(($row['runs'] / ($row['balls']/6)), 2)
        : "0.00";
    $avg  = $row['wickets'] > 0
        ? number_format($row['runs'] / $row['wickets'], 2)
        : "0.00";
    $rank_class = $rank == 1 ? "rank-1" : ($rank == 2 ? "rank-2" : "");
?>
<tr class="<?php echo $rank_class; ?>">
    <td><?php echo $rank++; ?></td>
    <td class="td-name"><?php echo $row['player_name']; ?></td>
    <td style="color:var(--text-secondary);font-size:0.85rem;"><?php echo $row['team_name']; ?></td>
    <td><?php echo $row['matches']; ?></td>
    <td class="td-score td-highlight"><?php echo $row['wickets']; ?></td>
    <td><?php echo $ov; ?></td>
    <td><?php echo $row['runs']; ?></td>
    <td><?php echo $econ; ?></td>
    <td><?php echo $avg; ?></td>
    <td style="color:var(--amber);"><?php echo $row['wides']; ?></td>
    <td style="color:var(--amber);"><?php echo $row['noballs']; ?></td>
</tr>
<?php } ?>
</tbody>
</table>
</div>
</div>

<br>
<div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
    <a href="most_runs.php?tournament_id=<?php echo $tournament_id; ?>" class="btn btn-secondary">Most Runs</a>
    <a href="../tournaments/points_table.php?tournament_id=<?php echo $tournament_id; ?>" class="btn btn-secondary">Points Table</a>
    <a href="/cricstat/index.php" class="btn btn-secondary">← Home</a>
</div>

</div>
</body>
</html>