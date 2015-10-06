<?php

// echo 'host: ' . getenv('IP') . ', user: ' . getenv('C9_USER') . ', database: c9, port: ' . 3306 . '\n';

// $db = new mysqli($servername, $username, $password, $database, $port);
// if ($db->connect_error)
// {
//     http_response_code(500);
//     echo 'Connection failed: ' . $db->connect_error . '\n';
//     die();
// }

class EntityContext
{
    protected $connection = array(
        'host' => 'localhost',
        'user' => '',
        'password' => '',
        'database' => 'mysql',
        'port' => 3306
        );
    protected $settings = array(
        'updateOnConnect' => false
        );
    protected $schema = null;
    protected $sql = null;
    function __construct($file = null)
    {
        if ($file)
        {
            if (is_string($file))
            {
                $file = json_decode(file_get_contents($file), true);
            }
            else if (is_object($file))
            {
                $file = get_object_vars($file);
            }
            
            // echo json_encode($file, JSON_PRETTY_PRINT);
            // var_dump($file);
            // echo '<br />';
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
                $this->setUpdateOnConnect($file['settings']['updateOnConnect']);
            }
            
            $this->reconnect();
            $this->refresh();
        }
    }
    
    function __destruct()
    {
        $this->disconnect();
    }
    
    function reconnect()
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
    
    function disconnect()
    {
        if (!empty($this->sql))
        {
            $this->sql->close();
            $this->sql = null;
        }
    }
    
    function refresh()
    {
        if (empty($this->sql))
        {
            return;
        }
        
        $schema = array(
            'tables' => [],
            'links' => []
            );
        $tables = $this->sql->query('show tables')->fetch_all(MYSQLI_NUM);
        foreach ($tables as $table)
        {
            $tableName = $table[0];
            $table = array(
                // 'name' => $table[0]
                );
            
            $columns = $this->sql->query('show columns in ' . $tableName);
            foreach ($columns as $column)
            {
                $table[$column['Field']] = array(
                    // 'name' => $column['Field'],
                    'type' => $column['Type'],
                    'nullable' => $column['Null'] === 'YES' ? true : false,
                    // 'primary' => false
                    );
                // var_dump($column);
            }
            
            // var_dump($table);
            $schema['tables'][$tableName] = $table;
        }
        
        foreach ($schema['tables'] as $tableName => &$table)
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
                    $schema['links'][$key['Key_name']] = array(
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
                
                // var_dump($key);
            }
            
            unset($table);
        }
        
        echo json_encode($schema, JSON_PRETTY_PRINT);
    }
    
    function setUpdateOnConnect($value)
    {
        if (!empty($value))
        {
            $this->settings['updateOnConnect'] = boolval($value);
        }
    }
}

header('Content-Type: text/plain');
$ctx0 = new EntityContext(array('connection' => array()));
$ctx1 = new EntityContext('test/schema.json');

// var_dump($ctx0);
// echo '<br />';
// var_dump($ctx1);
// echo '<br />';

?>