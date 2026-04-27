<?php

declare(strict_types=1);

namespace Settings\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeSettingsCommand extends Command
{
    protected $signature = 'settings:make {name : The name of the settings enum class (e.g. GeneralSettings)}';

    protected $description = 'Create a new settings enum class';

    public function handle(): int
    {
        $name = (string) $this->argument('name');
        $className = Str::studly($name);
        $path = app_path("Settings/{$className}.php");

        if (file_exists($path)) {
            $this->error("Settings class [{$className}] already exists.");

            return self::FAILURE;
        }

        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, recursive: true);
        }

        file_put_contents($path, $this->buildStub($className));

        $this->components->info("Settings class [{$className}] created successfully.");
        $this->line("  <fg=gray>→</> <fg=white>{$path}</>");

        return self::SUCCESS;
    }

    private function buildStub(string $className): string
    {
        $group = Str::snake(Str::replaceLast('Settings', '', $className));

        // Prefer the published stub so users can customise it
        $publishedStub = base_path('stubs/settings.stub');
        $packageStub   = __DIR__.'/../../../stubs/settings.stub';

        $stub = file_get_contents(file_exists($publishedStub) ? $publishedStub : $packageStub);

        return str_replace(
            ['{{ class }}', '{{ group }}'],
            [$className, $group],
            $stub,
        );
    }
}
