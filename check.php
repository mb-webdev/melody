<?php

require_once 'vendor/autoload.php';
require_once 'inc/Common.php';

session_start();
if (!isset($_SESSION['index']) || !$_SESSION['index']) {
    header('Location: ./index.php');
} else {
    $common = new Common();
    $result = $common->checkPHPInfo($common->getKey('requirements'));
    $_SESSION['check'] = $result['status'];

    $stepNumber = 1;

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

                if (count($result['global']) > 0) {
                    ?><ul class="error"><?php
                        foreach ($result['global'] as $error) {
                            ?><li><?php echo $error ?></li><?php
                        }
                    ?></ul><?php
                }

                ?><table><?php
                    foreach ($result['sections'] as $section => $rules) {
                        ?><tr><td colspan="3" class="title"><?php echo $section ?></td></tr><?php
                        foreach ($rules as $key => $value) {
                            if (!$value) {
                                $result['status'] = false;
                            }
                            ?><tr><td><?php echo $key ?></td><td><i class="fa <?php echo ($value ? 'fa-check green' : 'fa-close red')?>" aria-hidden="true"></i></td></tr><?php
                        }
                    }
                ?></table><?php
            ?></div><?php
            ?><div class="page-navigation"><?php
                ?><a class="btn" href="./index.php">Back</a><?php
                if ($result['status'] === false) :
                    ?><a class="btn btn-primary" href="./check.php">Retry</a><?php
                else :
                    ?><a class="btn btn-primary" href="./install.php">Begin install !</a><?php
                endif;
            ?></div><?php
        ?></body><?php
    ?></html><?php
}