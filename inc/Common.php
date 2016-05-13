<?php
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
class Common {
    protected $cache;
    protected $installationDir;
    protected $melodyDir;
    protected $resourcesDir;

    public function __construct()
    {
        $this->cache = array();
        $this->installationDir = realpath(dirname(__FILE__) . '/../../..');
        $this->melodyDir = realpath($this->installationDir . '/web/melody');
        $this->resourcesDir = realpath($this->melodyDir . '/resources');
    }

    public function generateSteps($current)
    {
        $steps = $this->getKey('steps');

        $out = '<ul id="progress">';
        foreach ($steps as $step) {
            $out .= '<li' . ($step == $current ? ' class="active"' : '') . '>' . $step . '</li>';
        }
        $out .= '</ul>';

        return $out;
    }

    public function removeMelody()
    {
        $this->rrmdir($this->melodyDir);
    }

    public function finalizeInstall()
    {
        $output = array('status' => true);

        $key = 'Installing database';
        $sql = $this->installSql();
        if ($sql !== null) {
            $output[$key] = $sql;
            if ($output[$key]['status'] !== true) {
                $output['status'] = false;
                return $output;
            }
        }

        $key = 'Downloading Composer';
        $commandOutput = array();
        $return = 0;
        exec('wget https://getcomposer.org/composer.phar -O composer.phar', $commandOutput, $return);
        $output[$key] = $this->fillReport(($return === 0), $commandOutput);
        if ($output[$key]['status'] !== true) {
            $output['status'] = false;
            return $output;
        }

        $key = 'Doownloading and installing vendors';
        $commandOutput = array();
        $return = 0;
        putenv('COMPOSER_HOME=' . $this->melodyDir . '/composer');
        exec('cd ' . $this->installationDir . '; php ' . $this->melodyDir . '/composer.phar install', $commandOutput, $return);
        $output[$key] = $this->fillReport(($return === 0), $commandOutput);
        if ($output[$key]['status'] !== true) {
            $output['status'] = false;
            return $output;
        }

        $key = 'Running custom scripts';
        $scripts = $this->installCustomScripts();
        if ($scripts !== null) {
            $output[$key] = $scripts;
            if ($output[$key]['status'] !== true) {
                $output['status'] = false;
                return $output;
            }
        }

        return $output;
    }

    public function getConfigurator()
    {
        if (!file_exists($this->installationDir .  '/app/config/parameters.yml.dist')) {
            return "<p>File '" . realpath($this->installationDir . '/app/config/parameters.yml.dist') . "' does not exists !</p>";
        }

        $parameters = file_get_contents($this->installationDir . '/app/config/parameters.yml.dist');
        $parser = new Parser();
        try {
            $parameters = $parser->parse($parameters);
        } catch(ParseException $e) {
            return "<p>Abording, " . $e->getMessage() . "</p>";
        }

        return $this->getForm($parameters['parameters']);
    }

    public function deployArchive()
    {
        $output = array();
        $output['status'] = true;
        $archive = $this->resourcesDir . '/' . $this->getKey('archive');
        if (!file_exists($archive)) {
            $output['extract'] = "Abording, archive '" . $archive . "' not found !";
            $output['status'] = false;
        } else if (!is_writable($this->installationDir)) {
            $output['extract'] = "Abording, directory '" . $this->installationDir . "' is not writable !";
            $output['status'] = false;
        } else {
            $zip = new ZipArchive();
            if ($zip->open($archive) === true) {
                $zip->extractTo($this->installationDir);
            } else {
                $output['extract'] = "Abording, archive '" . $archive . "' not readable !";
                $output['status'] = false;
            }
        }

        return $output;
    }

    public function getKey($path)
    {
        $keys = explode('.', $path);

        $node = $this->getConfig();
        foreach ($keys as $key) {
            if (isset($node[$key])) {
                $node = $node[$key];
            } else {
                return null;
            }
        }

        return $node;
    }

    public function checkPHPInfo(array $requirements)
    {
        $result = array(
            'global' => array(),
            'sections' => array(),
            'status' => true
        );

        $return = 0;
        $commandOutput = array();
        exec('php -v', $commandOutput, $return);
        if ($return !== 0) {
            $result['global'][] = 'PHP must be runnable in CLI mode.';
            $result['status'] = false;
        }

        foreach ($requirements as $identifier => $section) {
            $result['sections'][$identifier] = array();

            foreach ($section as $assert) {
                $path = $assert['key'];
                $value = $assert['value'];
                $message = $assert['message'];
                $keys = explode('.', $path);

                $node = $this->getPHPInfo();
                foreach ($keys as $key) {
                    if (isset($node[$key])) {
                        $node = $node[$key];
                    } else {
                        $result['global'][] = "key '" . $path . "' not found in PHPInfo !";
                        $result['status'] = false;
                    }
                }

                $result['sections'][$identifier][$message] = false;

                $current = $node;

                $operator = substr($value, 0, 1);
                if ($operator == '<' || $operator == '>') {
                    $current = $this->version2number($node);
                    $match = $this->version2number($value);
                    $numbers = $this->pad($current, $match);
                }
                if ($operator == '<') {
                    if (substr($value, 1, 1) == '=') {
                        $result['sections'][$identifier][$message] = ($numbers[0] <= $numbers[1]);
                    } else {
                        $result['sections'][$identifier][$message] = ($numbers[0] < $numbers[1]);
                    }
                } else if ($operator == '>') {
                    if (substr($value, 1, 1) == '=') {
                        $result['sections'][$identifier][$message] = ($numbers[0] >= $numbers[1]);
                    } else {
                        $result['sections'][$identifier][$message] = ($numbers[0] > $numbers[1]);
                    }
                } else if ($operator == '~') {
                    $match = substr($value, 1);
                    $result['sections'][$identifier][$message] = (strpos($current, $match) !== false);
                } else {
                    $result['sections'][$identifier][$message] = ($current == $value);
                }
            }
        }

        return $result;
    }

