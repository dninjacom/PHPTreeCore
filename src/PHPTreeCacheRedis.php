<?php

namespace PHPTree\Core;

use PHPTree\Core\PHPTreeCore AS CORE;	
/*

	REDIS
	https://redis.io/

*/
class PHPTreeCacheRedis { 
	
	public bool $connected = false;

	public static PHPTreeCacheRedis |null $instance = null;
	
	public \Redis | null $redis;
	
	/*
		REDIS
	*/
	public function __construct(){
	
		if ( class_exists('Redis') )
		{
			$this->redis = new \Redis();
		}else{
			throw new \Exception("Redis Not installed ");
		}
		
	}
	
	public static function init(){
		
		if ( static::$instance == null )
		{
		   
			static::$instance = new PHPTreeCacheRedis();
				
	 		//Setup redis if enabled 
	 		if( CORE::$env != null AND 
	 			CORE::$env['cache']['redis']['enabled'] )
	 		{
	 			 
	 			//Redis is not installed!
	 			if ( !class_exists('Redis') ){
	 				throw new \Exception("Cache type Redis enabled but 'Redis' is not installed.");
	 			}
	 			
	 			if ( isset(CORE::$env['cache']['redis']['server']) AND 
	 		   		 isset(CORE::$env['cache']['redis']['port']) AND 
	 				 isset(CORE::$env['cache']['redis']['timeout']))
	 			{
	 				static::$instance->connect(CORE::$env['cache']['redis']['server'], 
								  			   CORE::$env['cache']['redis']['port'],
								  			   CORE::$env['cache']['redis']['timeout']);
	 				
	 			}
	 		}  
			   
		}
	}
	
	public static function quit(){
		
		static::init();
			
		if ( static::$instance->redis != null  )
		{
			static::$instance->redis->close();
			static::$instance->connected = false;
		}
	}
	/*
		REDIS::SET
	*/		
	public static function set($key,$value, $seconds = 0) : bool{
		
		static::init();
			
		if ( !static::$instance->connected )
		{
			throw new \Exception("Redis not connected!");
		}
		
		static::$instance->redis->set($key, $value);
			
		if ( $seconds > 0 )
		{
			static::$instance->redis->expire($key, $seconds);
		}
		
		return true;
	}
	/*
		REDIS::GET
	*/		
	public static function get($key) {
		
		static::init();
		
		if ( !static::$instance->connected )
		{
			throw new \Exception("Redis not connected!");
		}
		
		return static::$instance->redis->get($key);
	
	}
	/*
		REDIS::EXISTS
	*/	
	public static function exists($key) :  bool {
		
		static::init();
	
		if ( !static::$instance->connected )
		{
			throw new \Exception("Redis not connected!");
		}
		
		return static::$instance->redis->exists($key);
	}
	/*
		REDIS::DELETE
	*/	
	public static function delete($key) : void{
		
		static::init();
		
		if ( !static::$instance->connected )
		{
			throw new \Exception("Redis not connected!");
		}
		
		static::$instance->redis->del($key);
	}
	/*
		REDIS::FLUSH
	*/	
	public static function flush() : void{
		
		static::init();
	
		if ( !static::$instance->connected )
		{
			throw new \Exception("Redis not connected!");
		}
		
		static::$instance->redis->flushAll();
	}
	/*
		REDIS::ADD SERVER
	*/
	public static function connect(string $host, int $port, int $timeout = 0){
		
		static::init();
			
		static::$instance->redis->connect($host, $port, $timeout);
		
		static::$instance->connected   =  ( static::$instance->redis->time() != null );
		
	}	
	
}
