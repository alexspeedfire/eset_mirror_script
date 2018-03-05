<?php

/**
 * Class Tools
 */
class Tools
{
    /**
     * @var
     */
    static private $CONF;

    /**
     * @var
     */
    static private $unrar;

    /**
     * @throws ConfigException
     */
    static public function init()
    {
        if (!file_exists(CONF_FILE))
            throw new ConfigException("Config file does not exist!");

        if (!is_readable(CONF_FILE))
            throw new ConfigException("Can't read config file! Check the file and its permissions!");

        $ini = parse_ini_file(CONF_FILE, true);

        if (empty($ini))
            throw new ConfigException("Empty config file!");

        static::$CONF = $ini['CONNECTION'];
        static::$unrar = $ini['SCRIPT']['unrar_binary'];
    }

    /**
     * @param array $options
     * @param $headers
     * @return mixed
     */
    static public function download_file($options = array(), &$headers)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, Mirror::$version);
        $out = FALSE;
        $opts = [
            CURLOPT_BINARYTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => static::$CONF['timeout'],
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
        ];

        if (key_exists(CURLOPT_FILE, $options)) {
            $dir = dirname($options[CURLOPT_FILE]);
            if (!@file_exists($dir)) @mkdir($dir, 0755, true);
            $out = fopen($options[CURLOPT_FILE], "wb");
            $options[CURLOPT_FILE] = $out;
        }

        if (($speed = static::$CONF['download_speed_limit']) != 0) $opts[CURLOPT_MAX_RECV_SPEED_LARGE] = $speed;

        if (static::$CONF['proxy'] != 0) {
            $opts[CURLOPT_PROXY] = static::$CONF['server'];
            $opts[CURLOPT_PROXYPORT] = static::$CONF['port'];

            if (!empty(static::$CONF['user'])) {
                $opts[CURLOPT_PROXYUSERNAME] = static::$CONF['user'];
                $opts[CURLOPT_PROXYPASSWORD] = static::$CONF['password'];
            }
        }

        $ch = curl_init();
        curl_setopt_array($ch, ($opts + $options));
        $res = curl_exec($ch);
        $headers = curl_getinfo($ch);
        if ($out) @fclose($out);
        curl_close($ch);

        if (key_exists(CURLOPT_RETURNTRANSFER, $options)) {
            if ($options[CURLOPT_RETURNTRANSFER] == 1) return $res;
        }

        return false;
    }

    /**
     * @return string
     */
    static public function get_archive_extension()
    {
        return ".gz";
    }

    /**
     * @param $file
     */
    static public function archive_file($file)
    {
        $fp = gzopen($file . ".1.gz", 'w9');
        gzwrite($fp, file_get_contents($file));
        gzclose($fp);
        unlink($file);
    }

    /**
     * @param $source
     * @param $destination
     * @throws ToolsException
     */
    static public function extract_file($source, $destination)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, Mirror::$version);
        $date = date("Y-m-d-H-i-s-") . explode('.', microtime(1))[1];

        if (!file_exists(static::$unrar))
            //Log::write_log(Language::t("Unrar not exists at %s", static::$unrar), 0, Mirror::$version);
            throw new ToolsException("Unrar not exists at %s", static::$unrar);

        if (!is_executable(static::$unrar))
            //Log::write_log(Language::t("Unrar not executable at %s", static::$unrar), 0, Mirror::$version);
            throw new ToolsException("Unrar not executable at %s", static::$unrar);

        switch (PHP_OS) {
            case "Darwin":
            case "Linux":
            case "FreeBSD":
            case "OpenBSD":
                exec(sprintf("%s x -inul -y %s %s", static::$unrar, $source, $destination));
                break;
            case "WINNT":
                shell_exec(sprintf("%s e -y %s %s", static::$unrar, $source, $destination));
                break;
        }
    }

    /**
     * @param $hostname
     * @param int $port
     * @param null $file
     * @return bool
     */
    static public function ping($hostname, $port = 80, $file = NULL)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, Mirror::$version);
        static::download_file(array(CURLOPT_URL => "http://" . $hostname . "/" . $file, CURLOPT_PORT => $port, CURLOPT_NOBODY => 1), $headers);
        return (is_array($headers)) ? true : false;
    }

    /**
     * @param $bytes
     * @param int $precision
     * @return string
     */
    static public function bytesToSize1024($bytes, $precision = 2)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, Mirror::$version);
        $unit = array('Bytes', 'KBytes', 'MBytes', 'GBytes', 'TBytes', 'PBytes', 'EBytes');
        return @round($bytes / pow(1024, ($i = floor(log($bytes, 1024)))), $precision) . ' ' . $unit[intval($i)];
    }

    /**
     * @param $secs
     * @return false|string
     */
    static public function secondsToHumanReadable($secs)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, Mirror::$version);
        return ($secs > 60 * 60 * 24) ? gmdate("H:i:s", $secs) : gmdate("i:s", $secs);
    }

    /**
     * @return mixed
     */
    static public function ds()
    {
        return preg_replace('/[\/\\\\]+/', DIRECTORY_SEPARATOR, implode('/', func_get_args()));
    }

    /**
     * @param $text
     * @param $to_encoding
     * @return mixed|string
     */
    static public function conv($text, $to_encoding)
    {
        if (preg_match("/utf-8/i", $to_encoding))
            return $text;
        elseif (function_exists('mb_convert_encoding'))
            return mb_convert_encoding($text, 'UTF-8', $to_encoding);
        elseif (function_exists('iconv'))
            return iconv('UTF-8', $to_encoding, $text);
        else {
            $conv = array();

            for ($x = 128; $x <= 143; $x++) {
                $conv['u'][] = chr(209) . chr($x);
                $conv['w'][] = chr($x + 112);

            }

            for ($x = 144; $x <= 191; $x++) {
                $conv['u'][] = chr(208) . chr($x);
                $conv['w'][] = chr($x + 48);
            }

            $conv['u'][] = chr(208) . chr(129);
            $conv['w'][] = chr(168);
            $conv['u'][] = chr(209) . chr(145);
            $conv['w'][] = chr(184);
            $conv['u'][] = chr(208) . chr(135);
            $conv['w'][] = chr(175);
            $conv['u'][] = chr(209) . chr(151);
            $conv['w'][] = chr(191);
            $conv['u'][] = chr(208) . chr(134);
            $conv['w'][] = chr(178);
            $conv['u'][] = chr(209) . chr(150);
            $conv['w'][] = chr(179);
            $conv['u'][] = chr(210) . chr(144);
            $conv['w'][] = chr(165);
            $conv['u'][] = chr(210) . chr(145);
            $conv['w'][] = chr(180);
            $conv['u'][] = chr(208) . chr(132);
            $conv['w'][] = chr(170);
            $conv['u'][] = chr(209) . chr(148);
            $conv['w'][] = chr(186);
            $conv['u'][] = chr(226) . chr(132) . chr(150);
            $conv['w'][] = chr(185);
            $win = str_replace($conv['u'], $conv['w'], $text);

            if (preg_match("/1251/i", $to_encoding))
                return $win;
            elseif (preg_match("/koi8/i", $to_encoding))
                return convert_cyr_string($win, 'w', 'k');
            elseif (preg_match("/866/i", $to_encoding))
                return convert_cyr_string($win, 'w', 'a');
            elseif (preg_match("/mac/i", $to_encoding))
                return convert_cyr_string($win, 'w', 'm');
            else
                return $text;
        }
    }

    /**
     * @param $resource
     * @return bool|mixed
     */
    static public function get_resource_id($resource)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, Mirror::$version);
        return (!is_resource($resource)) ? false : @end(explode('#', (string)$resource));
    }

    /**
     * @param $dir
     */
    static public function create_dir($dir)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, Mirror::$version);

        if (!file_exists($dir)) @mkdir($dir, 0755, true);
    }

    /**
     * @param $file1
     * @param $file2
     * @return bool
     */
    static public function compare_files($file1, $file2)
    {
        return ($file1['size'] == $file2['size']);
    }
}
