<?php

namespace PHPTree\Core;

abstract class PHPTreeAbstract 
{  
	
	/*
	   List of all controllers
	   no cache
	*/
	protected $controllers = array();
	/*
	   List of requested yaml files
	   no cache
	*/
	private $yaml		 = array();
	private $ndocs 		 = 0;
	protected $routes;
	
	
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
		Fetch all routes from all provided controllers 
		No cache
	*/
	protected  function fetchRoutes( $controllers )
	{
		if ( sizeof($controllers) == 0 ){
			return ;
		}
		
		foreach( $controllers AS $controller )
		{
			   
		   if ( !file_exists($controller['path'])){
			   continue ;
		   }	
		   
		   include($controller['path']);
			
		   //Get class	
		   $reflectionClass = new \ReflectionClass($controller['class']);
		   $methods		    = $reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC);

		   foreach ($methods as $method) {
				 
				 $reflectionMethod = new \ReflectionMethod($controller['class'], $method->getName());
					 
				 $attributes = $reflectionMethod->getAttributes('PHPTree\Core\PTRoute');

				//Method is route supported
				if ( sizeof($attributes) > 0 )
				{
					  foreach ($attributes as $attribute) {
						  
						$attr 		 = $attribute->newInstance();
						$route_regex = $attr->path;
	
						 //check if array not empty replace values
						  if ( @is_array($attr->keys) AND !@empty($attr->keys) )
						  {
							  foreach( $attr->keys AS $key => $pat )
							  {
								  $route_regex = str_replace( "{".$key."}" ,  "($pat)" , $route_regex);
							  }
						  }
						  
						//Register all Route information  
						$this->routes[$route_regex] = array('url_regex'  => $attr->path,
															'method'	 => $method->name,
															'request'	 => $attr->request,
															'params' 	 => $attr->params);
															
						$this->routes[$route_regex] = array_merge($this->routes[$route_regex], $controller);
						
						unset($attr,$attributes,$reflectionMethod);
					  }
				 }
			 }//End loop
			 
			 unset($reflectionClass,$methods); 
			 
		}//end loop
		
		unset($controllers); 
	}
	
	/*
	    Get all @controller_ files with class info 
		this will also fetch sub folders 
		No cache
	*/
	protected function getAllControllers( $folder = null ) : array
	{
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
					if ( preg_match('@controller_([a-z-A-Z-0-9]+)@',  $file->getFilename() , $m) )
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
	protected function filter_requested_route($route){
		
		//Match with regex
		if( @preg_match( "@^" . $route . "(/|)$@" , $this->request_uri , $m ) )
		{
			return true;
		}
		
		return false;
	}
	
}