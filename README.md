[![Build Status](https://travis-ci.org/LucaRainone/pdo-powered.svg?branch=master)](https://travis-ci.org/LucaRainone/pdo-powered)
[![Coverage Status](https://coveralls.io/repos/github/LucaRainone/pdo-powered/badge.svg?branch=master)](https://coveralls.io/github/LucaRainone/pdo-powered?branch=master)

PDOPowered is a wrapper for PDO providing these features:

- **lazy connection** The PDO instance is created only when needed. Configured credentials are deleted after the connection.
  - with `$db->onConnect($connect)` it's possible to do something after connection (first queries, configurations)
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

```php

$config = new Config(
            $dbname",
            $username,
            $password",
            $host,
            $port,
            $charset"
        );
$db = new PDOPowered($config);

$id = $db->insert("tabletest", ['id'=>1, 'col1'=>'col1_1']);

$db->update("tabletest", ['col1'=>'updated'], ['id'=>$id]);
$db->insertOnDuplicateKeyUpdate("tabletest", ['id'=> $id+1, 'col1'=>'updated']);
$db->delete("tabletest", ['id'=>$id]);

$row = $db->query("SELECT * FROM tabletest WHERE id = ?", [$id])->fetch();

// or equivalent

$row = $db->query("SELECT * FROM tabletest WHERE id = :id", ['id'=>$id])->fetch();

var_dump($row);

```

## Advanced usage:

```php

PDOPowered::$MAX_TRY_CONNECTION = 5;
$config = new Config(
    "dbname",
    "root",
    "root",
    "localhost",
    3306,
    "utf8"
);
$db = new PDOPowered($config);
$db->setPDOAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

$db->onConnectionFailure(function($connectionTry, \Exception $exception) {
    sleep($connectionTry);
    error_log("Unable to connect on mysql: " . $exception->getMessage());
});
$db->onConnect(function() {
    echo "Db connected\n";
});
$db->onDebug(function($debugType, \PDOStatement $PDOStatement = null, ...$args) {
    echo "$debugType\n";
    if($PDOStatement)
        $PDOStatement->debugDumpParams();
    echo "\n".json_encode($args);
});

// do your stuff like basic usage

```