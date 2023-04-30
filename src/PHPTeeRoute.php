<?php

namespace PHPTree\Core;

use \Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class PHPTeeRoute 
{	
	const GET  = 'get';
	const POST = 'post';
	
	public function __construct(
		public string $path,
		public ?array $params = null,
		public string $request = PHPTeeRoute::GET
	){
	}
}
