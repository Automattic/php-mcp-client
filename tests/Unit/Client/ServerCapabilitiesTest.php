<?php

declare(strict_types=1);

namespace GalatanOvidiu\PhpMcpClient\Tests\Unit\Client;

use GalatanOvidiu\PhpMcpClient\Client\ServerCapabilities;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ServerCapabilities value object.
 *
 * @covers \GalatanOvidiu\PhpMcpClient\Client\ServerCapabilities
 */
final class ServerCapabilitiesTest extends TestCase
{
	/**
	 * Test hasTools returns true when tools key is present.
	 */
	public function test_hasTools_withToolsKey_returnsTrue(): void
	{
		$capabilities = new ServerCapabilities(['tools' => []]);

		$this->assertTrue($capabilities->hasTools());
	}

	/**
	 * Test hasTools returns false when tools key is absent.
	 */
	public function test_hasTools_withoutToolsKey_returnsFalse(): void
	{
		$capabilities = new ServerCapabilities([]);

		$this->assertFalse($capabilities->hasTools());
	}

	/**
	 * Test hasResources returns true when resources key is present.
	 */
	public function test_hasResources_withResourcesKey_returnsTrue(): void
	{
		$capabilities = new ServerCapabilities(['resources' => []]);

		$this->assertTrue($capabilities->hasResources());
	}

	/**
	 * Test hasResources returns false when resources key is absent.
	 */
	public function test_hasResources_withoutResourcesKey_returnsFalse(): void
	{
		$capabilities = new ServerCapabilities([]);

		$this->assertFalse($capabilities->hasResources());
	}

	/**
	 * Test hasPrompts returns true when prompts key is present.
	 */
	public function test_hasPrompts_withPromptsKey_returnsTrue(): void
	{
		$capabilities = new ServerCapabilities(['prompts' => []]);

		$this->assertTrue($capabilities->hasPrompts());
	}

	/**
	 * Test hasPrompts returns false when prompts key is absent.
	 */
	public function test_hasPrompts_withoutPromptsKey_returnsFalse(): void
	{
		$capabilities = new ServerCapabilities([]);

		$this->assertFalse($capabilities->hasPrompts());
	}

	/**
	 * Test hasLogging returns true when logging key is present.
	 */
	public function test_hasLogging_withLoggingKey_returnsTrue(): void
	{
		$capabilities = new ServerCapabilities(['logging' => []]);

		$this->assertTrue($capabilities->hasLogging());
	}

	/**
	 * Test hasLogging returns false when logging key is absent.
	 */
	public function test_hasLogging_withoutLoggingKey_returnsFalse(): void
	{
		$capabilities = new ServerCapabilities([]);

		$this->assertFalse($capabilities->hasLogging());
	}

	/**
	 * Test hasCompletions returns true when completions key is present.
	 */
	public function test_hasCompletions_withCompletionsCapability_returnsTrue(): void
	{
		$capabilities = new ServerCapabilities(['completions' => []]);

		$this->assertTrue($capabilities->hasCompletions());
	}

	/**
	 * Test hasCompletions returns false when completions key is absent.
	 */
	public function test_hasCompletions_withoutCompletionsCapability_returnsFalse(): void
	{
		$capabilities = new ServerCapabilities([]);

		$this->assertFalse($capabilities->hasCompletions());
	}

	/**
	 * Test toolsListChanged returns true when listChanged is true.
	 */
	public function test_toolsListChanged_withListChangedTrue_returnsTrue(): void
	{
		$capabilities = new ServerCapabilities([
			'tools' => ['listChanged' => true],
		]);

		$this->assertTrue($capabilities->toolsListChanged());
	}

	/**
	 * Test toolsListChanged returns false when listChanged is false.
	 */
	public function test_toolsListChanged_withListChangedFalse_returnsFalse(): void
	{
		$capabilities = new ServerCapabilities([
			'tools' => ['listChanged' => false],
		]);

		$this->assertFalse($capabilities->toolsListChanged());
	}

	/**
	 * Test toolsListChanged returns false when tools key is missing.
	 */
	public function test_toolsListChanged_withoutToolsKey_returnsFalse(): void
	{
		$capabilities = new ServerCapabilities([]);

		$this->assertFalse($capabilities->toolsListChanged());
	}

	/**
	 * Test toolsListChanged returns false when tools is not an array.
	 */
	public function test_toolsListChanged_withNonArrayTools_returnsFalse(): void
	{
		$capabilities = new ServerCapabilities(['tools' => 'string']);

		$this->assertFalse($capabilities->toolsListChanged());
	}

	/**
	 * Test toolsListChanged returns false when listChanged key is missing.
	 */
	public function test_toolsListChanged_withoutListChangedKey_returnsFalse(): void
	{
		$capabilities = new ServerCapabilities(['tools' => []]);

		$this->assertFalse($capabilities->toolsListChanged());
	}

	/**
	 * Test resourcesListChanged returns true when listChanged is true.
	 */
	public function test_resourcesListChanged_withListChangedTrue_returnsTrue(): void
	{
		$capabilities = new ServerCapabilities([
			'resources' => ['listChanged' => true],
		]);

		$this->assertTrue($capabilities->resourcesListChanged());
	}

