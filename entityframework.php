<?php
/**
 * @author Max Alzner
 * @license http://opensource.org/licenses/GPL-3.0
 */

/**
 * A wrapper class for connecting to and querying the MySQL database.
 * 
 * @property string $host The URL of the MySQL database.
 * @property string $user The user's name.
 * @property string $password The user's password.
 * @property string $database The name of the database.
 * @property int $port The port of the database.
 * @property mysqli $sql An instance of the mysqli class.
 * 
 * @method void reconnect()
 * @method void disconnect()
 * @method mixed query(string $statement, array|null $schema)
 * @method string encode_str(string $str)
 */
class EntityConnection
{
    public $host = 'localhost';
    public $user = '';
    public $password = '';
    public $database = 'mysql';
    public $port = 3306;
    public $sql = null;
    
    /**
     * Sets up and connects to the database.
     * 
     * @param array|null $connection Array containing connection information.
     */
    function __construct(array $connection = null)
    {
        if (!empty($connection))
        {
            $this->host = !empty($connection['host']) ? strval($connection['host']) : $this->host;
            $this->user = !empty($connection['user']) ? strval($connection['user']) : $this->user;
            $this->password = !empty($connection['password']) ? strval($connection['password']) : $this->password;
            $this->database = !empty($connection['database']) ? strval($connection['database']) : $this->database;
            $this->port = !empty($connection['port']) ? intval($connection['port']) : $this->port;
        }
        
        $this->reconnect();
    }
    
    /**
     * Calls the disconnect function.
     */
    function __destruct()
    {
        $this->disconnect();
    }
    
    /**
     * Refreshs the connection to the database.
     */
    public function reconnect()
    {
        $this->disconnect();
        if (!empty($this->host) && !empty($this->user))
        {
            $this->sql = new mysqli(
                $this->host,
                $this->user,
                $this->password,
                $this->database,
                $this->port);
        }
    }
    
    /**
     * Disconnects from the database.
     */
    public function disconnect()
    {
        if (!empty($this->sql))
        {
            $this->sql->close();
            $this->sql = null;
        }
    }
    
    /**
     * Sends a query to the database.
     * 
     * @param string $statement A MySQL query.
     * @param array|null $schema Schema information to validate the result against.
     * 
     * @return array|bool The result of the query, or false if the query failed.
     */
    public function query($statement, array $schema = null)
    {
        if (!empty($this->sql) && !$this->sql->connect_errno && !empty($statement) && is_string($statement))
        {
            $result = $this->sql->query($statement);
            if ($this->sql->more_results())
            {
                $this->sql->next_result();
            }
            
            if ($result instanceof mysqli_result)
            {
                $result = $result->fetch_all(MYSQLI_ASSOC);
                if (!empty($schema))
                {
                    foreach ($result as &$row)
                    {
                        foreach ($row as $columnName => &$column)
                        {
                            $columnSchema = $schema[$columnName];
                            $column = self::map_value($column, $columnSchema['type'], $columnSchema['length']);
                            unset($column);
                        }
                        
                        unset($row);
                    }
                }
            }
            
            return $result;
        }
        
        return false;
    }
    
    /**
     * Encodes a string to be database safe.
     * 
     * @param string $value
     * 
     * @return string
     */
    public function encode_str($value)
    {
        if (!empty($this->sql))
        {
            return $this->sql->real_escape_string(strval($value));
        }
        
        return false;
    }
    
    /**
     * Transforms a value from what was returned by the MySQL query into the correct datatype.
     * 
     * @param mixed $value Value to be mapped.
     * @param string $type Datatype to be mapped.
     * @param int $length The length of the value.
     * 
     * @return mixed The mapped value.
     */
    protected static function map_value($value, $type, $length)
    {
        if ($value !== null)
        {
            switch ($type)
            {
                case 'int':
                case 'tinyint':
                case 'smallint':
                case 'mediumint':
                case 'bigint':
                    return intval($value);
                    break;
                case 'float':
                case 'double':
                case 'decimal':
                    return doubleval($value);
                    break;
                case 'bit':
                    return $value === '1' ? true : false;
                    break;
                case 'char':
                case 'varchar':
                case 'blob':
                case 'text':
                case 'tinyblob':
                case 'tinytext':
                case 'mediumblob':
                case 'mediumtext':
                case 'longblob':
                case 'longtext':
                    return substr(strval($value), 0, $length);
                default:
                    return $value;
                    break;
            }
        }
        
        return null;
    }
}

