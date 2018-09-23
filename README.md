PDOPowered is a wrapper for PDO providing these features:

- **lazy connection** The PDO instance is created only when needed. Configured credentials are deleted after the connection.
- **reconnection on fails** try to reconnect if the connection fails (for `PDOPowered::$MAX_TRY_CONNECTION` tries).
  - with `$db->onConnect($callback)` it's possible to do something after every connection fail (for example a sleep and/or error reporting)
- **fast query params** 
- Wrapper for `PDOStatement` with powered methods:
  - **fetchObjects** call  `fetchObject` for each row.
- **fast queries** available helpers for fast query:
  - **insert** for fast insert `$db->insert("tableName",$arrayValues)` returns the last insert id.
  - **update** for fast update `$db->update("tableName", $valuesToUpdate, $arrayCondition)` returns the count of affected rows.
  - **insertOnDuplicateKeyUpdate** for insert on duplicate key update `$db->insertOnDuplicateKeyUpdate($tableName, $arrayValues)`  returns the last insert id.
  - **delete** for fast delete `$db->delete("tableName", $arrayWhereCondition)` returns the count of affected rows
- **easy debug** with `$db->onDebug($callback)` it's possible to access on debug output and do your stuff (log in file, send to stdoutput and so on)
- **access to native PDO instance** extending `PDOPowered` you have access to `getPDO()` method for any needs.
