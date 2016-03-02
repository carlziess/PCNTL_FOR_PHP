<?php
/**
 * Run
 * @author lizhenglin@huoyunren.com
 * @access public
 * @return mixed
 */
error_reporting(E_ALL);
define('BASEPATH', dirname(__FILE__). DIRECTORY_SEPARATOR);
include BASEPATH . 'pcntl.class.php';
include BASEPATH . 'works.class.php';
//Worker service.
$service = new Works();

function run($service){
	$service->P1();
}

$daemon=new pcntl(true);
$daemon->daemonize();
$daemon->setJobs(array('function'=>'run','argv'=>$service,'runtime'=>1,'sleeptime'=>5));
$daemon->start(1);