/**
 * Contains methods and properties for interfacing with a MySQL table.
 * 
 * @property EntityContext $ctx An instance of the EntityContext class.
 * @property EntityConnection $connection An instance of the EntityConnection class.
 * @property array $query Array containing query information.
 * 
 * @method array|null select(string|null $statement)
 * @method array|null single(string|null $statement)
 * @method EntityObject where(string $statement)
 * @method EntityObject orderby(string $statement, string|null $direction)
 * @method EntityObject limit(string|int $num)
 * @method EntityObject inject(string $statement)
 * @method void attach(array $obj)
 * @method void detach(array $obj)
 */
class EntityObject
{
    protected $ctx = null;
    protected $connection = null;
    protected $query = array(
        'select' => [],
        'from' => null,
        'where' => [],
        'orderby' => [],
        'orderdir' => null,
        'limit' => null,
        'single' => false,
        'include' => []
        );
    
    /**
     * @param EntityContext $ctx An instance of the EntityContext class.
     * @param EntityConnection $connection An instance of the EntityConnection class.
     * @param array $query Array containing query information.
     */
    function __construct($ctx, $connection, array $query)
    {
        if (!($ctx instanceof EntityContext))
        {
            throw new Exception('EntityContext is invalid: ' . $ctx);
        }
        
        if (!($connection instanceof EntityConnection))
        {
            throw new Exception('EntityConnection is invalid: ' . $connection);
        }
        
        if (empty($query['from']))
        {
            throw new Exception('Table name must be defined: ' . $query);
        }
        
        $this->ctx = $ctx;
        $this->connection = $connection;
        $this->query = array_merge($this->query, $query);
    }
    
    /**
     * @return string Compiled MySQL query.
     */
    function __toString()
    {
        return $this->compile();
    }
    
    /**
     * Executes the query by selecting all possible resulting rows, and optionally limits the columns by the given $statement.
     * 
     * @param string|null $statement A string of columns to be selected, or null.
     * 
     * @return array|bool Array of rows from the resulting query, or false.
     */
    function select($statement = null)
    {
        $obj = new self($this->ctx, $this->connection, $this->query);
        if (!empty($statement) && is_string($statement))
        {
            $obj->query['select'] = [];
            $statement = explode(',', $statement);
            $schema = $this->ctx->get_table($this->query['from']);
            foreach ($statement as $column)
            {
                $column = $obj->connection->encode_str(trim($column));
                if (!array_key_exists($column, $schema))
                {
                    throw new Exception('Column name is invalid: "' . $column . '"');
                }
                
                $obj->query['select'][] = $column;
            }
        }
        
        return $obj->execute();
    }
    
    /**
     * Executes the query by selecting a single row, and optionally limits the columns by the given $statement.
     * 
     * @param string|null $statement A string of columns to be selected, or null.
     * 
     * @return array|bool A single row from the resulting query, or false.
     */
    function single($statement = null)
    {
        $obj = $this->limit(1);
        $obj->query['single'] = true;
        return $obj->select($statement);
    }
    
    /**
     * Adds a condition to the query.
     * 
     * @param string $statement A boolean condition.
     * 
     * @return EntityObject A new instance with the added condition.
     */
    function where($statement)
    {
        $obj = new self($this->ctx, $this->connection, $this->query);
        if (!empty($statement) && is_string($statement))
        {
            // TODO: where validation
            $obj->query['where'][] = $statement;// $obj->connection->encode_str($statement);
        }
        
        return $obj;
    }
    
