<?php
namespace PHPTree\Core;

use PHPTree\Core\PHPTreeCacheMemcached AS PTMEMCACHED;
use PHPTree\Core\PHPTreeCacheRedis AS PTREDIS;
use PHPTree\Core\PHPTreeCache AS PTCACHE;
use PHPTree\Core\PHPTreeCore AS CORE;

class PHPTreeControllerManager 
{	
	
	public static array | null $controllers = array();


	public static function build() : void{
		
		/*
		
			CACHE By Memcached
		
		*/	
		if (CORE::$env['controller']['cacheType'] == CACHE_TYPE_MEM AND 
			CORE::$env['cache']['memcached']['enabled']  )
		{
			
			if (  PTMEMCACHED::exists('PTControllers')  )
			{
				static::$controllers = PTMEMCACHED::get('PTControllers');
				
			}else{
				static::$controllers = static::prepareControllers(null);
				PTMEMCACHED::set('PTControllers',static::$controllers,null);
			}
			
		}else	
		/*
		
			CACHE By Redis
		
		*/	
		if (CORE::$env['controller']['cacheType'] == CACHE_TYPE_REDIS AND 
			CORE::$env['cache']['redis']['enabled']  )
		{
			
			if ( PTREDIS::exists('PTControllers')  )
			{
				static::$controllers = PTREDIS::get('PTControllers');
				
			}else{
				static::$controllers = static::prepareControllers(null);
				PTREDIS::set('PTControllers',static::$controllers,null);
			}
			
		}else
		/*
		
			CACHE By Files
		
		*/	
		if (CORE::$env['controller']['cacheType'] == CACHE_TYPE_FILE AND 
			CORE::$env['cache']['file']['enabled']  )
		{
			
			if ( PTCACHE::exists('PTControllers')  )
			{
				static::$controllers = PTCACHE::get('PTControllers');
				
			}else{
				static::$controllers = static::prepareControllers(null);
				PTCACHE::set('PTControllers',static::$controllers,null);
			}
			
		}else
		/*
		
			NO CACHE
		
		*/
		{
			static::$controllers = static::prepareControllers(null);
		}
		
	}
	
		
	/*
		Get all @prefix_ files with class info 
		will also fetch sub folders 
		No cache
	*/
	private static function prepareControllers(  null | string $dir = ""  ) : array {
		
		$path  = DIR . '/' . CORE::$env['controller']['dir'] . '/' . ( $dir != null ? join("" , $dir) : ""  ) ; 
		
		if ( file_exists($path) AND is_dir($path) )	
		{
			   foreach (new \DirectoryIterator($path) as $file)
			   {
				   if( $file->isDot() ) continue;
			
					 if ( is_dir( $path . $file->getFilename() ) )
				   {
					   $dir[] = $file->getFilename() . "/" ;
					   static::prepareControllers($dir);
				   }else
				   if( $file->isFile() )
				   {
						if ( preg_match('@' . CORE::$env['controller']['prefix'] . '([a-z-A-Z-0-9_-]+)@',  $file->getFilename() , $m) )
						{
							   static::$controllers[$m[1]]['path']    = $path .  $file->getFilename() ;
							   static::$controllers[$m[1]]['class']   = $m[1];
						}
				   }
			   }	
		   }
		
		return static::$controllers;
	}
}