<?php
/**
 * Class pcntl
 * @access public
 * @return mixed
 */
class pcntl{
	private $info_dir="/tmp";
	private $pid_file="";
	private $terminate=false; 
	private $workers_count=0;
	private $gc_enabled=null;
	private $workers_max=8; 
	
	/**
	 * Construct
	 * @param string $is_sington
	 * @param string $user
	 * @param string $output
	 */
	public function __construct($is_sington=false,$user='root',$output="/dev/null"){
		$this->is_sington=$is_sington; 
		$this->user=$user;
		$this->output=$output; 
		$this->checkPcntl();
	}
	
	/**
	 * Check pcntl support
	 * @throws Exception
	 */
	public function checkPcntl(){
		if(!function_exists('pcntl_signal_dispatch')){
			declare(ticks = 10);
		}
		if(!function_exists('pcntl_signal')){
			$message = 'PHP does not appear to be compiled with the PCNTL extension.  This is neccesary for daemonization';
			$this->_log($message);
			throw new Exception($message);
		}
		pcntl_signal(SIGTERM, array(__CLASS__, "signalHandler"),false);
		pcntl_signal(SIGINT, array(__CLASS__, "signalHandler"),false);
		pcntl_signal(SIGQUIT, array(__CLASS__, "signalHandler"),false);
		if(function_exists('gc_enable')){
			gc_enable();
			$this->gc_enabled = gc_enabled();
		}
	}
	
	/**
	 * pcntl daemonize
	 */
	public function daemonize(){
		global $stdin, $stdout, $stderr;
		global $argv;
		set_time_limit(0);
		if(php_sapi_name() != "cli"){
			die("Only run in command line mode\n");
		}
		if($this->is_sington==true){
			$this->pid_file = $this->info_dir."/". __CLASS__ ."_".substr(basename($argv[0]), 0, -4).".pid";
			$this->checkPidfile();
		}
		umask(0); 
		if(pcntl_fork() != 0){ 
			exit("Master quit\n");
		}
		posix_setsid();
		if(pcntl_fork() != 0){ 
			exit("First child process quit\n");
		}
		chdir("/"); 
		$this->setUser($this->user) or die("Cannot change owner\n");
		fclose(STDIN);
		fclose(STDOUT);
		fclose(STDERR);
		$stdin  = fopen($this->output, 'r');
		$stdout = fopen($this->output, 'a');
		$stderr = fopen($this->output, 'a');
		if ($this->is_sington==true){
			$this->createPidfile();
		}
	}
	
	/**
	 * Check PID
	 */
	public function checkPidfile(){
		if(!file_exists($this->pid_file)){
			return true;
		}
		$pid = file_get_contents($this->pid_file);
		$pid = intval($pid);
		if($pid > 0 && posix_kill($pid, 0)){
			$this->_log("The daemon process is already started");
		}
		else {
			$this->_log("The daemon proces end abnormally, please check pidfile ".$this->pid_file);
		}
		exit(1);
	}
	
	/**
	 * Create PID
	 */
	public function createPidfile(){
		if(!is_dir($this->info_dir)){
			mkdir($this->info_dir);
		}
		$fp = fopen($this->pid_file,'w') or die("Cannot create pid file\n");
		fwrite($fp, posix_getpid());
		fclose($fp);
		$this->_log("Create pid file " . $this->pid_file);
	}

	/**
	 * Set User
	 * @param string $name
	 * @return boolean
	 */
	public function setUser($name){
		$result = false;
		if(empty($name)){
			return true;
		}
		$user = posix_getpwnam($name);
		if($user){
			$uid = $user['uid'];
			$gid = $user['gid'];
			$result = posix_setuid($uid);
			posix_setgid($gid);
		}
		return $result;
	}
	
	/**
	 * Signal Handler
	 * @param unknown $signo
	 */
	public function signalHandler($signo){
		switch($signo){
			case SIGUSR1: 
			if($this->workers_count < $this->workers_max){
				$pid = pcntl_fork();
				if($pid > 0){
					$this->workers_count ++;
				}
			}
			break;
			case SIGCHLD:
				while(($pid=pcntl_waitpid(-1,$status,WNOHANG))>0){
					$this->workers_count--;
				}
			break;
			case SIGTERM:
			case SIGHUP:
			case SIGQUIT:
			$this->terminate = true;
			break;
			default:
			return false;
		}
	}
	
	/**
	 * Run process
	 * @param number $count
	 */
	public function start($count=1){
		$this->_log("daemon process is running now");
		pcntl_signal(SIGCHLD, array(__CLASS__, "signalHandler"),false); // if worker die, minus children num
		while(true){
			if(function_exists('pcntl_signal_dispatch'))pcntl_signal_dispatch();
			if($this->terminate)break;
			$pid=-1;
			if($this->workers_count<$count)$pid=pcntl_fork(); $pids[]=$pid;//fwrite(fopen('/tmp/b.txt','a+'),var_export($pids,true));
			if($pid>0){
				$this->workers_count++;
			}elseif($pid==0){
				pcntl_signal(SIGTERM,SIG_DFL);
				pcntl_signal(SIGCHLD,SIG_DFL);
				if(!empty($this->jobs)){
					while($this->jobs['runtime']){
						if(!empty($this->jobs['argv'])){
							call_user_func($this->jobs['function'],$this->jobs['argv']);
						}else{
							call_user_func($this->jobs['function']);
						}
						$this->jobs['runtime']--;
						sleep($this->jobs['sleeptime']);
					}
					//$this->mainQuit();
					exit();
				}
				return;
			}else{ 
				sleep($this->jobs['sleeptime']);
			}
		}
		$this->mainQuit();
		exit(0);
	}

	/**
	 * exit system
	 */
	public function mainQuit(){
		if (file_exists($this->pid_file)){
			unlink($this->pid_file);
			$this->_log("delete pid file " . $this->pid_file);
		}
		$this->_log("daemon process exit now");
		posix_kill(0, SIGKILL);
		exit(0);
	}

	/**
	 * Set jobs
	 * @param string $jobs
	 */
	public function setJobs($jobs=array()){
		if(!isset($jobs['argv'])||empty($jobs['argv'])){
			$jobs['argv']="";
		}
		if(!isset($jobs['runtime'])||empty($jobs['runtime'])){
			$jobs['runtime']=1;
		}
		if(!isset($jobs['function'])||empty($jobs['function'])){
			$this->log("你必须添加运行的函数！");
		}
		$this->jobs=$jobs;
	}
	
	/**
	 * Log handler
	 * @param string $message
	 */
	private  function _log($message){
		printf("%s\t%d\t%d\t%s\n", date("c"),posix_getpid(),posix_getppid(),$message);
	}

}

?>
