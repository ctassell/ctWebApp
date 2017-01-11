<?php

require_once('errorClass.php');

class ctWebApp extends errorClass {
    protected   $aryConfig, $aryPathFields, $strRawPathData, $module, $section, $aryDBAdapters, $defDBAdapter, $dbDebug, $debugLevel, $cmSettings;
    public      $tpl;

    function __construct($deployedServer='')
    {
        $this->aryConfig = array();
        $this->aryPathFields = array();
        $this->strRawPathData = '';  //Contains everything after the module/section
        $this->aryDBAdapters = array();
        $this->defDBAdapter = '';
        $this->dbDebug = 0;
        $this->debugLevel = 0;
        $this->loadConfig($deployedServer);
        if ($this->getConf('paths/appurl', '/') == '/') {
            $this->cmSettings->set('paths/appurl', '//' . $_SERVER['SERVER_ADDR']);
        }
        $this->parsePath();
        $this->tpl = new templateClass();
        //$this->tpl->addToken('base_url', $this->getConf('Paths/appurl'), true);
        if (array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER) && strlen($_SERVER['HTTP_X_FORWARDED_FOR']) >1) {
            $this->tpl->addToken('base_url', 'https://' . $_SERVER['HTTP_HOST'], true);
        } else {
            $this->tpl->addToken('base_url', $this->getConf('Paths/appurl'), true);
        }
            
