<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BackupService;
use Illuminate\Support\Facades\File;
use PDO;
use Tests\TestCase;

class BackupCommandTest extends TestCase
{
    private string $storage;

    protected function setUp(): void
    {
        parent::setUp();

        // Sicherungen in ein Wegwerf-Verzeichnis statt nach storage/ schreiben.
        $this->storage = sys_get_temp_dir().'/cardiopulse-backup-test-'.uniqid();
        $this->app->useStoragePath($this->storage);

        // Ohne RefreshDatabase: „VACUUM INTO“ läuft nicht innerhalb der Test-Transaktion.
        $this->artisan('migrate');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storage);

        parent::tearDown();
    }

    public function test_backup_writes_a_readable_snapshot_and_prints_its_path(): void
    {
        User::factory()->count(3)->create();

        $this->artisan('cardiopulse:backup')
            ->expectsOutputToContain('cardiopulse-')
            ->assertSuccessful();

        $files = File::glob($this->storage.'/app/backups/cardiopulse-*.sqlite');
        $this->assertCount(1, $files);

        $snapshot = new PDO('sqlite:'.$files[0]);
        $this->assertSame(3, (int) $snapshot->query('select count(*) from users')->fetchColumn());
        $this->assertSame('ok', $snapshot->query('pragma integrity_check')->fetchColumn());
    }

    public function test_only_the_newest_backups_are_kept(): void
    {
        $directory = $this->storage.'/app/backups';
        File::ensureDirectoryExists($directory);
        foreach (range(1, BackupService::KEEP + 3) as $day) {
            File::put(sprintf('%s/cardiopulse-2026-01-%02d_120000.sqlite', $directory, $day), 'alt');
        }

        $deleted = app(BackupService::class)->prune();

        $this->assertSame([
            'cardiopulse-2026-01-03_120000.sqlite',
            'cardiopulse-2026-01-02_120000.sqlite',
            'cardiopulse-2026-01-01_120000.sqlite',
        ], $deleted);
        $this->assertCount(BackupService::KEEP, File::glob($directory.'/cardiopulse-*.sqlite'));
    }
}
