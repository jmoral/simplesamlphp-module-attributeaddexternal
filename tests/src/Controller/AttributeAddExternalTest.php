<?php

declare(strict_types=1);

namespace SimpleSAML\Test\attributeaddexternal\Auth\Process;

use PHPUnit\Framework\TestCase;
use SimpleSAML\Error;
use SimpleSAML\Module\attributeaddexternal\Auth\Process\AttributeAddExternal;

/**
 * Set of tests for "attributeaddexternal" module.
 *
 * @package SimpleSAML\Test
 */
class AttributeAddExternalTest extends TestCase
{
    private string $url = 'https://fakerapi.it/api/v1/users?_quantity=1&_seed=1&_locale=es_ES';
    private string $wrongUrl = 'https://127.0.0.1:8080/wrong';

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
     * Test no new external attribute is added.
     */
    public function testNoExternalAttribute(): void
    {
        $config = [];
        $initialState = [
            'Attributes' => [],
        ];
        $state = $initialState;
        self::processFilter($config, $state);
        self::assertEquals($initialState, $state);
    }

    /**
     * Test .
     */
    public function testReplace(): void
    {
        $config = [
            'test' => [
                'url' => $this->url,
                'jsonpath' => 'data.0.username',
                'replace' => true
            ]
        ];
        $initialState = [
            'Attributes' => [
                'test' => 'a'
            ],
        ];
        $result = self::processFilter($config, $initialState);
        self::assertArrayHasKey("test", $result['Attributes']);
        self::assertEquals("zpineiro", $result['Attributes']['test'][0]);
    }

    /**
     * Test no replace but already exists attribute.
     */
    public function testNoReplace(): void
    {
        $config = [
            'test' => [
                'url' => $this->url,
                'jsonpath' => 'data.0.username',
            ]
        ];
        $initialState = [
            'Attributes' => [
                'test' => 'a'
            ],
        ];
        $result = self::processFilter($config, $initialState);
        self::assertArrayHasKey("test", $result['Attributes']);
        self::assertCount(2, $result['Attributes']['test']);
        self::assertEquals("a", $result['Attributes']['test'][0]);
        self::assertEquals("zpineiro", $result['Attributes']['test'][1]);
    }

    /**
     * Test replace false but already exists attribute.
     */
    public function testReplaceFalse(): void
    {
        $config = [
            'test' => [
                'url' => $this->url,
                'jsonpath' => 'data.0.username',
                'replace' => false
            ]
        ];
        $initialState = [
            'Attributes' => [
                'test' => 'a'
            ],
        ];
        $result = self::processFilter($config, $initialState);
        self::assertArrayHasKey("test", $result['Attributes']);
        self::assertCount(2, $result['Attributes']['test']);
        self::assertEquals("a", $result['Attributes']['test'][0]);
        self::assertEquals("zpineiro", $result['Attributes']['test'][1]);
    }

    /**
     * Test obtain a new external attribute.
     */
    public function testExternalAttribute(): void
    {
        $config = [
            'test' => [
                'url' => $this->url,
                'jsonpath' => 'data.0.username',
            ]
        ];
        $initialState = [
            'Attributes' => [],
        ];
        $result = self::processFilter($config, $initialState);
        self::assertNotEquals($initialState, $result);
        self::assertArrayHasKey("test", $result['Attributes']);
        self::assertEquals("zpineiro", $result['Attributes']['test'][0]);
    }

    /**
     * Test obtain a new external attribute not responding url.
     */
    public function testUrlNotResponding(): void
    {
        $config = [
            'test' => [
                'url' => $this->wrongUrl,
                'jsonpath' => 'data.0.username',
            ]
        ];
        $initialState = [
            'Attributes' => [],
        ];
        $this->expectException(Error\Exception::class);
        $this->expectExceptionMessage("AttributeAddExternal: failed to fetch '$this->wrongUrl'");
        self::processFilter($config, $initialState);
    }

    /**
     * Test obtain a new external attribute invalid jsonpath.
     */
    public function testinvalidJsonPath(): void
    {
        $config = [
            'test' => [
                'url' => $this->url,
                'jsonpath' => 'xx',
            ]
        ];
        $initialState = [
            'Attributes' => [],
        ];
        $this->expectException(Error\Exception::class);
        $this->expectExceptionMessage("AttributeAddExternal: invalid path 'xx'");
        self::processFilter($config, $initialState);
        self::fail();
    }

    /**
     * Test obtain a new external attribute invalid json response.
     */
    public function testinvalidJson(): void
    {
        $config = [
            'test' => [
                'url' => 'https://www.google.es',
                'jsonpath' => 'xx',
            ]
        ];
        $initialState = [
            'Attributes' => [],
        ];
        $this->expectException(Error\Exception::class);
        $msg = "AttributeAddExternal: failed to decode response from 'https://www.google.es'";
        $this->expectExceptionMessage($msg);
        self::processFilter($config, $initialState);
    }

    /**
     * Test obtain a new external attribute with parameters.
     */
    public function testExternalAttributeParameter(): void
    {
        $config = [
            'test' => [
                'url' => 'https://fakerapi.it/api/v1/users?_quantity=1&_locale=es_ES',
                'jsonpath' => 'data.0.username',
                'parameters' => [
                    '_seed' => 'userSeed'
                ]
            ]
        ];
        $initialState = [
            'Attributes' => [
                'userSeed' => '1'
            ],
        ];
        $result = self::processFilter($config, $initialState);
        self::assertNotEquals($initialState, $result);
        self::assertArrayHasKey("test", $result['Attributes']);
        self::assertEquals("zpineiro", $result['Attributes']['test'][0]);
    }

    /**
     * Test obtain a new external attribute with parameters not un attributes.
     */
    public function testParameterNotInAttrubites(): void
    {
        $config = [
            'test' => [
                'url' => $this->url,
                'jsonpath' => 'data.0.username',
                'parameters' => [
                    '_seed' => 'userSeed'
                ]
            ]
        ];
        $initialState = [
            'Attributes' => [
                'user' => '1'
            ],
        ];
        $this->expectException(Error\Exception::class);
        $msg = "AttributeAddExternal: parameter not found in attributes 'userSeed'";
        $this->expectExceptionMessage($msg);
        self::processFilter($config, $initialState);
    }
}