    /**
     * Adds a order to the query. 
     * Will default to ascending if $direction is null.
     * 
     * @param string $statement A string of columns to order the query by.
     * @param string|null $direction The direction to order the query by, or null.
     * 
     * @return EntityObject A new instance with the added order.
     */
    function orderby($statement, $direction = null)
    {
        $obj = new self($this->ctx, $this->connection, $this->query);
        if (!empty($statement) && is_string($statement))
        {
            $statement = explode(',', $statement);
            $schema = $this->ctx->get_table($this->query['from']);
            foreach ($statement as $column)
            {
                $column = $obj->connection->encode_str(trim($column));
                if (!array_key_exists($column, $schema))
                {
                    throw new Exception('Column name is invalid: "' . $column . '"');
                }
                
                $obj->query['orderby'][] = $column;
            }
        }
        
        $obj->query['orderdir'] = empty($direction) || ($direction != 'desc' && $direction != 'asc') ? 'asc' : $direction;
        return $obj;
    }
    
    /**
     * Adds a limit to the query.
     * 
     * @param string|int $num An amount to limit the query by.
     * 
     * @return EntityObject A new instance with the added limit.
     */
    function limit($num)
    {
        $obj = new self($this->ctx, $this->connection, $this->query);
        if (is_int($num) || is_string($num))
        {
            $obj->query['limit'] = intval($num);
        }
        
        return $obj;
    }
    
    /**
     * Adds an injection path to the query. 
     * When executing any associating tables along the injection path will be returned as well.
     * 
     * @param string $statement Names of navigation properties seperated by a period.
     * 
     * @return EntityObject A new instance with the added injection path.
     */
    function inject($statement)
    {
        $obj = new self($this->ctx, $this->connection, $this->query);
        $current = &$obj->query['include'];
        if (!empty($statement))
        {
            if (is_string($statement))
            {
                $path = explode('.', $statement);
                foreach ($path as $navigation)
                {
                    $navigation = $obj->connection->encode_str($navigation);
                    if (empty($current[$navigation]))
                    {
                        $current[$navigation] = [];
                    }
                    
                    $current = &$current[$navigation];
                }
            }
            else if (is_array($statement))
            {
                $current = empty($current) ? $statement : array_merge_recursive($current, $statement);
            }
            else
            {
                throw new Exception('Injection path is invalid: ' . $statement);
            }
        }
        
        unset($current);
        return $obj;
    }
    
    /**
     * Attaches an object to the database table. 
     * Will insert a new row if the entry does not exist, otherwise will update the existing row.
     * 
     * @param array $obj The field to attach to the database table.
     * 
     * @return bool A value indicating whether or not the action succeeded.
     */
    function attach(array $obj)
    {
        if (!empty(obj))
        {
            $table = $this->query['from'];
            $schema = $this->ctx->get_table($table);
            if (!empty($schema))
            {
                $columns = $this->ctx->get_table_columns($table);
                $primary = $this->ctx->get_table_primary_column($table);
                foreach ($columns as $column)
                {
                    $nullable = isset($schema[$column]['nullable']) ? $schema[$column]['nullable'] : false;
                    if ($column != $primary && !$nullable && !isset($obj[$column]))
                    {
                        throw new Exception('Column is not defined: ' . $column);
                    }
                }
                
                $values = [];
                foreach ($columns as $column)
                {
                    if (isset($obj[$column]))
                    {
                        $values[] = $obj[$column] == null ? 'null' : (is_string($obj[$column]) ? ('"' . $obj[$column] . '"') : $obj[$column]);
                    }
                    else
                    {
                        $columns = array_diff($columns, [$column]);
                    }
                }
                
                $columns = array_values($columns);
                
                $sql .= 'insert into ' . $table . ' ';
                $sql .= '(' . implode(', ', $columns) . ') ';
                $sql .= 'values (' . implode(', ', $values) . ') ';
                if ($primary != false)
                {
                    $sql .= 'on duplicate key update ';
                    $set = [];
                    for ($i = 0; $i < count($columns); $i++)
                    {
                        $set[] = $columns[$i] . ' = ' . $values[$i];
                    }
                    
                    $sql .= implode(', ', $set);
                }
                
                try
                {
                    return $this->connection->query(trim($sql));
                }
                catch (Exception $e)
                {
                    throw new Exception('Attach on ' . $table . ' has failed for object: ' . $obj, 0, $e);
                }
            }
        }
        
        return false;
    }
    
