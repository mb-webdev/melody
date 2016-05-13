<?php

require_once 'vendor/autoload.php';
require_once 'inc/Common.php';

session_start();

$common = new Common();
$_SESSION['index'] = true;

$stepNumber = 0;

?><html><?php
    ?><head><?php
        ?><title><?php echo $common->getKey('title') ?> Installer | <?php echo $common->getKey('steps')[$stepNumber] ?></title><?php
        ?><link href='https://fonts.googleapis.com/css?family=Open+Sans:400,300,400italic,700,700italic,300italic' rel='stylesheet' type='text/css'><?php
        ?><link rel="stylesheet" href="./assets/css/style.css"><?php
    ?></head><?php
    ?><body><?php
        ?><img src="./assets/img/logo.png" alt="logo" /><?php
        ?><div><?php
            echo $common->generateSteps($common->getKey('steps')[$stepNumber]);
            /**
             * Replace the next texts with your own messages
             */
            ?><h1>Installer</h1><?php
            ?><p>To use this installer, simply follow these rules :</p><?php
            ?><ol><?php
                ?><li>Put the zip of your project into the folder "melody/resources". The zip must contain your symfony project without the vendors and without the "app/config/parameters.yml" file.</li><?php
                ?><li>Open the file "melody/resources/config.yml" and edit the key "archive" to match with your zip name.</li><?php
                ?><li>In this configuration file, edit the key "requirements" to match with your requirements.<?php
                    ?><ul><?php
                        ?><li>You need to split your requirement into sections (you can name them as you want).</li><?php
                        ?><li>The "key" in the requirement is the path to find it in the PHPinfo function.</li><?php
                        ?><li>The message, is the error message which appear if the requirement does not match.</li><?php
                        ?><li>Starting with &lt;, &lt;=, &gt;, or &gt;= will validate number.</li><?php
                        ?><li>Starting with ~ will check if the value is in the text.</li><?php
                        ?><li>Do not starting with a prefix will check if the value is exactly as the text.</li><?php
                    ?></ul><?php
                ?></li><?php
                ?><li>Add a sql script in "melody/resources" if you want.</li><?php
                ?><li>If you added a sql script, then edit the key "sql" to match with the filename. Else, remove the key.</li><?php
                ?><li>If you want to perform custom actions after the installation, edit the key "after_install" to add all your unix commands. Else, just remove this part.</li><?php
                ?><li>You can change or add new tab names with the key "steps". Just remember to edit php files to change the step number.</li><?php
                ?><li>Finally, change the key "title" to match with your project's name.</li><?php
                ?><li>Don't forget to change the file "melody/assets/img/logo.png". And you can of course edit the css file as much as you want.</li><?php
                ?><li>And, or course, you can do whatever changes you want to this installer to match your mind. Just do what the fuck you want.</li><?php
            ?></ol><?php
            ?><hr/><?php
            ?><p>This project is under the "Do What The Fuck You Want To Public License" and described as following :</p><?php
            ?><p>DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE</p><?php
            ?><p>Version 2, December 2004</p><?php
            ?><p></p><?php
            ?><p>Copyright (C) 2004 Sam Hocevar sam@hocevar.net</p><?php
            ?><p></p><?php
            ?><p>Everyone is permitted to copy and distribute verbatim or modified copies of this license document, and changing it is allowed as long as the name is changed.</p><?php
            ?><p></p><?php
            ?><p>DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE</p><?php
            ?><p>TERMS AND CONDITIONS FOR COPYING, DISTRIBUTION AND MODIFICATION</p><?php
            ?><p></p><?php
            ?><p>0. You just DO WHAT THE FUCK YOU WANT TO.</p><?php
        ?></div><?php
        ?><div class="page-navigation"><?php
            ?><a class="btn btn-primary" href="./check.php"><?php echo $common->getKey('steps')[$stepNumber +1] ?></a><?php
        ?></div><?php
    ?></body><?php
?></html>