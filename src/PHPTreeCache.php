<?php

namespace PHPTree\Core;


/*

	File caching inside cache directory

*/
class PHPTreeCache  { 
	
	/*
		
		Caching folder directory 
	
	*/
	protected string $dir = "/var/cache/";
	/*
		
		Cache info ( files system only )
	
	*/
	protected string $referance = "_c_";
	
	private static PHPTreeCache|null $instance = null;
	
	public static function init(){
		
		if ( static::$instance == null )
	    {
		   static::$instance = new PHPTreeCache();
	    }
	}
	
	public static function config( array $config ){
		
		static::init();
		
		if ( isset($config['dir']) ){
			static::$instance->dir = $config['dir'];
		}
		
		if ( isset($config['referance']) ){
			static::$instance->referance = $config['referance'];
		}
			
	}
	/*
		FILE::SET
	*/
	public static function set($key , $array , $timestamp = 0) : bool{
		
		static::init();
		
		$name     = '_' . md5($key);
		$filePath = DIR . static::$instance->dir . $name . ".php";
		$content  = '<?php $cache_' . $name . ' = "' . base64_encode(json_encode($array,true)) . '"; ?>';	
			
		if ( file_put_contents($filePath, $content) ) {
			
			//Set expired ( create or update cache folders information )
			if ( $timestamp != null AND $timestamp > 0 )
			{
				$cacheInfoPath = DIR . static::$instance->dir . static::$instance->referance . ".php";
				$cacheInfo     = array();
				
				if ( file_exists($cacheInfoPath) )
				{
					include_once($cacheInfoPath);
				}
			
				$cacheInfo   	  = ( is_array($cacheInfo) ) ? $cacheInfo : json_decode(base64_decode($cacheInfo),true);
				$cacheInfo[$name] = array('file' => $filePath,
										  'ttl'  => $timestamp,
									   	  'key'  => $key);
									 
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
	public static function get($key)  {
		
		static::init();
			
		$name     = '_' . md5($key);
		$filePath = DIR . static::$instance->dir . $name . ".php";
		
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
	public static function exists($key) :  bool {
		
		static::init();
			
		$name     = '_' . md5($key);
		$filePath = DIR . static::$instance->dir . $name . ".php";
		
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
	public static function delete($key) : void{
		
		static::init();
			
		$name     = '_' . md5($key);
		$filePath = DIR . static::$instance->dir . $name . ".php";
		
		if ( file_exists($filePath))
		{
			unlink($filePath);
		}
	}
	/*
		FILE::FLUSH
	*/
	public static function flush() : void{
		
		static::init();
			
		$cacheInfoPath = DIR . static::$instance->dir . static::$instance->referance . ".php";
		
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

