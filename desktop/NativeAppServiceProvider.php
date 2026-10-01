<?php

namespace App\Providers;

use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Facades\Window;

/**
 * The Windows app's window and PHP settings (NativePHP). Copied into the
 * Windows build by desktop/build-windows.ps1; the online server never
 * loads it.
 */
class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the app has started: SchoolHub in one window, sized for
     * a school office computer and remembered between runs.
     */
    public function boot(): void
    {
        Window::open()
            ->title('SchoolHub')
            ->width(1280)
            ->height(820)
            ->minWidth(1024)
            ->minHeight(680)
            ->rememberState()
            ->hideMenu();
    }

    /**
     * Room for report cards, ID card PDFs and imports of a whole school.
     *
     * @return array<string, string>
     */
    public function phpIni(): array
    {
        return [
            'memory_limit' => '512M',
            'max_execution_time' => '300',
            'upload_max_filesize' => '20M',
            'post_max_size' => '25M',
        ];
    }
}
