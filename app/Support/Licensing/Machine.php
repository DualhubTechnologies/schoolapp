<?php

namespace App\Support\Licensing;

/**
 * This computer, for tying a Windows app licence (and a sync link) to it:
 * a short fingerprint of Windows' own machine id (MachineGuid, which stays
 * the same through reinstalls of SchoolHub but differs on every computer).
 * Copying SchoolHub's data folder to another computer therefore does not
 * copy its licence. Elsewhere (tests, Linux) the machine id or host name.
 */
class Machine
{
    protected static ?string $id = null;

    public static function id(): string
    {
        if (filled(config('licence.machine_id'))) {
            return (string) config('licence.machine_id');
        }

        return static::$id ??= substr(hash('sha256', 'schoolhub|'.static::raw()), 0, 20);
    }

    protected static function raw(): string
    {
        // Kept beside the clock note, in local app data, which stays on this
        // computer (it is not part of SchoolHub's data folder).
        $saved = dirname(DesktopLicence::clockFile()).DIRECTORY_SEPARATOR.'m.dat';

        // Read once from Windows, then from the saved copy (quicker).
        if (DesktopLicence::clockFile() !== 'off' && is_string($remembered = @file_get_contents($saved)) && trim($remembered) !== '') {
            return trim($remembered);
        }

        if (PHP_OS_FAMILY === 'Windows' && function_exists('shell_exec')) {
            $output = (string) @shell_exec('reg query "HKLM\\SOFTWARE\\Microsoft\\Cryptography" /v MachineGuid 2>NUL');

            if (preg_match('/MachineGuid\s+REG_SZ\s+([0-9a-fA-F-]{20,})/', $output, $match)) {
                $guid = strtolower($match[1]);

                if (DesktopLicence::clockFile() !== 'off') {
                    @mkdir(dirname($saved), 0755, true);
                    @file_put_contents($saved, $guid);
                }

                return $guid;
            }
        }

        foreach (['/etc/machine-id', '/var/lib/dbus/machine-id'] as $file) {
            if (is_readable($file) && ($id = trim((string) file_get_contents($file))) !== '') {
                return $id;
            }
        }

        return (string) (getenv('COMPUTERNAME') ?: (gethostname() ?: php_uname('n')));
    }
}
