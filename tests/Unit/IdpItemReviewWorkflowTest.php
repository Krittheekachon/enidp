<?php

namespace Tests\Unit;

use App\Services\IdpItemReviewWorkflow;
use App\Services\ReviewerChainResolver;
use PHPUnit\Framework\TestCase;

class IdpItemReviewWorkflowTest extends TestCase
{
    public function test_it_returns_configured_steps_in_order(): void
    {
        $owner = (object) ['id' => 1];
        $workflow = new IdpItemReviewWorkflow($this->resolverWithSteps([
            ['step' => 1, 'reviewer_id' => 11],
            ['step' => 2, 'reviewer_id' => 22],
            ['step' => 3, 'reviewer_id' => 33],
        ]));

        $this->assertSame([1, 2, 3], $workflow->configuredSteps($owner)->all());
        $this->assertSame(1, $workflow->firstStep($owner));
        $this->assertSame(2, $workflow->nextStep($owner, 1));
        $this->assertSame(3, $workflow->nextStep($owner, 2));
        $this->assertNull($workflow->nextStep($owner, 3));
    }

    public function test_it_skips_empty_steps(): void
    {
        $owner = (object) ['id' => 1];
        $workflow = new IdpItemReviewWorkflow($this->resolverWithSteps([
            ['step' => 1, 'reviewer_id' => 11],
            ['step' => 3, 'reviewer_id' => 33],
        ]));

        $this->assertSame([1, 3], $workflow->configuredSteps($owner)->all());
        $this->assertSame(3, $workflow->nextStep($owner, 1));
    }

    private function resolverWithSteps(array $steps): ReviewerChainResolver
    {
        return new class($steps) extends ReviewerChainResolver {
            public function __construct(private array $steps)
            {
            }

            public function stepsForUser(object $user, string $chainType = 'assessment'): array
            {
                return $chainType === 'idp' ? $this->steps : [];
            }
        };
    }
}
