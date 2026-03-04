<?php

declare(strict_types=1);

namespace GalatanOvidiu\PhpMcpClient\Tests\Unit\JsonRpc;

use GalatanOvidiu\PhpMcpClient\JsonRpc\IdGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for IdGenerator.
 *
 * @covers \GalatanOvidiu\PhpMcpClient\JsonRpc\IdGenerator
 */
final class IdGeneratorTest extends TestCase
{
	/**
	 * Test next returns 1 on first call and increments.
	 */
	public function test_next_onFirstAndSubsequentCalls_incrementsSequentially(): void
	{
		$generator = new IdGenerator();

		$this->assertSame(1, $generator->next());
		$this->assertSame(2, $generator->next());
		$this->assertSame(3, $generator->next());
	}

	/**
	 * Test reset sets counter back to 0 so next returns 1.
	 */
	public function test_reset_afterIncrements_resetsCounterToZero(): void
	{
		$generator = new IdGenerator();

		$generator->next();
		$generator->next();
		$generator->reset();

		$this->assertSame(1, $generator->next());
	}
}
