<?php

namespace App\Services;

use App\Models\StudentDevice;
use Illuminate\Support\Str;

class RemoteControlCredentialService
{
    public function ensureCredentials(StudentDevice $device): array
    {
        $username = $device->remote_access_username ?: $this->defaultUsername($device);
        $password = $device->remote_access_password ?: Str::password(24, true, true, false, false);

        $device->forceFill([
            'remote_access_username' => $username,
            'remote_access_password' => $password,
        ])->save();

        return [
            'username' => $username,
            'password' => $password,
        ];
    }

    protected function defaultUsername(StudentDevice $device): string
    {
        return 'airremote_'.$device->id;
    }
}
