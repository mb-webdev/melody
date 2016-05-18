<?php

require_once 'vendor/autoload.php';
require_once 'inc/Common.php';

session_start();
if (!isset($_SESSION['install']) || !$_SESSION['install']) {
    header('Location: ./index.php');
} else {
    $common = new Common();

    $stepNumber = 2;

    $form = $common->getConfigurator();
    $_SESSION['configure'] = false;
    if ($form === true) {
        $_SESSION['configure'] = true;
        header('Location: ./finalize.php');
    } else {
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
                    ?><form action="./configure.php" method="post"><?php
                        echo $form;
                        ?><div class="page-navigation"><?php
                            ?><p>Warning. The installation process can take between 3 and 15 minutes, depending the list of tasks to execute. Don't be affraid, the page will still be displayed at the end. Just press start and go take a coffee.</p><?php
                            ?><a class="btn" href="./configure.php">Reset</a><?php
                            ?><button type="submit" class="btn btn-primary">Submit</button><?php
                        ?></div><?php
                    ?></form><?php
                ?></div><?php
            ?></body><?php
        ?></html><?php
    }
}