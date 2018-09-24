[![Build Status](https://travis-ci.org/LucaRainone/pdo-powered.svg?branch=master)](https://travis-ci.org/LucaRainone/pdo-powered)
[![Coverage Status](https://coveralls.io/repos/github/LucaRainone/pdo-powered/badge.svg?branch=master)](https://coveralls.io/github/LucaRainone/pdo-powered?branch=master)

PDOPowered is a wrapper for PDO providing these features:

- **lazy connection** The PDO instance is created only when needed. Configured credentials are deleted after the connection.
  - with `$db->onConnect($connect)` it's possible to do something after the connection.
- **reconnection on fails** try to reconnect if the connection fails (for `PDOPowered::$MAX_TRY_CONNECTION` tries).
  - with `$db->onConnectionFailure($callback)` it's possible to do something after every connection fail (for example a sleep and/or error reporting)
- **fast query params**  prepare and execute are called in `query` method. `$db->query("SELECT ?,?,?", [1,2,3])`
- Wrapper for `PDOStatement` with powered methods:
  - **fetchObjects** call `fetchObject` for each row and store the result in array
- **fast queries** available helpers for fast query:
  - **insert** for fast insert `$db->insert("tableName",$arrayValues)` returns the last insert id.
  - **update** for fast update `$db->update("tableName", $valuesToUpdate, $arrayCondition)` returns the count of affected rows.
  - **insertOnDuplicateKeyUpdate** for insert on duplicate key update `$db->insertOnDuplicateKeyUpdate($tableName, $arrayValues)`  returns the last insert id.
  - **delete** for fast delete `$db->delete("tableName", $arrayWhereCondition)` returns the count of affected rows
- **easy debug** with `$db->onDebug($callback)` it's possible to access on debug output and do your stuff (log in file, send to stdoutput and so on)
- **access to native PDO instance** extending `PDOPowered` you have access to `getPDO()` method for any needs.

## Basic Usage

An implementation of rain1\PDOPowered\Config\ConfigInterface must be passed to constructor. 
We provide following implementations:

```php
$config = new \rain1\PDOPowered\Config\Config("mysql", "user", "password", "localhost", 3306, "dbname", "utf8", [\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\'']);
```

or equivalent

```php
$config = new \rain1\PDOPowered\Config\DSN("mysql:host=localhost;port=3306;dbname=dbname;charset=utf8", "user", "password");
$config->setOptions([\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\'']);
```

So we can instantiate the object
```php
$db = new PDOPowered($config);
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

In order to have maximum control on internal flows, there are some triggered events.

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

Other options:
```php
// set maximum number of connection attempts.
\rain1\PDOPowered\Config\PDOPowered::$MAX_TRY_CONNECTION = 5;

// set pdo attribute after connection. 
$db->setPDOAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
```
## Param hints

PDOPowered considers automatically php param strings as \PDO::PARAM_STR, and php integer as \PDO::PARAM_INT.
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

or build yourself a new Param type implementing `\rain1\PDOPowered\Param\ParamInterface`.

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
