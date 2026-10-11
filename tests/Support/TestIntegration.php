<?php

namespace App\Tests\Support;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Base des tests d'intégration : services réels et base de test (blvb_new_test).
 * Le schéma est créé une fois par les migrations (Foundry, mode migrate) ; chaque test tourne
 * dans une transaction annulée à la fin (dama/doctrine-test-bundle).
 */
abstract class TestIntegration extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    protected static function service(string $id): object
    {
        return static::getContainer()->get($id);
    }
}
