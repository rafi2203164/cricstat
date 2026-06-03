<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT']."/cricstat/config.php";

if(isset($_POST['password'])){
    if($_POST['password'] == ADMIN_PASSWORD){
        $_SESSION['logged_in'] = true;
        header("Location: index.php");
        exit;
    } else {
        $error = "Wrong password.";
    }
}

if(isset($_SESSION['logged_in']) && $_SESSION['logged_in'] == true){
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<title>CricStat Login</title>
<link rel="stylesheet" href="/cricstat/assets/css/style.css">
</head>
<body>

<nav class="navbar">
    <a href="/cricstat/login.php" class="navbar-brand">Cric<span>Stat</span></a>
</nav>

<div class="container-sm" style="margin-top:4rem;">

<div class="card" style="max-width:380px;margin:0 auto;">
    <h2 style="margin-bottom:0.25rem;">Welcome Back</h2>
    <p style="margin-bottom:1.5rem;">Enter password to continue</p>

    <?php if(isset($error)){ ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
    <?php } ?>

    <form method="POST">
    <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" class="form-control"
            placeholder="Enter password" autofocus required>
    </div>
    <button type="submit" class="btn btn-primary" style="width:100%;">Login</button>
    </form>
</div>

</div>
</body>
</html>