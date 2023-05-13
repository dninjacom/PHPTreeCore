<?php

namespace PHPTree\Core;

//Memcached
define("CACHE_TYPE_MEM", 3);
//Redis
define("CACHE_TYPE_REDIS", 2);
//File
define("CACHE_TYPE_FILE", 1);


interface PHPTreeCacheIFace {
	//Set cache
	public function set($name,$value, $timestamp = 0) : bool;
	//Get cache
	public function get($key);
	//Delete
	public function delete($key) : void;
	//If file exists
	public function exists($key) : bool;
	//Flush all caches 
	public function flush() : void ;	
}

/*

	Memcached
	https://www.php.net/manual/en/book.memcached.php	

*/
class PTCMEM implements PHPTreeCacheIFace { 
	
	var $instance;
	
	var $connected = false;
	
	/*
		MEM
	*/
	public function __construct(){
	
		if ( class_exists('Memcached') )
		{
			$this->$instance = new \Memcached();
		}
		
	}
	public function quit(){
		
		if ( $this->$instance != null  )
		{
			$this->$instance->quit();
			$this->connected = false;
		}
	}
	/*
		MEM::SET
	*/		
	public function set($key,$value, $timestamp = 8400 * 3) : bool{
		
		if ( !$this->connected )
		{
			throw new \Exception("Memcached not connected!");
		}
		
		$this->$instance->set($key, $value, $timestamp);
		
		return $this->$instance->getResultCode() == 0;
	}
	/*
		MEM::GET
	*/		
	public function get($key) {
		
		if ( !$this->connected )
		{
			throw new \Exception("Memcached not connected!");
		}
		
		return $this->$instance->get($key);
	}
	/*
		MEM::EXISTS
	*/	
	public function exists($key) :  bool {
		
		if ( !$this->connected )
		{
			throw new \Exception("Memcached not connected!");
		}
		
		return $this->$instance->get($key);
	}
	/*
		MEM::DELETE
	*/	
	public function delete($key) : void{
		
		if ( !$this->connected )
		{
			throw new \Exception("Memcached not connected!");
		}
		
		$this->$instance->delete($key);
	}
	/*
		MEM::FLUSH
	*/	
	public function flush() : void{
		
		if ( !$this->connected )
		{
			throw new \Exception("Memcached not connected!");
		}
		
		$this->$instance->deleteMulti( $this->$instance->getAllKeys() );
	}
	/*
		MEM::ADD SERVER
	*/
	public function addServer(string $host, int $port, int $weight = 0){
		
		$this->$instance->addServer($host, $port,$weight);
		
		$this->connected   =  ( $this->$instance->getStats() != null );
		
	}
}
/*

	File caching inside cache directory

*/
class PTCF implements PHPTreeCacheIFace { 
	
