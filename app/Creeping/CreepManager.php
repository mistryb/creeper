<?php

namespace App\Creeping;

use App\Creeping\Contracts\CreepDriver;
use App\Creeping\Drivers\FakeCreepDriver;
use App\Creeping\Drivers\HttpCreepDriver;
use App\Creeping\Drivers\LlmCreepDriver;
use App\Creeping\Fetching\PageFetcher;
use Illuminate\Support\Manager;

/**
 * Resolves the configured creep driver.
 *
 * @method CreepDriver driver(string|null $driver = null)
 */
class CreepManager extends Manager
{
    public function getDefaultDriver(): string
    {
        /** @var string $driver */
        $driver = $this->config->get('creeping.driver', 'fake');

        return $driver;
    }

    public function createFakeDriver(): CreepDriver
    {
        return new FakeCreepDriver;
    }

    public function createHttpDriver(): CreepDriver
    {
        /** @var array<string, mixed> $config */
        $config = $this->config->get('creeping.drivers.http', []);

        return new HttpCreepDriver($config);
    }

    public function createLlmDriver(): CreepDriver
    {
        /** @var array<string, mixed> $config */
        $config = $this->config->get('creeping.drivers.llm', []);

        return new LlmCreepDriver($config, new PageFetcher($config));
    }
}
