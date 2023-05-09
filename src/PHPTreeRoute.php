<?php

namespace PHPTree\Core;

use \Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class PHPTreeRoute 
{	
	const GET  = 'get';
	const POST = 'post';
	
	public function __construct(
		public string $path,
		public ?array $params = null,
		public string $request = PHPTreeRoute::GET
	){
		
	}
	
	/*
		Fetch all routes from all provided controllers 
		No cache
	*/
	public static function routesByControllers( $controllers ) : array
	{
		
		$routes = array();
		
		if ( sizeof($controllers) == 0 ){
			return $routes;
		}
		
		foreach( $controllers AS $controller )
		{
			   
		   if ( !file_exists($controller['path'])){
			   continue ;
		   }	
		   
		   include_once($controller['path']);
			
		   //Get class	
		   $reflectionClass = new \ReflectionClass($controller['class']);
		   $methods		    = $reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC);
	
		   foreach ($methods as $method) {
				 
				 $reflectionMethod = new \ReflectionMethod($controller['class'], $method->getName());
					 
				 $attributes = $reflectionMethod->getAttributes('PHPTree\Core\PHPTreeRoute');
	
				//Method is route supported
				if ( sizeof($attributes) > 0 )
				{
					  foreach ($attributes as $attribute) {
						  
						$attr 		 = $attribute->newInstance();
						$route_regex = $attr->path;
	
						 //check if array not empty replace values
						  if ( @is_array($attr->params) AND !@empty($attr->params) )
						  {
							  foreach( $attr->params AS $key => $pat )
							  {
								  $route_regex = str_replace( "{".$key."}" ,  "($pat)" , $route_regex);
							  }
						  }
						  
						//Register all Route information  
						$routes[$route_regex] = array('url_regex'  => $attr->path,
													  'method'	 => $method->name,
													  'request'	 => $attr->request,
													  'params' 	 => $attr->params);
															
						$routes[$route_regex] = array_merge($routes[$route_regex], $controller);
						
						unset($attr,$attributes,$reflectionMethod);
					  }
				 }
			 }//End loop
			 
			 unset($reflectionClass,$methods); 
			 
		}//end loop
		
		unset($controllers); 
		
		return $routes;
	}
	
}
