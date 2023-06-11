<?php

namespace PHPTree\Core;

use \Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION | Attribute::IS_REPEATABLE)]
class PHPTreeRoute 
{	
	/*
		Route request method
	*/
	const GET  = 'get';
	const POST = 'post';

	public function __construct(
		public string $path,
		public ?array $keys = null,
		public string $request = PHPTreeRoute::GET
	){
		
	}

}
