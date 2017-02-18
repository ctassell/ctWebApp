<?php

//require_once('errorClass.php');

class templateClass extends errorClass {
	private	$tplPrefix, $headerContent, $aryItemContent, $curItemIndex, $footerContent, $haveMultipleItems, $aryGlobalTokens, $aryTokens, $aryIncludeDirs;
	
	// $tplPrefix - prefix of template files.  Uses <prefix>_header.html, <prefix>_footer.html
	// and multiple <prefix>_item.html <prefix>_item[1-100].html
	function __construct($tplPrefix='')
	{
		$this->reset();
		if (strlen($tplPrefix)) {
			$this->setTemplateFiles($tplPrefix);
        } else {
            $this->tplPrefix = '';
        }
	}
    
    /*
     * @param string $tplPrefix Optional prefix of template files.  Uses <prefix>_header.html, _item.html _footer.html See setTemplateFiles for more details
     * @return  templateClass   Returns a new object
     */
    public function makeNew($tplPrefix='')
    {
        $tpl = new templateClass();
        foreach ($this->aryIncludeDirs as $folder) {
            $tpl->addTemplateFolder($folder);
        }
        $tplPrefix = (strlen($tplPrefix) > 0) ? $tplPrefix : $this->tplPrefix;
        if (strlen($tplPrefix)) {
            $tpl->setTemplateFiles($tplPrefix);
        }
        $tpl->addArrayOfTokens($this->aryGlobalTokens, true);
        $tpl->addArrayOfTokens($this->aryTokens, false);
        return $tpl;
    }
    
    /*
     * Searches the template folders and current working directory for the given filename.
     * @param string $fileName The name of the file to look for
     * @return mixed Returns false if the file wasn't found, or the path to the file if it was
     */
    private function findTemplateFile($fileName)
    {
      foreach ($this->aryIncludeDirs as $folder) {
          $path = $folder . '/' . $fileName;
          //print "Checking path $path\n";
          if (file_exists($path)) {
              return $path;
          }
      }
      //Wasn't found in the include dirs, check the cwd
      if (file_exists($fileName)) {
          return $fileName;
      } else {
          return false;
      }
    }
	public function setTemplateFiles($tplPrefix)
	{
		$this->headerContent = '';
		$this->footerContent = '';
		$this->aryItemContent = array();
		$this->curItemIndex = 0;
		$this->tplPrefix = $tplPrefix;
        
        
		//if (file_exists($tplPrefix . '_header.html')) {
		if ($fileName = $this->findTemplateFile($tplPrefix . '_header.html')) {
			$this->headerContent = file_get_contents($fileName);
		        if ($fileName = $this->findTemplateFile($tplPrefix . '_footer.html')) {
		        	$this->footerContent = file_get_contents($fileName);
		        }
		        if ($fileName = $this->findTemplateFile($tplPrefix . '_item.html')) {
		        	$this->aryItemContent[] = file_get_contents($fileName);
		        	$x=1;
		        	while ($fileName = $this->findTemplateFile($tplPrefix . '_item' . $x . '.html')) {
		        		$this->aryItemContent[] = file_get_contents($fileName);
		        		$x++;
				}
			} else { // Add an empty record if we have no items to avoid PHP warnings in getItem
				$this->aryItemContent[] = '';
			}
		} else {
			$fileName = $this->findTemplateFile($tplPrefix . '.html');
			if (file_exists($fileName)) { // We don't have a 3 file set, just a single template
				$this->headerContent = file_get_contents($fileName);
			}
		}
		if (count($this->aryItemContent) > 1) 
			$this->haveMultipleItems = true;
		//print "hc is $this->headerContent ($tplPrefix)";
	}
			
        
	public function reset()
	{
		$this->headerContent = '';
		$this->footerContent = '';
		$this->aryItemContent=array();
		$this->haveMultipleItems=false;
		$this->curItemIndex=0;
		$this->aryGlobalTokens=array('php_self' => $_SERVER['PHP_SELF']);
		$this->aryTokens=array();
        $this->aryIncludeDirs=array();
	}
    