	/*
		
		Caching folder directory 
	
	*/
	var $dir = "/var/cache/";
	/*
		
		Cache info ( files system only )
	
	*/
	var $referance = "_c_";
	
	
	/*
		FILE::SET
	*/
	public function set($key , $array , $timestamp = 0) : bool{
		
		$name     = '_' . md5($key);
		$filePath = DIR . $this->dir . $name . ".php";
		$content  = '<?php $cache_' . $name . ' = "' . base64_encode(json_encode($array,true)) . '"; ?>';	
			
		if ( file_put_contents($filePath, $content) ) {
			
			//Set expired ( create or update cache folders information )
			if ( $timestamp != null AND $timestamp > 0 )
			{
				$cacheInfoPath = DIR . $this->dir . $this->referance . ".php";
				$cacheInfo     = array();
				
				if ( file_exists($cacheInfoPath) )
				{
					include_once($cacheInfoPath);
				}
			
				$cacheInfo   	  = ( is_array($cacheInfo) ) ? $cacheInfo : json_decode(base64_decode($cacheInfo),true);
				$cacheInfo[$name] = array('file' => $filePath,
										   'ttl'  => $timestamp );
									 
				$cacheInfo   = base64_encode(json_encode($cacheInfo,true));					 
				$cacheInfo	 = '<?php $cacheInfo = "' . $cacheInfo . '"; ?>';	
					
				file_put_contents($cacheInfoPath, $cacheInfo);	
				
				unset($cacheInfoPath,$cacheInfo);				 
			}
			
			unset($content,$filePath,$name);
			
			return true ;
			
		}else{
			return false;
		}
	}
	/*
		FILE::GET
	*/
	public function get($key)  {
		
		$name     = '_' . md5($key);
		$filePath = DIR . $this->dir . $name . ".php";
		
		if (file_exists($filePath))
		{
			include_once($filePath);
			return json_decode( base64_decode(${'cache_' . $name}) , true );
		}
		
		return false;
	}
	/*
		FILE::EXISTS
	*/
	public function exists($key) :  bool {
		
		$name     = '_' . md5($key);
		$filePath = DIR . $this->dir . $name . ".php";
		
		if ( file_exists($filePath))
		{
			unset($filePath,$name);
			return true;
		}
		
		return false;
	}
	/*
		FILE::DELETE
	*/	
	public function delete($key) : void{
		
		$name     = '_' . md5($key);
		$filePath = DIR . $this->dir . $name . ".php";
		
		if ( file_exists($filePath))
		{
			unlink($filePath);
		}
	}
	/*
		FILE::FLUSH
	*/
	public function flush() : void{
		
		$cacheInfoPath = DIR . $this->dir . $this->referance . ".php";
		
		if ( file_exists($cacheInfoPath) )
		{
			include_once($cacheInfoPath);
			
			$cacheInfo  = json_decode(base64_decode($cacheInfo),true);
			$_cacheInfo = array();
			
			foreach( $cacheInfo AS $name => $info )
			{
				if ( $info['ttl'] < time() )
				{
					if ( file_exists($info['file']) )
					{
						unlink($info['file']);
					}
					
				}else{
					$_cacheInfo[$name] = $info;
				}
			}
			
			//Update with new info
			$_cacheInfo   = base64_encode(json_encode($_cacheInfo,true));					 
			$_cacheInfo	 = '<?php $cacheInfo = "' . $_cacheInfo . '"; ?>';	
				
			file_put_contents($cacheInfoPath, $_cacheInfo);	
		}
	}
}

/*

	The parent of all cache types
	
*/
class PHPTreeCache {
	
	/*

   		memcached 
 	
	*/		
	protected  $mem;
	/*
	
	   File cache
	 
	*/	
	protected  $file;
	/*
	
	   System Environment 
	 
	*/
	protected $env;
	
	/*
	
		Read and cache Env 
		also setup enabled caching algorithms 
		example :
		$api->cache->set | get | delete | flush | exists
		
		supported the following : 
			
		PTCF -> PHPTreeCacheFiles -> files and directories
		PTCMEM -> PHPTreeCacheMemcached -> Memcached
	
	*/
	public function __construct(){
		
		//initialize file caching
		$this->file = new PTCF();
		
		//CACHED :: read from file
		if ( !$this->exists('PTEnv',CACHE_TYPE_FILE) )
		{
			$this->env 	= $this->readYaml( DIR . "/.env.yaml");
			
			if ( $this->env['cache']['file']['enabled'] )
			{
				$this->set('PTEnv',$this->env,null,CACHE_TYPE_FILE);
			}
			
		}else
		//No cached :: read and cache if enabled
		{
			$this->env = $this->get('PTEnv', CACHE_TYPE_FILE);
		}
		
		//Setup memcached if enabled
		if( $this->env != null AND 
			$this->isEnabled(CACHE_TYPE_MEM) AND
			isset($this->env['cache']) AND 
			isset($this->env['cache']['memcached']) 
			)
		{
			//Memcached is not installed!
			if ( !class_exists('Memcached') ){
				throw new \Exception("Cache type Memcached enabled but 'Memcached' is not installed.");
			}
			
			//initialize memcached
			$this->mem = new PTCMEM();
			
			if ( sizeof($this->env['cache']['memcached']['servers']) > 0 )
			{
				foreach( $this->env['cache']['memcached']['servers'] AS $server )
				{
					$this->mem->addServer($server['server'], $server['port'],  $server['weight']);
				}
			}
		}
	}
	
