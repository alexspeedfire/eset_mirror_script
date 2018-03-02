<?php

/**
 * Class Language
 */
class Language
{
    /**
     * @var null
     */
    static private $language = null;
    /**
     * @var
     */
    static private $language_file = null;
    /**
     * @var
     */
    static private $language_pack = array();
    /**
     * @var
     */
    static private $default_language_pack = array();
    /**
     * @var
     */
    static private $default_language_file = null;

    /**
     * @param $lang
     * @return null|string
     */
    static public function init($lang = 'en')
    {
        static::$language = $lang;
        static::$language_file = Tools::ds(LANGPACKS_DIR, $lang . '.lng');
        static::$default_language_file = Tools::ds(LANGPACKS_DIR, 'en.lng');

        if (static::$language != 'en') {
            if (!file_exists(static::$language_file))
                return sprintf("Language file [%s.lng] does not exist!", static::$language);
        } else return null;

        $tmp = file(static::$language_file);
        static::$default_language_pack = file(static::$default_language_file);

        if (count($tmp) != count(static::$default_language_pack))
            return sprintf("Language file [%s] is corrupted!", static::$language);

        for ($i = 0; $i < count($tmp); $i++)
            static::$language_pack[trim($tmp[$i])] = trim(static::$default_language_pack[$i]);
        return null;
    }

    /**
     * @return string
     */
    static public function t()
    {
        $text = func_get_arg(0);
        $params = @array_shift(func_get_args());
        return (($key = array_search($text, static::$language_pack)) != FALSE) ? vsprintf($key, $params) : vsprintf($text, $params);
    }
}
