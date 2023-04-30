<?php

namespace PHPTree\Core;

use PHPTree\Core\PHPTreeAbstract;
use PHPTree\Core\PHPTreeCache AS Cache;
use PHPTree\Core\PHPTreeRoute as Route;

class PHPTreeCore extends PHPTreeAbstract
{  
	
	/*
	
	   System Environment 
	 
	*/
	protected $env;//system Environment
	/*
	   Requested url without domain name 
	*/
	protected $request_uri;
	/*
		Current active route Params
	*/
	public $params = array();
	
	function __destruct() {
		
		$this->params   	= null;
		$this->env      	= null;
		$this->controllers  = null;
		$this->routes		= null;
		
	}
	  
	public function __construct()
	{
		//Flush expired PTCaches 
		Cache::flushExpired();
		
		//Read environment 
		$this->env 			= $this->readYaml(DIR . "/.env.yaml");
	    $this->request_uri	= $_SERVER['REQUEST_URI'];
		
		//Fetch and init all Extensions 
		
		//Fetch all available controllers PTCached version
		if ( !Cache::exists('controllers', CACHE_TYPE_FILE)  )
		{
			$this->controllers = $this->getAllControllers(null);
			
			if ( $this->env['cache']['enabled'] )
			{
				Cache::set('controllers',$this->controllers,null,CACHE_TYPE_FILE);
			}
			
		}else{
			$this->controllers = Cache::get('controllers', CACHE_TYPE_FILE);
		}
		
		//Fetch all available Routes PTCached version
		if ( !Cache::exists('routes', CACHE_TYPE_FILE) )
		{
			$this->fetchRoutes( $this->controllers );
			
			if ( $this->env['cache']['enabled'] )
			{
				Cache::set('routes',$this->routes,null,CACHE_TYPE_FILE);
			}
			
		}else{
			$this->routes = Cache::get('routes', CACHE_TYPE_FILE);
		}
	
	 	//Parse selected route url and register its params
		if ( $filter_route = array_filter(array_keys($this->routes), array($this , 'filter_requested_route') )  )
		{
		
			$route_key	  = $filter_route[array_keys($filter_route)[0]];
			$route 		  = $this->routes[$route_key];
	
			//Confirm match and get params
			if( @preg_match( "@^" . $route_key . "(/|)$@" , $this->request_uri , $m ) )
			{
				//Route matched keys by regex
				$route['matched_keys']  = $m;
				
				//Route request method Checkpoint
				if ($_SERVER['REQUEST_METHOD'] == 'GET' AND $route['request'] == Route::POST ) {

					//Check if 404 is set from .env file 
					$this->print404();
				
				}else{
					$this->loadRoute ( $route );
				}
			
			}//End confirm route
			
			
		}else
		//Route not found!
		{
			$this->print404();
		}
	}
	
	
	private function print404(){
		
		//Route for 404 page should be registered in routes
		if ( isset($this->env['route']) AND isset($this->routes[$this->env['route']['404']]) ){
			
			//Load 404 route
			$this->loadRoute( $this->routes[$this->env['route']['404']] );
			
		}else{
			header("HTTP/1.0 404 Not Found");
			exit();
		}
		
	}
	
	private function loadRoute ( $route ){
		
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
		
		ob_start();
		call_user_func( array( new $route['class']($this) , $route['method'] ), $this );
		ob_flush();
	}
	

}
