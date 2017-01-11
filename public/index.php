<?php

// Change to the project root, not the web root and setup the basic environment

chdir(__DIR__ . '/..');
//define('TEMPLATEDIR', getcwd() . '/templates');
set_include_path(get_include_path() . PATH_SEPARATOR . 'externals');

require_once('lib/configManager.php');
require_once('lib/zf2App.php');

// Read in our deployment environment
$deployment=getenv('ZF2_DEPLOYMENT');
if (strlen($deployment) < 3) {
    
    if (file_exists('conf/deployment.txt')) {
        $deployment=file_get_contents('conf/deployment.txt');
    } else {
        $deployment = 'charles';
    }
}

class myApp extends zf2App {

    function __construct($deployment='')
    {
        parent::__construct($deployment);
        $this->formHandler();
        
    }
    
    public function formHandler()
    {
        $tpl = &$this->tpl;
        $tpl->addToken('module', $this->module, true);
        $tpl->addToken('section', $this->section, true);
        $aryPathVars = $this->getPathVarsAsArray();
        $tpl->addArrayOfTokens($aryPathVars, false);
        $content = '';
        try {
            switch ($this->module) {
                case 'add':
                    break;
                case 'score':
                default:
                    $leaderBoard = new Leaderboard($this);
                    $content = $leaderBoard->handleForm($this->section);
                    break;
                case 'broken':
                    
                    $content = '';
/*                    $rs = $this->runQuery("SELECT * FROM game_types ORDER BY sort_order DESC, name ASC");
                    //print "Count: " . $rs->RecordCount() . "  Fields: " . $rs->FieldCount() . "<br/>\n";
                    while ($aryRow = $rs->FetchRow() ) {
                        $content .= $aryRow['game_type_id'] ." = " . $aryRow['name'] . "<br/>\n";
                        //var_dump($aryRow);
                    }
                    throw new Exception("Not yet implemented!");
*/                    break;   
            }
            if ($content == false) {
                $content = "Template error";
            }
            $tpl->addToken('content', $content);
            $text = $tpl->getHeader();
            if ($text == false) {
                print $tpl->getErrorMessage();
            } else {
                print $text;
            }
            exit();
            
        } catch (Exception $e) {
            $this->showError($e->getMessage());
        }
        
    }
}

$deployment=trim($deployment);
$app = new myApp($deployment);

?>