    /*
     * Adds a path to the search dir for the template files
     * @param mixed $folder The folder to look for template files in.  This may be either a string with a single path, or an array of strings with multiple paths
     */
    public function addTemplateFolder($folder)
    {
        if (is_array($folder)) {
            foreach ($folder as $value) {
                $this->aryIncludeDirs[] = $value;
            }
        } else {
            $this->aryIncludeDirs[] = $folder;
        }
    }
    
    /*
     * Returns the array of all the search dirs for templates
     * @return array List of folders
     */
    public function getTemplateFolders()
    {
        return $this->aryIncludeDirs;
    }
	
	public function clearTokens()
	{
		$this->aryTokens=array();
	}

	public function clearAllTokens()
	{
		$this->aryTokens=array();
		$this->aryGlobalTokens=array('php_self' => $_SERVER['PHP_SELF']);
	}
	
	public function getToken($tokenName, $default=FALSE)
	{
		if (array_key_exists($tokenName, $this->aryTokens)) {
			return $this->aryTokens[$tokenName];
		} elseif (array_key_exists($tokenName, $this->aryGlobalTokens)) {
			return $this->aryGlobalTokens[$tokenName];
		} else {
			return $default;
		}
	}
	
    public function getTokens()
    {
		$aryTokens = $this->aryGlobalTokens;
        foreach ($this->aryTokens as $key => $value) {
			$aryTokens[$key] = $value;
        }
			return $aryTokens;
	}
	
	public function addToken($key, $value, $isGlobal=true)
	{
		if ($isGlobal)
			$this->aryGlobalTokens[strtolower($key)]=$value;
		else
			$this->aryTokens[strtolower($key)]=$value;
	}
	
	public function addArrayOfTokens(&$aryTokens, $isGlobal=true)
	{
		foreach ($aryTokens as $key => $value) {
			$this->addToken(strtolower($key), $value, $isGlobal);
			//print "added $key = $value<br>\n";
		}
	}
	
	public function addTokens(&$aryTokens, $isGlobal=true)
	{
		return $this->addArrayOfTokens($aryTokens, $isGlobal);
	}
	
	public function addTokensWithPrefix(&$aryTokens, $prefix, $isGlobal=true)
	{
		foreach ($aryTokens as $key => $value) {
			$this->addToken("$prefix" . strtolower($key), $value, $isGlobal);
		}
	}
	
	public function getHeader()
	{
		return $this->replaceTokens($this->headerContent, true);
	}

	public function getFooter()
	{
		return $this->replaceTokens($this->footerContent, true);
	}

