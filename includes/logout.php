<?php

session_start();

$_SESSION = [];

session_destroy();

header("Location: ../users/login.php");
exit;