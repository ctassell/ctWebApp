<?php
/**
  * Since this code doesn't actually have anything to do with the ZendFramework
  * anymore I'm renaming the main class ctWebApp.  This stub is for compatibility
  * with old code so it will continue to function without requiring any rewrites.
  * - Charles Tassell <charles@islandadmin.ca>
  **/
require_once(dirname(__FILE__) . '/ctWebApp.php');

class zf2App extends ctWebApp {
    public function __construct($deployedServer='')
    {
        parent::__construct($deployedServer);
    }
}
?>
