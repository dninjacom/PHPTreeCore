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
}
