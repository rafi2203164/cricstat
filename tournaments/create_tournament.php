<?php
<?php
require_once $_SERVER['DOCUMENT_ROOT']."/cricstat/auth.php";
require_once $_SERVER['DOCUMENT_ROOT']."/cricstat/db.php";

if(isset($_POST['submit'])){
    $tournament_name = trim($_POST['tournament_name']);
    $squad_size      = (int)$_POST['squad_size'];

    if($tournament_name == ""){
        $error = "Tournament name cannot be empty.";
    } elseif($squad_size < 2){
        $error = "Squad size must be at least 2.";
    } else {
        mysqli_query($conn,
            "INSERT INTO tournaments (tournament_name, squad_size)
             VALUES ('$tournament_name', '$squad_size')"
        );
        $new_id = mysqli_insert_id($conn);
        header("Location: ../teams/add_team.php?tournament_id=".$new_id);
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Create Tournament</title>
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

<h2>Create Tournament</h2>

<?php if(isset($error)){ ?>
<div class="alert alert-error"><?php echo $error; ?></div>
<?php } ?>

<div class="card">
<form method="POST">

<div class="form-group">
    <label>Tournament Name</label>
    <input type="text" name="tournament_name" class="form-control"
        placeholder="e.g. Premier League 2025" required>
</div>

<div class="form-group">
    <label>Players per Team</label>
    <input type="number" name="squad_size" class="form-control"
        value="11" min="2" max="15" required>
</div>

<button type="submit" name="submit" class="btn btn-primary">Create Tournament</button>

</form>
</div>

<br>
<a href="/cricstat/index.php" class="btn btn-secondary">← Back to Home</a>

</div>
</body>
</html>