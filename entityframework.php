<?php

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
            
            $this->hookSchema();
        }
    }
    
    function __destruct()
    {
        $this->disconnect();
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
    
    protected function hookSchema()
    {
        
    }
    
    protected function unhookSchema()
    {
        
    }
    
    function setAlwaysRefreshSchema($value)
    {
        if (!empty($value))
        {
            $this->settings['alwaysRefreshSchema'] = boolval($value);
        }
    }
}

header('Content-Type: text/plain');
$ctx0 = new EntityContext(array('connection' => array()));
$ctx1 = new EntityContext('test/schema.json');

var_dump($ctx0);
var_dump($ctx1);

// echo json_encode($ctx1->schema, JSON_PRETTY_PRINT);

?>