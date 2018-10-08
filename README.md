[![Build Status](https://travis-ci.org/LucaRainone/pdo-powered.svg?branch=master)](https://travis-ci.org/LucaRainone/pdo-powered)
[![Coverage Status](https://coveralls.io/repos/github/LucaRainone/pdo-powered/badge.svg?branch=master)](https://coveralls.io/github/LucaRainone/pdo-powered?branch=master)

PDOPowered is a wrapper for PDO providing following features:

- **lazy connection** The PDO instance is created only when needed. Configured credentials are deleted after the connection.
  - with `$db->onConnect($closure)` it's possible to do something after the connection.
- **reconnect on failure** try to reconnect if the connection fails (for `PDOPowered::$MAX_TRY_CONNECTION` tries).
  - with `$db->onConnectionFailure($closure)` it's possible to do something after every connection fail (for example a sleep and/or error reporting)
- **fast query params**  prepare and execute are called in `query` method. `$db->query("SELECT ?,?,?", [1,2,3])`
- Query method returns a wrapper for `PDOStatement` with powered methods:
  - **fetchObjects** call `fetchObject` for each row and store the result in array
  - **getPDOStatement** for fetch the native PDOStatement.
- **fast queries** there are helpers for fast query:
  - **insert** for fast insert `$db->insert("tableName",$arrayValues)` returns the last insert id.
  - **update** for fast update `$db->update("tableName", $valuesToUpdate, $arrayCondition)` returns the count of affected rows.
  - **insertOnDuplicateKeyUpdate** for insert on duplicate key update `$db->insertOnDuplicateKeyUpdate($tableName, $arrayValues)`  returns the last insert id.
  - **delete** for fast delete `$db->delete("tableName", $arrayWhereCondition)` returns the count of affected rows
- **easy debug** with `$db->onDebug($closure)` it's possible to access to debug output and do your stuff (log in file, send to stdoutput and so on)
- **access to native PDO instance** extending `PDOPowered` you have access to `getPDO()` method for any needs.

## Basic Usage

### Installation
```
composer require rain1/pdo-powered
```
or checkout https://github.com/LucaRainone/pdo-powered.git

or download here (https://github.com/LucaRainone/pdo-powered/archive/master.zip)

### Get Started
An implementation of rain1\PDOPowered\Config\ConfigInterface must be passed to the constructor. 
We provide following implementations:

```php
use \rain1\PDOPowered\Config\Config;
$config = new Config("mysql", "user", "password", "localhost", 3306, "dbname", "utf8", [\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\'']);
```

or equivalent

```php
use \rain1\PDOPowered\Config\DSN;
$config = new DSN(
    "mysql:host=localhost;port=3306;dbname=dbname;charset=utf8", "user", "password"
);
$config->setOptions([\PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'UTF8'"]);
```

So, we build the instance in this way:

```php
$db = new PDOPowered($config);
```

It's possible to use PDOPowered with a pre-existent PDO instance
```php
$db = PDOPowered::buildFromPDOInstance($pdoInstance);
```

The library provides shortcuts for insert / update / delete and update on duplicate key:

```php
// fast insert. Return the inserted id
$id = $db->insert("tabletest", ['id'=>1, 'col1'=>'col1_1']);

// fast update
$db->update("tabletest", ['col1'=>'updated'], ['id'=>$id]);

// build a "INSERT ON DUPLICATE KEY UPDATE" query
$db->insertOnDuplicateKeyUpdate("tabletest", ['id'=> $id, 'col1'=>'updated']);
$db->insertOnDuplicateKeyUpdate("tabletest", ['id'=> $id+1, 'col1'=>'updated']);

// performs a delete
$db->delete("tabletest", ['id'=>$id]);

// simple query
$row = $db->query("SELECT * FROM tabletest WHERE id = ?", [$id])->fetch();

// or equivalent
$row = $db->query("SELECT * FROM tabletest WHERE id = :id", ['id'=>$id])->fetch();

```

The method `query` returns a wrapper of \PDOStatement. Availabile methods:
- `fetch`
- `fetchAll`
- `fetchObject`
- `fetchColumn`
- `rowCount`
- `closeCursor`
- `getPDOStatement` returns the native PDOStatmenet (for any evenience)
- `fetchObjects` returns an array containing all of the result of `fetchObject` call


## Advanced usage:

Following events are triggered:

- connectionFailure
- connect
- debug

```php
// triggered after a connection fails: before the next attempt
$db->onConnectionFailure(function($connectionTry, \Exception $exception) {
    sleep($connectionTry);
    error_log("Unable to connect on mysql: " . $exception->getMessage());
});

// triggered after a connection success
$db->onConnect(function() {
    echo "Db connected\n";
});

// triggered on debug string
$idDebugListener = $db->onDebug(function($debugType, \PDOStatement $PDOStatement = null, ...$args) {
    echo "$debugType\n";
    if($PDOStatement)
        $PDOStatement->debugDumpParams();
    echo "\n".json_encode($args);
});

// for each event we can remove the listener
$db->removeDebugListener($idDebugListener);

```

We provide an helper for easy debug

```php
$db->onDebug(DebugParser::onParse(function ($info) {
    print_r($info); // info for timing, query, params and finalized query
}));
```

Other options:
```php
// set maximum number of connection attempts.
\rain1\PDOPowered\Config\PDOPowered::$MAX_TRY_CONNECTION = 5;

// set pdo attribute after connection. 
$db->setPDOAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
```
## Param hints

PDOPowered automatically considers value param strings as \PDO::PARAM_STR, and value integer as \PDO::PARAM_INT.
For any evenience, you can do it explicitly:

```php
$users = $db->query(
    "SELECT * FROM user WHERE email = :email AND id = :id", 
    [
        "email" => new \rain1\PDOPowered\Param\ParamString("me@me.it"),
        "id"=> new \rain1\PDOPowered\Param\ParamInt("123"),
    ]
)->fetchAll();
```

And generally you can use every PDO Param supported:

```php
[
    "param" => new \rain1\PDOPowered\Param\ParamNative("value", \PDO::PARAM_STR)
]
```

or build yourself a new Param type, implementing `\rain1\PDOPowered\Param\ParamInterface`.

For example as bonus we have:
```php
[
    "param" => new \rain1\PDOPowered\Param\ParamJSON(["key"=>"value"])
]
```

this is equivalent to:
```php
[
    "param" => json_encode(["key"=>"value"])
]
```

## Real use case (try me at home)

You need to create a database "test".

```php

use rain1\PDOPowered\Config\Config;
use rain1\PDOPowered\Debug\DebugParser;
use rain1\PDOPowered\Param\ParamJSON;
use rain1\PDOPowered\Param\ParamString;
use rain1\PDOPowered\PDOPowered;

require "vendor/autoload.php";

function output(...$texts) {
    $endline =  php_sapi_name() === "cli" ? "\n" : "<br/>";
    foreach($texts as $text)
        echo str_replace("\n", $endline, $text) .$endline;
}

$config = new Config(
    "mysql",
    "root",
    "vagrant",
    "localhost",
    3306,
    "test",
    "utf8",
    [
        \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\''
    ]
);


$db = new PDOPowered($config);

output("adding listeners...");

$db->onConnectionFailure(function ($try, \Exception $e) {
    error_log("connection failed: " . $e->getMessage());
    output("connection failed");
});

$db->onConnect(function() {
    output("Connected", "");
});

// only for debug
$debug = 1;
$countQuery = 0;
$debug && $db->onDebug(
    DebugParser::onParse(
        function ($info) use (&$countQuery) {

            $str = "---- DEBUG ROW " . (++$countQuery) .  " ----\n";

            if (isset($info['query']))
                $str .= $info['query']['demo'] . "\nexecution time: " . $info['query']['executionTime'];
            else
                $str .= $info['type'] . " " . json_encode($info['args']);

            output($str, "");
        }
    )
);


output("set attributes");

$db->setPDOAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

output("first query");

$db->query("DROP TABLE IF EXISTS user");

$db->query("create table user
(
	id int auto_increment
		primary key,
	email varchar(32) null,
	settings text null
)
; ");

$db->beginTransaction();
$id = $db->insert("user", ['email' => 'me@me.com']);

$db->update("user", ["email" => "me2@me.com"], ['id' => (int)$id]);

$db->delete("user", ['id' => 1]);

$db->insert("user", [
    'id' => 1,
    'email' => new ParamString("me@me.com")
]);

$db->insertOnDuplicateKeyUpdate("user", [
    'id' => 1,
    'email' => new ParamString("me3@me.com"),
    'settings' => new ParamJSON(['privacy' => 1, 'newsletter' => 'no'])
]);

$rows = $db->query("SELECT * FROM user WHERE id = :id", ['id' => 1])->fetchAll();
$db->commitTransaction();

$db->query("SELECT SLEEP (1)");

print_r($rows);

```

