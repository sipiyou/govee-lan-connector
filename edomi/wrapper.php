<?php

$argv[1] = 9910;

require(dirname(__FILE__)."/../../main/include/php/incl_lbsexec.php");

$hasWrapper = 1;
define ('LBSID', "19002760");

function W_logic_setVar($id, $v1,$v2) {
  $V[$v1] = $v2;
  printf ("V[%s] = %s\n",$v1,$v2);
}


function W_logic_setOutput($id, $v1,$v2) {
  printf ("O[%s] = %s\n",$v1,$v2);
}

function W_writeToCustomLog($lName, $dbgTxt, $output) {
  printf ("%s => %s\n",$lName.$dbgTxt,$output);
}


function W_logic_getInputs() {
    global $id;

    $id = 98765;
    
    $arr = array();
    for ($i=1;$i<50;$i++) {
        $arr[$i]['value']='';
        //$arr[$i]['refresh']='';
    }

    $arr[1]['value'] = 1;
    $arr[2]['value'] = 10;
    
    $arr[8]['value'] = 1;
    $arr[8]['refresh'] = 1;
    $arr[9]['value'] = 3;

    return ($arr);
}

$ADMIN_WWW = MAIN_PATH . "/www/Govee";
$LIB_DIR   = MAIN_PATH . "/main/include/php/Govee";

$adminFile = $ADMIN_WWW . "/govee_admin.php";	
LB_LBSID_installAdmin($adminFile, 3);
LB_LBSID_installLib($LIB_DIR, 3);

?>
