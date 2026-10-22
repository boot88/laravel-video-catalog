<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // В тестах не нужен собранный Vite-манифест: layout проверяет его наличие,
        // но withoutVite делает рендер независимым от npm build.
        $this->withoutVite();
    }
}
