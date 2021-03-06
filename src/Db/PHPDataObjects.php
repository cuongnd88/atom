<?php

namespace Atom\Db;

use Atom\Db\Exception\DatabaseException;
use PDO;

abstract class PHPDataObjects
{
    protected static $con = [];
    protected $sth;
    protected $table;
    protected $params = [];
    protected $where = [];

    /**
     * Create a PDO instance
     */
    public function __construct()
    {
        $this->connect(env('DB_HOST'), env('DB_USER'), env('DB_PASSWORD'), env('DB_NAME'), env('DB_PORT'));
    }

    /**
     * Connect to Database
     *
     * @param string $host     DB Host
     * @param string $user     DB User
     * @param string $password DB User's password
     * @param string $database DB Name
     * @param string $port     DB Port
     *
     * @return void
     */
    public function connect(string $host, string $user, string $password, string $database, string $port)
    {
        try {
            $mode = env('DB_CONNECTION') .'/'. $host .'/'. $database;
            if (isset(static::$con[$mode])) {
                $this->db = static::$con[$mode];
            }
            if (empty(static::$con) && !isset(static::$con[$mode])) {
                $this->db = new PDO(env('DB_CONNECTION') . ':dbname=' . $database .';host=' . $host . ';port=' . $port, $user, $password);
                static::$con[$mode] = $this->db;
            }
        } catch (PDOException $e) {
            throw new \Exception(DatabaseException::ERR_MSG_CONNECTION_FAIL . ' => ' . $e->getMessage());
        }
    }

    /**
     * Set params
     *
     * @param array $data Params
     */
    public function setParams(array $data)
    {
        $this->params = array_merge($this->params, $data);
    }

    /**
     * Execute query
     *
     * @param  string $query SQL query
     *
     * @return boolean
     */
    public function execute($query)
    {
        $this->sth = $this->db->prepare($query);
        foreach ($this->params as $key => &$value) {
            $this->sth->bindParam(':' .$key, $value);
        }
        $this->sth->execute();
        return $this->sth;
    }

    /**
     * Parse conditions
     *
     * @param  array  $condition Where conditions
     *
     * @return throw Exception | string
     */
    public function parseConditions(array $condition)
    {
        switch (count($condition)) {
            case 1:
                throw new DatabaseException(DatabaseException::ERR_MSG_INVALID_ARGUMENTS);
                break;
            case 2:
                list($key, $value) = $condition;
                $this->setParams([$key => $value]);
                return $key . ' = :' . $key;
                break;
            case 3:
                list($key, $operator, $value) = $condition;
                $operator = strtoupper($operator);
                $this->setParams([$key => $value]);
                return $key . ' '. $operator .' :' . $key;
                break;
        }
    }

    /**
     * Where conditions
     *
     * @param  array  $conditions Where conditions
     *
     * @return $this;
     */
    public function where(array $conditions = [])
    {
        if (!is_array($conditions) || empty($conditions)) {
            throw new DatabaseException(DatabaseException::ERR_MSG_INVALID_ARGUMENTS);
        }

        if (!is_array($conditions[0])) {
            $this->where[] = $this->parseConditions($conditions);
        } else {
            foreach ($conditions as $condition) {
                $this->where[] = $this->parseConditions($condition);
            }
        }

        return $this;
    }

    /**
     * Delete
     *
     * @return boolean
     */
    public function delete()
    {
        $query = "DELETE FROM {$this->table}";
        if (false === empty($this->where)) {
            $query .= " WHERE ". implode(' AND ', $this->where);
        }
        $this->execute($query);
    }

    /**
     * Update
     *
     * @param  array  $data Data
     *
     * @return boolean
     */
    public function update(array $data)
    {
        $tmp = [];
        foreach ($data as $key => $value) {
            $tmp[] = "$key = :{$key}";
        }
        $this->setParams($data);
        $query = "UPDATE {$this->table} SET ".implode(", ", $tmp);
        if (false === empty($this->where)) {
            $query .= " WHERE ". implode(' AND ', $this->where);
        }
        $this->execute($query);
    }

    /**
     * Insert data
     *
     * @param  array  $data Request data
     *
     * @return array
     */
    public function insert(array $data)
    {
        $keys = array_keys($data);
        $callBack = function ($key) {
            return ":".$key;
        };
        $query = "INSERT INTO {$this->table}(". implode(', ', $keys). ") VALUES(". implode(',', array_map($callBack, $keys)) .")";
        $this->setParams($data);
        $this->execute($query);
        return ['id' => $this->getLastInsertId()];
    }

    /**
     * Get data
     *
     * @return array
     */
    public function get()
    {
        $query = "SELECT * FROM {$this->table}";
        return $this->execute($query)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get last insert id
     *
     * @return int
     */
    public function getLastInsertId()
    {
        return $this->db->lastInsertId();
    }
}
