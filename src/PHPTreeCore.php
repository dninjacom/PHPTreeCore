<?php
namespace PHPTree\Core;

use PHPTree\Core\PHPTreeCacheMemcached AS PTMEMCACHED;
use PHPTree\Core\PHPTreeCacheRedis AS PTREDIS;
use PHPTree\Core\PHPTreeCache AS PTCACHE;
use PHPTree\Core\PHPTreeLogs AS Logs;
use PHPTree\Core\PHPTreeErrors;

//Memcached
define("CACHE_TYPE_MEM",   3);
//Redis
define("CACHE_TYPE_REDIS", 2);
//File
define("CACHE_TYPE_FILE",  1);

class PHPTreeCore  
{  
	/*

  	 	System Environment 
 	
	*/
	static array | null $env;
	
   /*
   		This is our shutdown function, in 
 		here we can do any last operations
  		before the script is complete.
   */
	private function shutdown() : void {
		
		//Already killed
		if ( static::$env == null )
		{
			return;
		}
	
		//Write Logs 
		if ( isset( static::$env['logs'] )  )
		{
			//Log errors 
			if ( isset(static::$env['logs']['errors']) AND
				 static::$env['logs']['errors'] != null AND 
				 sizeof(PHPTreeErrors::$errors) > 0 )
			{
				Logs::writeLogs( DIR . '/' . static::$env['logs']['errors'],
								 PHPTreeErrors::$errors);
			}
		}
		
		//Show debug panel 
		if ( isset( static::$env['debug'] ) AND static::$env['debug'] )
		{
			Logs::debugger();
		}
		
		
		//Disconnect servers
		if ( static::$env['cache']['memcached']['enabled']  AND PTMEMCACHED::$instance->mem != null )
		{
			PTMEMCACHED::quit();
		}
		
		if ( static::$env['cache']['redis']['enabled'] AND PTREDIS::$instance->redis != null )
		{
			PTREDIS::quit();
		}
		
		//Clear logs 
		PHPTreeErrors::$errors	   = array();
			
		//Bye bye
		
	}
	  
	public function __construct() {
		
		//Register shutdown
		register_shutdown_function(array($this, 'shutdown'));

		//Get and parse system environment 
		if ( PTCACHE::exists("PTEnv")  ){
			
			static::$env = PTCACHE::get("PTEnv");
			
		}else{
			
			$envPath = DIR . "/.env.json";
			
			if ( file_exists($envPath) AND !is_dir($envPath) ) {
				
				$content = file_get_contents( $envPath );
				$ndocs   = 0;
			
				static::$env = json_decode($content,true);
				
				if ( static::$env['cache']['file']['enabled']  )
				{
					PTCACHE::set( "PTEnv" , static::$env);
				}							
											
			}
		}

		if ( static::$env != null )
		{
			//Auto load
			spl_autoload_register(array($this,"autoLoad"));
			spl_autoload_register(array($this,'autoloadPSR0'));
		
			//Auto init 
			if ( isset(static::$env['autoload']['init']) AND sizeof(static::$env['autoload']['init']) > 0 )
			{
				foreach( static::$env['autoload']['init'] AS $classname )
				{
					new $classname();
				}
			}
			
		}else{
			throw new \Exception('File .env does not exists , or not readable .');
		}
		
	}
	/*
			
		Auto load application classes
		PSR-0
	
	*/	
	private function autoloadPSR0($className)
	{
		$className = ltrim($className, '\\');
		$fileName  = '';
		$namespace = '';
		
		if ($lastNsPos = strrpos($className, '\\')) {
			$namespace = substr($className, 0, $lastNsPos);
			$className = substr($className, $lastNsPos + 1);
			$fileName  = DIR . '/' . str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
		}
		
		//$fileName .= str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';
		$fileName .= $className . '.php';
	
		if ( file_exists( $fileName ) AND !is_dir($fileName) )
		{
			require($fileName);
		}
	}
	/*
			
		Auto load application classes
	
	*/
	private function autoLoad( $class ) : void {
		
		if ( !isset(static::$env['autoload']) )
		{
			return;
		}
		
		if ( sizeof(static::$env['autoload']['classmap']) == 0 )
		{
			return;
		}
		
		foreach( static::$env['autoload']['classmap'] AS $dir ){
				
			$root = DIR . '/' . $dir . "/" ;
				
			if ( file_exists( $root . $class . '.php'))
			{
				require( $root . $class . '.php');
			}
			
		}
		
	}
	
}