<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Enums\UserRole;
use App\Models\Student;
use App\Models\StudentConsequenceProfile;
use App\Models\StudentSetting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Process\Process;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('legacy:import-recent-users
    {--source-json=}
    {--host=192.168.11.66}
    {--ssh-user=root}
    {--ssh-pass=}
    {--db-name=cw}
    {--db-user=root}
    {--db-pass=}
    {--cutoff=2025-01-01 00:00:00}
    {--temp-password=0}', function () {
    $sourceJson = (string) $this->option('source-json');
    $host = (string) $this->option('host');
    $sshUser = (string) $this->option('ssh-user');
    $sshPass = (string) $this->option('ssh-pass');
    $dbName = (string) $this->option('db-name');
    $dbUser = (string) $this->option('db-user');
    $dbPass = (string) $this->option('db-pass');
    $cutoff = (string) $this->option('cutoff');
    $tempPassword = (string) $this->option('temp-password');

    if ($sourceJson === '' && ($sshPass === '' || $dbPass === '')) {
        $this->error('Provide both --ssh-pass and --db-pass to connect to the legacy system.');
        return Command::FAILURE;
    }

    if ($sourceJson !== '') {
        if (! is_file($sourceJson)) {
            $this->error("Source file {$sourceJson} was not found.");
            return Command::FAILURE;
        }

        $decoded = json_decode((string) file_get_contents($sourceJson), true);

        if (! is_array($decoded)) {
            $this->error("Source file {$sourceJson} does not contain a valid JSON array.");
            return Command::FAILURE;
        }

        $rows = collect($decoded)
            ->filter(fn ($row) => is_array($row))
            ->map(fn (array $row) => [
                'user_id' => (int) ($row['user_id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'email' => ($row['email'] ?? null) !== null && (string) $row['email'] !== '' ? (string) $row['email'] : null,
                'lastdate' => (int) ($row['lastdate'] ?? 0),
                'logged_ip' => (string) ($row['logged_ip'] ?? ''),
            ])
            ->all();
    } else {
        $sql = sprintf(
            "SELECT user_id, name, IFNULL(NULLIF(email, ''), ''), lastdate, IFNULL(logged_ip, '') FROM dle_users WHERE lastdate >= UNIX_TIMESTAMP('%s') ORDER BY lastdate DESC, user_id ASC;",
            addslashes($cutoff),
        );

        $remoteCommand = sprintf(
            'mysql -u%s -p%s %s -N -B -e %s',
            escapeshellarg($dbUser),
            escapeshellarg($dbPass),
            escapeshellarg($dbName),
            escapeshellarg($sql),
        );

        $process = new Process([
            'sshpass',
            '-p',
            $sshPass,
            'ssh',
            '-o',
            'PreferredAuthentications=password',
            '-o',
            'PubkeyAuthentication=no',
            '-o',
            'NumberOfPasswordPrompts=1',
            '-o',
            'StrictHostKeyChecking=no',
            sprintf('%s@%s', $sshUser, $host),
            $remoteCommand,
        ]);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error(trim($process->getErrorOutput()) ?: trim($process->getOutput()) ?: 'Legacy user query failed.');
            return Command::FAILURE;
        }

        $rows = collect(preg_split("/\r\n|\n|\r/", trim($process->getOutput())))
            ->filter(fn (?string $line) => $line !== null && $line !== '')
            ->map(function (string $line): array {
                [$userId, $name, $email, $lastDate, $loggedIp] = array_pad(explode("\t", $line), 5, '');

                return [
                    'user_id' => (int) $userId,
                    'name' => $name,
                    'email' => $email !== '' ? $email : null,
                    'lastdate' => (int) $lastDate,
                    'logged_ip' => $loggedIp,
                ];
            })
            ->all();
    }

    $created = 0;
    $updated = 0;

    DB::transaction(function () use ($rows, $tempPassword, &$created, &$updated) {
        foreach ($rows as $row) {
            $legacyId = (int) $row['user_id'];
            $username = strtolower(trim((string) $row['name']));
            $email = $row['email'] !== null ? strtolower(trim((string) $row['email'])) : null;
            $lastLoginAt = ! empty($row['lastdate']) ? Carbon::createFromTimestamp((int) $row['lastdate']) : null;
            $legacyIp = trim((string) ($row['logged_ip'] ?? ''));

            $student = Student::query()
                ->whereHas('consequenceProfile', fn ($query) => $query->where('legacy_owner_user_id', $legacyId))
                ->first();

            if (! $student) {
                $existingUser = User::query()
                    ->where('username', $username)
                    ->where('role', UserRole::Student)
                    ->first();

                if ($existingUser) {
                    $student = Student::query()->where('user_id', $existingUser->id)->first();
                }
            }

            if (! $student) {
                $user = User::create([
                    'username' => $username,
                    'name' => $username,
                    'email' => $email,
                    'role' => UserRole::Student,
                    'is_active' => true,
                    'last_login_at' => $lastLoginAt,
                    'password' => Hash::make($tempPassword),
                ]);

                $student = Student::create([
                    'user_id' => $user->id,
                    'display_name' => $username,
                    'status' => 'active',
                    'notes' => "Imported from legacy user {$username}.",
                ]);

                StudentSetting::create([
                    'student_id' => $student->id,
                    'can_manage_own_schedule' => true,
                    'can_use_ad_hoc_timer' => true,
                    'preferred_timezone' => null,
                ]);

                StudentConsequenceProfile::create([
                    'student_id' => $student->id,
                    'default_push_up_count' => 0,
                    'rest_duration_seconds' => 0,
                    'legacy_owner_user_id' => $legacyId,
                    'notes' => $legacyIp !== '' ? "Legacy IP {$legacyIp}" : null,
                ]);

                $created++;
                continue;
            }

            $student->loadMissing(['user', 'consequenceProfile']);

            $student->user->update([
                'username' => $username,
                'name' => $username,
                'email' => $email,
                'role' => UserRole::Student,
                'is_active' => true,
                'last_login_at' => $lastLoginAt,
                'password' => Hash::make($tempPassword),
            ]);

            $student->update([
                'display_name' => $username,
                'status' => 'active',
                'notes' => "Imported from legacy user {$username}.",
            ]);

            $student->setting()->updateOrCreate(
                ['student_id' => $student->id],
                [
                    'can_manage_own_schedule' => true,
                    'can_use_ad_hoc_timer' => true,
                    'preferred_timezone' => null,
                ],
            );

            $student->consequenceProfile()->updateOrCreate(
                ['student_id' => $student->id],
                [
                    'default_push_up_count' => $student->consequenceProfile?->default_push_up_count ?? 0,
                    'rest_duration_seconds' => $student->consequenceProfile?->rest_duration_seconds ?? 0,
                    'legacy_owner_user_id' => $legacyId,
                    'notes' => $legacyIp !== '' ? "Legacy IP {$legacyIp}" : null,
                ],
            );

            $updated++;
        }
    });

    $this->info("Imported {$created} new students and updated {$updated} existing students.");
    $this->line('Temporary password assigned to imported accounts has been reset.');

    return Command::SUCCESS;
})->purpose('Import legacy users with recent logins into the student accounts table');
