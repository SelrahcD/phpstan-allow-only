<?php

declare(strict_types=1);

namespace App\Tests\Rules;

use App\Rules\AllowOnlyFromRule;
use PHPStan\Testing\RuleTestCase;
use PHPStan\Type\FileTypeMapper;

/**
 * @extends RuleTestCase<AllowOnlyFromRule>
 */
class AllowOnlyFromRuleTest extends RuleTestCase
{
    protected function getRule(): \PHPStan\Rules\Rule
    {
        return new AllowOnlyFromRule(
            self::getContainer()->getByType(FileTypeMapper::class)
        );
    }

    public function testRule(): void
    {
        $this->analyse([
            __DIR__ . '/data/AllowOnlyFromRuleExample.php',
        ], [
            $this->errorAtLine(56),
            $this->errorAtLine(58),
            $this->errorAtLine(60),
            $this->errorAtLine(62),
            $this->errorAtLine(68),
        ]);
    }

    /**
     * @return array{string, int}
     */
    private function errorAtLine(int $lineNumber): array
    {
        return  [
            'Call to AnEntity::setSomethingOnlyFromAggregate is authorized only from:
- AnAggregate
- AnotherAggregate',
            $lineNumber,
        ];
    }
}