    /**
     * Detaches an object from the database table.
     * Will delete a row from the the based on the primary key, or any supplied columns.
     * 
     * @param array $obj The field to detach from the database table.
     * 
     * @return bool A value indicating whether or not the action succeeded.
     */
    function detach(array $obj)
    {
        if (!empty(obj))
        {
            $table = $this->query['from'];
            $schema = $this->ctx->get_table($table);
            if (!empty($schema))
            {
                $columns = $this->ctx->get_table_columns($table);
                $primary = $this->ctx->get_table_primary_column($table);
                
                $values = [];
                if ($primary != false && isset($obj[$primary]))
                {
                    $columns = [$primary];
                }
                
                foreach ($columns as $column)
                {
                    if (isset($obj[$column]) && !empty($obj[$column]))
                    {
                        $values[] = $column . ' = ' . (is_string($obj[$column]) ? ('"' . $obj[$column] . '"') : $obj[$column]);
                    }
                }
                
                $sql = 'delete from ' . $table . ' where ' . implode(', ', $values);
                try
                {
                    return $this->connection->query($sql);
                }
                catch (Exception $e)
                {
                    throw new Exception('Detach on ' . $table . ' has failed for object: ' . $obj, 0, $e);
                }
            }
        }
        
        return false;
    }
    
    /**
     * @return string Compiled MySQL query.
     */
    protected function compile()
    {
        $columns = empty($this->query['select']) ? $this->ctx->get_table_columns($this->query['from']) : $this->query['select'];
        $sql = 'select ';
        $sql .= implode(', ', $columns) . ' ';
        $sql .= 'from ' . $this->query['from'] . ' ';
        if (!empty($this->query['where']))
        {
            $sql .= 'where (' . implode(') and (', $this->query['where']) . ') ';
        }
        
        if (!empty($this->query['orderby']) && !empty($this->query['orderdir']))
        {
            $sql .= 'order by ' . implode(', ', $this->query['orderby']) . ' ' . $this->query['orderdir'] . ' ';
        }
        
        if (!empty($this->query['limit']))
        {
            $sql .= 'limit ' . $this->query['limit'] . ' ';
        }
        
        return trim($sql);
    }
    
    /**
     * Executes the query.
     * 
     * @return array|bool The result of the query, or false.
     */
    protected function execute()
    {
        try
        {
            $query = $this->compile();
            // echo $query . PHP_EOL;
            $result = $this->connection->query($query, $this->ctx->get_table($this->query['from']));
            $relationships = $this->ctx->get_table_relationships($this->query['from']);
            if (!empty($relationships) && !empty($this->query['include']))
            {
                foreach ($result as &$row)
                {
                    foreach ($relationships as $relationship)
                    {
                        foreach ($this->query['include'] as $navigation => $chain)
                        {
                            if ($relationship['to']['property'] === $navigation)
                            {
                                $key = $relationship['to']['key'];
                                $property = $relationship['to']['property'];
                                $table = $relationship['from']['table'];
                                $reference = $relationship['from']['key'];
                                $multiplicity = $relationship['to']['multiplicity'];
                            }
                            else if ($relationship['from']['property'] === $navigation)
                            {
                                $key = $relationship['from']['key'];
                                $property = $relationship['from']['property'];
                                $table = $relationship['to']['table'];
                                $reference = $relationship['to']['key'];
                                $multiplicity = $relationship['from']['multiplicity'];
                            }
                            else
                            {
                                continue;
                            }
                            
                            $key = $row[$key];
                            $key = is_string($key) ? ('"' . $key . '"') : $key;
                            $obj = $this->ctx
                                ->$table
                                ->inject($chain)
                                ->where($reference . ' = ' . $key);
                            $row[$property] = $multiplicity === '*' ? $obj->select() : $obj->single();
                        }
                    }
                    
                    unset($row);
                }
            }
            
            return $result !== false && !empty($result) ? ($this->query['single'] ? $result[0] : $result) : false;
        }
        catch (Exception $e)
        {
            throw new Exception('Query on ' . $this->query['from'] . ' has failed', 0, $e);
        }
    }
}

