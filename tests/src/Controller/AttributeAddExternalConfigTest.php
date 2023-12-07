<?php

declare(strict_types=1);

namespace SimpleSAML\Test\attributeaddexternal\Auth\Process;

use PHPUnit\Framework\TestCase;
use SimpleSAML\Assert\AssertionFailedException;
use SimpleSAML\Module\attributeaddexternal\Auth\Process\AttributeAddExternal;

/**
 * Set of tests for config "attributeaddexternal" module.
 *
 * @package SimpleSAML\Test
 */
class AttributeAddExternalConfigTest extends TestCase
{
    /**
     * Helper function to run the filter with a given configuration.
     *
     * @param array $config  The filter configuration.
     * @param array $request  The request state.
     * @return array  The state array after processing.
     */
    private static function processFilter(array $config, array $request): array
    {
        $filter = new AttributeAddExternal($config, null);
        $filter->process($request);
        return $request;
    }

    /**
     * Test invalid url.
     */
    public function testConfigInvalidUrl(): void
    {
        $config = [
            'test' => [
                'url' => 'xxx',
                'jsonpath' => 'data.0.username',
            ]
        ];
        $this->expectException(AssertionFailedException::class);
        $this->expectExceptionMessage("'xxx' is not a valid RFC2396 compliant URL");
        self::processFilter($config, []);
    }

    /**
     * Test invalid origin value.
     */
    public function testConfigInvalidOriginValue(): void
    {
        $config = [
            'test' => [
                'url' => 'https://google.es/',
                'jsonpath' => 'data.0.username',
                'test' => 'xx'
            ]
        ];
        $this->expectException(AssertionFailedException::class);
        $this->expectExceptionMessage("Unknown origin param: 'test'");
        self::processFilter($config, []);
    }

    /**
     * Test invalid origin.
     */
    public function testConfigInvalidOrigin(): void
    {
        $config = [
            'test' => 'x'
        ];
        $this->expectException(AssertionFailedException::class);
        $this->expectExceptionMessage("external origin should be an array");
        self::processFilter($config, []);
    }

    /**
     * Test invalid replace.
     */
    public function testConfigInvalidReplace(): void
    {
        $config = [
            'test' => [
                'replace' => 'x'
            ]
        ];
        $this->expectException(AssertionFailedException::class);
        $this->expectExceptionMessage("replace should be boolean");
        self::processFilter($config, []);
    }

    /**
     * Test invalid parameters.
     */
    public function testConfigInvalidParameters(): void
    {
        $config = [
            'test' => [
                'parameters' => 'x'
            ]
        ];
        $this->expectException(AssertionFailedException::class);
        $this->expectExceptionMessage("parameters should be an associative array");
        self::processFilter($config, []);
    }
}
