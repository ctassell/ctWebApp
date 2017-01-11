<?php

class errorClass {
  private $errorCode=0,
          $errorMsg='',
          $errorRoutine='',
          $showErrors=false;

  public  function showErrors($isOn)
  {
    $this->showErrors = $isOn;
  }
  
  public function getShowErrors()
  {
    return $this->showErrors;
  }

  public  function setError($msg, $code=1)
  {
    /*if (!defined($code)) {
      $code = 1;
    }*/
    $this->errorCode = $code;
    $this->errorMsg = $msg;
    if ($this->showErrors) {
      print $msg . "\n";
    }
	
	if (strlen($this->errorRoutine) && function_exists($this->errorRoutine)) { // Not tested yet
		$this->errorRoutine($this);
	}

    return 0;
  }
  
  public	function setErrorHandler($funcName='')	// Not tested yet
  // This can be set to the name of a custom function which will be called
  // whenever an error is set.
  {
	  if (function_exists($funcName)) {
		  $this->errorRoutine=$funcName;
		  return $this->clearError();
	  } else {
		  $this->errorRoutine=''; // Set to empty instead of preserving what was already there
		  return $this->setError("Error function does not exist: $funcName");
	  }
  }

  public  function getError()
  {
    return $this->errorCode;
  }

  public  function getErrorCode()
  {
    return $this->errorCode;
  }

  public function getErrorMessage()
  {
    return  $this->errorMsg;
  }

  public function clearError($retValue=1)
  {
    $this->errorCode=0;
	$this->errorMsg='';
    return $retValue;
  }
}


?>