/**
 * Contains methods and properties for interfacing with a MySQL database.
 * 
 * @property string $filename Filename to either read or write the datebase context info.
 * @property EntityConnection $connection An instance of the EntityConnection class.
 * @property array $settings JSON array containing different settings for the context.
 * @property array $schema JSON array representing the database schema.
 * 
 * @method array|bool get_table(string $name)
 * @method string[] get_table_columns(string $name)
 * @method string|bool get_table_primary_column(string $name)
 * @method array get_table_relationships(string $name)
 * @method string[] get_table_navigations(string $name)
 * @method array get_procedure(string $name)
 * @method array get_function(string $name)
 * @method mixed call_procedure(string $method, array $args)
 * @method mixed call_function(string $method, array $args)
 */
class EntityContext
{
    public $filename = null;
    public $connection = null;
    public $settings = array(
        'alwaysRefreshSchema' => false,
        'pluralizeNavigation' => true
        );
    public $schema = null;
    
    /**
     * Constructs a EntityContext object based on the given JSON file.
     * 
     * @param string|array $file A filename pointing to a JSON file, or an array, containing the database context.
     */
    function __construct($file = null)
    {
        if ($file)
        {
            if (is_string($file))
            {
                $this->filename = $file;
                $file = json_decode(file_get_contents($file), true);
            }
            else if (is_object($file))
            {
                $file = get_object_vars($file);
            }
            
            if (!empty($file['settings']))
            {
                foreach ($file['settings'] as $setting => $value)
                {
                    $this->settings[$setting] = is_bool($value) ? $value : boolval($value);
                }
            }
            
            $this->connection = new EntityConnection(!empty($file['connection']) ? $file['connection'] : null);
            if (empty($file['schema']) || $this->settings['alwaysRefreshSchema'])
            {
                $this->refresh_schema();
            }
            else
            {
                $this->schema = $file['schema'];
            }
        }
    }
    
    /**
     * @return string JSON encoded string representing the database context.
     */
    function __toString()
    {
        return json_encode(array(
            'connection' => array(
                'host' => $this->connection->host,
                'user' => $this->connection->user,
                'password' => $this->connection->password,
                'database' => $this->connection->database,
                'port' => $this->connection->port),
            'settings' => $this->settings,
            'schema' => $this->schema
            ), JSON_PRETTY_PRINT);
    }
    
    /**
     * @param string $property The name of a table or view within the database.
     * 
     * @return EntityObject
     */
    function __get($property)
    {
        $table = $this->get_table($property);
        if ($table !== false)
        {
            return new EntityObject($this, $this->connection, array('from' => $property));
        }
        
        throw new Exception('Property is invalid or schema could not be found.');
    }
    
    /**
     * @param string $method The name of a procedure or function within the database.
     * @param mixed[] $args Array of the database method's parameters.
     * 
     * @return mixed The result of the requested database procedure or function.
     */
    function __call($method, array $args)
    {
        $routine = $this->get_procedure($method);
        $isprocedure = $routine !== false;
        $routine = $isprocedure ? $routine : $this->get_function($method);
        if ($routine !== false)
        {
            $ctx = $this;
            return call_user_func_array(
                array(&$this, $isprocedure ? 'call_procedure' : 'call_function'),
                array('method' => $method, 'args' => $args));
        }
        
        throw new Exception('Method is invalid or schema could not be found: ' . $method);
    }
    
    /**
     * @param string $name The name of a table or view within the database.
     * 
     * @return array|bool The schema for the specified table, or false if $name is invalid.
     */
    public function get_table($name)
    {
        if (!empty($this->schema))
        {
            foreach ($this->schema['tables'] as $tableName => $table)
            {
                if ($name === $tableName)
                {
                    return $table;
                }
            }
            
            foreach ($this->schema['views'] as $viewName => $view)
            {
                if ($name === $viewName)
                {
                    return $view;
                }
            }
        }
        
        return false;
    }
    
