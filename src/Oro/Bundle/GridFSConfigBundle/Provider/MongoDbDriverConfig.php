<?php

namespace Oro\Bundle\GridFSConfigBundle\Provider;

use MongoDB\Client;
use MongoDB\Driver\Exception\RuntimeException;
use MongoDB\Exception\InvalidArgumentException;
use Psr\Log\LoggerInterface;

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

    public function isConnected(LoggerInterface $logger = null): bool
    {
        $mongoDbConfig = $this->getDbConfig();
        $mongoDbName = $this->getDbName();

        try {
            $client = new Client($mongoDbConfig);
            $client->selectDatabase($mongoDbName)->command(['ping' => 1]);
        } catch (InvalidArgumentException|RuntimeException $e) {
            if ($logger) {
                $logger->warning(
                    sprintf('MongoDB ping failed.'),
                    ['exception' => $e]
                );
            }

            return false;
        }

        return true;
    }
}