    private function rrmdir($dir) {
       if (is_dir($dir)) {
         $objects = scandir($dir);
         foreach ($objects as $object) {
           if ($object != "." && $object != "..") {
             if (filetype($dir."/".$object) == "dir") $this->rrmdir($dir."/".$object); else unlink($dir."/".$object);
           }
         }
         reset($objects);
         rmdir($dir);
       }
    }

    private function fillReport($status, array $content = null)
    {
        $output = array(
                'status' => $status,
                'content' => $content
        );
        return $output;
    }

    private function installCustomScripts()
    {
        $scripts = $this->getKey('after_install');
        if ($scripts !== null) {
            $content = array();
            $return = true;

            foreach ($scripts as  $script) {
                $commandReturn = 0;
                $content[] = '===========================================================';
                $content[] = '---> Executing command :' . $script;
                $content[] = '===========================================================';
                exec('cd ' . $this->installationDir . '; ' . $script, $content, $commandReturn);
                if ($commandReturn !== 0) {
                    $return = false;
                }
            }
            return $this->fillReport($return, $content);
        }
        return null;
    }

    private function installSql()
    {
        $errors = array();
        $file = $this->getKey('sql');
        if ($file) {
            $file = $this->resourcesDir . '/' . $file;
            if (file_exists($file)) {
                $config = file_get_contents($this->installationDir . '/app/config/parameters.yml');
                $config = Yaml::parse($config);
                $config = $config['parameters'];

                try {
                    $host = $config['database_host'];
                    $port = $config['database_port'];
                    $name = $config['database_name'];
                    $user = $config['database_user'];
                    $pswd = $config['database_password'];
                    if ($port != '') {
                        $conn = new PDO('mysql: host=' . $host . ';dbname=' . $name . ';port=' . $port, $user, $pswd);
                    } else {
                        $conn = new PDO('mysql: host=' . $host . ';dbname=' . $name, $user, $pswd);
                    }
                    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    $query = file_get_contents($file);
                    $conn->exec($query);

                } catch (PDOException $e) {
                    $errors[] = "Connection failed: " . $e->getMessage();
                }
            } else {
                $errors[] = "SQL file '" . $file . "' not found.";
            }
        } else {
            return null;
        }

        if (!empty($errors)) {
            return $this->fillReport(false, $errors);
        } else {
            return $this->fillReport(true);
        }
    }

    private function getForm($config)
    {
        $output = "";
        $errors = $this->validateForm();
        if (!empty($errors)) {
            $output .= '<ul class="error">';
            foreach ($errors as $error) {
                $output .= '<li>' . $error . '</li>';
            }
            $output .= '</ul>';
        } else if (!empty($_POST)) {
            $this->writeSFConf();
            return true;
        }
        $output .= "<table>";

        foreach ($config as $key => $field) {
            $isPassword = false;
            if (strpos($key, 'password') !== false) {
                $isPassword = true;
            }
            $value = $field;
            if (isset($_POST[$key])) {
                $value = $_POST[$key];
            }
            $output .= "<tr><td><label for='" . $key . "'>" . $key . "</label></td><td><input type='" . ($isPassword ? 'password' : 'text') . "' id='" . $key . "' name='" . $key . "' value='" . str_replace("'", "\'", $value) . "'/></td></tr>";
        }
        $output .= '</table>';
        return $output;
    }

