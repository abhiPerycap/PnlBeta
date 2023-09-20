<?php return array (
  'beyondcode/laravel-websockets' => 
  array (
    'providers' => 
    array (
      0 => 'BeyondCode\\LaravelWebSockets\\WebSocketsServiceProvider',
    ),
    'aliases' => 
    array (
      'WebSocketRouter' => 'BeyondCode\\LaravelWebSockets\\Facades\\WebSocketRouter',
    ),
  ),
  'facade/ignition' => 
  array (
    'providers' => 
    array (
      0 => 'Facade\\Ignition\\IgnitionServiceProvider',
    ),
    'aliases' => 
    array (
      'Flare' => 'Facade\\Ignition\\Facades\\Flare',
    ),
  ),
  'fruitcake/laravel-cors' => 
  array (
    'providers' => 
    array (
      0 => 'Fruitcake\\Cors\\CorsServiceProvider',
    ),
  ),
  'haruncpi/laravel-id-generator' => 
  array (
    'providers' => 
    array (
      0 => 'Haruncpi\\LaravelIdGenerator\\IdGeneratorServiceProvider',
    ),
  ),
  'jenssegers/mongodb' => 
  array (
    'providers' => 
    array (
      0 => 'Jenssegers\\Mongodb\\MongodbServiceProvider',
      1 => 'Jenssegers\\Mongodb\\MongodbQueueServiceProvider',
    ),
  ),
  'laravel/sail' => 
  array (
    'providers' => 
    array (
      0 => 'Laravel\\Sail\\SailServiceProvider',
    ),
  ),
  'laravel/sanctum' => 
  array (
    'providers' => 
    array (
      0 => 'Laravel\\Sanctum\\SanctumServiceProvider',
    ),
  ),
  'laravel/tinker' => 
  array (
    'providers' => 
    array (
      0 => 'Laravel\\Tinker\\TinkerServiceProvider',
    ),
  ),
  'maatwebsite/excel' => 
  array (
    'providers' => 
    array (
      0 => 'Maatwebsite\\Excel\\ExcelServiceProvider',
    ),
    'aliases' => 
    array (
      'Excel' => 'Maatwebsite\\Excel\\Facades\\Excel',
    ),
  ),
  'nesbot/carbon' => 
  array (
    'providers' => 
    array (
      0 => 'Carbon\\Laravel\\ServiceProvider',
    ),
  ),
  'nunomaduro/collision' => 
  array (
    'providers' => 
    array (
      0 => 'NunoMaduro\\Collision\\Adapters\\Laravel\\CollisionServiceProvider',
    ),
  ),
);