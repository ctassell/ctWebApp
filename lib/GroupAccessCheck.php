<?php
/*
 * Written by Charles Tassell <charles@islandadmin.ca>
 *
 * Provies a simple group + user access checking policy.
 * Users are in groups.  Access can be granted/denied via either user or group level
 * If a user is in multiple groups and any group is specifically denied access, user must have
 * a record in the user table to allow access.  User being denied in the user table always revokes access.
 */

class GroupAccessCheck extends errorClass {
	private	$appObj, $groupTable, $userTable;

	public function __construct($appObj)
	{
		$this->appObj = $appObj;
		$this->groupTable = 'access_group_map';
		$this->userTable = 'access_user_map';
	}

	/**
	 * Uses the supplied query to check wether access is granted, denied, or undefined.
	 * @param string $query The raw query to run
	 * @return int Returns -1 if there is no specific access policy, 0 if access was denied, 1 if access was specifically granted
	 */
	private function queryAccess($query)
	{
		$accessLevel = -1;
		$rslt = $this->appObj->runQuery($query);
		if (!$rslt) {
			return $this->setError("DB Error:" . $this->appObj->getErrorMessage());
		} else {
			$this->clearError();
			while ($aryResult = $rslt->FetchRow()) {
				if (!$aryResult['has_access']) {
					$accessLevel = 0;
				} elseif ($accessLevel != 0) {
					$accessLevel = 1;
				}
			}
		}
		return $accessLevel;
	}

	/**
	 * Checks access for a user and his associated groups
	 * @param int $recordID The ID of the record you want to check access to
	 * @param int $userID The user_id to check access for
	 * @param mixed $aryGroups Optional. Either a single group_id or an array of them.  Must be ints (will be converted to ints for the check)
	 * @param number $defaultAccess What to return if no settings were found.  Defaults to 0 (access denied)
	 * @return number Returns $defaultAccess if no settings were found, or 0 for denied access 1 for allowed.  Note that you need to call getError()
	 * to check to see if there was an error since a return code of false might be confused for no access.
	 *
	 */
	public function checkAccess($recordID, $userID, $aryGroups='', $defaultAccess=0)
	{
		$userID = intval($userID);
		$recordID = intval($recordID);
		if (!is_array($aryGroups)) {	// If a non-empty/0 default value was set turn it into an array
			$i = intval($aryGroups);
			if ($i > 0) {
				$aryGroups = array($i);
			} else {
				$aryGroups = array();
			}
		} else {	// Make sure all the specified group_ids are integers greated than 0
			$aryNew = array();
			foreach ($aryGroups as $value) {
				$i = intval($value);
				if ($i > 0) {
					$aryNew[]=$i;
				}
			}
			$aryGroups = $aryNew;
		}

		$access = $this->queryAccess("SELECT * FROM $this->userTable WHERE record_id = $recordID AND user_id =$userID");
		if ($this->getError() != 0) {
			return $this->setError($this->getErrorMessage());
		}
		if ($access == -1 && count($aryGroups) > 0) { // There was no defined access level for the user, check his groups
			$access = $this->queryAccess("SELECT * FROM $this->groupTable WHERE record_id = $recordID AND group_id IN (" . join(',', $aryGroups) . ")");
			if ($this->getError() != 0) {
				return $this->setError($this->getErrorMessage());
			}
		}
		if ($access == -1) {
			return $defaultAccess;
		} else {
			return $access;
		}
	}
}
