<?php
require_once $_SERVER['DOCUMENT_ROOT']."/cricstat/db.php";

$tournaments = mysqli_query($conn,
    "SELECT t.*, 
        COUNT(DISTINCT te.team_id) AS team_count,
        COUNT(DISTINCT m.match_id) AS match_count
     FROM tournaments t
     LEFT JOIN teams te ON te.tournament_id = t.tournament_id
     LEFT JOIN matches m ON m.tournament_id = t.tournament_id
     GROUP BY t.tournament_id
     ORDER BY t.tournament_id DESC"
);
?>
<!DOCTYPE html>
<html>
<head>
<title>CricStat</title>
<link rel="stylesheet" href="/cricstat/assets/css/style.css">
</head>
<body>

<nav class="navbar">
    <a href="/cricstat/index.php" class="navbar-brand">Cric<span>Stat</span></a>
    <div class="navbar-links">
        <a href="stats/most_runs.php">Most Runs</a>
        <a href="stats/most_wickets.php">Most Wickets</a>
    </div>
</nav>

<div class="container">

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:0.75rem;">
    <h2 style="margin:0;">Tournaments</h2>
    <a href="tournaments/create_tournament.php" class="btn btn-primary">+ New Tournament</a>
</div>

<?php if(mysqli_num_rows($tournaments) == 0){ ?>
    <div class="alert alert-info">No tournaments yet. Create one to get started.</div>
<?php } else { ?>

<?php while($t = mysqli_fetch_assoc($tournaments)){ ?>
<div class="card card-accent">
    <div class="card-header">
        <h3 style="margin:0;"><?php echo $t['tournament_name']; ?></h3>
        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
            <a href="teams/add_team.php?tournament_id=<?php echo $t['tournament_id']; ?>" class="btn btn-secondary" style="font-size:0.85rem;padding:0.4rem 0.9rem;">Manage Teams</a>
            <a href="matches/create_match.php?tournament_id=<?php echo $t['tournament_id']; ?>" class="btn btn-primary" style="font-size:0.85rem;padding:0.4rem 0.9rem;">New Match</a>
            <a href="tournaments/points_table.php?tournament_id=<?php echo $t['tournament_id']; ?>" class="btn btn-secondary" style="font-size:0.85rem;padding:0.4rem 0.9rem;">Points Table</a>
        </div>
    </div>

    <p style="margin:0 0 0.75rem;font-size:0.85rem;">
        <?php echo $t['team_count']; ?> teams &nbsp;·&nbsp;
        <?php echo $t['match_count']; ?> matches
    </p>

    <?php
    $matches = mysqli_query($conn,
        "SELECT m.*, t1.team_name AS team1_name, t2.team_name AS team2_name
         FROM matches m
         JOIN teams t1 ON t1.team_id = m.team1_id
         JOIN teams t2 ON t2.team_id = m.team2_id
         WHERE m.tournament_id='".$t['tournament_id']."'
         ORDER BY m.match_id DESC"
    );
    if(mysqli_num_rows($matches) > 0){ ?>
    <div class="section-title">Matches</div>
    <?php while($m = mysqli_fetch_assoc($matches)){
        $status = $m['result']
            ? "<span class='badge badge-green'>✔ Completed</span>"
            : "<span class='badge badge-amber'>⏳ In Progress</span>";
        $link = $m['result']
            ? "<a href='matches/scorecard.php?match_id=".$m['match_id']."' class='btn btn-secondary' style='font-size:0.8rem;padding:0.3rem 0.7rem;'>Scorecard</a>"
            : "<a href='matches/score_match.php?match_id=".$m['match_id']."' class='btn btn-primary' style='font-size:0.8rem;padding:0.3rem 0.7rem;'>Continue</a>";
    ?>
    <div style="display:flex;align-items:center;justify-content:space-between;padding:0.5rem 0;border-bottom:1px solid var(--border);flex-wrap:wrap;gap:0.5rem;">
        <div>
            <span style="font-weight:500;"><?php echo $m['team1_name']; ?> vs <?php echo $m['team2_name']; ?></span>
            &nbsp; <?php echo $status; ?>
            <?php if($m['result']){ ?>
            <br><small><?php echo $m['result']; ?></small>
            <?php } ?>
        </div>
        <?php echo $link; ?>
    </div>
    <?php } ?>
    <?php } ?>
</div>
<?php } ?>
<?php } ?>

</div>
</body>
</html>