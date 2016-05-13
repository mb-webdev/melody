<?php

require_once 'vendor/autoload.php';
require_once 'inc/Common.php';

session_start();
if (!isset($_SESSION['finalize']) || !$_SESSION['finalize']) {
    header('Location: ./index.php');
} else {
    $common = new Common();
    $common->removeMelody();
    header('Location: ../');
}