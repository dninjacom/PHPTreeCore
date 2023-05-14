<?php

namespace PHPTree\Core;

use PHPTree\Core\PHPTreeCache AS Cache;
use PHPTree\Core\PHPTreeRoute AS Route;


abstract class PHPTreeAbstract 
{  
	protected array | null $routes;
	protected array | null $controllers = array();
	/*
	
	   $_SERVER
	 
	*/
	protected array | null $server;
	/*
	
	   System Environment 
	 
	*/
	protected array | null $env =  null;
	/*
		
		Caching instance
	
	*/
	public Cache | null $cache;

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
		
			Fetch controllers 
			- cached version will return if enabled
		
		*/
		$this->fetchControllers();
		/*
		
			Fetch Routes 
			- cached version will return if enabled
		
		*/		
		$this->loadRoutes();
		/*
		
			Fetch Routes
			- Filter and find requested route then execute it
		
		*/	
		$this->findRoute();
		
	}
	/*
	
		Load all controllers 
		fetch from cached version 
		or fetch all available controllers
		
	*/	
	private function fetchControllers() : void {
	
		/*
		
			CACHE By Memcached
		
		*/	
		if ($this->env['controller']['cacheType'] == CACHE_TYPE_MEM AND 
			$this->cache->isEnabled(CACHE_TYPE_MEM)  )
		{
			
			if (  $this->cache->exists('PTControllers', CACHE_TYPE_MEM)  )
			{
				$this->controllers = $this->cache->get('PTControllers', CACHE_TYPE_MEM);
				
			}else{
				$this->controllers = $this->prepareControllers(null);
				$this->cache->set('PTControllers',$this->controllers,null,CACHE_TYPE_MEM);
			}
			
		}else	
		/*
		
			CACHE By Redis
		
		*/	
		if ($this->env['controller']['cacheType'] == CACHE_TYPE_REDIS AND 
			$this->cache->isEnabled(CACHE_TYPE_REDIS)  )
		{
			
			if (  $this->cache->exists('PTControllers', CACHE_TYPE_REDIS)  )
			{
				$this->controllers = $this->cache->get('PTControllers', CACHE_TYPE_REDIS);
				
			}else{
				$this->controllers = $this->prepareControllers(null);
				$this->cache->set('PTControllers',$this->controllers,null,CACHE_TYPE_REDIS);
			}
			
		}else
		/*
		
			CACHE By Files
		
		*/	
		if ($this->env['controller']['cacheType'] == CACHE_TYPE_FILE AND 
			$this->cache->isEnabled(CACHE_TYPE_FILE)  )
		{
			
			if (  $this->cache->exists('PTControllers', CACHE_TYPE_FILE)  )
			{
				$this->controllers = $this->cache->get('PTControllers', CACHE_TYPE_FILE);
				
			}else{
				$this->controllers = $this->prepareControllers(null);
				$this->cache->set('PTControllers',$this->controllers,null,CACHE_TYPE_FILE);
			}
			
		}else
		/*
		
			NO CACHE
		
		*/
		{
			$this->controllers = $this->prepareControllers(null);
		}
	
	}
	/*
	
		Load all controllers into the system
		Will use enabled caching extensions  
		
	*/
	protected function loadRoutes() : void {
		
		/*
	
			CACHE By Memcached
		
		*/	
		if ($this->env['route']['cacheType'] == CACHE_TYPE_MEM AND 
			$this->cache->isEnabled(CACHE_TYPE_MEM)  )
		{
			
			if (  $this->cache->exists('PTRoutes', CACHE_TYPE_MEM)  )
			{
				$this->routes = $this->cache->get('PTRoutes', CACHE_TYPE_MEM);
				
			}else{
				$this->routes = Route::routesByControllers( $this->controllers );
				$this->cache->set('PTRoutes',$this->routes,null,CACHE_TYPE_MEM);
			}
			
		}else	
		/*
	
			CACHE By Redis
	
		*/	
		if ($this->env['route']['cacheType'] == CACHE_TYPE_REDIS AND 
			$this->cache->isEnabled(CACHE_TYPE_REDIS)  )
		{
			
			if (  $this->cache->exists('PTRoutes', CACHE_TYPE_REDIS)  )
			{
				$this->routes = $this->cache->get('PTRoutes', CACHE_TYPE_REDIS);
				
			}else{
				$this->routes = Route::routesByControllers( $this->controllers );
				$this->cache->set('PTRoutes',$this->routes,null,CACHE_TYPE_REDIS);
			}
			
		}else		
		/*
	
			CACHE By Files
		
		*/	
		if ($this->env['route']['cacheType'] == CACHE_TYPE_FILE AND 
			$this->cache->isEnabled(CACHE_TYPE_FILE)  )
		{
			
			if (  $this->cache->exists('PTRoutes', CACHE_TYPE_FILE)  )
			{
				$this->routes = $this->cache->get('PTRoutes', CACHE_TYPE_FILE);
				
			}else{
				$this->routes = Route::routesByControllers( $this->controllers );
				$this->cache->set('PTRoutes',$this->routes,null,CACHE_TYPE_FILE);
			}
			
		}else
		/*
		
			NO CACHE
		
		*/
		{
			$this->routes = Route::routesByControllers( $this->controllers );
		}
	
	}
	
	/*
	
		Parse selected route 
		
	*/
	protected function findRoute() : void {


		//Get route mapping key
		$mapping_key = explode("/", $this->server['PTUri']);
		$mapping_key = array_splice($mapping_key, 1);
		$mapping_key = sizeof($mapping_key);
		
		/*
		
			Find route by Regex 
			
		*/
		if ( $this->routes != null AND
			 isset($this->routes[$mapping_key]) AND
			 $filter_route = array_filter(array_keys($this->routes[$mapping_key]), 
			 							  array($this , 'filter_requested_route') )  )
		{
		
			$route_key	  = $filter_route[array_keys($filter_route)[0]];
			$route 		  = $this->routes[$mapping_key][$route_key];
		
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
		
		unset($mapping_key);
	}
	/*
	
		Last stop , load route and execute it
	
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
		
		$path  = DIR . '/' . $this->env['controller']['dir'] . '/' . $folder; 
			
	   foreach (new \DirectoryIterator($path) as $file)
	   {
		   if( $file->isDot() ) continue;
	
	  	   if ( is_dir( $path .  $file->getFilename() ) )
		   {
			   $this->prepareControllers( $file->getFilename() . "/" );
		   }else
		   if( $file->isFile() )
		   {
				if ( preg_match('@' . $this->env['controller']['prefix'] . '([a-z-A-Z-0-9_-]+)@',  $file->getFilename() , $m) )
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