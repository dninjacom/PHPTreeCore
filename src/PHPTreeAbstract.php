<?php

namespace PHPTree\Core;

use PHPTree\Core\PHPTreeCache AS Cache;
use PHPTree\Core\PHPTreeRoute AS Route;


abstract class PHPTreeAbstract 
{  
	protected $routes;
	protected $controllers = null;
	/*
	
	   $_SERVER
	 
	*/
	protected $server;
	/*
	
	   System Environment 
	 
	*/
	protected $env =  null;
	/*
	
		Current active route Params
		
	*/
	public $params = array();
	/*
		
		All Caching instance
	
	*/
	public Cache $cache;
	private $cached_route = null;
	/*
	
		Setup env.yaml
		This file has the core information for the whole system
		
	*/
	protected function setup_system_environment(): void {
		
		$this->server			= $_SERVER; 
		$this->server['PTUri']	= parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
		
		$this->cache = new Cache();
		
		$this->env	 = $this->cache->getEnvironment();
	}
	
	/*
	
		Load and execute the current requested URL
		
	*/
	protected function LoadAndExecuteRequest() : void {
		
		/*
		
			For fast load the route without reFetching and Filtering 
			get the Requested route from caching apis.
		
		*/
		if ( $this->cache->isEnabled(CACHE_TYPE_MEM) )
		{
			//Find the ruote in memcached 
			$this->$cached_route = $this->cache->get( "ruote_" . md5( $this->server['PTUri'] ) , CACHE_TYPE_MEM );
			
			//Route already cached! great lets load it
			if ( is_array($this->$cached_route) AND !empty($this->$cached_route) )
			{
				$this->loadRoute( $this->$cached_route );
				return;
			}
		}
		
		//Fetch all available controllers cached version
		if ( !$this->cache->exists('controllers', CACHE_TYPE_FILE)  )
		{
			$this->controllers = $this->prepareControllers(null);
			
			if ( $this->cache->isEnabled(CACHE_TYPE_FILE) )
			{
				$this->cache->set('controllers',$this->controllers,null,CACHE_TYPE_FILE);
			}
			
		}else{
			$this->controllers = $this->cache->get('controllers', CACHE_TYPE_FILE);
		}


		$this->loadRoutes();
	}
	/*
	
		Load all controllers into the system
		Will use enabled caching extensions  
		
	*/
	protected function loadRoutes() : void {
		
		//Fetch all available Routes cached version
		if ( !$this->cache->exists('routes', CACHE_TYPE_FILE) )
		{
			$this->routes = Route::routesByControllers( $this->controllers );
			
			if ( $this->cache->isEnabled(CACHE_TYPE_FILE) )
			{
				$this->cache->set('routes',$this->routes,null,CACHE_TYPE_FILE);
			}
			
		}else{
			$this->routes = $this->cache->get('routes', CACHE_TYPE_FILE);
		}
		
		$this->findRoute();
	}
	
	/*
	
		Parse selected route 
		
	*/
	protected function findRoute() : void {

		/*
		
			Find route by Regex 
			
		*/
		if ( $this->routes != null and
			 $filter_route = array_filter(array_keys($this->routes), 
			 							  array($this , 'filter_requested_route') )  )
		{
		
			$route_key	  = $filter_route[array_keys($filter_route)[0]];
			$route 		  = $this->routes[$route_key];
		
			/*
				Confirm route and get Params
			*/
			if( @preg_match( "@^" . $route_key . "(/|)$@" , $this->server['PTUri'] , $m) )
			{
				$route['values']  = array_splice($m, 1);//Remove full url matching
				$this->loadRoute( $route );
			
			}//End confirm route
			else{
				$this->print404();
			}
			
		}else
		//Route not found!
		{
			$this->print404();
		}
	}
	/*
	
		Load route method and execute it 
	
	*/
	private function loadRoute( $route ) : void {
		
		//Route request method Checkpoint
		if ($_SERVER['REQUEST_METHOD'] == 'GET' AND 
			$route['request'] == Route::POST ) {
		
			//Alert Dev
			if ( !$this->env['prod'] ){
				throw new \Exception("Error (400) : " . $route_key. " . accepting only 'POST' when the request was 'GET'");
				return;
			}else{
				http_response_code(400);
				die();
			}
		}
		/*
		
			Cache Ruote
		
		*/
		if ( $this->cache->isEnabled(CACHE_TYPE_MEM) )
		{
			//Already cached!
			if ( $this->$cached_route != null )
			{
				$this->cache->getMem()->touch( "ruote_" . md5( $this->server['PTUri'] ) ,
											   $this->env['cache']['memcached']['ruote_ttl'] );
				
			}else{
				$this->cache->set("ruote_" . md5( $this->server['PTUri'] ) , 
								   $route ,
								   $this->env['cache']['memcached']['ruote_ttl'] ,
								   CACHE_TYPE_MEM );
			}
		}
		
		//Get current route params
		if ( is_array($route['keys']) AND 
			 sizeof($route['keys']) > 0  )
		{
			foreach( $route['keys'] AS $i => $key )
			{
				Route::$params[$key] = $route['values'][$i];
			}
		}
		
		//Execute the route 
		include_once($route['path']);
		call_user_func( array( new $route['class']($this) , $route['method'] ), $this );
	}
	
	/*
	    Get all @controller_ files with class info 
		this will also fetch sub folders 
		No cache
	*/
	private function prepareControllers( $folder = null ) : array {
		
		if ( $this->env == null )
		{
			return array();
		}
		
		$path  = DIR . '/' . $this->env['system']['controllers'] . '/' . $folder; 
			
	   foreach (new \DirectoryIterator($path) as $file)
	   {
		   if( $file->isDot() ) continue;
	
	  	   if ( is_dir( $path .  $file->getFilename() ) )
		   {
			   $this->getAllControllers( $file->getFilename() . "/" );
		   }else
		   if( $file->isFile() )
		   {
				if ( preg_match('@controller_([a-z-A-Z-0-9_-]+)@',  $file->getFilename() , $m) )
				{
				   $this->controllers[$m[1]]['path']    = $path .  $file->getFilename() ;
				   $this->controllers[$m[1]]['folder']  = $path;
				   $this->controllers[$m[1]]['class']   = $m[1];
				}
		   }
	   }	
		
		return $this->controllers;
	}
		
	/*
	
		Filter requested Route
		
	*/
	protected function filter_requested_route($route) : bool {
		
		//Match with regex
		if( @preg_match( "@^" . $route . "(/|)$@" , $this->server['PTUri'] , $m ) )
		{
			return true;
		}
		
		return false;
	}
	
	/*
	
		404
		
	*/
	private function print404() : void {
		
		//Dev mode
		if ( !$this->env['prod'] ){
			throw new \Exception("Route (404) : " . $this->server['PTUri'] . " Not found! ");
			return;
		}
		
		//Route for 404 page maybe registered in routes
		if ( isset($this->env['route']) AND 
			 isset($this->routes[$this->env['route']['404']]) ){
			
			//Load 404 route
			$this->loadRoute( $this->routes[$this->env['route']['404']] );
			
		}else{
			http_response_code(404);
		}
	}
	

	
}