<?php

require_once 'vendor/autoload.php';
require_once 'inc/Common.php';

session_start();
if (!isset($_SESSION['check']) || !$_SESSION['check']) {
    header('Location: ./index.php');
} else {
    $common = new Common();
    $installed = $common->deployArchive();

    $_SESSION['install'] = $installed->getStatus();

    if ($installed->getStatus()) {
        header('Location: ./configure.php');
    } else {

        $stepNumber = 2;

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
                        foreach ($installed->getContent() as $value) {
                            ?><tr><td><?php echo $value ?></td></tr><?php
                        }
                    ?></table><?php
                ?></div><?php
                ?><div class="page-navigation"><?php
                    ?><a class="btn" href="./check.php">Back</a><?php
                    if ($installed['status'] === false) :
                        ?><a class="btn btn-primary" href="./install.php">Retry</a><?php
                    endif;
                ?></div><?php
            ?></body><?php
        ?></html><?php
    }
}