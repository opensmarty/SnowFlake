# SnowFlake
A distributed generation ID is based in twitter's SnawFlake for our system

#### Project Introduce
Documentation: Coming Soon
Source: http://github.com/opensmarty/SnowFlake

To install via Composer
-----------------------
`composer require opensmarty/SnowFlake`

# example
```php
    /**
     * test for SnowFlake's workId
     */
    public function makeMachineId()
    {
        $worker = new \SnowFlake\IdWorker(31, 31);
        for ($i = 0; $i < 10; $i++) {
            $id = $worker->nextId();
            echo "id[{$i}]:" . $id . PHP_EOL;
        }
    }
```

# Releases

