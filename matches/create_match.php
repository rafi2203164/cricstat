<?php
require_once $_SERVER['DOCUMENT_ROOT']."/cricstat/db.php";

$tournament_id = $_GET['tournament_id'] ?? 0;

if(isset($_POST['submit'])){
    $team1 = $_POST['team1'];
    $team2 = $_POST['team2'];
    $tid   = $_POST['tournament_id'];
    $overs = $_POST['overs'];

    if($team1 == $team2){
        $error = "Team 1 and Team 2 cannot be the same!";
    } else {
        $in_progress = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT match_id FROM matches
             WHERE tournament_id='$tid'
             AND result IS NULL
             AND winning_team IS NULL
             LIMIT 1"
        ));
        if($in_progress){
            $error = "There is already a match in progress.";
            $in_progress_id = $in_progress['match_id'];
        } else {
            $sql = "INSERT INTO matches (tournament_id, team1_id, team2_id, total_overs)
            VALUES ('$tid','$team1','$team2','$overs')";
            mysqli_query($conn, $sql);
            $match_id = mysqli_insert_id($conn);
            header("Location: score_match.php?match_id=".$match_id);
            exit;
        }
    }
}

$tournament = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT tournament_name FROM tournaments WHERE tournament_id='$tournament_id'"
));

$team_count = mysqli_num_rows(mysqli_query($conn,
    "SELECT team_id FROM teams WHERE tournament_id='$tournament_id'"
));
?>
<!DOCTYPE html>
<html>
<head>
<title>Create Match</title>
<link rel="stylesheet" href="/cricstat/assets/css/style.css">
</head>
<body>

<nav class="navbar">
    <a href="/cricstat/index.php" class="navbar-brand">Cric<span>Stat</span></a>
    <div class="navbar-links">
        <a href="/cricstat/index.php">Home</a>
    </div>
</nav>

<div class="container-sm">

<h2>Create Match</h2>
<p style="margin-bottom:1.5rem;">
    <?php echo $tournament['tournament_name'] ?? 'Unknown'; ?>
</p>

<?php if(isset($error)){ ?>
<div class="alert alert-error">
    <?php echo $error; ?>
    <?php if(isset($in_progress_id)){ ?>
    &nbsp; <a href="score_match.php?match_id=<?php echo $in_progress_id; ?>">Continue that match →</a>
    <?php } ?>
</div>
<?php } ?>

<?php if($team_count < 2){ ?>
<div class="alert alert-warning">
    You need at least 2 teams before creating a match.
    <br><br>
    <a href="../teams/add_team.php?tournament_id=<?php echo $tournament_id; ?>" class="btn btn-primary">Add Teams</a>
</div>
<?php } else { ?>

<div class="card">
<form method="POST">
<input type="hidden" name="tournament_id" value="<?php echo $tournament_id; ?>">

<div class="form-group">
    <label>Team 1 (Batting First)</label>
    <select name="team1" class="form-control">
    <?php
    $teams1 = mysqli_query($conn, "SELECT * FROM teams WHERE tournament_id='$tournament_id'");
    while($row = mysqli_fetch_assoc($teams1)){
        $pc = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT COUNT(*) AS cnt FROM players WHERE team_id='".$row['team_id']."'"
        ))['cnt'];
        echo '<option value="'.$row['team_id'].'">'.$row['team_name'].' ('.$pc.' players)</option>';
    }
    ?>
    </select>
</div>

<div class="form-group">
    <label>Team 2 (Bowling First)</label>
    <select name="team2" class="form-control">
    <?php
    $teams2 = mysqli_query($conn, "SELECT * FROM teams WHERE tournament_id='$tournament_id'");
    while($row = mysqli_fetch_assoc($teams2)){
        $pc = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT COUNT(*) AS cnt FROM players WHERE team_id='".$row['team_id']."'"
        ))['cnt'];
        echo '<option value="'.$row['team_id'].'">'.$row['team_name'].' ('.$pc.' players)</option>';
    }
    ?>
    </select>
</div>

<div class="form-group">
    <label>Overs</label>
    <input type="number" name="overs" class="form-control"
        value="20" min="1" max="50" required style="max-width:120px;">
</div>

<button type="submit" name="submit" class="btn btn-primary">Start Match</button>

</form>
</div>

<?php } ?>

<br>
<a href="../teams/add_team.php?tournament_id=<?php echo $tournament_id; ?>" class="btn btn-secondary">← Back to Teams</a>

</div>
</body>
</html>