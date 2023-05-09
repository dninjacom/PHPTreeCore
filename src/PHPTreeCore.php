<?php
namespace PHPTree\Core;


use PHPTree\Core\PHPTreeAbstract;
use PHPTree\Core\PHPTreeCache AS Cache;
use PHPTree\Core\PHPTreeErrors;
use PHPTree\Core\PHPTreeLogs AS Logs;
use PHPTree\Core\PHPTreeRoute AS Route;

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
	public function shutdown(){
		
		//Write Logs 
		if ( $this->env['logs'] != null  )
		{
			//Log exceptions
			if ( isset($this->env['logs']['exceptions']) AND $this->env['logs']['exceptions'] != null AND sizeof(PHPTreeErrors::$exceptions) > 0 )
			{
				Logs::writeLogs( DIR . '/' . $this->env['logs']['exceptions'],
								 PHPTreeErrors::$exceptions);
			}
			
			//Log errors 
			if ( isset($this->env['logs']['errors']) AND $this->env['logs']['errors'] != null AND sizeof(PHPTreeErrors::$errors) > 0 )
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
		
		//Clear logs 
		PHPTreeErrors::$errors	   = array();
		PHPTreeErrors::$exceptions = array();
	}
	  
	public function __construct()
	{
		
		//setup environment 
		$this->server			= $_SERVER; 
		$this->server['PTUri']	= $_SERVER['REQUEST_URI'];
		
		register_shutdown_function(array($this, 'shutdown'));

		$this->setup_system_environment();
	
		
		if ( $this->env != null ){
			
			/*
			
				Load all available Controllers 
				then check with system cache if 
				there is available version of all controllers 
				PS : if caching is enabled you need to flush the caches every time you add 
				a new controller 
			
			*/
			$this->loadControllers();
			
			/*
			
				Same logic as Controllers with caching system,
				PS : an advanced algorithm is added to cache most visited routes 
				into a memory cache
			
			*/
			$this->loadRoutes();
		
	 		/*
			 
			 	Last step is to parse the request url 
				find its route or go 404
			 
			 */
			$this->parseRouteUrl();
			
		}else{
			throw new \Exception('File ' . DIR . '.env.yaml does not exists , or not readable .');
		}
	}
	
}