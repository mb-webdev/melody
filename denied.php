<?php

require_once 'vendor/autoload.php';
require_once 'inc/Common.php';

session_start();

$common = new Common();

?><html><?php
    ?><head><?php
        ?><title><?php echo $common->getKey('title') ?> Installer | Access Denied</title><?php
        ?><link href='https://fonts.googleapis.com/css?family=Open+Sans:400,300,400italic,700,700italic,300italic' rel='stylesheet' type='text/css'><?php
        ?><link rel="stylesheet" href="./assets/css/style.css"><?php
    ?></head><?php
    ?><body><?php
        ?><img src="./assets/img/logo.png" alt="logo" /><?php
        ?><div><?php
            ?><h1>Access Denied</h1><?php
            ?><p>You can't access to this installer, somebody else is already using it.</p><?php
            ?><p>If you think it's an error, please remove the file "<?php echo realpath(__DIR__ . '/../../melody.lock') ?>" and retry.</p><?php
            ?><div class="page-navigation"><?php
                ?><a class="btn btn-primary" href="./index.php">Retry</a><?php
            ?></div><?php
        ?></div><?php
    ?></body><?php
?></html>