<?php

namespace Mond;

use League\CLImate\CLImate;
use MongoDB\Client;
use MongoDB\Database;

class Mond
{
    protected $root;
    protected $command;
    protected $options;
    protected $config;

    public function run()
    {
        $this->setProperties();
        if ($this->command == 'init') {
            $this->init();
        } else {
            $this->loadConfig();
            $this->runCommand();
        }
    }

    protected function setProperties()
    {
        $this->root = $_SERVER['PWD'];
        $params = $_SERVER['argv'];
        array_shift($params);
        $command = array_shift($params);
        $options = $params;
        $this->command = $command;
        $this->options = $options;
        $this->climate = new CLImate;
    }

    protected function init()
    {
        $configPath = $this->root . '/mond.json';
        if (!is_file($configPath)) {
            copy(__DIR__ . '/_source/mond.json', $configPath);
            $this->climate->green('Initialized successfully!');
        } else {
            $this->climate->red('Initialized already!');
        }
        $this->climate->green('Config file saved in (' . $configPath . ')');
    }

    protected function loadConfig()
    {
        $configPath = $this->root . '/mond.json';
        $this->config = json_decode(file_get_contents($configPath), true);
    }

    protected function runCommand()
    {
        if ($this->command == 'run') {
            return $this->scriptRun();
        }
        if ($this->command == 'create') {
            return $this->scriptCreate();
        }
    }

    protected function scriptRun()
    {
        $database = $this->getDatabase();
        $commandType = $this->options[0];
        $mondLogs = $database->mond_logs;
        if ($commandType == 'up') {
            return $this->scriptRunUp($database, $mondLogs);
        } elseif ($commandType == 'down') {
            return $this->scriptRunDown($database, $mondLogs);
        } elseif ($commandType == 'seed') {
            return $this->scriptRunSeed($database);
        }
    }

    protected function scriptRunUp(Database $database, $mondLogs)
    {
        $logs = array_map(function ($log) {
            return $log->time;
        }, $mondLogs->find()->toArray());
        $dir = sprintf('%s/%s/*.php', $this->root, $this->config['dir']['migrations']);
        $files = glob($dir);
        foreach ($files as $file) {
            $fileName = substr($file, strrpos($file, '/') + 1);
            $args = explode('_', $fileName);
            $time = array_shift($args);
            if (!in_array($time, $logs)) {
                require_once $file;
                $className = substr(implode('_', $args), 0, -4);
                (new $className($database))->runUp();
                $mondLogs->insertOne([
                    'time' => $time,
                    'name' => $className,
                ]);
                $this->climate->green('+ ' . $className);
            }
        }
    }

    protected function scriptRunDown(Database $database, $mondLogs)
    {
        $logs = array_map(function ($log) {
            return $log->time;
        }, $mondLogs->find()->toArray());
        $dir = sprintf('%s/%s/*.php', $this->root, $this->config['dir']['migrations']);
        $files = glob($dir);
        $downAll = ($this->options[1] ?? '') === 'all';
        foreach ($files as $file) {
            $fileName = substr($file, strrpos($file, '/') + 1);
            $args = explode('_', $fileName);
            $time = array_shift($args);
            if (in_array($time, $logs)) {
                require_once $file;
                $className = substr(implode('_', $args), 0, -4);
                (new $className($database))->runDown();
                $mondLogs->deleteOne([
                    'time' => $time,
                ]);
                $this->climate->green('- ' . $className);
                if (!$downAll) {
                    break;
                }
            }
        }
    }

    protected function scriptRunSeed(Database $database)
    {
        if ($seeder = $this->options[1] ?? null) {
            $dir = sprintf("%s/%s/$seeder.php", $this->root, $this->config['dir']['seeds']);
        } else {
            $dir = sprintf('%s/%s/*.php', $this->root, $this->config['dir']['seeds']);
        }
        $files = glob($dir);
        foreach ($files as $file) {
            $fileName = substr($file, strrpos($file, '/') + 1);
            $args = explode('_', $fileName);
            require_once $file;
            $className = substr(implode('_', $args), 0, -4);
            (new $className($database))->runSeed();
            $this->climate->green('... ' . $className);
        }
    }

    protected function scriptCreate()
    {
        $className = $this->options[0];
        $args = explode(':', $className);
        if (count($args) === 2) {
            if ($args[0] === 'seed') {
                return $this->scriptCreateSeed($args[1]);
            } elseif ($args[0] === 'migrate') {
                return $this->scriptCreateMigrate($args[1]);
            }
        }
        return $this->scriptCreateMigrate($className);
    }

    protected function scriptCreateSeed($className)
    {
        $name = $className . '.php';
        $dir = sprintf('%s/%s/%s', $this->root, $this->config['dir']['seeds'], $name);
        $content = file_get_contents(__DIR__ . '/_source/seed.txt');
        $content = str_replace('[class_name]', $className, $content);
        file_put_contents($dir, $content);
    }

    protected function scriptCreateMigrate($className)
    {
        $name = intval(microtime(true) * 1000) . '_' . $className . '.php';
        $dir = sprintf('%s/%s/%s', $this->root, $this->config['dir']['migrations'], $name);
        $content = file_get_contents(__DIR__ . '/_source/migration.txt');
        $content = str_replace('[class_name]', $className, $content);
        file_put_contents($dir, $content);
    }

    protected function getDatabase(): Database
    {
        static $database;
        if (!$database) {
            $connection = $this->config['connection'];
            $uri = sprintf('mongodb://%s:%s@%s:%s', $connection['user'], $connection['password'], $connection['host'], $connection['port']);
            $database = (new Client($uri))->{$connection['database']};
        }
        return $database;
    }
}
