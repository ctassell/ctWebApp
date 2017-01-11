<?php
/*
 * $Id: configManager.php 2 2014-02-26 09:47:55Z ctassell $
 *
 * Configuration file parser class
 * Written by Charles Tassell
 * August 2013
 */


// TODO: Possibly add a "merge" option to parseConfig so keys that are arrays can be appended to instead of overwritten
require_once('errorClass.php');

class configManager extends errorClass
{
    private $arySettings, $forceLowerCase;

    public function __construct($configFile='', $caseSensitive = true)
    {
        $this->clear();
        $this->forceLowerCase = !$caseSensitive;
        if ($configFile !== '') {
            $this->parseConfig($configFile);
        }
    }

    function setCaseSensitive($enabled=true)
    {
        $this->forceLowerCase = !$enabled;
    }

    /**
     * Parse a .ini style config file, with section support
     * @param   string $confFile    The name (with path) of the file to parse
     * @param   bool $overwriteExisting If a previous config file has been read in, should we overwrite any duplicate keys.  Defaults to true
     * @param   bool $mergeArrays If a previous config file has been read in and contains a key which is an array, should we merge the array rather than honouring $overwriteExisting?
     * @return  bool Returns true on success, false if the INI file couldn't be read or parsed
     */
    public function parseConfig($confFile, $overwriteExisting = true, $mergeArrays = true)
    {
        $aryConfig = parse_ini_file($confFile, TRUE);
        if (!is_array($aryConfig)) {
            return $this->setError("Could not parse config file: $confFile", false);
        } else {
            foreach ($aryConfig as $sectionName => $arySettings) {
                if ($this->forceLowerCase) {
                    $sectionName = strtolower($sectionName);
                }
                foreach ($arySettings as $key => $value) {
                    if ($this->forceLowerCase) {
                        $key = strtolower($key);
                    }
                    if ($mergeArrays && is_array($value)) {
                        // Handle merging arrays
                        foreach ($value as $configValue) {
                            $this->arySettings[$sectionName][$key][] = $configValue;
                        }
                    } elseif ($overwriteExisting || !array_key_exists($key, $this->arySettings[$sectionName])) {
                        $this->arySettings[$sectionName][$key] = $value;
                    }
                }
            }
        }
        return $this->clearError();
    }

    /**
     * Clears out any existing configuration settings
     * @return bool Always returns true
     */
    public function clear()
    {
        $this->arySettings = array();
        return $this->clearError();
    }

    /**
     * Returns a config setting
     * @param string $keyName The name of a configuration key.  If you use sections ()[section]\nkey=value) then separate the section with a /
     *   IE "database/host"  If you just specify a section name you get an array of all settings in that section
     * @return mixed Returns the config value if the key exists, false if it does not.
     */
    public function get($keyName)
    {
        $arySections = explode('/', $keyName);
        $aryPtr = &$this->arySettings;
        while ($key = array_shift($arySections)) {
            if ($this->forceLowerCase) {
                $key = strtolower($key);
            }
            if (array_key_exists($key, $aryPtr)) {
                $aryPtr = &$aryPtr[$key];
            } else {
                return $this->setError("No such config setting: $keyName", false);
            }
        }
        return $aryPtr;
    }

    /**
     * Adds a config setting
     * @param string $keyName The name of a configuration key.  If you use sections ()[section]\nkey=value) Then separate the section with a /  IE "database/host"
     * @param mixed $value The value for the config setting
     * @return bool Returns true
     */
    public function set($keyName, $value)
    {
        $arySections = explode('/', $keyName);
        $aryPtr = &$this->arySettings;
        $numSections = count($arySections) - 1;
       for ($curSection = 0; $curSection < $numSections; $curSection++) {
            $key = $arySections[$curSection];
            if ($this->forceLowerCase) {
                $key = strtolower($key);
            }
            if (!array_key_exists($key, $aryPtr)) { // If the section doesn't already exist, create it
                $aryPtr[$key] = array();
            }
            $aryPtr = &$aryPtr[$key];
        }
        $key = $arySections[$numSections];

        if ($this->forceLowerCase) {
            $key = strtolower($key);
        }
        $aryPtr[$key] = $value;
        return $this->clearError();
    }

    /**
     * Returns an array of all parsed config settings
     * @return array multi-dimensioned array of all config settings
     */
    public function getAll()
    {
        return $this->arySettings;
    }
}

/*
 Simple unit test...
*/
/*
$cf = new configManager('../conf/charles.ini', false);
$cf->set('Test/Entry', "Manually set");
$cf->parseConfig('../conf/global.ini', false);
foreach (array('Paths/appurl', 'includes/paThs', 'Test/Entry', 'global/test') as $key) {
    $value = $cf->get($key);
    if ($value == false) {
        print "$key not found\n";
    } else {
        print "$key:\n";
        var_dump($value);
    }
}
*/
?>