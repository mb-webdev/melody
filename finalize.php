<?php

require_once 'vendor/autoload.php';
require_once 'inc/Common.php';

session_start();
if (!isset($_SESSION['configure']) || !$_SESSION['configure']) {
    header('Location: ./index.php');
} else {
    $common = new Common();
    $steps = $common->finalizeInstall();
    $_SESSION['finalize'] = $steps['status'];

    $stepNumber = 3;

    ?><html><?php
        ?><head><?php
            ?><title><?php echo $common->getKey('title') ?> Installer | <?php echo $common->getKey('steps')[$stepNumber] ?></title><?php
            ?><link href='https://fonts.googleapis.com/css?family=Open+Sans:400,300,400italic,700,700italic,300italic' rel='stylesheet' type='text/css'><?php
            ?><link rel="stylesheet" href="./assets/css/style.css"><?php
            ?><link rel="stylesheet" href="./assets/css/font-awesome.min.css"><?php
        ?></head><?php
        ?><body><?php
            ?><img src="./assets/img/logo.png" alt="logo" /><?php
            ?><div><?php
                echo $common->generateSteps($common->getKey('steps')[$stepNumber]);
                ?><table><?php
                    foreach ($steps as $step => $content) {
                        if ($step != "status") {
                            ?><tr><td class="title"><?php echo $step ?></td><td class="title"><i class="fa <?php echo ($content['status'] ? 'fa-check green' : 'fa-close red')?>" aria-hidden="true"></i></td></tr><?php
                            if (!$content['status']) {
                                foreach ($content['content'] as $line) {
                                    ?><tr><td><?php echo $line ?></td></tr><?php
                                }
                            }
                        }
                    }
                ?></table><?php
                ?><div class="page-navigation"><?php
                    if ($steps['status'] !== true) {
                        ?><a class="btn" href="./finalize.php">Retry</a><?php
                    } else {
                        ?><p>It's finished. Now the installer will remove itself. Enjoy your life and be happy ;-)</p><?php
                        ?><a class="btn btn-primary" href="./remove.php">Finish and remove installer</a><?php
                    }
                ?></div><?php
            ?></div><?php
        ?></body><?php
    ?></html><?php
}