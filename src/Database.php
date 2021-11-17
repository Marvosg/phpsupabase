<?php

namespace PHPSupabase;

use Exception;

class Database {
    private $service;
    private $tableName;
    private $primaryKey;
    private $result;

    /**
     * Construct method (Set the Service instance, the table to be used and the table primary key)
     * @access public
     * @param $service Service The Supabase Service instance
     * @param $tableName String The table
     * @param $primaryKey String The table primary key
     * @return void
     */
    public function __construct(Service $service, string $tableName, string $primaryKey)
    {
        $this->service = $service;
        $this->tableName = $tableName;
        $this->primaryKey = $primaryKey;
    }

    /**
     * Returns the error generated
     * @access public
     * @return string
     */
    public function getError() : string
    {
        return $this->service->getError();
    }

    /**
     * Returns the result (data) generated by a fetch
     * @access public
     * @return array
     */
    public function getResult() : array
    {
        return $this->result;
    }

    /**
     * Returns the first result (data) generated by a fetch
     * @access public
     * @return object
     */
    public function getFirstResult() : object
    {
        return count($this->result) > 0
            ? $this->result[0]
            : [];
    }

    /**
     * Execute a query in database
     * @access private
     * @param $queryString String The parameters to be used in the request
     * @param $table String (optional) Use a different table that the set in construct method
     * @return void
     */
    private function executeQuery(string $queryString, string $table = null) : void
    {
        $table = is_null($table)
                ? $this->tableName
                : $table;
        $uri = $this->service->getUriBase($table . '?' . $queryString);
        $options = [
            'headers' => $this->service->getHeaders()
        ];
        $this->result = $this->service->executeHttpRequest('GET', $uri, $options);
    }

    /**
     * Execute a DML (Data Manipulation Language) query in database
     * @access private
     * @param $method String The request method (GET, POST, PUT, DELETE, PATCH, ...)
     * @param $data array The fields to be used in query/request
     * @param $queryString String (optional) The parameters to be used in the requests
     * @return array|object|null
     */
    private function executeDml(string $method, array $data, string $queryString = null)
    {
        $endPoint = ($queryString == null) ? $this->tableName : $this->tableName . '?' . $queryString; 
        $uri = $this->service->getUriBase($endPoint);
        
        $this->service->setHeader('Prefer', 'return=representation');
        $options = [
            'headers' => $this->service->getHeaders(),
            'body' => json_encode($data)
        ];
        return $this->service->executeHttpRequest($method, $uri, $options);
    }

    /**
     * Insert a new register into table
     * @access public
     * @param $data array The values to be inserted
     * @return array|object|null
     */
    public function insert(array $data)
    {
        return $this->executeDml('POST', $data);
    }

    /**
     * Update a register into table
     * @access public
     * @param $id String The "id" (PK) of the register, to be used in WHERE clause
     * @param $data array The values to be updated
     * @return array|object|null
     */
    public function update(string $id, array $data)
    {
        return $this->executeDml('PATCH', $data, $this->primaryKey . '=eq.' . $id);
    }

    /**
     * Delete a register into table
     * @access public
     * @param $id String The "id" (PK) of the register, to be used in WHERE clause
     * @return array|object|null
     */
    public function delete(string $id)
    {
        return $this->executeDml('DELETE', [], $this->primaryKey . '=eq.' . $id);
    }

    /**
     * Fetch all registers of table
     * @access public
     * @return Database
     */
    public function fetchAll() : Database
    {
        $this->executeQuery('select=*');
        return $this;
    }

    /**
     * Fetch registers of table by a especific column/value
     * @access public
     * @param $column String The column name
     * @param $value String The value
     * @return Database
     */
    public function findBy(string $column, string $value) : Database
    {
        $this->executeQuery($column . '=eq.' . $value);
        return $this;
    }

    /**
     * Fetch registers of table by a especific column/value, using LIKE operator
     * @access public
     * @param $column String The column name
     * @param $value String The value
     * @return Database
     */
    public function findByLike(string $column, string $value) : Database
    {
        $this->executeQuery($column . '=like.%' . $value . '%');
        return $this;
    }

    /**
     * Make a "join" between the seted table and another table related
     * @access public
     * @param $foreignTable String The related table
     * @param $foreignKey String The foreign key (usually "id")
     * @return Database
     */
    public function join(string $foreignTable, string $foreignKey) : Database
    {
        $this->executeQuery('select=*,' . $foreignTable . '(' . $foreignKey . ', *)');
        return $this;
    }

    /**
     * Create a custom query to fetch into database
     * @access public
     * @param $args array The query structure (Available keys: "select", "from", "join", "where", "range")
     * @return Database
     */
    public function createCustomQuery(array $args) : Database
    {
        $queryBuilder = $this->service->initializeQueryBuilder();
        
        $select = isset($args['select'])
                            ? $args['select']
                            : '*';
        $queryBuilder->select($select);

        $from = isset($args['from'])
                ? $args['from']
                : $this->tableName;
        $queryBuilder->from($from);

        if(isset($args['join'])){
            if(is_array($args['join']) && count($args['join']) > 0){
                foreach ($args['join'] as $join){
                    if(is_array($join) && isset($join['table']) && isset($join['tablekey'])){
                        $select = isset($join['select'])
                                    ? $join['select']
                                    : null;
                        $queryBuilder->join($join['table'], $join['tablekey'], $select);
                    }
                    else{
                        throw new Exception('"JOIN" argument must have "table" and "tablekey" keys');
                    }
                }
            }
            else {
                throw new Exception('"JOIN" argument must be an array');
            }
        }

        if(isset($args['where'])){
            if(is_array($args['where']) && count($args['where']) > 0){
                foreach ($args['where'] as $key => $where){
                    $queryBuilder->where($key, $where);
                }
            }
            else{
                throw new Exception('"WHERE" argument must be an array');
            }
        }

        if(isset($args['range'])){
            $queryBuilder->range($args['range']);
        }
        
        $this->result = $queryBuilder->execute()->getResult();
        return $this;
    }
}