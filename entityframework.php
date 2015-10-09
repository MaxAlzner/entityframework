<?php

class EntityObject
{
    protected $ctx = null;
    protected $table = null;
    protected $query = array(
        'select' => [],
        'where' => [],
        'orderby' => [],
        'orderdir' => null,
        'limit' => null,
        'single' => false,
        'include' => []
        );
    function __construct($ctx, $table, array $query = null)
    {
        if (!($ctx instanceof EntityContext))
        {
            throw new Exception('EntityContext is invalid: ' . $ctx);
        }
        
        if (empty($table))
        {
            throw new Exception('Table name is invalid: ' . $table);
        }
        
        $this->ctx = $ctx;
        $this->table = $table;
        if (!empty($query))
        {
            $this->query['select'] = $query['select'];
            $this->query['where'] = $query['where'];
            $this->query['orderby'] = $query['orderby'];
            $this->query['orderdir'] = $query['orderdir'];
            $this->query['limit'] = $query['limit'];
            $this->query['single'] = $query['single'];
            $this->query['include'] = $query['include'];
        }
    }
    
    function __toString()
    {
        return $this->compile();
    }
    
    function select($statement = null)
    {
        $ctx = new EntityObject($this->ctx, $this->table, $this->query);
        if (!empty($statement) && is_string($statement))
        {
            $statement = explode(',', $statement);
            foreach ($statement as $column)
            {
                $ctx->query['select'][] = trim($column);
            }
        }
        
        return $ctx->execute();
    }
    
    function single($statement = null)
    {
        $ctx = $this->limit(1);
        $ctx->query['single'] = true;
        return $ctx->select($statement);
    }
    
    function where($statement)
    {
        $ctx = new EntityObject($this->ctx, $this->table, $this->query);
        if (!empty($statement) && is_string($statement))
        {
            $ctx->query['where'][] = $statement;
        }
        
        return $ctx;
    }
    
    function orderby($statement, $direction = null)
    {
        $ctx = new EntityObject($this->ctx, $this->table, $this->query);
        if (!empty($statement) && is_string($statement))
        {
            $statement = explode(',', $statement);
            foreach ($statement as $column)
            {
                $ctx->query['orderby'][] = trim($column);
            }
        }
        
        $ctx->query['orderdir'] = empty($direction) || ($direction != 'desc' && $direction != 'asc') ? 'asc' : $direction;
        return $ctx;
    }
    
    function limit($num)
    {
        $ctx = new EntityObject($this->ctx, $this->table, $this->query);
        if (is_int($num))
        {
            $ctx->query['limit'] = $num;
        }
        
        return $ctx;
    }
    
    function inject($statement)
    {
        $ctx = new EntityObject($this->ctx, $this->table, $this->query);
        if (!empty($statement) && is_string($statement))
        {
            $ctx->query['include'][] = $statement;
        }
        
        return $ctx;
    }
    
    protected function compile()
    {
        $sql = 'select ';
        $statements = $this->query['select'];
        if (empty($statements))
        {
            $sql .= '* ';
        }
        else
        {
            foreach ($statements as $index => $column)
            {
                $sql .= $column . ($index + 1 < count($statements) ? ', ' : ' ');
            }
        }
        
        $sql .= 'from ' . $this->table . ' ';
        $statements = $this->query['where'];
        if (!empty($statements))
        {
            $sql .= 'where ';
            foreach ($statements as $index => $criteria)
            {
                $sql .= '(' . $criteria . ($index + 1 < count($statements) ? ') and ' : ') ');
            }
        }
        
        $statements = $this->query['orderby'];
        if (!empty($statements) && !empty($this->query['orderdir']))
        {
            $sql .= 'order by ';
            foreach ($statements as $index => $column)
            {
                $sql .= $column . ($index + 1 < count($statements) ? ', ' : ' ');
            }
            
            $sql .= $this->query['orderdir'] . ' ';
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
            // echo $this->compile() . PHP_EOL;
            $result = $this->ctx->sql->query($this->compile())->fetch_all(MYSQLI_ASSOC);
            $links = $this->ctx->get_links($this->table);
            if (!empty($links) && !empty($this->query['include']))
            {
                foreach ($result as &$row)
                {
                    foreach ($links as $link)
                    {
                        foreach ($this->query['include'] as $navigation)
                        {
                            // $navigation = explode('.', $navigation);
                            if ($link['to']['property'] === $navigation)
                            {
                                $key = $row[$link['to']['key']];
                                $key = is_string($key) ? ('"' . $key . '"') : $key;
                                $row[$link['to']['property']] = $this->ctx
                                    ->__get($link['from']['table'])
                                    ->where($link['from']['key'] . ' = ' . $key)
                                    ->select();
                            }
                            else if ($link['from']['property'] === $navigation)
                            {
                                $key = $row[$link['from']['key']];
                                $key = is_string($key) ? ('"' . $key . '"') : $key;
                                $row[$link['from']['property']] = $this->ctx
                                    ->__get($link['to']['table'])
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
            throw new Exception('Query is invalid');
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
                    return new EntityObject($this, $tableName);
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