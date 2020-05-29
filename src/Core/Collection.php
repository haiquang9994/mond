<?php

namespace Mond\Core;

class Collection
{
    protected $__collection;

    public function __construct(string $name, $database)
    {
        $this->database = $database;
        $this->__collection = $database->{$name};
    }

    public function addIndex(string $name, array $fields, array $options = [])
    {
        $index_fields = [];
        foreach ($fields as $field) {
            $index_fields[$field] = 1;
        }
        $unique = boolval($options['unique']);
        $this->__collection->createIndex($index_fields, ['unique' => $unique, 'name' => $name]);
        return $this;
    }

    public function dropIndex(string $name)
    {
        $this->__collection->dropIndex($name);
        return $this;
    }

    public function addDocument(array $data)
    {
        $this->__collection->insertOne($data);
        return $this;
    }
}