    private function writeSFConf()
    {
        $params = array('parameters' => array());
        foreach ($_POST as $key => $field) {
            $params['parameters'][$key] = $field;
        }

        $yaml = Yaml::dump($params, 2);
        file_put_contents($this->installationDir . '/app/config/parameters.yml', $yaml);
    }
    private function validateForm()
    {
        $errors = array();
        if (isset($_POST) && !empty($_POST)) {
            foreach ($_POST as $key => $value) {
                if (trim($value) == '') {
                    $_POST[$key] = null;
                }
            }

            if (!isset($_POST['database_host']) || $_POST['database_host'] === null) {
                $errors[] = 'The database host must be defined.';
            }
            if (isset($_POST['database_port']) && $_POST['database_port'] !== null && !ctype_digit($_POST['database_port'])) {
                $errors[] = 'The database port must be empty or filled with a numeric value.';
            }
            if (!isset($_POST['database_name']) || $_POST['database_name'] === null) {
                $errors[] = 'The database name must be defined.';
            }
            if (!isset($_POST['database_user']) || $_POST['database_user'] === null) {
                $errors[] = 'The database user must be defined.';
            }
            if (!isset($_POST['mailer_transport']) || !in_array($_POST['mailer_transport'], array('smtp', 'mail', 'sendmail', 'gmail'))) {
                $errors[] = 'The mailer transport must be defined with one of these values : smtp, mail, sendmail or gmail.';
            }
            if (!isset($_POST['mailer_host']) || $_POST['mailer_host'] === null) {
                $errors[] = 'The mailer host must be defined.';
            }
            if (!isset($_POST['secret']) || $_POST['secret'] === null || $_POST['secret'] == 'ThisTokenIsNotSoSecretChangeIt') {
                $errors[] = 'The secret key must be filled and different from the default value.';
            }

            if (empty($errors)) {
                try {
                    $host = $_POST['database_host'];
                    $port = $_POST['database_port'];
                    $name = $_POST['database_name'];
                    $user = $_POST['database_user'];
                    $pswd = $_POST['database_password'];
                    if ($port != '') {
                        $conn = new PDO('mysql: host=' . $host . ';dbname=' . $name . ';port=' . $port, $user, $pswd);
                    } else {
                        $conn = new PDO('mysql: host=' . $host . ';dbname=' . $name, $user, $pswd);
                    }
                    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                } catch (PDOException $e) {
                    $errors[] = "Connection failed: " . $e->getMessage();
                }
            }
        }

        return $errors;
    }

    private function version2number($str)
    {
        $operator = substr($str, 0, 1);
        $equal = substr($str, 1, 1);

        $number = null;
        if ($operator == '<' || $operator == '>') {
            $number = substr($str, 1);

            if ($equal == '=') {
                $number = substr($str, 2);
            }
        } else {
            $number = $str;
        }

        $number = explode('.', $number);
        $number = trim(implode('', $number));

        return $number;
    }

    private function pad($number1, $number2)
    {
        $number1 = intval($number1, 10);
        $number2 = intval($number2, 10);
        $len1 = strlen($number1);
        $len2 = strlen($number2);

        $diff = abs($len1 - $len2);
        $pad = '';
        if ($diff > 0) {
            for ($i = 0; $i < $diff; $i++) {
                $pad .= 0;
            }

            if ($len1 > $len2) {
                $number2 .= $pad;
            } else {
                $number1 .= $pad;
            }
        }
        return array(intval($number1, 10), intval($number2, 10));
    }

    private function getConfig()
    {
        if (!isset($this->cache['getConfig'])) {
            if (!file_exists($this->resourcesDir . '/config.yml')) {
                echo "Abording, configuration file does not exists !";
                die();
            }

            $content = file_get_contents($this->resourcesDir . '/config.yml');
            $parser = new Parser();
            try {
                $content = $parser->parse($content);
            } catch(ParseException $e) {
                echo "Abording, " . $e->getMessage();
                die();
            }

            $this->cache['getConfig'] = $content;
        }
        return $this->cache['getConfig'];
    }

    private function getPHPInfo()
    {
        if (!isset($this->cache['getPHPInfo'])) {
            ob_start(); phpinfo(INFO_MODULES); $s = ob_get_contents(); ob_end_clean();
            $s = strip_tags($s, '<h2><th><td>');
            $s = preg_replace('/<th[^>]*>([^<]+)<\/th>/', '<info>\1</info>', $s);
            $s = preg_replace('/<td[^>]*>([^<]+)<\/td>/', '<info>\1</info>', $s);
            $t = preg_split('/(<h2[^>]*>[^<]+<\/h2>)/', $s, -1, PREG_SPLIT_DELIM_CAPTURE);
            $r = array(); $count = count($t);
            $p1 = '<info>([^<]+)<\/info>';
            $p2 = '/'.$p1.'\s*'.$p1.'\s*'.$p1.'/';
            $p3 = '/'.$p1.'\s*'.$p1.'/';
            for ($i = 1; $i < $count; $i++) {
                if (preg_match('/<h2[^>]*>([^<]+)<\/h2>/', $t[$i], $matchs)) {
                    $name = trim($matchs[1]);
                    $vals = explode("\n", $t[$i + 1]);
                    foreach ($vals AS $val) {
                        if (preg_match($p2, $val, $matchs)) { // 3cols
                            $r[$name][trim($matchs[1])] = array(trim($matchs[2]), trim($matchs[3]));
                        } elseif (preg_match($p3, $val, $matchs)) { // 2cols
                            $r[$name][trim($matchs[1])] = trim($matchs[2]);
                        }
                    }
                }
            }
            $this->cache['getPHPInfo'] = $r;
        }
        return $this->cache['getPHPInfo'];
    }
}