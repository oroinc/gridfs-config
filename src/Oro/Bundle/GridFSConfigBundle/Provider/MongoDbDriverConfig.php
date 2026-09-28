<?php

declare(strict_types=1);

namespace Oro\Bundle\GridFSConfigBundle\Provider;

use MongoDB\Client;
use MongoDB\Driver\Exception\Exception as DriverException;
use MongoDB\Exception\InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Mongo DB driver config provider.
 *
 * Takes the database name from a MongoDB connection string, rejects a string it cannot resolve one
 * from, and quotes no part of the string in any message it produces.
 */
class MongoDbDriverConfig
{
    /**
     * Captures the database name. The credentials end at the first "@", they may hold a "?", and
     * only "?" ends the name: a connection string has no fragment. Everything after the scheme is
     * optional, so a failed match means a wrong scheme, and an absent or empty group means the
     * string carries no database name.
     */
    private const CONNECTION_STRING_PATTERN
        = '#^(?:mongodb|mongodb\+srv)://(?:[^/@]*@)?[^/?]*(?:/(?<db>[^?]*))?#';

    /**
     * The characters MongoDB does not allow in a database name, and the control characters.
     */
    private const FORBIDDEN_DB_NAME_PATTERN = '#[\x00-\x1F\x7F "$./\\\\]#';

    private string $dbConfig;

    private string $dbName;

    public function __construct(string $dbConfig)
    {
        // Checked before the trim, which strips a NUL of its own. The driver stops at a NUL byte
        // and would not see the rest of the string, the options included, while this class would.
        if (str_contains($dbConfig, "\0")) {
            throw new InvalidArgumentException(
                'The MongoDB connection string must not contain a NUL byte.'
            );
        }

        // A value from a file secret ends with a line break.
        $dbConfig = trim($dbConfig);

        if (!preg_match(self::CONNECTION_STRING_PATTERN, $dbConfig, $matches)) {
            throw new InvalidArgumentException(
                'The MongoDB connection string must use the "mongodb" or "mongodb+srv" scheme.'
            );
        }

        $dbName = rawurldecode($matches['db'] ?? '');
        if ('' === $dbName) {
            throw new InvalidArgumentException(
                'The MongoDB connection string must contain a database name,'
                . ' for example "mongodb://127.0.0.1:27017/media".'
            );
        }

        // Decoded first, so a percent-encoded character cannot pass the check.
        if (preg_match(self::FORBIDDEN_DB_NAME_PATTERN, $dbName)) {
            throw new InvalidArgumentException(
                'The MongoDB database name must not contain a control character, a space'
                . ' or any of the " $ . / \\ characters.'
                . ' Percent-encode the reserved characters of the username and the password.'
            );
        }

        $this->dbConfig = $dbConfig;
        $this->dbName = $dbName;
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
        } catch (DriverException $e) {
            // Not the exception object: its message and its trace hold the connection string.
            $logger?->warning('MongoDB ping failed.', [
                'error' => sprintf(
                    '%s: %s',
                    $e::class,
                    str_replace($mongoDbConfig, '<connection string>', $e->getMessage())
                ),
            ]);

            return false;
        }

        return true;
    }
}
