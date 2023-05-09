<?php

namespace PHPTree\Core;

define("CACHE_TYPE_REDIS", 2);
define("CACHE_TYPE_FILE", 1);

class PHPTreeCache {
	
	
	/*
		
		Caching folder 
	
	*/
	private const folder = "/var/cache/";
	/*
		
		Cache info ( files system only )
	
	*/
	private const cache_info = "_c_";
	
	
	/*
		Flush all expired cache files
		Call every 1 or 3 minutes from cronJob 
		
		Example : 
			
			use PHPTree\Core\PHPTreeCache AS Cache;
	
			//Flush expired PTCaches 
			Cache::flushExpired();
	*/	
	public static function flushExpired()
	{
		$cacheInfoPath = DIR . PHPTreeCache::folder . PHPTreeCache::cache_info . ".php";
		
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
	
	/*
		if cache exists
		@key = string 
		@type = Type of cache
	*/
	public static function exists($key, $type = CACHE_TYPE_FILE){
		
		switch($type)
		{
			/*
				Using cache type files and folders
			*/
			case 1:
				
				$name     = '_' . md5($key);
				$filePath = DIR . PHPTreeCache::folder . $name . ".php";
				
				if ( file_exists($filePath))
				{
					unset($filePath,$name);
					return true;
				}
				
			break;	
		}
		
		return false;
	}
	
	/*
		remove cache 
		@key = string 
		@type = Type of cache
	*/
	public static function remove($key, $type = CACHE_TYPE_FILE ){
		
		switch($type)
		{
			/*
				Using cache type files and folders
			*/
			case 1:
				
				$name     = '_' . md5($key);
				$filePath = DIR . PHPTreeCache::folder . $name . ".php";
				
				if ( file_exists($filePath))
				{
					unlink($filePath);
				}
				
			break;	
		}		
		
	}
	
	/*
		get cache 
		@key = string 
		@type = Type of cache
		
		@return array 
	*/
	public static function get($key, $type = CACHE_TYPE_FILE ){
		
		switch($type)
		{
			/*
				Using cache type files and folders
			*/
			case 1:
				
				$name     = '_' . md5($key);
				$filePath = DIR . PHPTreeCache::folder . $name . ".php";
				
				if (file_exists($filePath))
				{
					include_once($filePath);
					return json_decode( base64_decode(${'cache_' . $name}),true );
				}
			break;		
			default : 
				return false;
		}		
		
		return false;
	}
	
	/*
		Set cache 
		@key = string 
		@array = array only
		@timestamp = expire unix timestamp
		@type = Type of cache

		@return boolean if set or not.
	*/
	public static function set( $key , $array , $timestamp = 0 , $type = CACHE_TYPE_FILE ){
	
		switch($type)
		{
			
			/*
				Using cache type files and folders
			*/
			case 1:
				
				$name     = '_' . md5($key);
				$filePath = DIR . PHPTreeCache::folder . $name . ".php";
				$content  = '<?php $cache_' . $name . ' = "' . base64_encode(json_encode($array,true)) . '"; ?>';	
					
				if ( file_put_contents($filePath, $content) ) {
					
					//Set expired ( create or update cache folders information )
					if ( $timestamp != null AND $timestamp > 0 )
					{
						$cacheInfoPath = DIR . PHPTreeCache::folder . PHPTreeCache::cache_info . ".php";
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
			break;		
			default : 
			return false;
		}
		
		
	}
}

