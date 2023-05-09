<?php

namespace PHPTree\Core;

use PHPTree\Core\PHPTreeCache AS Cache;
use PHPTree\Core\PHPTreeRoute AS Route;


abstract class PHPTreeAbstract 
{  
	

	/*
	   List of requested yaml files
	   no cache
	*/
	private $yaml			 = array();
	private $ndocs 			 = 0;
	protected $routes;
	protected $controllers	 = array();
	/*
	
	   $_SERVER
	 
	*/
	protected $server;
	/*
	
	   System Environment 
	 
	*/
	protected $env;
	/*
		Current active route Params
	*/
	public $params = array();
	
	
	protected function setup_system_environment(){
		
		
		$this->env 	= $this->readYaml(DIR . "/.env.yaml");

	}
	
	/*
	   Read YAML file and return values 
	   No cache
	*/
	function readYaml( $fullPath )
	{
		
		if ( isset($this->yaml[md5($fullPath)]) AND $this->yaml[md5($fullPath)] != null )
		{
			return $this->yaml[md5($fullPath)];
		}
	
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
	
	/*
		Load up all controllers into the system
		Will use enabled caching extensions  
	*/
	protected function loadRoutes() : void{
		
		//Fetch all available Routes cached version
		if ( !Cache::exists('routes', CACHE_TYPE_FILE) )
		{
			$this->routes = Route::routesByControllers( $this->controllers );
			
			if ( $this->env['cache']['enabled'] )
			{
				Cache::set('routes',$this->routes,null,CACHE_TYPE_FILE);
			}
			
		}else{
			$this->routes = Cache::get('routes', CACHE_TYPE_FILE);
		}
		
	}
	
	/*
		Load up all controllers into the system
		Will use enabled caching extensions  
	*/
	protected function loadControllers() : void{
		
		//Fetch all available controllers cached version
		if ( !Cache::exists('controllers', CACHE_TYPE_FILE)  )
		{
			$this->controllers = $this->prepareControllers(null);
			
			if ( $this->env['cache']['enabled'] )
			{
				Cache::set('controllers',$this->controllers,null,CACHE_TYPE_FILE);
			}
			
		}else{
			$this->controllers = Cache::get('controllers', CACHE_TYPE_FILE);
		}
	}
	
	/*
	    Get all @controller_ files with class info 
		this will also fetch sub folders 
		No cache
	*/
	private function prepareControllers( $folder = null ) : array
	{
		
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
		
	protected function parseRouteUrl(){
		
		if ( $this->routes != null and
			 $filter_route = array_filter(array_keys($this->routes), array($this , 'filter_requested_route') )  )
		{
		
			$route_key	  = $filter_route[array_keys($filter_route)[0]];
			$route 		  = $this->routes[$route_key];
		
			//Confirm match and get params
			if( @preg_match( "@^" . $route_key . "(/|)$@" , $this->server['PTUri'] , $m ) )
			{
				//Route matched keys by regex
				$route['matched_keys']  = $m;
				
				//Route request method Checkpoint
				if ($_SERVER['REQUEST_METHOD'] == 'GET' AND 
					$route['request'] == Route::POST ) {
		
					$this->print404();
				
				}else{
					$this->loadRoute ( $route );
				}
			
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
	
	private function loadRoute ( $route ) : void{
		
		//Get current route params
		if ( is_array($route['params']) AND sizeof($route['params']) > 0  )
		{
			$i = 1;
			
			foreach( $route['params'] AS $key => $pattren )
			{
				$this->params[$key] = $route['matched_keys'][$i];
				$i++;
			}
		}
		
		//Execute the route 
		include_once($route['path']);
		call_user_func( array( new $route['class']($this) , $route['method'] ), $this );
	}
	
	/*
		Filter requested Route
	*/
	protected function filter_requested_route($route) : bool{
		
		//Match with regex
		if( @preg_match( "@^" . $route . "(/|)$@" , $this->server['PTUri'] , $m ) )
		{
			return true;
		}
		
		return false;
	}
	
	private function print404() : void{
		
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