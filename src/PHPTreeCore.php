<?php
namespace PHPTree\Core;

use PHPTree\Core\PHPTreeAbstract;
use PHPTree\Core\PHPTreeErrors;
use PHPTree\Core\PHPTreeLogs AS Logs;

class PHPTreeCore extends PHPTreeAbstract  
{  

	function __destruct() {
		$this->shutDown();
	}
	  
   /*
   		This is our shutdown function, in 
 		here we can do any last operations
  		before the script is complete.
   */
	public function shutdown() : void {
		
		//Write Logs 
		if ( isset( $this->env['logs'] )  )
		{
			//Log exceptions
			if ( isset($this->env['logs']['exceptions']) AND 
				$this->env['logs']['exceptions'] != null AND 
				sizeof(PHPTreeErrors::$exceptions) > 0 )
			{
				Logs::writeLogs( DIR . '/' . $this->env['logs']['exceptions'],
								 PHPTreeErrors::$exceptions);
			}
			
			//Log errors 
			if ( isset($this->env['logs']['errors']) AND
				 $this->env['logs']['errors'] != null AND 
				 sizeof(PHPTreeErrors::$errors) > 0 )
			{
				Logs::writeLogs( DIR . '/' . $this->env['logs']['errors'],
								 PHPTreeErrors::$errors);
			}
		}
		
		//Clean
		$this->params   	= null;
		$this->env      	= null;
		$this->controllers  = null;
		$this->routes		= null;
		
		//Disconnect servers
		if ( $this->cache->isEnabled(CACHE_TYPE_MEM) )
		{
			$this->cache->disconnect(CACHE_TYPE_MEM);
		}
		
		//Clear logs 
		PHPTreeErrors::$errors	   = array();
		PHPTreeErrors::$exceptions = array();
	}
	  
	public function __construct() {
		
		//setup environment 
		
		register_shutdown_function(array($this, 'shutdown'));

		$this->setup_system_environment();
		
		if ( $this->env != null ){
			
			/*
			
				( EXECUTE ) 
				Load and execute requested route by its controller 
			
			*/
			$this->LoadAndExecuteRequest();
			
		}else{
			throw new \Exception('File ' . DIR . '.env.yaml does not exists , or not readable .');
		}
	}
	
}