	public function getItem()
	{
		//print "Showing $this->curItemIndex of " . count($this->aryItemContent) . " item files.<br>\n";
		if (!$this->haveMultipleItems)
			$result = $this->replaceTokens($this->aryItemContent[0], false);
		else {
			$index = &$this->curItemIndex;
			$result = $this->replaceTokens($this->aryItemContent[$index], false);
			$index++;
			if ($index >= count($this->aryItemContent))
				$index = 0;
		}
		$this->clearTokens();
		return $result;
	}
	/*
     * Used to actually do the token replacement.  It has a few special macro commands that can be added to tokens.
     * <token>|html Replaces the token with it's value after cleaning it up with htmlspecialchars()
     * <token>|selected-<checkValue> Replaces the token with the word "selected" if it's value is == to the string in <checkValue>
     * <token>|checked-<checkValue> Replaces the token with the word "checked" if it's value is == to the string in <checkValue>
     * @param string $buffer    The template as a string, used to run the replacements on
     * @param bool $globalsOnly  Whether to use all tokens or only the ones set as global
     * @return string The contents of $buffer with all the tokens replaced.  Empty tokens (tokens set in the template but not
     * specified via an addToken() call) are replaced with empty strings 
     */
	protected function replaceTokens($buffer, $globalsOnly)
	{
		//print "buffer was $buffer<br>\n";
		//Get the list of tokens in the template source
		preg_match_all('/%([a-zA-Z0-9\|_\-\+\.]*)%/', $buffer, $aryKeys);
        //Convert them all to lower case, strip out duplicates, and remove the |-<action> stuff)
        $aryTempKeys=array();
        foreach ($aryKeys[1] as $key) {
            if (strpos($key, '|')) {
                list($key, $trash) = explode('|', $key, 2); 
            }
            $aryTempKeys[strtolower($key)] = 1;
        }
        $aryKeys = array_keys($aryTempKeys);
		//Check for replacements in item tokens
		foreach ($aryKeys as $key) {
			$key = substr(strtolower($key), 0, 100);
			$value = '';
			$found = false;
			if (!$globalsOnly && array_key_exists($key, $this->aryTokens)) {
				$value = $this->aryTokens[$key];
				$found = true;
			} elseif (array_key_exists($key, $this->aryGlobalTokens)) {
				$value = $this->aryGlobalTokens[$key];
				$found = true;
			}
			if ($found) { // We found the key in our tokens, so do all the replacements
				//print "$key = $value<br>\n";
  				$value = preg_replace('/\$/', '&#36;', $value); //Quick hack to fix the $# getting replaced by 0 
  				$safeValue=substr(preg_replace('/[^\w\d_-]/', '', $value),0,1024);	// FIXME: Need to remove spaces, % and any other funky chars
				//print "$key = $value<br>\n";
  				$buffer = preg_replace("/%" . $key . "%/i", $value, $buffer);
  				$buffer = preg_replace("/%" . $key . "\|html%/i", htmlspecialchars($value), $buffer);
  				$buffer = preg_replace("/%" . $key . "\|checked-" . $safeValue . "%/i", 'checked', $buffer);
  				$buffer = preg_replace("/%" . $key . "\|selected-" . $safeValue . "%/i", 'selected', $buffer);
  			}
  		}
  		// Clean out any tokens we didn't have matches for
  		$buffer = preg_replace('/%[a-zA-Z0-9\|_\-\+\.]*%/', '', $buffer);
  		// Undu the $# problem
  		$buffer = preg_replace('/&#36;/', '\$', $buffer); //Reverse the quick hack to fix the $# getting replaced by 0
  		return $buffer;

	}
	
	// This is a copy from the old ISN template class.  It hasn't been switched over
	// to work with this new one, it's just meant as a reference
	private function replaceTokensAlt($HFfile) {
		// HFfile usage example: "filename" (without any path or extension)
		// open file
		$fp = fopen("$this->IncludeFolder/$HFfile.$this->Extension", "r");
		if (!$fp) {
			$this->ErrorMessage = "Can't open $HFfile.$this->Extension";
			if ($this->ShowErrors) {
				print $this->ErrorMessage . "<BR>\n";
			}
			return false;
		}
		$OutString = "";
		
		// loops through the lines of the file until EOF
		// copies all file into the string OutString
		while (!feof($fp)) {
			$line = fgets($fp, filesize("$this->IncludeFolder/$HFfile.$this->Extension"));
			$OutString .= $line;
		}
		
		// close file
		$closeFile = fclose($fp);
		
		// put all variable names between % symbols into the array matches
		// $matches[0][i] holds names with % symbol around
		// $matches[1][i] holds just names between % symbols
		$x = preg_match_all("($this->TokenSymbol([^$this->TokenSymbol\\s\\'\\\"]*)$this->TokenSymbol)", $OutString , $matches);
		
		// gets size of the array containing the matches
		$i = count($matches[1]);
		
		// gets all variables' names inside the array and use them as keys in the hash table
		// to build a new array with the values returned by the hash table
		for ($c = 0; $c < $i; $c++) {
			$temp = $matches[1][$c];
			//print "string is $temp<BR>\n";
			if (isset($this->GlobalTokens["$temp"])) {
				$hashVars[$c] = $this->doEscape($this->GlobalTokens["$temp"]);
			} else {
				$hashVars[$c] = $matches[0][$c];
			}
			
		}
		
		// substitue all the values returned by the hash table into the string
		$z = 0;
		while (list($key, $val) = each($matches[0])) {
			$temp = $hashVars[$z];
			//print "replacing $val with $temp<BR>\n";
			$OutString = ereg_replace("$val", "$temp", "$OutString");
			$z++;
		}
		
		// OutString has the file with the changes
		return $OutString;
	}
	
	
}

?>
