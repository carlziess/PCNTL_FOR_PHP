<?php
/**
 * Works
 * @author lizhenglin@huoyunren.com
 * @access public
 * @return mixed
 */
class Works
{
	public $TempFilePath1 = "/tmp/a.txt";
	
	public $TempFilePath2 = "/tmp/b.txt";
	
	public function P1(){
		if(is_file($this->TempFilePath1)){
			fwrite(fopen($this->TempFilePath1,'a'),date("Y-m-d H:i:s",time())."\r\n");
		}else{
			return false;
		}	
	}
	
	public function P2(){
		if(is_file($this->TempFilePath2)){
			fwrite(fopen($this->TempFilePath2,'a'),date("Y-m-d H:i:s",time())."\r\n");
		}else{
			return false;
		}	
	}

}
