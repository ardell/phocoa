<?php

class WFYaml
{

    /**
     * Make sure that the parsed yaml result was not an error.
     * Empty array() IS a valid result, but NULL is an invalid
     * result and should throw an exception.
     *
     * @throws Exception If the result returned from the yaml parser is NULL (i.e. the YAML parser couldn't parse the file/string)
     */
    private static function assertValidYamlParseResult($result, $errorMessage)
    {
        // We need to check $result === NULL here because simply checking
        // the truthiness of $result would evaluate empty array() as false
        // meaning that empty yaml files (or strings) would result in parse
        // exceptions instead of empty (valid) parse results.
        if ($result === NULL) throw new Exception($errorMessage);
    }

    /**
     * Load and parse a yaml file, returning a php hash.
     *
     * @param The file path to parse as yaml.
     * @return array The parsed yaml file represented as a php hash.
     * @throws Exception If the yaml parser was unable to parse the file as valid yaml.
     */
    public static function loadFile($file)
    {
        if (function_exists('yaml_parse_file'))
        {
            $a = yaml_parse_file($file);
            self::assertValidYamlParseResult($a, "Error processing YAML file: {$file}");
            return $a;
        }
        else if (function_exists('syck_load'))
        {
            // php-lib-c version, much faster!
            // ******* NOTE: if using libsyck with PHP, you should install from pear/pecl (http://trac.symfony-project.com/wiki/InstallingSyck)
            // ******* NOTE: as it escalates YAML syntax errors to PHP Exceptions.
            // ******* NOTE: without this, if your YAML has a syntax error, you will be really confused when trying to debug it b/c syck_load will just return NULL.
            $yaml = NULL;
            $yamlfile = file_get_contents($file);
            if (strlen($yamlfile) != 0)
            {
                $yaml = syck_load($yamlfile);
            }
            if ($yaml === NULL)
            {
                $yaml = array();
            }
            return $yaml;
        }
        else
        {
            // php version
            return Horde_Yaml::loadFile($file);
        }
    }

    /**
     * Parse yaml from a string. If yaml is invalid an Exception will
     * be thrown, otherwise a php hash will be returned.
     *
     * NOTE: libsyck extension doesn't have a 'string' loader, so we have to write a tmp file. Kinda slow... in any case though shouldn't really use YAML strings
     * for anything but testing stuff anyway
     *
     * @param The string to parse.
     * @return array The parsed string represented as a php hash.
     * @throws Exception If the yaml parser was unable to parse the string as valid yaml.
     */
    public static function loadString($string)
    {
        if (function_exists('yaml_parse'))
        {
            $result = yaml_parse($string);
            self::assertValidYamlParseResult($result, "Error processing YAML string");
            return $result;
        }
        else if (function_exists('syck_load'))
        {
            // extension version
            $file = tempnam("/tmp", 'syck_yaml_tmp_');
            file_put_contents($file, $string);
            return self::loadFile($file);
        }
        else
        {
            // php version
            return Horde_Yaml::load($string);
        }
    }

    /**
     * @deprecated Use loadFile()
     */
    public static function load($file)
    {
        return self::loadFile($file);
    }

    /**
     *  Given a php structure, returns a valid YAML string representation.
     *
     *  @param mixed PHP data
     *  @return string YAML equivalent.
     */
    public static function dump($phpData)
    {
        if (function_exists('yaml_emit'))
        {
            return yaml_emit($phpData);
        }
        else if (function_exists('syck_dump'))
        {
            // php-lib-c version, much faster!
            return syck_dump($phpData);
        }
        else
        {
            // php version
            return Horde_Yaml::dump($phpData);
        }
    }
}
