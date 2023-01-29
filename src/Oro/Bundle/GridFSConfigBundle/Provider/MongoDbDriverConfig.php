<?php

namespace Oro\Bundle\GridFSConfigBundle\Provider;

/**
 * Mongo DB driver config provider.
 */
class MongoDbDriverConfig
{
    private string $dbConfig;

    private string $dbName;

    public function __construct(string $dbConfig)
    {
        preg_match("|mongodb://.*/(?<db>\w+)$|", $dbConfig, $matches);
        $this->dbConfig = $dbConfig;
        $this->dbName = $matches['db'];
    }

    public function getDbConfig(): string
    {
        return $this->dbConfig;
    }

    public function getDbName(): string
    {
        return $this->dbName;
    }
}