        // Setup the template search dirs
        //FIXME: We've got two different include systems here.  Need to use the .ini system that marriagelicense does
        //$this->tpl->addTemplateFolder('templates');
        if ($this->getConf('Includes/templates')) {
            $this->tpl->addTemplateFolder($this->getConf('Includes/templates'));
        }
        // Use layout.html as the default template file
        $this->tpl->setTemplateFiles('layout');;
        if ($this->getConf('database') !== false) {
            $this->initDatabases($this->getConf('database'));
        } else {
            echo "No DBs!<br/>\n";
        }
    }

    protected function initDatabases($aryDB)
    {
        global $ADODB_FETCH_MODE;
        $ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;
        //FIXME: Add error checking here
        $name = $aryDB['database'];
        //print "Driver " . $aryDB['driver'] . " DB $name\n";
        $adapter = ADONewConnection($aryDB['driver']);
        //$adapter->debug = true;
        $adapter->Connect($aryDB['hostname'], $aryDB['username'], $aryDB['password'], $name);
        $this->aryDBAdapters[$name] = $adapter;
        if ($this->defDBAdapter == '') {
            $this->defDBAdapter = $name;
        }
    }

    public function getAdapter($named = '')
    {
        if ($named == '') {
            $named = $this->defDBAdapter;
        }
        if (array_key_exists($named, $this->aryDBAdapters)) {
            return $this->aryDBAdapters[$named];
        } else {
            return false;
        }
    }

    /**
     *
     * @param string $query
     * @param string $adapterName Optional adapter name from the config
     * @param string $aryParams Optional adapter options
     * @return boolean|result_object Returns false on failure or an adodb result object
     */
    public function runQuery($query, $adapterName='', $aryParams='')
    {
        if (is_array($adapterName)) {
            $aryParams = $adapterName;
            $adapterName = '';
        }
        $adapter = $this->getAdapter($adapterName);
        if ($adapter === false) {
        	if ($this->dbDebug > 0) {
        		print "DB ERROR: No adapter present<br/>\n";
        	}
            return false;
        } else {
            if ($this->dbDebug > 1) {
        		print "Running query $query<br/>\n";
        	}
        	if (is_array($aryParams)) {
                $result = $adapter->Execute($query, $aryParams);
            } else {
            	$result = $adapter->Execute($query);
            }
            if ($result == false && $this->dbDebug > 0) {
            	print "DB Error: " . $adapter->ErrorMsg() . "<br/>\n";
            	if ($this->dbDebug == 1) {
            		print "QUERY: $query<br/>\n";
            	}
            }
            //print "$query<br/>\n";
            //print "Count: " . $result->RecordCount() . "  Fields: " . $result->FieldCount() . "<br/>\n";
           return $result;

        }
    }

    /**
     * Sets the debug level for database calls.  0 is no debugging, 1 prints error messages, 2 prints error messages and all executed queries
     * @param number $level The level to set
     */
    public function setDBDebugLevel($level=2)
    {
    	$this->dbDebug = intval($level);
    }

    /*
     * //FIXME: Not yet tested
     * Returns the assigned ID of the last insert on a table with an auto-increment field.
     * @param string $adapterName
     * @return mixed Returns the Id assigned, or false on error.  May throw an exception in certain circumstances
     */
    public function getLastID($adapterName='')
    {
        $adapter = $this->getAdapter($adapterName);
        if ($adapter === false) {
            return false;
        } else {
            return $adapter->Insert_ID();
        }
    }

    /**
     * Gets the last error message from the database
     * @param string $adapterName
     * @return string|false Returns false if there is no such adapter (or no active adapter) or the last DB error message
     */
    public function getLastDBErrorMsg($adapterName='')
    {
    	$adapter = $this->getAdapter($adapterName);
    	if ($adapter === false) {
    		return "No such database adapter";
    	} else {
    		 return $adapter->ErrorMsg();
    	}
    }
    /*
    protected function loadConfig($deployedServer='')
    {
        require_once('conf/config.php');
        if ($deployedServer === '') {   // If we don't specify the deployment server, then check for an ENV variable or use the first available
            if (isset($_ENV['APPLICATION_ENV'])) {
                $deployedServer = $_ENV['APPLICATION_ENV'];
            } else {
                $aryServers = array_keys($CONF);
                if (count($aryServers) > 0) {
                    $deployedServer = $aryServers[0];
                }
            }
        }

        // Add any config parameters outside of the server specific ones into the config array
        foreach ($CONF as $key => $value) {
            if ($key != 'server') {
                $this->aryConfig[$key] = $value;
            }
        }

        // Load the settings from conf/config.php for the server we are deployed on into aryConfig, overriding any of the global ones with the same name
        if (!array_key_exists($deployedServer, $CONF['server'])) {
            $this->showError("Invalid deployment environment specified: '$deployedServer'");
        } else {
            foreach ($CONF['server'][$deployedServer] as $key => $value) {
                $this->aryConfig[$key] = $value;
            }
        }

        // Extend the default include path to include the lib directory for our classes
        // and any extras specified in the deployment config
        $pwd = getcwd();
        $includePath = get_include_path() . PATH_SEPARATOR . $pwd . '/lib' . PATH_SEPARATOR . $pwd . '/externals';
        if (array_key_exists('includes_path', $this->aryConfig)) {
            foreach ($this->aryConfig['includes_path'] as $path) {
                $includePath .= PATH_SEPARATOR . $path;
            }
        }
        set_include_path($includePath);

        // This next part is for our custom classes.  Files should be named <className>.php  The class name is case sensitive
         spl_autoload_register(function ($class) {
            if (strpos($class, 'Zend') === 0) {
                return false;
            } else {
                require_once($class . '.php');
                return true;
            }

        });

        // If we have any libraries that need to be specifically included, include them now that the include path is fixed up
        $aryLibs = $this->getConf('libs');
        if (is_array($aryLibs)) {
            foreach ($aryLibs as $libFile) {
                require_once($libFile);
            }
        }

    }
    */
    protected function loadConfig($deployedServer='' )
    {
        if ($deployedServer === '') {   // If we don't specify the deployment server, then check for an ENV variable or use the first available
            if (isset($_ENV['APPLICATION_ENV'])) {
                $deployedServer = $_ENV['APPLICATION_ENV'];
            } else {
                $deployedServer = 'local';
            }
        }

        $configFile = getcwd() . '/conf/' . $deployedServer . '.ini';
        $this->cmSettings = new configManager($configFile, false);

        // Parse the framework's global config file as well
        $configFile = __DIR__ . '/../conf/' . $deployedServer . '.ini';
        if (file_exists($configFile)) {
            $this->cmSettings->parseConfig($configFile, false, true);
        }
        //var_dump($this->cmSettings->getAll());
        //var_dump($this->cmSettings->get('includes/templates'));

        // If we have any include paths specified in the config file, add them to the include path
        $aryPaths = $this->cmSettings->get('includes/paths');
        if (count($aryPaths)) {
            $includePath = join(PATH_SEPARATOR, $aryPaths);
            $includePath .= PATH_SEPARATOR . get_include_path();
            set_include_path($includePath);
        }

        $this->enableAutoloader(); // Now that the config path is setup, initialize the autoloader in case we need it
                                    // for any of the include libs

        // If we have any libraries that need to be specifically included, include them now that the include path is fixed up
        $aryLibs = $this->cmSettings->get('includes/libs');
        if (is_array($aryLibs)) {
            foreach ($aryLibs as $libFile) {
                require_once($libFile);
            }
        }
    }

    private function enableAutoloader()
    {
        // Extend the default include path to include the lib directory for our classes
        $pwd = getcwd();
        $includePath = $pwd . '/lib' . PATH_SEPARATOR . $pwd . '/externals' . PATH_SEPARATOR . get_include_path();
        // If we are deployed in an environment with a global ZF2APP dir for the base classes, load them in.
        if (isset($_ENV['ZF2APP_GLOBAL_DIR'])) {
            $includePath .= PATH_SEPARATOR . $_ENV['ZF2APP_GLOBAL_DIR'] . '/lib' . PATH_SEPARATOR . $_ENV['ZF2APP_GLOBAL_DIR'] . '/externals';
        }
        set_include_path($includePath);

        // This next part is for our custom classes.  Files should be named <className>.php  The class name is case sensitive
         spl_autoload_register(function ($class) {
            require_once($class . '.php');
            return true;
         });

    }

    public function getConf($keyName, $default=false)
    {
        $value = $this->cmSettings->get($keyName);
        if ($value == false) {
        	return $default;
        } else {
        	return $value;
        }
        /*
        if (array_key_exists($keyName, $this->aryConfig)) {
            return $this->aryConfig[$keyName];
        } else {
            return false;
        }
         * */
    }

    public function showError($message='')
    {
        print "<b>Error:</b> $message<br/>\n";
        exit(1);
    }

    protected function parsePath()
    {
        $module =  ''; $section = '' ;
        $this->strRawPathData = '';
        $dirPrefix = trim($this->getConf('Paths/appdir'), '/');

		if (array_key_exists('REQUEST_URI', $_SERVER)) {
	        $URI = preg_replace('/\/+/', '/', $_SERVER['REQUEST_URI']); // Cleanup paths with double slashes, this might not be desirable though
	        $URI = urldecode($URI); // Remove any translated %20 or the like and turn them back into their real values
	        if ($dirPrefix !== false && strlen($dirPrefix) > 0) {   // If we are installed into a subdir, remove that subdir prefix from the URI
	            //For somke reason URI=preg_replace(URI) results in an empty string...
	            $newURI = preg_replace("/^\/*$dirPrefix/", '', $URI);
	            $URI = urldecode($newURI);
	        } else {
	            $URI = urldecode($_SERVER['REQUEST_URI']);
	        }
	        // Strip out any GET form data
	        $URI = preg_replace('/\?.*/', '', $URI);
	        // Break the URI into components.  The first two being the module and section, the rest being var/value pairs for the pathVar methods
	        $reqparts = explode('/', $URI);
	        array_shift($reqparts); // Remove the empty string from the initial /
	        $numParts = count($reqparts);
	        //var_dump($reqparts);
	        switch ($numParts) {
	            case 0:
	                $module = '';
	                $section = '';
	            break;
	            case 1:
	                $module = $reqparts[0];
	                $section = '';
	                break;
	            case 2:
	                $module = $reqparts[0];
	                $section = $reqparts[1];
	                break;
	            default:
	                $module = $reqparts[0];
	                $section = $reqparts[1];
	                array_shift($reqparts);
	                array_shift($reqparts);
	                $this->strRawPathData = join('/', $reqparts);
	                while (count($reqparts)) {
	                    $this->aryPathFields[array_shift($reqparts)] = array_shift($reqparts);
	                }
	                break;
	        }
		}
        // Force module and section to lower case
        $this->module = strtolower($module);
        $this->section =strtolower($section);
    }

    /**
     * Returns a string with all of the path data after the module/section
     * @@return A string in the format field1/field2/field3
     */
    public function getRawPathData()
    {
    	return $this->strRawPathData;
    }

    /*
     * Takes a multi-dimensional array and turns it into a single dimensional one (ie key=> array(values) becomes key => single_value)
     */
    static function flattenArray($sourceArray, $valueField)
    {
        $destArray=array();
        foreach ($sourceArray as $key => $ary) {
            $destArray[$key] = $ary[$valueField];
        }
        return $destArray;
    }

    public function getSelectBox($fieldName, $aryOptions, $defaultValue='', $template='selectBox')
    {
        $tpl = $this->tpl->makeNew($template);
        $tpl->addToken('field_name', $fieldName, true);
        $content = $tpl->getHeader();
        foreach ($aryOptions as $value => $label) {
            if ($value == $defaultValue) {
                $tpl->addToken('selected', 'selected', false);
            }
            $tpl->addToken('value', $value);
            $tpl->addToken('label', $label);
            $content .= $tpl->getItem();
        }
        $content .= $tpl->getFooter();
        return $content;
    }

    public function getModule()
    {
        return $this->module;
    }

    public function getSection()
    {
        return $this->section;
    }
    /*
     * Looks in the $_REQUEST array of form variables for a given variable.  This will check $_GET, $_POST and $_COOKIES
     * @param string $varName   The name of the variable to look for
     * @param string $defaultValue If no value is found, return this instead.  Defaults to an empty string
     * @return string Returns the value for the found field, or whatever was specified by $defaultValue
     */
    public function getFormField($varName, $defaultValue='')
    {
        if (array_key_exists($varName, $_REQUEST)) {
            return $_REQUEST[$varName];
        } else {
            return $defaultValue;
        }
    }

    /*
     * Looks in both the path vars and the form fields to find a field.  Path vars has the higher precedence.
     * @param string $varName   The name of the variable to look for
     * @param string $defaultValue If no value is found, return this instead.  Defaults to an empty string
     * @return string Returns the value for the found field, or whatever was specified by $defaultValue
     */
    public function getField($varName, $defaultValue='', $decodeJSON=false)
    {
        $value = $this->getPathVar($varName);
        if ($value === false) {
            $value = $this->getFormField($varName, $defaultValue);
        }
        if ($decodeJSON) {
        	$json = json_decode($value, true);
        	if (is_null($json)) {
        		return $value;
        	} else {
        		return $json;
        	}
        } else {
            return $value;
        }
    }
    
    /**
     * Returns an array of all the fields from $_REQUEST as well as the path variables.
     * Path variables override $_REQUEST ones
     * @return array An array of all form fields
     */
    public function getAllFields()
    {
    	$aryFields = $_REQUEST;
    	foreach ($this->aryPathFields as $key => $value) {
    		$aryFields[$key] = $value;
    	}
    	return $aryFields;
    }

    public function getServerField($varName, $defaultValue='')
    {
        return array_key_exists($varName, $_SERVER) ? $_SERVER[$varName] : $defaultValue;
    }
    
    public function addPathVar($varName, $value)
    {
    	$this->aryPathFields[$varName] = $value;
    }

    public function getPathVar($varName)
    {
        if (array_key_exists($varName, $this->aryPathFields)) {
            return $this->aryPathFields[$varName];
        } else {
            return false;
        }
    }

    public function getPathVarsAsArray()
    {
        return $this->aryPathFields;
    }

    /**
     * Replaces the file at $destination with the new version at $newFile and creates a backup
     * @param string $newFile The full path to the new version of the file
     * @param string $destination The full path to the old version of the file
     * @param string $backupExtension The extension to add to $destination for a backup.  If blank, no backup is created.
     */
    public function replaceFile($newFile, $destination, $backupExtension='.bak')
    {
    	if (strlen($backupExtension)) {
	    	$destPath = $destination . $backupExtension;
	    	if (file_exists($destPath)) {
	    		if (!unlink($destPath)) {
	    			return $this->setError("Could not remove the backup file: $destPath");
	    		}
	    	}
	    	if (file_exists($destination) && !rename($destination, $destPath)) {
	    		return $this->setError("Could not create the backup file: $destPath");
	    	}
    	} else {
    		if (file_exists($destination) && !unlink($destination)) {
    			return $this->setError("Could not clear out the original file at: $destination");
    		}
    	}
    	if (!rename($newFile, $destination)) {
    		return $this->setError("Could not rename $newFile to $destination");
    	} else {
    		return $this->clearError();
    	}
    }
    
    /**
     * Sets the verbosity level of debug entries to print.  0 means don't print any debug info, 100 would probably print everything. Default is 0
     * @param int $level The minimum level of debug messages you want to see
     */
    public function setDebugLevel($level)
    {
    	$level = intval($level);
    	if ($level < 1) {
    		$level = 0;
    	}
    	$this->debugLevel = $level;
    }
    
    /**
     * Gets the currently set debug level
     * @return integet The currently set debug level
     */
    public function getDebugLevel()
    {
    	return $this->debugLevel;
    }
    
    /**
     * Prints a message to the screen if the specified debug level is lower or equal to the one set by setDebugLevel()
     * @param string $message The message to print.  \n or <br>\n will be appended as necessary.
     * @param integer $level The debug level of this message
     */
    public function debug($message, $level=1)
    {
    	$level = intval($level);
    	if ($level < 1) {
    		$level = 1;
    	}
    	if ($this->debugLevel >= $level) {
    		if (array_key_exists('REMOTE_ADDR', $_SERVER)) {
	    		print "DEBUG ($level): $message<br/>\n";
    		} else {
    			print "DEBUG ($level): $message\n";
    		}
    	}
    }
}
?>