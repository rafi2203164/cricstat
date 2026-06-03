<?php
require_once $_SERVER['DOCUMENT_ROOT']."/cricstat/auth.php";
require_once $_SERVER['DOCUMENT_ROOT']."/cricstat/db.php";

$tournament_id = $_GET['tournament_id'] ?? 0;
if($tournament_id == 0){ echo "Invalid tournament."; exit; }

$tournament = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT * FROM tournaments WHERE tournament_id='$tournament_id'"
));
$squad_size = $tournament['squad_size'] ?? 11;
if(!$tournament){ echo "Tournament not found."; exit; }

$last_team_id = $_GET['team_id'] ?? 0;

if(isset($_POST['add_team'])){
    $team_name = trim($_POST['team_name']);
    if($team_name != ""){
        mysqli_query($conn,
            "INSERT INTO teams (team_name, tournament_id)
             VALUES ('$team_name','$tournament_id')"
        );
        $new_team_id = mysqli_insert_id($conn);
    }
    header("Location: add_team.php?tournament_id=".$tournament_id."#team_".$new_team_id);
    exit;
}

if(isset($_POST['add_player'])){
    $player_name = trim($_POST['player_name']);
    $team_id     = $_POST['team_id'];
    if($player_name != ""){
        mysqli_query($conn,
            "INSERT INTO players (player_name, team_id)
             VALUES ('$player_name','$team_id')"
        );
    }
    header("Location: add_team.php?tournament_id=".$tournament_id."&focus_team=".$team_id."#team_".$team_id);
    exit;
}

if(isset($_POST['delete_player'])){
    $player_id = $_POST['player_id'];
    $team_id   = $_POST['team_id'];
    mysqli_query($conn, "DELETE FROM players WHERE player_id='$player_id'");
    header("Location: add_team.php?tournament_id=".$tournament_id."#team_".$team_id);
    exit;
}

if(isset($_POST['delete_team'])){
    $team_id = $_POST['team_id'];
    mysqli_query($conn, "DELETE FROM players WHERE team_id='$team_id'");
    mysqli_query($conn, "DELETE FROM teams WHERE team_id='$team_id'");
    header("Location: add_team.php?tournament_id=".$tournament_id);
    exit;
}

$focus_team = $_GET['focus_team'] ?? 0;
?>
<!DOCTYPE html>
<html>
<head>
<title>Setup Teams</title>
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

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:0.75rem;">
    <div>
        <h2 style="margin:0;">Setup Teams</h2>
        <p style="margin:0.25rem 0 0;font-size:0.85rem;">
            <?php echo $tournament['tournament_name']; ?> &nbsp;·&nbsp;
            <?php echo $squad_size; ?> players per team
        </p>
    </div>
</div>

<!-- ADD TEAM FORM -->
<div class="card" style="margin-bottom:1.5rem;">
<h3 style="margin-bottom:0.75rem;">Add New Team</h3>
<form method="POST" style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:flex-end;">
    <div class="form-group" style="margin:0;flex:1;min-width:200px;">
        <label>Team Name</label>
        <input type="text" name="team_name" class="form-control"
            placeholder="e.g. Team Alpha" required>
    </div>
    <button type="submit" name="add_team" class="btn btn-primary">Add Team</button>
</form>
</div>

<hr>

<!-- TEAMS LIST -->
<?php
$teams = mysqli_query($conn,
    "SELECT * FROM teams WHERE tournament_id='$tournament_id' ORDER BY team_id ASC"
);
if(mysqli_num_rows($teams) == 0){
    echo "<div class='alert alert-info'>No teams added yet. Add your first team above.</div>";
}