	/**
	 * Test resourcesListChanged returns false when listChanged is false.
	 */
	public function test_resourcesListChanged_withListChangedFalse_returnsFalse(): void
	{
		$capabilities = new ServerCapabilities([
			'resources' => ['listChanged' => false],
		]);

		$this->assertFalse($capabilities->resourcesListChanged());
	}

	/**
	 * Test resourcesListChanged returns false when resources key is missing.
	 */
	public function test_resourcesListChanged_withoutResourcesKey_returnsFalse(): void
	{
		$capabilities = new ServerCapabilities([]);

		$this->assertFalse($capabilities->resourcesListChanged());
	}

	/**
	 * Test resourcesListChanged returns false when resources is not an array.
	 */
	public function test_resourcesListChanged_withNonArrayResources_returnsFalse(): void
	{
		$capabilities = new ServerCapabilities(['resources' => 'string']);

		$this->assertFalse($capabilities->resourcesListChanged());
	}

	/**
	 * Test resourcesSubscribe returns true when subscribe is true.
	 */
	public function test_resourcesSubscribe_withSubscribeTrue_returnsTrue(): void
	{
		$capabilities = new ServerCapabilities([
			'resources' => ['subscribe' => true],
		]);

		$this->assertTrue($capabilities->resourcesSubscribe());
	}

	/**
	 * Test resourcesSubscribe returns false when subscribe is false.
	 */
	public function test_resourcesSubscribe_withSubscribeFalse_returnsFalse(): void
	{
		$capabilities = new ServerCapabilities([
			'resources' => ['subscribe' => false],
		]);

		$this->assertFalse($capabilities->resourcesSubscribe());
	}

	/**
	 * Test resourcesSubscribe returns false when resources key is missing.
	 */
	public function test_resourcesSubscribe_withoutResourcesKey_returnsFalse(): void
	{
		$capabilities = new ServerCapabilities([]);

		$this->assertFalse($capabilities->resourcesSubscribe());
	}

	/**
	 * Test resourcesSubscribe returns false when resources is not an array.
	 */
	public function test_resourcesSubscribe_withNonArrayResources_returnsFalse(): void
	{
		$capabilities = new ServerCapabilities(['resources' => 'string']);

		$this->assertFalse($capabilities->resourcesSubscribe());
	}

	/**
	 * Test promptsListChanged returns true when listChanged is true.
	 */
	public function test_promptsListChanged_withListChangedTrue_returnsTrue(): void
	{
		$capabilities = new ServerCapabilities([
			'prompts' => ['listChanged' => true],
		]);

		$this->assertTrue($capabilities->promptsListChanged());
	}

	/**
	 * Test promptsListChanged returns false when listChanged is false.
	 */
	public function test_promptsListChanged_withListChangedFalse_returnsFalse(): void
	{
		$capabilities = new ServerCapabilities([
			'prompts' => ['listChanged' => false],
		]);

		$this->assertFalse($capabilities->promptsListChanged());
	}

	/**
	 * Test promptsListChanged returns false when prompts key is missing.
	 */
	public function test_promptsListChanged_withoutPromptsKey_returnsFalse(): void
	{
		$capabilities = new ServerCapabilities([]);

		$this->assertFalse($capabilities->promptsListChanged());
	}

	/**
	 * Test promptsListChanged returns false when prompts is not an array.
	 */
	public function test_promptsListChanged_withNonArrayPrompts_returnsFalse(): void
	{
		$capabilities = new ServerCapabilities(['prompts' => 'string']);

		$this->assertFalse($capabilities->promptsListChanged());
	}

	/**
	 * Test getExperimental returns experimental array when present.
	 */
	public function test_getExperimental_withExperimentalData_returnsArray(): void
	{
		$experimental = ['feature_x' => true, 'feature_y' => 'beta'];
		$capabilities = new ServerCapabilities(['experimental' => $experimental]);

		$this->assertSame($experimental, $capabilities->getExperimental());
	}

	/**
	 * Test getExperimental returns empty array when key is missing.
	 */
	public function test_getExperimental_withoutExperimentalKey_returnsEmptyArray(): void
	{
		$capabilities = new ServerCapabilities([]);

		$this->assertSame([], $capabilities->getExperimental());
	}

	/**
	 * Test getExperimental returns empty array when value is not an array.
	 */
	public function test_getExperimental_withNonArrayValue_returnsEmptyArray(): void
	{
		$capabilities = new ServerCapabilities(['experimental' => 'invalid']);

		$this->assertSame([], $capabilities->getExperimental());
	}

	/**
	 * Test getRaw returns the original capabilities array.
	 */
	public function test_getRaw_withCapabilities_returnsOriginalArray(): void
	{
		$raw = [
			'tools'     => ['listChanged' => true],
			'resources' => ['subscribe' => true],
			'prompts'   => [],
			'logging'   => [],
		];
		$capabilities = new ServerCapabilities($raw);

		$this->assertSame($raw, $capabilities->getRaw());
	}

	/**
	 * Test getRaw with empty capabilities returns empty array.
	 */
	public function test_getRaw_withEmptyCapabilities_returnsEmptyArray(): void
	{
		$capabilities = new ServerCapabilities([]);

		$this->assertSame([], $capabilities->getRaw());
	}
}
