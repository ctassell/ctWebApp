<?php
/*
 * $Id:$
* Written by Charles Tassell <charles@islandadmin.ca>
*
*
*/

class RESTParameterCheck extends errorClass {
	private $appObj, $aryParams;

	public function __construct($appObj)
	{
		$this->appObj = $appObj;
		$this->clear();
	}
	/**
	 * Clears out any saved parameters
	 */
	public function clear()
	{
		$this->aryParams = array();
	}

	/**
	 * Returns the sanitized value saved by one of the lookup functions.
	 * @param unknown $fieldName The field to lookup
	 * @param string $default If there was no saved value for $fieldName return this instead
	 * @return mixed Returns the stored value or $default if there was none
	 */
	public function getParameter($fieldName, $default = false)
	{
		if (array_key_exists($fieldName, $this->aryParams)) {
			return $this->aryParams[$fieldName];
		} else {
			return $default;
		}
	}

	/**
	 * Alias function for getParameter()
	 *
	 */
	public function getParam($fieldName, $default = false)
	{
		return $this->getParameter($fieldName, $default);
	}

	/**
	 * Looks up a form parameter (get/post/path var) for either a single integer or an array of them.
	 * if found it's saved for lookup via getParameter().  The integers can be specified as either
	 * a PHP array (ints[]), a comma separated list (int=1,2,3) or JSON (int={..})
	 * Note that if there is at least one valid value this function returns success
	 * @param string $fieldName The name of the form parameter
	 * @param bool $isRequired If true and the form parameter wasn't specified, returns an error
	 * @return Returns true or false via set/clearError.  Sets the error code to 1 if the field wasn't present,
	 * 2 if there were no valid values
	 */
	public function lookupIntArray($fieldName, $isRequired = false)
	{
		$a = $this->appObj;
		$aryValues=array();
		$invalidValues = 0;
		$result = $a->getField($fieldName, false);

		if ($result === false && $isRequired == true) {
			return $this->setError("No such field as: $fieldName", 1);
		} elseif ($result === false) {
			return $this->clearError();
		} else {
			$json = json_decode($result, true);
			if ($json !== NULL) {
				$result = $json;
			} else {	// Wasn't json, check to see if it's comma separated list of ints
				if (strpos($result, ',') !== false) {
					$result = explode(',', $result);
				}
			}

			if (is_array($result)) {
				foreach ($result as $key => $value) {
					if (is_numeric($value)) {
						$aryValues[$key] = intval($value);
					} else {
						$invalidValues++;
					}
				}
			} else {
				if (is_numeric($result)) {
					$aryValues[] = intval($result);
				} else {
					$invalidValues++;
				}
			}
			if (count($aryValues) > 0) {
				$this->aryParams[$fieldName] = $aryValues;
				return $this->clearError();
			} elseif ($invalidValues > 0) {
				return $this->setError("No valid values", 2);
			} else { // This should not be reachable code...
				return $this->setError("No such field as: $fieldName (2)", 1);
			}
		}
	}


	/**
	 * Looks up a form parameter (get/post/path var) for a single integer which is saved for lookup via getParameter().
	 * @param string $fieldName The name of the form parameter
	 * @param bool $isRequired If true and the form parameter wasn't specified, returns an error
	 * @return Returns true or false via set/clearError.  Sets the error code to 1 if the field wasn't present,
	 * 2 if there were no valid values
	 */
	public function lookupInt($fieldName, $isRequired=false)
	{
		$a = $this->appObj;
		$result = $a->getField($fieldName, false);

		if ($result === false && $isRequired == true) {
			return $this->setError("No such field as: $fieldName", 1);
		} elseif ($result === false) {	//Not specified but not required
			return $this->clearError();
		} else {
			if (is_numeric($result)) {
				$this->aryParams[$fieldName] = intval($result);
				return $this->clearError();
			} else {
				return $this->setError("No valid values", 2);
			}
		}
	}

	/**
	 * Looks up a form parameter (get/post/path var) for a single string which is saved for lookup via getParameter().
	 * @param string $fieldName The name of the form parameter
	 * @param bool $isRequired If true and the form parameter wasn't specified, returns an error
	 * @param bool $trimValue If true, the value is trimmed of leading and trailing whitespace
	 * @return Returns true or false via set/clearError.  Sets the error code to 1 if the field wasn't present
	 */
	public function lookupString($fieldName, $isRequired=false, $trimValue=false)
	{
		$a = $this->appObj;
		$result = $a->getField($fieldName, false);

		if ($result === false && $isRequired == true) {
			return $this->setError("No such field as: $fieldName", 1);
		} elseif ($result === false) {	//Not specified but not required
			return $this->clearError();
		} else {
			if ($trimValue) {
				$result = trim($result);
			}
			$this->aryParams[$fieldName] = $result;
			return $this->clearError();
		}
	}

}

?>
