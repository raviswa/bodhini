<?php

namespace App\Services\Backup\Dumpers;

use App\Contracts\DatabaseDumper;
use App\Exceptions\InvalidValueException;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Use pg_dump and psql, keeping the password in the process environment.
 */
class PgsqlDumper implements DatabaseDumper
{
    /**
     * How long a dump or a restore may run, in seconds.
     */
    private const TIMEOUT = 3600;

    /**
     * Get the connection driver this dumper handles.
     */
    public function driver(): string
    {
        return 'pgsql';
    }

    /**
     * Check whether the tools this dumper needs are installed.
     */
    public function isAvailable(): bool
    {
        return $this->found('pg_dump') && $this->found('psql');
    }

    /**
     * Write the whole database to one file.
     *
     * @param  array<string, mixed>  $connection
     */
    public function dumpTo(string $path, array $connection): void
    {
        $this->run('pg_dump', $connection, [
            '--format=plain',
            '--no-owner',
            '--no-acl',
            '--file='.$path,
            (string) $connection['database'],
        ]);
    }

    /**
     * Load one file back into a database.
     *
     * @param  array<string, mixed>  $connection
     */
    public function restoreFrom(string $path, array $connection): void
    {
        $file = fopen($path, 'rb');

        if ($file === false) {
            throw new RuntimeException("The file [$path] could not be opened.");
        }

        $this->run('psql', $connection, [
            '--set',
            'ON_ERROR_STOP=1',
            '--dbname='.(string) $connection['database'],
        ], $file);
        fclose($file);
    }

    /**
     * Run one of the Postgres tools against the connection.
     *
     * @param  array<string, mixed>  $connection
     * @param  array<int, string>  $arguments
     * @param  resource|null  $input
     */
    private function run(string $tool, array $connection, array $arguments, $input = null): void
    {
        $process = new Process([$tool, ...$arguments], null, $this->environment($connection));
        $process->setTimeout(self::TIMEOUT);

        if ($input !== null) {
            $process->setInput($input);
        }

        $process->run();

        if (!$process->isSuccessful()) {
            throw new InvalidValueException("$tool failed: ".trim($process->getErrorOutput()));
        }
    }

    /**
     * Pass host credentials through the environment, not the argv list.
     *
     * @param  array<string, mixed>  $connection
     * @return array<string, string>
     */
    private function environment(array $connection): array
    {
        $environment = [
            'PGHOST' => (string) ($connection['host'] ?? '127.0.0.1'),
            'PGPORT' => (string) ($connection['port'] ?? 5432),
            'PGUSER' => (string) ($connection['username'] ?? ''),
            'PGPASSWORD' => (string) ($connection['password'] ?? ''),
            'PGDATABASE' => (string) ($connection['database'] ?? ''),
        ];

        $sslmode = $connection['sslmode'] ?? null;

        if (is_string($sslmode) && $sslmode !== '') {
            $environment['PGSSLMODE'] = $sslmode;
        }

        return array_merge($_ENV, $_SERVER, $environment);
    }

    /**
     * Check whether one tool is installed.
     */
    private function found(string $tool): bool
    {
        $process = new Process(['which', $tool]);
        $process->run();

        return $process->isSuccessful();
    }
}
