<?php

class EntityObject
{
    protected $table = null;
    protected $schema = null;
    protected $query = array(
        'select' => [],
        'where' => []
        );
    function __construct($table, array $schema, array $query = null)
    {
        if (empty($table))
        {
            throw new Exception('Table name is invalid: ' . $table);
        }
        
        if (empty($schema))
        {
            throw new Exception('Schema is invalid: ' . $schema);
        }
        
        $this->table = $table;
        $this->schema = $schema;
        if (!empty($query))
        {
            $this->query['where'] = $query['where'];
        }
    }
    
    function __toString()
    {
        return $this->compile();
    }
    
    function select(string $statement)
    {
        
    }
    
    function where($statement)
    {
        $ctx = new EntityObject($this->table, $this->schema, $this->query);
        $ctx->query['where'][] = $statement;
        return $ctx;
    }
    
    protected function compile()
    {
        $sql = 'select ';
        if (empty($this->query['select']))
        {
            $sql .= '* ';
        }
        else
        {
            foreach ($this->query['select'] as $column)
            {
                $sql .= $column . ' ';
            }
        }
        
        $sql .= 'from ' . $this->table . ' ';
        if (!empty($this->query['where']))
        {
            $sql .= 'where ';
            foreach ($this->query['where'] as $criteria)
            {
                $sql .= $criteria . ' ';
            }
        }
        
        return $sql;
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
    protected $sql = null;
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
                $this->setAlwaysRefreshSchema($file['settings']['alwaysRefreshSchema']);
            }
            
            $this->reconnect();
            if (empty($file['schema']) || $this->settings['alwaysRefreshSchema'])
            {
                $this->refreshSchema();
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
                    return new EntityObject($tableName, $table);
                }
            }
        }
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
    
    protected function refreshSchema()
    {
        if (empty($this->sql))
        {
            return;
        }
        
        $this->schema = array(
            'tables' => [],
            'links' => []
            );
        $tables = $this->sql->query('show tables')->fetch_all(MYSQLI_NUM);
        foreach ($tables as $table)
        {
            $tableName = $table[0];
            $table = array();
            $columns = $this->sql->query('show columns in ' . $tableName);
            foreach ($columns as $column)
            {
                preg_match_all('!\d+!', $column['Type'], $length);
                $table[$column['Field']] = array(
                    'type' => explode('(', $column['Type'])[0],
                    'length' => intval(implode($length[0])),
                    'nullable' => $column['Null'] === 'YES' ? true : false,
                    // 'primary' => false
                    );
            }
            
            $this->schema['tables'][$tableName] = $table;
        }
        
        foreach ($this->schema['tables'] as $tableName => &$table)
        {
            $keys = $this->sql->query('show keys in ' . $tableName);
            foreach ($keys as $key)
            {
                if ($key['Key_name'] === 'PRIMARY')
                {
                    $table[$key['Column_name']]['primary'] = true;
                }
                if (strtoupper(substr($key['Key_name'], 0, 3)) === 'FK_')
                {
                    $principal = $key['Table'];
                    $dependent = substr($key['Key_name'], 3);
                    $this->schema['links'][$key['Key_name']] = array(
                        'from' => array(
                            'table' => $principal,
                            'key' => $key['Column_name'],
                            'property' => $dependent,
                            'multiplicity' => $key['Null'] === 'YES' ? '0..1' : '1'
                            ),
                        'to' => array(
                            'table' => $dependent,
                            'property' => $principal . 's',
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
    
    function setAlwaysRefreshSchema($value)
    {
        if (!empty($value))
        {
            $this->settings['alwaysRefreshSchema'] = boolval($value);
        }
    }
}

if ($_REQUEST['debug'])
{
    header('Content-Type: text/plain');
    $ctx0 = new EntityContext(array(
        'connection' => array(
            'host' => 'localhost',
            'user' => 'maxalzner',
            'database' => 'c9'
            )));
    $ctx1 = new EntityContext('test/db.json');
    
    // var_dump($ctx0);
    // var_dump($ctx1);
    var_dump($ctx1->connection);
    var_dump($q0 = $ctx1->StateProvince);
    var_dump($q1 = $q0->where('Code = "IL"'));
    
    echo $q1;
}

?>