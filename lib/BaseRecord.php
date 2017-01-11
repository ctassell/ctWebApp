<?php
/**
 * $Id: BaseRecord.php 2 2014-02-26 09:47:55Z ctassell $
 *
 * Base record class that other classes which load arrays of records from the DB should inherit from
 * Written by Charles Tassell <charles@islandadmin.ca>
 */

 class BaseRecord extends errorClass {
     protected    $keyField, $loadQuery, $appObj, $aryRecords;

     /**
      * Base record class that other classes which load arrays of records from the DB should inherit from.
      * Does not automatically load records from the DB.  You must call loadRecords()
      * @param object $appObj A zf2App instance which is used for it's database connection
      * @param string $keyField The record field used for an index.  Must be unique
      * @param string $loadQuery An SQL query used to load all records from the database.
      */
     public function __construct($appObj, $keyField, $loadQuery = '')
     {
         $this->appObj = &$appObj;
         $this->clearRecords();
         $this->setKeyField($keyField);
         $this->setLoadQuery($loadQuery);
     }

     /**
      * Clears out any records previously loaded/added
      * @return bool Calls clearError() and returns true
      */
     public function clearRecords()
     {
         $this->aryRecords = array();
         return $this->clearError();
     }
     /**
      * Alias for clearRecords
      */
     public function clear()
     {
     	return $this->clearRecords();
     }

     /**
      * Sets the name of the field used for an index in records
      * @param string $keyField The record field used for an index.  Must be unique
      * returns bool false on failure (empty keyField) or clearError on success
      */
     protected function setKeyField($keyField)
     {
         if (strlen($keyField) > 0) {
             $this->keyField = $keyField;
             return $this->clearError();
         } else {
             return $this->setError('Invalid keyfield: must be a string');
         }
     }

     /**
      * Sets the query used by loadRecords to pull entries from the database
      * @param string $query The query used to load the records
      * @return bool  Calls clearError which returns true
      */
     protected function setLoadQuery($query)
     {
         $this->loadQuery = $query;
         return $this->clearError();
     }

     /**
      * Manually add a record to the list.  This does not save the record to the database!
      * @param mixed $aryRecord The record to add.  Does not necessarily have to be an array, but if not you must specify $key
      * @param string $key The index key to use.  If not specified it will default to the value of $aryRecord[$keyField]
      * @return bool Calls setError if there was a problem, clearError if the record was added
      */
     public function addRecord($aryRecord, $key='')
     {
         if ($key === '') {
             if (!is_array($aryRecord) || !array_key_exists($this->keyField, $aryRecord)) {
                 return $this->setError("Key '$this->keyField' not found in the specified record");
             } else {
                 $key = $aryRecord[$this->keyField];
             }
         }
         $this->aryRecords[$key] = $aryRecord;
         return $this->clearError();
     }

     /**
      * Load in a list of records from the database with an optional limiting clause.  Does not call clearRecords by itself
      * @param string $query The query to send to the DB.  IE SELECT * FROM foo WHERE is_active=1
      * @return bool Returns true on success, calls setError and returns false if there was a problem.  Not finding any records is not considered a problem.
      */
     public function loadByQuery($query)
     {
         //$query = $this->loadQuery . " $whereClause";
         //print "query is $query<br/>\n";
         $numPassed = 0;
         $numFailed = 0;
         try {
            $rs = $this->appObj->runQuery($query);
            //print "$query<br/>\n";
            //FIXME: Need to errorcheck $rs here
			if (!$rs) {
				//print "Error running query $query<br>\n";
				return $this->setError('Database error: ' . $this->appObj->getAdapter()->ErrorMsg());
			}
            while ($aryRecord = $rs->FetchRow()) {
            	//var_dump($aryRecord);
                if (array_key_exists($this->keyField, $aryRecord) && strlen($aryRecord[$this->keyField]) >0) { // Confirm the keyField exists and it holds a valid index value
                    $this->aryRecords[$aryRecord[$this->keyField]] = $aryRecord;
                    $numPassed++;
                } else {
                    $numFailed++; // No index value could be found
                }
            }
            if ($numFailed == 0) {
                return $this->clearError();
            } else {
                return $this->setError("Could not find an index value for $numFailed of " . ($numFailed + $numPassed) . " records");
            }
         } catch (Exception $e) {
             return $this->setError('Database error: ' . $e->getMessage());
         }

     }

     /**
      * Load in a list of records from the database with an optional limiting clause.  Does not call clearRecords by itself
      * @param string $whereClause String appended to the loadQuery attribute that can be used to only load specific records.  IE 'WHERE age > 65'
      * @param boolean $aBool Put in as a PHP7 compatibility hack
      * @return bool Returns true on success, calls setError and returns false if there was a problem.  Not finding any records is not considered a problem.
      */
     public function loadRecords($whereClause='', $aBool=false)
     {
     	return $this->loadByQuery($this->loadQuery . " $whereClause");
     }

     /**
      * Returns the number of records currently loaded
      * @return int Returns the number of records that have currently been loaded
      **/

      public function count()
      {
          return count($this->aryRecords);
      }

     /**
      * Returns the record for a given index value (aka key)
      * @param string $key The index value to look for.  If not specified, it returns the first record found
      * @return mixed Returns the record if found, or false and calls setError if no record with the given index exists.
      */
     public function getRecord($key=null)
     {
     	if (is_null($key)) {
			$key = $this->getFirstID();
     	}
         if (array_key_exists($key, $this->aryRecords)) {
         	//var_dump($this->aryRecords);
         	//var_dump($this->aryRecords[$key]);
             return $this->aryRecords[$key];
         } else {
             return $this->setError("Could not find a record with index '$key'");
         }

     }

     /**
      * Returns the ID of the first loaded record, which is probably very random.
      * Only to be used if you know there is only one record or you just don't care
      * @return int Returns the id of the first loaded record or 0 if there are no records
      */
     public function getFirstID()
     {
     	if (count($this->aryRecords) > 0) {
	     	$aryIDs=array_keys($this->aryRecords);
    	 	return $aryIDs[0];
     	} else {
     		return 0;
     	}
   	}

     /**
      * Returns an array of all the indexes.  This can be looped through with getRecord() to return all results
      * @return array Returns an array of all the indexes
      */
     public function getKeyList()
     {
         return array_keys($this->aryRecords);
     }

     /**
      * Returns an array of all loaded records, indexed by their keyField value
      * @return array All records indexed by their keyField value
      */
     public function getAllRecords()
     {
         return $this->aryRecords;
     }
     
     /**
      * Looks up a single field in a particular record and returns its value.
      * @param mixed $key The index (keyField value) of the record.  Usually the <whatever>_id value
      * @param string $fieldName The name of the attribute in the record to return the value of
      * @return mixed Returns false via setError if the record or field doesn't exist, otherwise returns the value.
      */
     public function getSpecificField($key, $fieldName)
     {
     	if (!array_key_exists($key, $this->aryRecords)) {
     		return $this->setError("Record not found", 1);
     	} elseif (!array_key_exists($fieldName, $this->aryRecords[$key])) {
     		return $this->setError("Field not found", 2);
     	} else {
     		return $this->aryRecords[$key][$fieldName];
     	}
     }
     
     /**
      * Returns a map of keyField => value where value is the value of a specific field for all loaded records.
      * @param string $fieldName The name of the field to return as a value
      * @return boolean|array Returns false via setError if the specified $fieldName doesn't exist.  Otherwise returns an array
      */
     public function getSpecificFieldMap($fieldName)
     {
     	$aryMap = array();
     	foreach ($this->aryRecords as $key => $aryRec) {
     		if (!array_key_exists($fieldName, $aryRec)) {
     			return $this->setError("Field not found");
     		} else {
     			$aryMap[$key] = $aryRec[$fieldName];
     		}
     	}
     	return $aryMap;
     }

     /**
      * Searches all records and returns those who have a field whose value matches the supplied regexp.  This does not call loadRecords to do the search on the database, it just searches records that are currently stored in the object.
      * @param string $searchField The field to search on
      * @param string $regExp The regexp used by preg_match to find valid matches.  Should include the surrounding delimiters, ie '/charles/i'
      * @param bool $returnMultiple  If false, returns the first found match.  If true, will return an array of all matching records
      * @return mixed If $returnMultiple is false, returns a single record.  If it's true, returns an array of all matching records.  If no records are found, returns false but does not call setError.  Returns false and calls setError if the records do not contain the field specified by searchField
      */
     public function searchRecords($searchField, $regExp, $returnMultiple=true)
     {
         $aryMatches = array();
         foreach ($this->aryRecords as $aryRecord) {
             if (!array_key_exists($searchField, $aryRecord)) {
                 return $this->setError("The records do not contain a field named '$searchField'");
             } elseif (preg_match($regExp, $aryRecord[$searchField])) {
                 if (!$returnMultiple) {
                     return $aryRecord;
                 } else {
                     $aryMatches[$aryRecord[$this->keyField]] = $aryRecord;
                 }
             }
         }
         if (count($aryMatches) == 0) {
             return false;
         } else {
             return $aryMatches;
         }
     }

      /**
      * Takes either a single integer or an array of them and returns a sanatized array with all the duplicates removed.
      * @param integer|array $argList List of value(s)
      * @return array An array of all the unique values stored as integers
      */
     static function intArgsToArray($argList)
     {/* Doing this in zf2App::getField() now
     	if (!is_array($argList)) {
	     	$json = json_decode($argList, true);
	     	if (is_array($json)) {
	     		$argList = $json;
	     	}
     	}*/
     	if (is_array($argList)) {
     		$aryIDs = array();
     		foreach ($argList as $id) {
     			$aryIDs[intval($id)] = 1;
     		}
     		return array_keys($aryIDs);
     	} else {
     		return array(intval($argList));
     	}
     }
     /**
      * Takes either a single string or an array of them and returns a sanatized array with all the duplicates removed and addslashes applied
      * @param string|array $argList  List of value(s)
      * @return array An array of all the unique values stored as addslashes($value)
      */
     static function stringArgsToArray($argList)
     {/* Doing this in zf2App::getField() now
     	if (!is_array($argList)) {
	        $json = json_decode($argList, true);
	     	if (is_array($json)) {
	     		$argList = $json;
	     	}
     	}*/
     	if (is_array($argList)) {
     		$aryIDs = array();
     		foreach ($argList as $id) {
     			$aryIDs[addslashes($id)] = 1;
     		}
     		return array_keys($aryIDs);
     	} else {
     		return array(addslashes($argList));
     	}
     }
     
     /**
      * Helper method which calls the debug() method of $appObj.
      * @param string $message The message to display
      * @param integer $level The debug level of this message
      * @return No return value at this time
      */
     public function debug($message, $level=1)
     {
     	return $this->appObj->debug($message, $level);
     }

 }


 ?>
