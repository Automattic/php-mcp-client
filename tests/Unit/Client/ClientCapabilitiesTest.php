<?php

declare(strict_types=1);

namespace GalatanOvidiu\PhpMcpClient\Tests\Unit\Client;

use GalatanOvidiu\PhpMcpClient\Client\ClientCapabilities;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ClientCapabilities value object.
 *
 * @covers \GalatanOvidiu\PhpMcpClient\Client\ClientCapabilities
 */
final class ClientCapabilitiesTest extends TestCase
{
	/**
	 * Test default state has sampling disabled.
	 */
	public function test_hasSampling_defaultState_returnsFalse(): void
	{
		$capabilities = new ClientCapabilities();

		$this->assertFalse($capabilities->hasSampling());
	}

	/**
	 * Test default state has roots disabled.
	 */
	public function test_hasRoots_defaultState_returnsFalse(): void
	{
		$capabilities = new ClientCapabilities();

		$this->assertFalse($capabilities->hasRoots());
	}

	/**
	 * Test default state has elicitation disabled.
	 */
	public function test_hasElicitation_defaultState_returnsFalse(): void
	{
		$capabilities = new ClientCapabilities();

		$this->assertFalse($capabilities->hasElicitation());
	}

	/**
	 * Test withSampling enables sampling capability.
	 */
	public function test_withSampling_enabledTrue_returnsTrueForHasSampling(): void
	{
		$capabilities = (new ClientCapabilities())->withSampling(true);

		$this->assertTrue($capabilities->hasSampling());
	}

	/**
	 * Test withSampling with no argument defaults to true.
	 */
	public function test_withSampling_noArgument_enablesSampling(): void
	{
		$capabilities = (new ClientCapabilities())->withSampling();

		$this->assertTrue($capabilities->hasSampling());
	}

	/**
	 * Test withSampling can disable a previously enabled capability.
	 */
	public function test_withSampling_enabledFalse_disablesSampling(): void
	{
		$capabilities = (new ClientCapabilities())->withSampling(true)->withSampling(false);

		$this->assertFalse($capabilities->hasSampling());
	}

	/**
	 * Test withRoots enables roots capability.
	 */
	public function test_withRoots_enabledTrue_returnsTrueForHasRoots(): void
	{
		$capabilities = (new ClientCapabilities())->withRoots(true);

		$this->assertTrue($capabilities->hasRoots());
	}

	/**
	 * Test withRoots with no argument defaults to true.
	 */
	public function test_withRoots_noArgument_enablesRoots(): void
	{
		$capabilities = (new ClientCapabilities())->withRoots();

		$this->assertTrue($capabilities->hasRoots());
	}

	/**
	 * Test withElicitation enables elicitation capability.
	 */
	public function test_withElicitation_enabledTrue_returnsTrueForHasElicitation(): void
	{
		$capabilities = (new ClientCapabilities())->withElicitation(true);

		$this->assertTrue($capabilities->hasElicitation());
	}

	/**
	 * Test withElicitation with no argument defaults to true.
	 */
	public function test_withElicitation_noArgument_enablesElicitation(): void
	{
		$capabilities = (new ClientCapabilities())->withElicitation();

		$this->assertTrue($capabilities->hasElicitation());
	}

	/**
	 * Test with*() methods are immutable and return a new instance.
	 */
	public function test_withSampling_immutability_returnsNewInstance(): void
	{
		$original = new ClientCapabilities();
		$modified = $original->withSampling(true);

		$this->assertNotSame($original, $modified);
		$this->assertFalse($original->hasSampling());
		$this->assertTrue($modified->hasSampling());
	}

	/**
	 * Test withRoots returns a new instance without modifying original.
	 */
	public function test_withRoots_immutability_returnsNewInstance(): void
	{
		$original = new ClientCapabilities();
		$modified = $original->withRoots(true);

		$this->assertNotSame($original, $modified);
		$this->assertFalse($original->hasRoots());
		$this->assertTrue($modified->hasRoots());
	}

