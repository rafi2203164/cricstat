<?php
require_once $_SERVER['DOCUMENT_ROOT']."/cricstat/db.php";

$tournament_id = $_GET['tournament_id'] ?? 0;

if($tournament_id == 0){
    $tournaments = mysqli_query($conn, "SELECT * FROM tournaments ORDER BY tournament_id DESC");
    ?>
    <!DOCTYPE html>
    <html>
    <head>
    <title>Most Runs</title>
    <link rel="stylesheet" href="/cricstat/assets/css/style.css">
    </head>
    <body>
    <nav class="navbar">
        <a href="/cricstat/index.php" class="navbar-brand">Cric<span>Stat</span></a>
        <div class="navbar-links"><a href="/cricstat/index.php">Home</a></div>
    </nav>
    <div class="container-sm">
    <h2>Most Runs</h2>
    <p>Select a tournament:</p>
    <?php while($t = mysqli_fetch_assoc($tournaments)){ ?>
    <a href="most_runs.php?tournament_id=<?php echo $t['tournament_id']; ?>"
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
        SUM(b.runs_batsman) AS runs,
        SUM(CASE WHEN b.extra_type IS NULL OR b.extra_type NOT IN ('wide','noball')
            THEN 1 ELSE 0 END) AS balls,
        SUM(CASE WHEN b.runs_batsman=4 AND b.extra_type IS NULL THEN 1 ELSE 0 END) AS fours,
        SUM(CASE WHEN b.runs_batsman=6 AND b.extra_type IS NULL THEN 1 ELSE 0 END) AS sixes,
        COUNT(DISTINCT b.match_id) AS matches,
        SUM(CASE WHEN b.wicket=1 THEN 1 ELSE 0 END) AS dismissals
     FROM balls b
     JOIN players p ON p.player_id = b.batsman_id
     JOIN teams t ON t.team_id = p.team_id
     JOIN matches m ON m.match_id = b.match_id
     WHERE m.tournament_id='$tournament_id'
     GROUP BY b.batsman_id
     ORDER BY runs DESC"
);
?>
<!DOCTYPE html>
<html>
<head>
<title>Most Runs</title>
<link rel="stylesheet" href="/cricstat/assets/css/style.css">
</head>
<body>

<nav class="navbar">
    <a href="/cricstat/index.php" class="navbar-brand">Cric<span>Stat</span></a>
    <div class="navbar-links">
        <a href="most_wickets.php?tournament_id=<?php echo $tournament_id; ?>">Most Wickets</a>
        <a href="/cricstat/index.php">Home</a>
    </div>
</nav>

<div class="container">

<div style="margin-bottom:1.5rem;">
    <h2 style="margin:0;">Most Runs</h2>
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
    <th>Runs</th>
    <th>Balls</th>
    <th>SR</th>
    <th>4s</th>
    <th>6s</th>
    <th>Avg</th>
</tr>
</thead>
<tbody>
<?php
$rank = 1;
while($row = mysqli_fetch_assoc($result)){
    $sr  = $row['balls'] > 0
        ? number_format(($row['runs'] / $row['balls']) * 100, 1)
        : "0.0";
    $avg = $row['dismissals'] > 0
        ? number_format($row['runs'] / $row['dismissals'], 2)
        : ($row['runs'] > 0 ? $row['runs']."*" : "0.00");
    $rank_class = $rank == 1 ? "rank-1" : ($rank == 2 ? "rank-2" : "");
?>
<tr class="<?php echo $rank_class; ?>">
    <td><?php echo $rank++; ?></td>
    <td class="td-name"><?php echo $row['player_name']; ?></td>
    <td style="color:var(--text-secondary);font-size:0.85rem;"><?php echo $row['team_name']; ?></td>
    <td><?php echo $row['matches']; ?></td>
    <td class="td-score td-highlight"><?php echo $row['runs']; ?></td>
    <td><?php echo $row['balls']; ?></td>
    <td><?php echo $sr; ?></td>
    <td><?php echo $row['fours']; ?></td>
    <td><?php echo $row['sixes']; ?></td>
    <td><?php echo $avg; ?></td>
</tr>
<?php } ?>
</tbody>
</table>
</div>
</div>

<br>
<div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
    <a href="most_wickets.php?tournament_id=<?php echo $tournament_id; ?>" class="btn btn-secondary">Most Wickets</a>
    <a href="../tournaments/points_table.php?tournament_id=<?php echo $tournament_id; ?>" class="btn btn-secondary">Points Table</a>
    <a href="/cricstat/index.php" class="btn btn-secondary">← Home</a>
</div>

</div>
</body>
</html>