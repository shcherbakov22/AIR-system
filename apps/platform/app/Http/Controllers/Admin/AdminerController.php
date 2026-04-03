<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class AdminerController extends Controller
{
    public function __invoke(): View
    {
        $defaultConnection = (string) config('database.default', 'pgsql');
        $connection = (array) config("database.connections.{$defaultConnection}", []);

        $driver = match ((string) ($connection['driver'] ?? 'pgsql')) {
            'pgsql' => 'pgsql',
            'mysql', 'mariadb' => 'server',
            'sqlite' => 'sqlite',
            'sqlsrv' => 'mssql',
            default => 'pgsql',
        };

        $host = trim((string) ($connection['host'] ?? '127.0.0.1'));
        $host = $host === '' || strcasecmp($host, 'localhost') === 0 ? '127.0.0.1' : $host;
        $port = trim((string) ($connection['port'] ?? ''));
        $server = $port !== '' ? "{$host}:{$port}" : $host;

        return view('admin.adminer-bridge', [
            'driver' => $driver,
            'server' => $server,
            'username' => (string) ($connection['username'] ?? ''),
            'password' => (string) ($connection['password'] ?? ''),
            'database' => (string) ($connection['database'] ?? ''),
        ]);
    }
}