	/**
	 * Test withElicitation returns a new instance without modifying original.
	 */
	public function test_withElicitation_immutability_returnsNewInstance(): void
	{
		$original = new ClientCapabilities();
		$modified = $original->withElicitation(true);

		$this->assertNotSame($original, $modified);
		$this->assertFalse($original->hasElicitation());
		$this->assertTrue($modified->hasElicitation());
	}

	/**
	 * Test withExperimental returns a new instance without modifying original.
	 */
	public function test_withExperimental_immutability_returnsNewInstance(): void
	{
		$original = new ClientCapabilities();
		$modified = $original->withExperimental(['custom' => true]);

		$this->assertNotSame($original, $modified);
	}

	/**
	 * Test toObject with no capabilities returns empty stdClass.
	 */
	public function test_toObject_noCapabilities_returnsEmptyStdClass(): void
	{
		$capabilities = new ClientCapabilities();
		$result = $capabilities->toObject();

		$this->assertInstanceOf(\stdClass::class, $result);
		$this->assertEquals(new \stdClass(), $result);
	}

	/**
	 * Test toObject with sampling enabled includes sampling property.
	 */
	public function test_toObject_withSampling_includesSamplingProperty(): void
	{
		$capabilities = (new ClientCapabilities())->withSampling(true);
		$result = $capabilities->toObject();

		$this->assertTrue(property_exists($result, 'sampling'));
		$this->assertInstanceOf(\stdClass::class, $result->sampling);
	}

	/**
	 * Test toObject with roots enabled includes roots property with listChanged.
	 */
	public function test_toObject_withRoots_includesRootsWithListChanged(): void
	{
		$capabilities = (new ClientCapabilities())->withRoots(true);
		$result = $capabilities->toObject();

		$this->assertTrue(property_exists($result, 'roots'));
		$this->assertTrue($result->roots->listChanged);
	}

	/**
	 * Test toObject with elicitation enabled includes elicitation property.
	 */
	public function test_toObject_withElicitation_includesElicitationProperty(): void
	{
		$capabilities = (new ClientCapabilities())->withElicitation(true);
		$result = $capabilities->toObject();

		$this->assertTrue(property_exists($result, 'elicitation'));
		$this->assertInstanceOf(\stdClass::class, $result->elicitation);
	}

	/**
	 * Test toObject with experimental capabilities includes experimental property.
	 */
	public function test_toObject_withExperimental_includesExperimentalProperty(): void
	{
		$capabilities = (new ClientCapabilities())->withExperimental(['custom' => 'value']);
		$result = $capabilities->toObject();

		$this->assertTrue(property_exists($result, 'experimental'));
		$this->assertSame('value', $result->experimental->custom);
	}

	/**
	 * Test toObject with empty experimental array does not include experimental property.
	 */
	public function test_toObject_withEmptyExperimental_excludesExperimentalProperty(): void
	{
		$capabilities = (new ClientCapabilities())->withExperimental([]);
		$result = $capabilities->toObject();

		$this->assertFalse(property_exists($result, 'experimental'));
	}

	/**
	 * Test toObject with all capabilities enabled includes all properties.
	 */
	public function test_toObject_withAllCapabilities_includesAllProperties(): void
	{
		$capabilities = (new ClientCapabilities())
			->withSampling(true)
			->withRoots(true)
			->withElicitation(true)
			->withExperimental(['feature' => true]);

		$result = $capabilities->toObject();

		$this->assertTrue(property_exists($result, 'sampling'));
		$this->assertTrue(property_exists($result, 'roots'));
		$this->assertTrue(property_exists($result, 'elicitation'));
		$this->assertTrue(property_exists($result, 'experimental'));
	}

	/**
	 * Test toObject with disabled capabilities excludes their properties.
	 */
	public function test_toObject_withDisabledCapabilities_excludesProperties(): void
	{
		$capabilities = (new ClientCapabilities())
			->withSampling(false)
			->withRoots(false)
			->withElicitation(false);

		$result = $capabilities->toObject();

		$this->assertFalse(property_exists($result, 'sampling'));
		$this->assertFalse(property_exists($result, 'roots'));
		$this->assertFalse(property_exists($result, 'elicitation'));
	}
}
