<?php

namespace Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // A key missing from $fillable is otherwise dropped without a word -
        // which is how registration once saved every account unverified.
        Model::preventSilentlyDiscardingAttributes();
    }
}
