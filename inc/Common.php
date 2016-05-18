<?php
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
class Common {
    protected $cache;
    protected $installationDir;
    protected $melodyDir;
    protected $resourcesDir;
    protected $lockFile;

    public function __construct()
    {
        $this->cache = array();
        $this->installationDir = realpath(dirname(__FILE__) . '/../../..');
        $this->melodyDir = realpath(dirname(__FILE__) . '/..');
        $this->resourcesDir = realpath($this->melodyDir . '/resources');
        $this->lockFile = $this->installationDir . '/melody.lock';
    }

    /**
     * Check if the current user is allowed to access to the installer
     *
     * @return boolean
     */
    public function identifyAccess()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['identifier_token'])) {
            $token = md5(rand(0, time()));
            $_SESSION['identifier_token'] = $token;
        } else {
            $token = $_SESSION['identifier_token'];
        }

        if (!file_exists($this->lockFile)) {
            file_put_contents($this->lockFile, $token);
            return true;
        } else {
            $registeredToken = file_get_contents($this->lockFile);
            return ($registeredToken == $token);
        }
    }

    /**
     * Generate the breadcrumbs
     *
     * @param unknown $current
     * @return string
     */
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

    /**
     * Remove the installation directory and the lock file
     */
    public function removeMelody()
    {
        $this->rrmdir($this->melodyDir);
        if (file_exists($this->lockFile)) {
            unlink($this->lockFile);
        }
    }

    /**
     * Perform the following tasks :
     *     - install sql dump if defined
     *     - download composer if needed
     *     - download and install vendors if needed
     *     - install assets if needed
     *     - run custom commands if defined
     *
     * @return array
     */
    public function finalizeInstall()
    {
        $output = array('status' => true);

        /**
         * Installing sql dump
         */
        $key = 'Installing database';
        $sql = $this->installSql();
        if ($sql !== null) {
            $output[$key] = $sql;
            if (!$output[$key]->getStatus()) {
                $output['status'] = false;
                return $output;
            }
        }

        /**
         * Installing vendors
         */
        if ($this->needInstallVendors()) {
            $key = 'Downloading Composer';
            $commandOutput = array();
            $return = 0;
            exec('wget https://getcomposer.org/composer.phar -O composer.phar', $commandOutput, $return);
            $output[$key] = new Report(($return === 0), $commandOutput);
            if (!$output[$key]->getStatus()) {
                $output['status'] = false;
                return $output;
            }

            $key = 'Downloading and installing vendors';
            $commandOutput = array();
            $return = 0;
            putenv('COMPOSER_HOME=' . $this->melodyDir . '/composer');
            exec('cd ' . $this->installationDir . '; php ' . $this->melodyDir . '/composer.phar install', $commandOutput, $return);
            $output[$key] = new Report(($return === 0), $commandOutput);
            if (!$output[$key]->getStatus()) {
                $output['status'] = false;
                return $output;
            }
        }

        /**
         * Installing assets
         */
        if ($this->needInstallAssets()) {
            $key = 'Installing assets';
            $consolePath = '';
            if (file_exists($this->installationDir .  '/app/console')) {
                $consolePath = 'app/console';
            } else if (file_exists($this->installationDir . '/bin/console')) {
                $consolePath = 'bin/console';
            }
            $assets = $this->runCommand('php ' . $consolePath . ' assets:install --env=prod');
            $output[$key] = $assets;
            if (!$output[$key]->getStatus()) {
                $output['status'] = false;
                return $output;
            }
        }

        /**
         * Running custom commands
         */
        if ($this->needRunCustomCommands()) {
            $key = 'Running custom scripts';
            $scripts = $this->installCustomScripts();
            if ($scripts !== null) {
                $output[$key] = $scripts;
                if (!$output[$key]->getStatus()) {
                    $output['status'] = false;
                    return $output;
                }
            }
        }

        return $output;
    }

    /**
     * Get the form to use to generate the parameters.yml file
     *
     * @return string|bool
     */
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

    /**
     * Unzip the archive
     *
     * @return Report
     */
    public function deployArchive()
    {
        $output = new Report(true);

        $archive = $this->resourcesDir . '/' . $this->getKey('archive.filename');
        if (!file_exists($archive)) {
            $output->setStatus(false);
            $output->setContent("Abording, archive '" . $archive . "' not found !");
        } else if (!is_writable($this->installationDir)) {
            $output->setStatus(false);
            $output->setContent("Abording, directory '" . $this->installationDir . "' is not writable !");
        } else {
            $zip = new ZipArchive();
            if ($zip->open($archive) === true) {
                $zip->extractTo($this->installationDir);
            } else {
                $output->setStatus(false);
                $output->setContent("Abording, archive '" . $archive . "' not readable !");
            }
        }

        return $output;
    }

    /**
     * Return the value from the config.yml file for the given configuration key
     *
     * @param string $path
     * @return string|array|null
     */
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

    /**
     * Check if the given requirements match with the server configuration
     *
     * @param array $requirements
     * @return array(array global, array sections, bool status)
     */
    public function checkPHPInfo(array $requirements)
    {
        $result = array(
            'global' => array(),
            'sections' => array(),
            'status' => true
        );

        $needCli = false;

        if ($this->needInstallVendors() || $this->needInstallAssets() || $this->needRunCustomCommands()) {
            $needCli = true;
        }

        if ($needCli) {
            $return = 0;
            $commandOutput = array();
            exec('php -v', $commandOutput, $return);
            if ($return !== 0) {
                $result['global'][] = 'This installer require PHP to be runnable in CLI mode.';
                $result['status'] = false;
            }
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

    /**
     * Check if we need to install vendors
     *
     * @return bool
     */
    private function needInstallVendors() {
        if (!isset($this->cache['needInstallVendors'])) {
            $vendors = $this->getKey('archive.contains_vendors');

            $return = false;
            if ($vendors === null || $vendors == false) {
                $return = true;
            }

            $this->cache['needInstallVendors'] = $return;
        }
        return $this->cache['needInstallVendors'];
    }

    /**
     * Check if we need to install assets
     *
     * @return bool
     */
    private function needInstallAssets() {
        if (!isset($this->cache['needInstallAssets'])) {
            $assets = $this->getKey('archive.contains_installed_assets');

            $return = false;
            if ($assets === null || $assets == false) {
                $return = true;
            }

            $this->cache['needInstallAssets'] = $return;
        }
        return $this->cache['needInstallAssets'];
    }

    /**
     * Check if we need to run custom commands
     *
     * @return bool
     */
    private function needRunCustomCommands() {
        if (!isset($this->cache['needRunCustomCommands'])) {
            $commands = $this->getKey('after_install');

            $return = false;
            if ($commands !== null && is_array($commands) && !empty($commands)) {
                $return = true;
            }

            $this->cache['needRunCustomCommands'] = $return;
        }

        return $this->cache['needRunCustomCommands'];
    }

    /**
     * Remove recursively the given directory
     *
     * @param string $dir
     */
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

    /**
     * Run all custom scripts if they are defined
     *
     * @return Report|null
     */
    private function installCustomScripts()
    {
        $scripts = $this->getKey('after_install');
        if ($scripts !== null) {
            $content = array();
            $return = true;

            foreach ($scripts as  $script) {
                $commandOutput = $this->runCommand($script);
                $content = array_merge($content, $commandOutput->getContent());

                if (!$commandOutput->getStatus()) {
                    $return = false;
                }
            }
            return new Report($return, $content);
        }
        return null;
    }

    /**
     * Run the given command in a shell
     *
     * @param string $script
     * @return Report
     */
    private function runCommand($script) {
        $content = array();
        $commandReturn = 0;
        $content[] = '===========================================================';
        $content[] = '---> Executing command :' . $script;
        $content[] = '===========================================================';
        exec('cd ' . $this->installationDir . '; ' . $script, $content, $commandReturn);

        return new Report(($commandReturn === 0), $content);
    }

    /**
     * Perform the installation a the sql dump
     *
     * @return null|Report
     */
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
            return new Report(false, $errors);
        } else {
            return new Report(true);
        }
    }

    /**
     * Return the html form to use to configure the parameters.yml file
     *
     * @param array $config
     * @return boolean|string
     */
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

    /**
     * Write the parameters.yml file
     */
    private function writeSFConf()
    {
        $params = array('parameters' => array());
        foreach ($_POST as $key => $field) {
            $params['parameters'][$key] = $field;
        }

        $yaml = Yaml::dump($params, 2);
        file_put_contents($this->installationDir . '/app/config/parameters.yml', $yaml);
    }

    /**
     * Validate the form used to configure the parameters.yml file
     *
     * @return array
     */
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

    /**
     * Convert a given version number or contraint number from string to int
     *
     * @param string $str
     * @return int
     */
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

    /**
     * Align two version numbers to the same decimal to easily compare them
     *
     * @param int $number1
     * @param int $number2
     * @return array(int, int)
     */
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

    /**
     * Return the array version of the config.yml file
     *
     * @return array
     */
    private function getConfig()
    {
        if (!isset($this->cache['getConfig'])) {
            if (!file_exists($this->resourcesDir . '/config.yml')) {
                // It's a critical error. No layout, nothing, just display a big error message and stop here
                echo "Abording, configuration file does not exists !";
                die();
            }

            $content = file_get_contents($this->resourcesDir . '/config.yml');
            $parser = new Parser();
            try {
                $content = $parser->parse($content);
            } catch(ParseException $e) {
                // It's a critical error. No layout, nothing, just display a big error message and stop here
                echo "Abording, " . $e->getMessage();
                die();
            }

            $this->cache['getConfig'] = $content;
        }
        return $this->cache['getConfig'];
    }

    /**
     * Parse the PHPinfo into array
     *
     * @return array
     */
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



class Report {
    private $status = true;
    private $content = array();
    public function __construct($status, $content = null)
    {
        $this->setStatus($status);

        $this->setContent($content);
        if (!is_array($content)) {
            $content = array($content);
        }
        $this->status = $status;
        $this->content = $content;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    public function getContent()
    {
        return $this->content;
    }

    public function setContent($content)
    {
        $this->content = $this->parseContent($content);

        return $this;
    }

    public function addContent($content)
    {
        $this->content = array_merge($this->content, $this->parseContent($content));

        return $this;
    }

    private function parseContent($content)
    {
        if ($content === null) {
            $content = array();
        } else if (!is_array($content)) {
            $content = array($content);
        } else if ($content instanceof Report) {
            $content = array('status' => $content->getStatus(), 'content' => $content->getContent());
        }

        return $content;
    }
}