	/*
		return memcached instance
	*/
	public function getMem() : \Memcached  | null{
		return $this->mem->$instance;
	}
	/*
		
		return full Environment array
		
	*/
	public function getEnvironment() : array | bool {
		return $this->env;
	}
	/*
		Flush all expired cache files
		Call every 1 or 3 minutes from cronJob 
	*/	
	public function flush($type = CACHE_TYPE_FILE) : void
	{
		switch($type)
		{
			/*
			Memcached
			*/
			case CACHE_TYPE_MEM:
				
				if ( $this->mem != null ){
					 $this->mem->flush();
				}
				
			break;
			/*
				FILES
			*/
			case CACHE_TYPE_FILE:
				
				$this->file->flush();
				
			break;	
		}
		
	}
	/*
		Close connection with specific type
	*/
	public function disconnect($type){
		
		switch($type)
		{
			/*
				Memcached
			*/
			case CACHE_TYPE_MEM:
				
				if ( $this->mem != null ){
					 $this->mem->quit();
				 }
				
			break;	
		}
	}
	/*
		IF specific type of caching is enabled 
	*/
	public function isEnabled($type) : bool {
		
		switch($type)
		{
			/*
				Memcached
			*/
			case CACHE_TYPE_MEM:
				
				return $this->env['cache']['memcached']['enabled'];
				
			break;
			/*
				FILES
			*/
			case CACHE_TYPE_FILE:
				
				return $this->env['cache']['file']['enabled'];
				
			break;	
		}
		
		return false;
	}
	/*
		if cache exists
		@param key  = string 
		@param type = Type of cache
	*/
	public function exists($key, $type = CACHE_TYPE_FILE){
		
		switch($type)
		{
			/*
				Memcached
			*/
			case CACHE_TYPE_MEM:
				
				if ( $this->mem != null ){
					return $this->mem->exists($key);
				}
				
			break;
			/*
				FILES
			*/
			case CACHE_TYPE_FILE:
				
				return $this->file->exists($key);
				
			break;	
		}
		
		return false;
	}
	
	/*
		delete cache 
		@param key = string 
		@param type = Type of cache
	*/
	public function delete($key, $type = CACHE_TYPE_FILE ){
		
		switch($type)
		{
			/*
				Memcached
			*/
			case CACHE_TYPE_MEM:
				
				if ( $this->mem != null ){
					return $this->mem->delete($key);
				}
				
			break;
			/*
				FILES
			*/
			case CACHE_TYPE_FILE:
				
				$this->file->delete($key);
				
			break;	
		}		
		
	}
	
	/*
		get cache 
		@param key = string 
		@param type = Type of cache
		
		@return array 
	*/
	public function get($key, $type = CACHE_TYPE_FILE ){
		
		switch($type)
		{
			/*
				Memcached
			*/
			case CACHE_TYPE_MEM:
				
				if ( $this->mem != null ){
					return $this->mem->get($key);
				}
				
			break;
			/*
				FILES
			*/
			case CACHE_TYPE_FILE:
				
				return $this->file->get($key);
				
			break;		
			default : 
				return false;
		}		
		
		return false;
	}
	
	/*
		Set cache 
		@param key = string 
		@param array = array only
		@param timestamp = expire unix timestamp
		@param type = Type of cache

		@return boolean if set or not.
	*/
	public function set( $key , $array , $timestamp = 0 , $type = CACHE_TYPE_FILE ){
	
		switch($type)
		{
			
			/*
				Memcached
			*/
			case CACHE_TYPE_MEM:
				
				if ( $this->mem != null ){
					return $this->mem->set($key , $array , $timestamp );
				}
				
			break;
			/*
				FILES 
			*/
			case CACHE_TYPE_FILE:
				
				return  $this->file->set($key , $array , $timestamp );
				
			break;		
			default : 
			return false;
		}
		
	}
	/*
	
	   Read YAML file and return values 
	   
	*/
	private $ndocs 		 = 0;
	private $yaml		 = array();
	
	function readYaml( $fullPath ) : array | false {
		
		//Return cached version 
		if ( isset($this->yaml[md5($fullPath)]) AND $this->yaml[md5($fullPath)] != null )
		{
			return $this->yaml[md5($fullPath)];
		}
	
		//Read , parse and return 
		if ( file_exists($fullPath) AND !is_dir($fullPath) ) {
			
			$this->yaml[md5($fullPath)] = file_get_contents( $fullPath );
			
			$this->yaml[md5($fullPath)] = yaml_parse($this->yaml[md5($fullPath)], 
													 0, 
													 $this->ndocs, 
													 array());
													 
			return $this->yaml[md5($fullPath)];
			
		}else{
			return false ;
		}
	}
}

