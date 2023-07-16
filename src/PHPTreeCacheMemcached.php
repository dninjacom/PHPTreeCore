<?php

namespace PHPTree\Core;

use PHPTree\Core\PHPTreeCore AS CORE;	
/*

	Memcached
	https://www.php.net/manual/en/book.memcached.php	

*/
class PHPTreeCacheMemcached  { 
	
	public bool $connected = false;
	
	public static PHPTreeCacheMemcached|null $instance = null;
	
	public \Memcached | null $mem;
	
	/*
		MEM
	*/
	public function __construct(){
	
		if ( class_exists('Memcached') )
		{
			$this->mem = new \Memcached();
		}else{
			throw new \Exception("Memcached Not installed ");
		}
	}
	
	public static function init(){
		
		if ( static::$instance == null )
		{
		   static::$instance = new PHPTreeCacheMemcached();
			   
		    if ( sizeof(CORE::$env['cache']['memcached']['servers']) > 0 )
   			{
   				foreach( CORE::$env['cache']['memcached']['servers'] AS $server )
   				{
   					static::$instance->addServer($server['server'], $server['port'],  $server['weight']);
   				}
   			}
		}
	}
	
	public static function quit(){
		
		static::init();
			
		if ( static::$instance->mem != null  )
		{
			static::$instance->mem->quit();
			static::$instance->connected = false;
		}
	}
	/*
		MEM::SET
	*/		
	public static function set($key,$value, int  $timestamp = 8400 * 3) : bool{
		
		static::init();
			
		if ( !static::$instance->connected )
		{
			throw new \Exception("Memcached not connected!");
		}
		
		static::$instance->mem->set($key, $value, $timestamp);
		
		return static::$instance->mem->getResultCode() == 0;
	}
	/*
		MEM::GET
	*/		
	public static function get($key) {
		
		static::init();
		
		if ( !static::$instance->connected )
		{
			throw new \Exception("Memcached not connected!");
		}
		
		return static::$instance->mem->get($key);
	}
	/*
		MEM::EXISTS
	*/	
	public static function exists($key) :  bool {
		
		static::init();
		
		if ( !static::$instance->connected )
		{
			throw new \Exception("Memcached not connected!");
		}
		
		return static::$instance->mem->get($key) != null;
	}
	/*
		MEM::DELETE
	*/	
	public static function delete($key) : void{
		
		static::init();
	
		if ( !static::$instance->connected )
		{
			throw new \Exception("Memcached not connected!");
		}
		
		static::$instance->mem->delete($key);
	}
	/*
		MEM::FLUSH
	*/	
	public static function flush() : void{
		
		static::init();
		
		if ( !static::$instance->connected )
		{
			throw new \Exception("Memcached not connected!");
		}
		
		static::$instance->mem->deleteMulti( static::$instance->mem->getAllKeys() );
	}
	/*
		MEM::ADD SERVER
	*/
	public static function addServer(string $host, int $port, int $weight = 0){
		
		static::init();
			
		static::$instance->mem->addServer($host, $port,$weight);
		
		static::$instance->connected   =  ( static::$instance->mem->getStats() != null );
		
	}
}





