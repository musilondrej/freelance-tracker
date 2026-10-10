<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use SplFileInfo;
use Tests\TestCase;

/**
 * The rules from CLAUDE.md and docs/architecture.md, checked on the source so they cannot drift.
 */
final class ArchitectureTest extends TestCase
{
    public function test_every_php_file_declares_strict_types(): void
    {
        $missing = $this->phpFiles(['app', 'config', 'database', 'routes', 'tests'])
            ->reject(fn (string $path): bool => str_contains(File::get($path), 'declare(strict_types=1);'))
            ->values()
            ->all();

        $this->assertSame([], $missing);
    }

    public function test_actions_are_final_with_a_handle_method(): void
    {
        foreach ($this->phpFiles(['app/Actions']) as $path) {
            $source = File::get($path);

            $this->assertMatchesRegularExpression('/final (readonly )?class/', $source, $path);
            $this->assertMatchesRegularExpression('/public function handle\(/', $source, $path);
        }
    }

    /**
     * @param  list<string>  $directories
     */
    #[DataProvider('layersWithoutUi')]
    public function test_domain_layers_do_not_depend_on_the_ui(array $directories): void
    {
        $offending = $this->phpFiles($directories)
            // The User model is the panel account, so it implements Filament's contracts by design.
            ->reject(fn (string $path): bool => Str::endsWith($path, 'app/Models/User.php'))
            ->filter(fn (string $path): bool => str_contains(File::get($path), 'use Filament\\'))
            ->values()
            ->all();

        $this->assertSame([], $offending);
    }

    /**
     * @return array<string, array{list<string>}>
     */
    public static function layersWithoutUi(): array
    {
        return [
            'actions' => [['app/Actions']],
            'models' => [['app/Models']],
            'reporting' => [['app/Reporting']],
            'support' => [['app/Support']],
        ];
    }

    public function test_env_is_read_only_in_config(): void
    {
        $offending = $this->phpFiles(['app', 'routes', 'database'])
            ->filter(fn (string $path): bool => preg_match('/(?<![\w>:])env\(/', File::get($path)) === 1)
            ->values()
            ->all();

        $this->assertSame([], $offending);
    }

    public function test_no_debugging_leftovers(): void
    {
        $offending = $this->phpFiles(['app', 'routes', 'database'])
            ->filter(fn (string $path): bool => preg_match('/(?<![\w>:$])(dd|dump|ddd|ray|var_dump|print_r)\(/', File::get($path)) === 1)
            ->values()
            ->all();

        $this->assertSame([], $offending);
    }

    /**
     * @param  list<string>  $directories
     * @return Collection<int, string>
     */
    private function phpFiles(array $directories): Collection
    {
        /** @var list<string> $paths */
        $paths = collect($directories)
            ->flatMap(fn (string $directory): array => File::allFiles(base_path($directory)))
            ->map(fn (SplFileInfo $file): string => $file->getPathname())
            ->filter(fn (string $path): bool => str_ends_with($path, '.php') && ! str_ends_with($path, '.blade.php'))
            ->values()
            ->all();

        return collect($paths);
    }
}
