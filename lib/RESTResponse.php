<?php
/*
 * $Id:$
 * Written by Charles Tassell <charles@islandadmin.ca>
 */

//require_once('errorClass.php');

class RESTResponse extends errorClass {
	protected	$errorCode,	// 0 if not in error, otherwise can be set via setError()
				$aryResponseFields, // An array of fields to be returned as response data
				$responseFormat, // The format to generate response objects in.  Should be either 'json' or 'text' Defaults to json
				$jsonIsPretty;

	public function __construct($responseFormat='')
	{
		$this->clear();
		$this->jsonIsPretty=true;
		if ($responseFormat != '') {
			$this->setResponseFormat($responseFormat);
		}
	}

	/**
	 * Resets the object, clearing out all response fields and error levels
	 */
	public function clear()
	{
		$this->errorCode = 0;
		$this->aryResponseFields = array();
		$this->responseFormat = 'json';
		$this->jsonIsPretty = false;
		parent::clearError();
	}

	/**
	 * Sets the response format to generate.
	 * @param string $responseFormat  Either JSON or TEXT.  This is case insensitive
	 * @return boolean Returns true if the respone format was set, false via errorClass::setError if you specified an invalid format.
	 */
	public function setResponseFormat($responseFormat)
	{
		switch (strtolower($responseFormat)) {
			case 'json':
				$this->responseFormat = 'json';
				break;
			case 'text':
				$this->responseFormat = 'text';
				break;
			default:
				return parent::setError("Invalid response format '$responseFormat'  Must be either json or text");
				break;
		}
		return parent::clearError();
	}
	
	/**
	 * Specifies whether to pretty up JSON output or compact it for quicker processing 
	 * @param boolean $enabled If true  JSON output will be sent in a more human-readable format
	 */
	public function enableJSONPrettyPrint($enabled=false)
	{
		$this->jsonIsPretty = ($enabled) ? true : false;
	}

	/**
	 * Returns the current response format.  Either json or text
	 * @return string Returns the current response format.  Either json or text
	 */
	public function getResponseFormat()
	{
		return $this->responseFormat();
	}

	/**
	 * Adds a field to be returned in the response output
	 * @param string $fieldName The name of the field
	 * @param mixed $value The value of the field.  May be of any datatype that the output format can process
	 */
	public function addResponseField($fieldName, $value)
	{
		$this->aryResponseFields[$fieldName] = $value;
	}

	/**
	 * Returns the value of a specified response field.
	 * @param string $fieldName The name of the field to return
	 * @param string $default The value to return if the field has not been set
	 * @return mixed Returns the value of the field that was set or $default
	 */
	public function getResponseField($fieldName, $default = false)
	{
		if (array_key_exists($fieldName, $this->aryResponseFields)) {
			return $this->aryResponseFields[$fieldName];
		} else {
			return $default;
		}
	}

	/**
	 * Returns an array of all the response fields currently set
	 * @return array Returns an array of all the response fields currently set
	 */
	public function getAllResponseFields()
	{
		return $this->aryResponseFields();
	}

	/**
	 * Internal function used by setError() and setNoError() to log response data
	 * @param array|string $aryErrors Either an array of response fields or a string that contains the error/response_message
	 * @param int $errorCode The response code to return for the REST call
	 * @param string $defaultLabel The label for the error message if aryErrors is a string
	 * @param int $defaultErrorCode The error code to set if the specified one was invalid (ie, is not an integer)
	 * @return Returns false if the errorCode you specified was not an integer.  Otherwise returns true
	 */
	private function setResponse($aryErrors, $errorCode, $defaultLabel, $defaultErrorCode)
	{
		//$this->errorCode = $errorCode;
		if (!is_array($aryErrors)) {
			$this->addResponseField($defaultLabel, $aryErrors);
		} else {
			foreach ($aryErrors as $field => $value) {
				$this->addResponseField($field, $value);
			}
		}
		if (!is_int($errorCode)) {
			$this->errorCode = $defaultErrorCode;
			return parent::setError("Error code '$errorCode' must be an integer");
		} else {
			$this->errorCode = $errorCode;
			return parent::clearError();
		}

	}

	/**
	 * Calls setResponse() with a defaultLabel of 'errorMessage'
	 * @param array|string $aryErrors Either an array of response fields or a string that contains the error/response message
	 * @param integer $errorCode The response code to return for the REST call
	 * @return boolean Returns false if the errorCode you specified was not an integer.  Otherwise returns true
	 */
	public function setError($aryErrors, $errorCode=1)
	{
		return $this->setResponse($aryErrors, $errorCode, 'errorMessage', 1);
	}

	/**
	 * Calls setResponse() with a defaultLabel of 'responseMessage'
	 * @param array|string $aryErrors Either an array of response fields or a string that contains the error/response_message
	 * @param integer $errorCode The response code to return for the REST call
	 * @return boolean Returns false if the errorCode you specified was not an integer.  Otherwise returns true
	 */
	public function setNoError($aryErrors, $responseCode=0)
	{
		return $this->setResponse($aryErrors, $responseCode, 'responseMessage', 0);
	}
	
	/**
	 * Returns the currently set error code
	 * @return integer Returns the currently set error code
	 */
	public function getErrorCode()
	{
		return $this->errorCode;
	}

	/**
	 * Returns a string with the formatted response output
	 * @return string Returns a string with the formatted response output
	 */
	public function getResponse()
	{
		switch ($this->responseFormat) {
			case 'json':
				return $this->getJSONResponse();
				break;
			case 'text':
				return $this->getTextResponse();
				break;
		}
	}

	private function getJSONResponse()
	{
		$aryResponse = $this->aryResponseFields;
		if (array_key_exists('errorMessage', $aryResponse)) {
			$aryResponse['errorCode'] =  $this->errorCode;
		} else {
			$aryResponse['responseCode'] =  $this->errorCode;
			if (!array_key_exists('responseMessage', $aryResponse) ) {
				$aryResponse['responseMessage'] = 'OK';
			}
		} 
		if ($this->jsonIsPretty) {
			return json_encode($aryResponse, JSON_PRETTY_PRINT);
		} else {
			return json_encode($aryResponse);
		}
	}

	private function getTextResponse()
	{
		//FIXME: Should we run some sort of htmlentities on the output of this?
		$aryResponse = $this->aryResponseFields;
		if (array_key_exists('responseMessage', $aryResponse)) {
			$buffer = "OK:" . $this->errorCode . ":" . $aryResponse['responseMessage'] . "\n";
			unset($aryResponse['responseMessage']);
		}  else {
			$buffer = "ERROR:" . $this->errorCode . ":" . $aryResponse['errorMessage'] . "\n";
			unset($aryResponse['errorMessage']);
		}
		foreach ($aryResponse as $fieldName => $value) {
			if (is_array($value)) {

				$value = print_r($value, true);
			}
			$buffer .= "$fieldName:$value\n";
		}
		return $buffer;
	}
}
/*
$obj = new RESTResponse('text');
$obj->addResponseField('string_test', 'This is some text data');
$obj->addResponseField('array_test', array('field1' => 'Some data', 'field2' => 'More data'));
$obj->setNoError('Just a string to test with');
print $obj->getResponse() . "\n";
*/
?>