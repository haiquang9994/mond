<?php

namespace Mond\Core;

use Exception;
use League\CLImate\CLImate;

class MongoCollection
{
    private $database;

    public function __construct($database)
    {
        $this->database = $database;
        $this->climate = new CLImate;
    }

    protected function collection(string $name): Collection
    {
        return new Collection($name, $this->database);
    }

    protected function up()
    {
    }

    protected function down()
    {
    }

    protected function seed()
    {
    }

    public function runUp()
    {
        try {
            $this->up();
        } catch (Exception $e) {
            $this->climate->red($e->getMessage());
            die;
        }
    }

    public function runDown()
    {
        try {
            $this->down();
        } catch (Exception $e) {
            $this->climate->red($e->getMessage());
            die;
        }
    }

    public function runSeed()
    {
        try {
            $this->seed();
        } catch (Exception $e) {
            $this->climate->red($e->getMessage());
            die;
        }
    }
}
