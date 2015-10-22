<?php

class EntityConnection
{
    public $host = 'localhost';
    public $user = '';
    public $password = '';
    public $database = 'mysql';
    public $port = 3306;
    public $sql = null;
    
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
    
    function __destruct()
    {
        $this->disconnect();
    }
    
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
    
    public function disconnect()
    {
        if (!empty($this->sql))
        {
            $this->sql->close();
            $this->sql = null;
        }
    }
    
    public function query($statement, array $schema = null)
    {
        if (!empty($this->sql) && !empty($statement) && is_string($statement))
        {
            $result = $this->sql->query($statement);
            $this->sql->next_result();
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
        $this->query = $query;
    }
    
    function __toString()
    {
        return $this->compile();
    }
    
    function select($statement = null)
    {
        $obj = new EntityObject($this->ctx, $this->connection, $this->query);
        if (!empty($statement) && is_string($statement))
        {
            $obj->query['select'] = [];
            $statement = explode(',', $statement);
            $schema = $this->ctx->get_table($this->query['from']);
            foreach ($statement as $column)
            {
                $column = trim($column);
                if (!array_key_exists($column, $schema))
                {
                    throw new Exception('Column name is invalid: "' . $column . '"');
                }
                
                $obj->query['select'][] = $column;
            }
        }
        
        return $obj->execute();
    }
    
    function single($statement = null)
    {
        $obj = $this->limit(1);
        $obj->query['single'] = true;
        return $obj->select($statement);
    }
    
    function where($statement)
    {
        $obj = new EntityObject($this->ctx, $this->connection, $this->query);
        if (!empty($statement) && is_string($statement))
        {
            $obj->query['where'][] = $statement;
        }
        
        return $obj;
    }
    
    function orderby($statement, $direction = null)
    {
        $obj = new EntityObject($this->ctx, $this->connection, $this->query);
        if (!empty($statement) && is_string($statement))
        {
            $statement = explode(',', $statement);
            $schema = $this->ctx->get_table($this->query['from']);
            foreach ($statement as $column)
            {
                $column = trim($column);
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
    
    function limit($num)
    {
        $obj = new EntityObject($this->ctx, $this->connection, $this->query);
        if (is_int($num) || is_string($num))
        {
            $obj->query['limit'] = intval($num);
        }
        
        return $obj;
    }
    
    function inject($statement)
    {
        $obj = new EntityObject($this->ctx, $this->connection, $this->query);
        $current = &$obj->query['include'];
        if (!empty($statement))
        {
            if (is_string($statement))
            {
                $path = explode('.', $statement);
                foreach ($path as $navigation)
                {
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
    
    protected function execute()
    {
        try
        {
            $query = $this->compile();
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
            
            return $this->query['single'] ? $result[0] : $result;
        }
        catch (Exception $e)
        {
            throw new Exception('Query is invalid', 0, $e);
        }
    }
}

class EntityContext
{
    public $filename = null;
    public $connection = null;
    public $settings = array(
        'alwaysRefreshSchema' => false,
        'pluralizeNavigation' => true
        );
    public $schema = null;
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
    
    function __get($property)
    {
        $table = $this->get_table($property);
        if ($table !== false)
        {
            return new EntityObject($this, $this->connection, array('from' => $property));
        }
        
        throw new Exception('Property is invalid or schema could not be found.');
    }
    
    function __call($method, $args)
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
    
    public function get_table_relationships($tableName)
    {
        $relationships = [];
        if (!empty($this->schema))
        {
            foreach ($this->schema['relationships'] as $key => $relationship)
            {
                if ($relationship['from']['table'] === $tableName || $relationship['to']['table'] === $tableName)
                {
                    $relationships[] = $relationship;
                }
            }
        }
        
        return $relationships;
    }
    
    public function get_table_navigations($tableName)
    {
        $navigations = [];
        if (!empty($this->schema))
        {
            foreach ($this->schema['relationships'] as $relationship)
            {
                if ($relationship['from']['table'] === $tableName)
                {
                    $navigations[] = $relationship['from']['property'];
                }
                else if ($relationship['to']['table'] === $tableName)
                {
                    $navigations[] = $relationship['to']['property'];
                }
            }
        }
        
        return $navigations;
    }
    
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
    
    public function call_procedure($method, $args)
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
    
    public function call_function($method, $args)
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
    
    protected function validate_parameters($arguments, $parameters)
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

?>