    /**
     * @param string $name The name of a table or view within the database.
     * 
     * @return string[] Array of columns name for the table or view, or false if $name is invalid.
     */
    public function get_table_columns($name)
    {
        if (!empty($this->schema))
        {
            foreach ($this->schema['tables'] as $tableName => $table)
            {
                if ($name === $tableName)
                {
                    return array_keys($table);
                }
            }
            
            foreach ($this->schema['views'] as $viewName => $view)
            {
                if ($name === $viewName)
                {
                    return array_keys($view);
                }
            }
        }
        
        return [];
    }
    
    /**
     * @param string $name The name of a table or view within the database.
     * 
     * @return string|bool The name of the table's primary key, or false if $name is invalid or the table has not primary key.
     */
    public function get_table_primary_column($name)
    {
        if (!empty($this->schema))
        {
            foreach ($this->schema['tables'] as $tableName => $table)
            {
                if ($name === $tableName)
                {
                    foreach ($table as $columnName => $column)
                    {
                        if (array_key_exists('primary', $column) && $column['primary'] == true)
                        {
                            return $columnName;
                        }
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * @param string $name The name of a table or view within the database.
     * 
     * @return array Array of table foreign key relationships.
     */
    public function get_table_relationships($name)
    {
        $relationships = [];
        if (!empty($this->schema))
        {
            foreach ($this->schema['relationships'] as $key => $relationship)
            {
                if ($relationship['from']['table'] === $name || $relationship['to']['table'] === $name)
                {
                    $relationships[] = $relationship;
                }
            }
        }
        
        return $relationships;
    }
    
    /**
     * @param string $name The name of a table or view within the database.
     * 
     * @return string[] Array of foreign key navigation properties.
     */
    public function get_table_navigations($name)
    {
        $navigations = [];
        if (!empty($this->schema))
        {
            foreach ($this->schema['relationships'] as $relationship)
            {
                if ($relationship['from']['table'] === $name)
                {
                    $navigations[] = $relationship['from']['property'];
                }
                else if ($relationship['to']['table'] === $name)
                {
                    $navigations[] = $relationship['to']['property'];
                }
            }
        }
        
        return $navigations;
    }
    
    /**
     * @param string $name The name of a procedure within the database.
     * 
     * @return array The schema for the specified procedure, or false if $name if invalid.
     */
    public function get_procedure($name)
    {
        if (!empty($this->schema))
        {
            foreach ($this->schema['procedures'] as $procedureName => $procedure)
            {
                if ($name === $procedureName)
                {
                    return $procedure;
                }
            }
        }
        
        return false;
    }
    
    /**
     * @param string $name The name of a function within the database.
     * 
     * @return array The schema for the specified function, or false if $name if invalid.
     */
    public function get_function($name)
    {
        if (!empty($this->schema))
        {
            foreach ($this->schema['functions'] as $functionName => $function)
            {
                if ($name === $functionName)
                {
                    return $function;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Calls a procedure within the database.
     * 
     * @param string $method The name of the procedure within the database.
     * @param array $args Array of the arguments for the procedure.
     * 
     * @return mixed The result of the procedure.
     */
    public function call_procedure($method, array $args)
    {
        $schema = $this->schema['procedures'][$method];
        if (count($schema['parameters']) !== count($args))
        {
            throw new Exception('Invalid number of arguments for method: ' . $method);
        }
        
        $args = $this->validate_parameters($args, $schema['parameters']);
        $sql = 'call ' . $method . ' (' . implode(', ', $args) . ')';
        return $this->connection->query($sql);
    }
    
    /**
     * Calls a function within the database.
     * 
     * @param string $method The name of the function within the database.
     * @param array $args Array of the arguments for the function.
     * 
     * @return mixed The result of the function.
     */
    public function call_function($method, array $args)
    {
        $schema = $this->schema['functions'][$method];
        if (count($schema['parameters']) !== count($args))
        {
            throw new Exception('Invalid number of arguments for method: ' . $method);
        }
        
        $args = $this->validate_parameters($args, $schema['parameters']);
        $sql = $method . ' (' . implode(', ', $args) . ')';
        $result = $this->connection->query('select ' . $sql, array($sql => $schema));
        return $result[0][$sql];
    }
    
    /**
     * Updates the databases's schema information.
     */
    protected function refresh_schema()
    {
        $this->schema = array(
            'tables' => [],
            'views' => [],
            'procedures' => [],
            'functions' => [],
            'relationships' => []
            );
        $views = $this->connection
            ->query('select table_name
            from information_schema.views
            where table_schema = "' . $this->connection->database . '"');
        foreach ($views as &$name)
        {
            $name = $name['table_name'];
            unset($name);
        }
        
        $tables = $this->connection
            ->query('select table_name
            from information_schema.tables
            where table_schema = "' . $this->connection->database . '"');
        foreach ($tables as $table)
        {
            $tableName = $table['table_name'];
            $table = array();
            $columns = $this->connection
                ->query('select *
                from information_schema.columns
                where table_name = "' . $tableName . '"');
            foreach ($columns as $column)
            {
                $schema = array('type' => $column['DATA_TYPE']);
                if (!empty($column['NUMERIC_PRECISION']))
                {
                    $schema['length'] = intval($column['NUMERIC_PRECISION']);
                }
                else if (!empty($column['CHARACTER_MAXIMUM_LENGTH']))
                {
                    $schema['length'] = intval($column['CHARACTER_MAXIMUM_LENGTH']);
                }
                
                if ($column['IS_NULLABLE'] === 'YES')
                {
                    $schema['nullable'] = true;
                }
                
                $table[$column['COLUMN_NAME']] = $schema;
                // $table[$column['COLUMN_NAME']] = array(
                //     'type' => $column['DATA_TYPE'],
                //     'length' => !empty($column['NUMERIC_PRECISION']) ? intval($column['NUMERIC_PRECISION']) :
                //         (!empty($column['CHARACTER_MAXIMUM_LENGTH']) ? intval($column['CHARACTER_MAXIMUM_LENGTH']) : null),
                //     'nullable' => $column['IS_NULLABLE'] === 'YES' ? true : false
                //     );
            }
            
            $this->schema[array_search($tableName, $views) === false ? 'tables' : 'views'][$tableName] = $table;
        }
        
        $routines = $this->connection
            ->query('select *
            from information_schema.routines
            where routine_schema = "' . $this->connection->database . '"');
        foreach ($routines as $routine)
        {
            $routineName = $routine['ROUTINE_NAME'];
            if ($routine['ROUTINE_TYPE'] === 'PROCEDURE')
            {
                $this->schema['procedures'][$routineName] = array(
                    'parameters' => []
                    );
                $routine = &$this->schema['procedures'][$routineName];
            }
            else if ($routine['ROUTINE_TYPE'] === 'FUNCTION')
            {
                $this->schema['functions'][$routineName] = array(
                    'type' => $routine['DATA_TYPE'],
                    'length' => empty($routine['NUMERIC_PRECISION']) ?
                        intval($routine['CHARACTER_MAXIMUM_LENGTH']) :
                        intval($routine['NUMERIC_PRECISION']),
                    'parameters' => []
                    );
                $routine = &$this->schema['functions'][$routineName];
            }
            
            $parameters = $this->connection
                ->query('select *
                from information_schema.parameters
                where specific_name = "' . $routineName . '"');
            foreach ($parameters as $parameter)
            {
                if (!empty($parameter['PARAMETER_MODE']) && !empty($parameter['PARAMETER_NAME']))
                {
                    $routine['parameters'][intval($parameter['ORDINAL_POSITION']) - 1] = array(
                        'mode' => strtolower($parameter['PARAMETER_MODE']),
                        'name' => $parameter['PARAMETER_NAME'],
                        'type' => $parameter['DATA_TYPE'],
                        'length' => empty($parameter['NUMERIC_PRECISION']) ?
                            intval($parameter['CHARACTER_MAXIMUM_LENGTH']) :
                            intval($parameter['NUMERIC_PRECISION'])
                        );
                }
            }
            
            unset($routine);
        }
        
        $keys = $this->connection
            ->query('select
            c.constraint_catalog,
            c.constraint_schema,
            c.constraint_name,
            k.table_catalog,
            c.table_schema,
            c.table_name,
            k.column_name,
            c.constraint_type,
            k.ordinal_position,
            k.position_in_unique_constraint,
            k.referenced_table_schema,
            k.referenced_table_name,
            k.referenced_column_name
            from information_schema.table_constraints as c
            inner join information_schema.key_column_usage as k
                on k.constraint_name = c.constraint_name and k.table_name = c.table_name
            where c.table_schema = "' . $this->connection->database . '"');
        foreach ($keys as $key)
        {
            if ($key['constraint_type'] === 'PRIMARY KEY')
            {
                $this->schema['tables'][$key['table_name']][$key['column_name']]['primary'] = true;
            }
            else if ($key['constraint_type'] === 'UNIQUE')
            {
                $this->schema['tables'][$key['table_name']][$key['column_name']]['unique'] = true;
            }
        }
        
        foreach ($keys as $key)
        {
            if ($key['constraint_type'] === 'FOREIGN KEY')
            {
                $principalName = $key['table_name'];
                $dependentName = $key['referenced_table_name'];
                $principal = $this->schema['tables'][$principalName];
                $dependent = $this->schema['tables'][$dependentName];
                $coupled = $principal[$key['column_name']]['primary'] && $dependent[$key['referenced_column_name']]['primary'];
                $property = $coupled || !$this->settings['pluralizeNavigation'] ? $principalName : self::strpluralize($principalName);
                $this->schema['relationships'][$key['constraint_name']] = array(
                    'from' => array(
                        'table' => $principalName,
                        'key' => $key['column_name'],
                        'property' => $dependentName,
                        'multiplicity' => $coupled ? '1' : ($principal[$key['column_name']]['nullable'] ? '0..1' : '1')
                        ),
                    'to' => array(
                        'table' => $dependentName,
                        'key' => $key['referenced_column_name'],
                        'property' => $property,
                        'multiplicity' => $coupled ? '1' : '*'
                        )
                    );
            }
        }
        
        if (!empty($this->filename))
        {
            $file = json_decode(file_get_contents($this->filename), true);
            $file['schema'] = $this->schema;
            file_put_contents($this->filename, json_encode($file, JSON_PRETTY_PRINT));
        }
    }
    
    /**
     * @param mixed[] $arguments Array of arguments to be validated.
     * @param array $parameters Array of schema information to validate the arguments.
     * 
     * @return mixed[] Array of tranformed arguments that have been validated.
     */
    protected function validate_parameters(array $arguments, array $parameters)
    {
        for ($i = 0; $i < count($arguments); $i++)
        {
            $arg = &$arguments[$i];
            $parameter = $parameters[$i];
            
            $arg = is_string($arg) ? ('"' . $arg . '"') : $arg;
            $arg = $arg === null ? 'null' : $arg;
        }
        
        return $arguments;
    }
    
    /**
     * @param string @str String to be pluralized.
     * 
     * @return string Transforms the given string into the plural form.
     */
    protected static function strpluralize($str)
    {
        if (in_array(substr($str, -1), ['s', 'x', 'z']) || in_array(substr($str, -2), ['ch', 'sh']))
        {
            return $str . 'es';
        }
        else if (preg_match('/f$/', $str))
        {
            return substr($str, 0, strlen($str) - 1) . 'ves';
        }
        else if (preg_match('/fe$/', $str))
        {
            return substr($str, 0, strlen($str) - 2) . 'ves';
        }
        else if (preg_match('/is$/', $str))
        {
            return substr($str, 0, strlen($str) - 2) . 'es';
        }
        else if (preg_match('/y$/', $str))
        {
            return substr($str, 0, strlen($str) - 1) . 'ies';
        }
        
        return $str . 's';
    }
}

/**
 * Creates an instance of a EntityContext.
 * 
 * @return EntityContext An instance of the EntityContext class.
 */
function entity_framework($file = null)
{
    return new EntityContext($file);
}

?>