while($team = mysqli_fetch_assoc($teams)){
    $tid   = $team['team_id'];
    $tname = $team['team_name'];
    $players = mysqli_query($conn,
        "SELECT * FROM players WHERE team_id='$tid' ORDER BY player_id ASC"
    );
    $player_count = mysqli_num_rows($players);
    $is_full = $player_count >= $squad_size;
?>

<div id="team_<?php echo $tid; ?>" class="card" style="margin-bottom:1rem;">

    <div class="card-header">
        <div>
            <h3 style="margin:0;"><?php echo $tname; ?></h3>
            <small style="color:<?php echo $is_full ? 'var(--green)' : 'var(--amber)'; ?>;">
                <?php echo $player_count."/".$squad_size; ?> players
                <?php echo $is_full ? "✔ Full squad" : ""; ?>
            </small>
        </div>
        <form method="POST" style="display:inline;">
        <input type="hidden" name="team_id" value="<?php echo $tid; ?>">
        <button type="submit" name="delete_team"
            onclick="return confirm('Delete <?php echo $tname; ?> and all its players?')"
            class="btn btn-danger">Delete Team</button>
        </form>
    </div>

    <!-- Player List -->
    <?php
    $players = mysqli_query($conn,
        "SELECT * FROM players WHERE team_id='$tid' ORDER BY player_id ASC"
    );
    ?>
    <div class="player-list" style="margin-bottom:0.75rem;">
    <?php while($p = mysqli_fetch_assoc($players)){ ?>
    <div class="player-item">
        <span>→ <?php echo $p['player_name']; ?></span>
        <form method="POST" style="display:inline;">
        <input type="hidden" name="player_id" value="<?php echo $p['player_id']; ?>">
        <input type="hidden" name="team_id"   value="<?php echo $tid; ?>">
        <button type="submit" name="delete_player" class="btn btn-danger"
            style="padding:0.15rem 0.5rem;font-size:0.75rem;">✕</button>
        </form>
    </div>
    <?php } ?>
    </div>

    <!-- Add Player Form -->
    <?php if(!$is_full){ ?>
    <form method="POST" style="display:flex;gap:0.5rem;align-items:flex-end;">
    <input type="hidden" name="team_id" value="<?php echo $tid; ?>">
    <div style="flex:1;">
        <input type="text" name="player_name"
            class="form-control"
            placeholder="Player name"
            id="player_input_<?php echo $tid; ?>"
            <?php echo ($focus_team == $tid) ? 'autofocus' : ''; ?>
            required>
    </div>
    <button type="submit" name="add_player" class="btn btn-primary">Add</button>
    </form>
    <?php } else { ?>
    <div class="alert alert-success" style="margin:0;padding:0.4rem 0.75rem;font-size:0.85rem;">
        Squad complete ✔
    </div>
    <?php } ?>

</div>

<?php } ?>

<hr>

<?php
$team_count = mysqli_num_rows(mysqli_query($conn,
    "SELECT team_id FROM teams WHERE tournament_id='$tournament_id'"
));
$incomplete = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS cnt FROM teams t
     WHERE t.tournament_id='$tournament_id'
     AND (SELECT COUNT(*) FROM players p WHERE p.team_id = t.team_id) < '$squad_size'"
));

if($team_count < 2){
    echo "<div class='alert alert-warning'>Add at least 2 teams to proceed.</div>";
} elseif($incomplete['cnt'] > 0){
    echo "<div class='alert alert-warning'>⚠️ Some teams don't have a full squad of $squad_size players yet.</div>";
    echo "<a href='../matches/create_match.php?tournament_id=$tournament_id' class='btn btn-secondary'>Create Match anyway</a>";
} else {
    echo "<a href='../matches/create_match.php?tournament_id=$tournament_id' class='btn btn-primary'>Next: Create Match →</a>";
}
?>

<br><br>
<a href="/cricstat/index.php" class="btn btn-secondary">← Home</a>

</div>

<?php if($focus_team){ ?>
<script>
window.addEventListener('load', function(){
    var input = document.getElementById('player_input_<?php echo $focus_team; ?>');
    if(input){
        input.focus();
        input.scrollIntoView({behavior:'smooth', block:'center'});
    }
});
</script>
<?php } ?>

</body>
</html>