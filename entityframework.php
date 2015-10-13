<?php

class EntityObject
{
    protected $ctx = null;
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
    function __construct($ctx, array $query)
    {
        if (!($ctx instanceof EntityContext))
        {
            throw new Exception('EntityContext is invalid: ' . $ctx);
        }
        
        if (empty($query['from']))
        {
            throw new Exception('Table name must be defined: ' . $query);
        }
        
        $this->ctx = $ctx;
        $this->query = $query;
    }
    
    function __toString()
    {
        return $this->compile();
    }
    
    function select($statement = null)
    {
        $obj = new EntityObject($this->ctx, $this->query);
        if (!empty($statement) && is_string($statement))
        {
            $statement = explode(',', $statement);
            foreach ($statement as $column)
            {
                $column = trim($column);
                if (!array_key_exists($column, $obj->ctx->schema['tables'][$obj->query['from']]))
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
        $obj = new EntityObject($this->ctx, $this->query);
        if (!empty($statement) && is_string($statement))
        {
            $obj->query['where'][] = $statement;
        }
        
        return $obj;
    }
    
    function orderby($statement, $direction = null)
    {
        $obj = new EntityObject($this->ctx, $this->query);
        if (!empty($statement) && is_string($statement))
        {
            $statement = explode(',', $statement);
            foreach ($statement as $column)
            {
                $column = trim($column);
                if (!array_key_exists($column, $obj->ctx->schema['tables'][$obj->query['from']]))
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
        $obj = new EntityObject($this->ctx, $this->query);
        if (is_int($num) || is_string($num))
        {
            $obj->query['limit'] = intval($num);
        }
        
        return $obj;
    }
    
    function inject($statement)
    {
        $obj = new EntityObject($this->ctx, $this->query);
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
        
        return $obj;
    }
    
    protected function compile()
    {
        $sql = 'select ';
        $sql .= empty($this->query['select']) ? '* ' : (implode(', ', $this->query['select']) . ' ');
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
            // echo $query . PHP_EOL;
            $result = $this->ctx->sql->query($query)->fetch_all(MYSQLI_ASSOC);
            foreach ($result as &$row)
            {
                foreach ($row as $columnName => &$column)
                {
                    $schema = $this->ctx->schema['tables'][$this->query['from']][$columnName];
                    switch ($schema['type'])
                    {
                        case 'int':
                        case 'tinyint':
                        case 'smallint':
                        case 'mediumint':
                        case 'bigint':
                            $column = $column !== null ? intval($column) : $column;
                            break;
                        case 'float':
                        case 'double':
                        case 'decimal':
                            $column = $column !== null ? doubleval($column) : $column;
                            break;
                        case 'bit':
                            $column = $column !== null ? ($column === '1' ? true : false) : $column;
                            break;
                        default:
                            break;
                    }
                }
            }
            
            $links = $this->ctx->get_links($this->query['from']);
            if (!empty($links) && !empty($this->query['include']))
            {
                foreach ($result as &$row)
                {
                    foreach ($links as $link)
                    {
                        foreach ($this->query['include'] as $navigation => $chain)
                        {
                            if ($link['to']['property'] === $navigation)
                            {
                                $key = $row[$link['to']['key']];
                                $key = is_string($key) ? ('"' . $key . '"') : $key;
                                $row[$link['to']['property']] = $this->ctx
                                    ->__get($link['from']['table'])
                                    ->inject($chain)
                                    ->where($link['from']['key'] . ' = ' . $key)
                                    ->select();
                            }
                            else if ($link['from']['property'] === $navigation)
                            {
                                $key = $row[$link['from']['key']];
                                $key = is_string($key) ? ('"' . $key . '"') : $key;
                                $row[$link['from']['property']] = $this->ctx
                                    ->__get($link['to']['table'])
                                    ->inject($chain)
                                    ->where($link['to']['key'] . ' = ' . $key)
                                    ->single();
                            }
                        }
                    }
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
    public $connection = array(
        'host' => 'localhost',
        'user' => '',
        'password' => '',
        'database' => 'mysql',
        'port' => 3306
        );
    public $settings = array(
        'alwaysRefreshSchema' => false
        );
    public $schema = null;
    public $sql = null;
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
            
            if (!empty($file['connection']))
            {
                $this->connection['host'] = !empty($file['connection']['host']) ? strval($file['connection']['host']) : $this->connection['host'];
                $this->connection['user'] = !empty($file['connection']['user']) ? strval($file['connection']['user']) : $this->connection['user'];
                $this->connection['password'] = !empty($file['connection']['password']) ? strval($file['connection']['password']) : $this->connection['password'];
                $this->connection['database'] = !empty($file['connection']['database']) ? strval($file['connection']['database']) : $this->connection['database'];
                $this->connection['port'] = !empty($file['connection']['port']) ? intval($file['connection']['port']) : $this->connection['port'];
            }
            
            if (!empty($file['settings']))
            {
                foreach ($file['settings'] as $setting => $value)
                {
                    $this->settings[$setting] = is_bool($value) ? $value : boolval($value);
                }
            }
            
            $this->reconnect();
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
    
    function __destruct()
    {
        $this->disconnect();
    }
    
    function __get($property)
    {
        if (!empty($this->schema))
        {
            foreach ($this->schema['tables'] as $tableName => $table)
            {
                if ($property === $tableName)
                {
                    return new EntityObject($this, array('from' => $tableName));
                }
            }
        }
    }
    
    public function get_primary($table)
    {
        foreach ($this->schema['tables'][$table] as $columnName => $column)
        {
            if ($column['primary'])
            {
                return $columnName;
            }
        }
    }
    
    public function get_links($table)
    {
        $links = [];
        foreach ($this->schema['links'] as $key => $link)
        {
            if ($link['from']['table'] === $table || $link['to']['table'] === $table)
            {
                $links[] = $link;
            }
        }
        
        return $links;
    }
    
    protected function reconnect()
    {
        $this->disconnect();
        if ($this->connection['host'] && $this->connection['user'])
        {
            $this->sql = new mysqli(
                $this->connection['host'],
                $this->connection['user'],
                $this->connection['password'],
                $this->connection['database'],
                $this->connection['port']);
        }
    }
    
    protected function disconnect()
    {
        if (!empty($this->sql))
        {
            $this->sql->close();
            $this->sql = null;
        }
    }
    
    protected function refresh_schema()
    {
        if (empty($this->sql))
        {
            return;
        }
        
        $this->schema = array(
            'tables' => [],
            'links' => []
            );
        $tables = $this->sql->query('select table_name from information_schema.tables where table_schema = "' . $this->connection['database'] . '"')->fetch_all(MYSQLI_NUM);
        foreach ($tables as $table)
        {
            $tableName = $table[0];
            $table = array();
            $columns = $this->sql->query('select * from information_schema.columns where table_name = "' . $tableName . '"');
            foreach ($columns as $column)
            {
                $table[$column['COLUMN_NAME']] = array(
                    'type' => $column['DATA_TYPE'],
                    'length' => empty($column['NUMERIC_PRECISION']) ?
                        intval($column['CHARACTER_MAXIMUM_LENGTH']) :
                        intval($column['NUMERIC_PRECISION']),
                    'nullable' => $column['IS_NULLABLE'] === 'YES' ? true : false,
                    // 'primary' => false
                    );
            }
            
            $this->schema['tables'][$tableName] = $table;
        }
        
        foreach ($this->schema['tables'] as $tableName => &$table)
        {
            $keys = $this->sql->query('select * from information_schema.key_column_usage where table_schema = "' . $this->connection['database'] . '"');
            foreach ($keys as $key)
            {
                if ($key['CONSTRAINT_NAME'] === 'PRIMARY')
                {
                    $table[$key['COLUMN_NAME']]['primary'] = true;
                }
                else if (substr($key['CONSTRAINT_NAME'], -7) === '_ibfk_1')
                {
                    $principal = $key['TABLE_NAME'];
                    $dependent = $key['REFERENCED_TABLE_NAME'];
                    $property = substr($principal, -1) === 's' ? ($principal . 'es') :
                        (substr($principal, -1) === 'y' ? (substr($principal, 0, count($principal) - 1) . 'ies') :
                        ($principal . 's'));
                    $this->schema['links'][$key['CONSTRAINT_NAME']] = array(
                        'from' => array(
                            'table' => $principal,
                            'key' => $key['COLUMN_NAME'],
                            'property' => $dependent,
                            'multiplicity' => $this->schema['tables'][$principal][$key['COLUMN_NAME']]['nullable'] ? '0..1' : '1'
                            ),
                        'to' => array(
                            'table' => $dependent,
                            'key' => $key['REFERENCED_COLUMN_NAME'],
                            'property' => $property,
                            'multiplicity' => '*'
                            )
                        );
                }
            }
            
            unset($table);
        }
        
        if (!empty($this->filename))
        {
            $file = json_decode(file_get_contents($this->filename), true);
            $file['schema'] = $this->schema;
            file_put_contents($this->filename, json_encode($file, JSON_PRETTY_PRINT));
        }
    }
}

?>