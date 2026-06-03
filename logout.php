<?php
session_start();
session_destroy();
header("Location: /cricstat/login.php");
exit;
?>