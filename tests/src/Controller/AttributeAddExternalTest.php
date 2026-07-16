<?php

declare(strict_types=1);

namespace SimpleSAML\Test\attributeaddexternal\Auth\Process;

use PHPUnit\Framework\TestCase;
use SimpleSAML\Error;
use SimpleSAML\Module\attributeaddexternal\Auth\Process\AttributeAddExternal;
use SimpleSAML\Utils\HTTP;

/**
 * Set of tests for "attributeaddexternal" module.
 *
 * These tests never hit the network: AttributeAddExternal::getHttp() is overridden
 * with a test double that fakes the responses a real external API would give.
 *
 * @package SimpleSAML\Test
 */
class AttributeAddExternalTest extends TestCase
{
    private string $url = 'https://fakerapi.it/api/v1/users?_quantity=1&_seed=1&_locale=es_ES';

    private string $wrongUrl = 'https://127.0.0.1:8080/wrong';


    /**
     * Build an HTTP double that fakes external responses instead of hitting the network.
     *
     * - the configured wrong URL simulates a connection failure.
     * - google.es simulates a non-JSON response.
     * - anything else (fakerapi.it URLs) returns a canned JSON payload with a
     *   'data.0.username' of 'zpineiro', matching what the real API used to return.
     */
    private function createHttpDouble(): HTTP
    {
        $http = $this->createStub(HTTP::class);
        $http->method('fetch')->willReturnCallback(function (string $url) {
            if (str_starts_with($url, $this->wrongUrl)) {
                throw new Error\Exception("Could not connect to '$url'");
            }
            if (str_contains($url, 'google.es')) {
                return '<html><body>not json</body></html>';
            }
            return json_encode([
                'status' => 'OK',
                'code' => 200,
                'total' => 1,
                'data' => [
                    ['id' => 1, 'username' => 'zpineiro'],
                ],
            ]);
        });
        return $http;
    }


    /**
     * Helper function to run the filter with a given configuration.
     *
     * @param array $config  The filter configuration.
     * @param array $request  The request state.
     * @return array  The state array after processing.
     */
    private function processFilter(array $config, array $request): array
    {
        $http = $this->createHttpDouble();
        $filter = new class ($config, null, $http) extends AttributeAddExternal {
            public function __construct(array &$config, mixed $reserved, private readonly HTTP $httpDouble)
            {
                parent::__construct($config, $reserved);
            }


            #[\Override]
            protected function getHttp(): HTTP
            {
                return $this->httpDouble;
            }
        };
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
        $this->processFilter($config, $state);
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
                'replace' => true,
            ],
        ];
        $initialState = [
            'Attributes' => [
                'test' => 'a',
            ],
        ];
        $result = $this->processFilter($config, $initialState);
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
            ],
        ];
        $initialState = [
            'Attributes' => [
                'test' => 'a',
            ],
        ];
        $result = $this->processFilter($config, $initialState);
        self::assertArrayHasKey("test", $result['Attributes']);
        self::assertCount(2, $result['Attributes']['test']);
        self::assertEquals("a", $result['Attributes']['test'][0]);
        self::assertEquals("zpineiro", $result['Attributes']['test'][1]);
    }


    /**
     * Test no replace but already exists attribute as array.
     */
    public function testNoReplaceArray(): void
    {
        $config = [
            'test' => [
                'url' => $this->url,
                'jsonpath' => 'data.0.username',
            ],
        ];
        $initialState = [
            'Attributes' => [
                'test' => ['a', 'b'],
            ],
        ];
        $result = $this->processFilter($config, $initialState);
        self::assertArrayHasKey("test", $result['Attributes']);
        self::assertCount(3, $result['Attributes']['test']);
        self::assertEquals("a", $result['Attributes']['test'][0]);
        self::assertEquals("b", $result['Attributes']['test'][1]);
        self::assertEquals("zpineiro", $result['Attributes']['test'][2]);
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
                'replace' => false,
            ],
        ];
        $initialState = [
            'Attributes' => [
                'test' => 'a',
            ],
        ];
        $result = $this->processFilter($config, $initialState);
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
            ],
        ];
        $initialState = [
            'Attributes' => [],
        ];
        $result = $this->processFilter($config, $initialState);
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
            ],
        ];
        $initialState = [
            'Attributes' => [],
        ];
        $this->expectException(Error\Exception::class);
        $this->expectExceptionMessage("AttributeAddExternal: failed to fetch '$this->wrongUrl'");
        $this->processFilter($config, $initialState);
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
            ],
        ];
        $initialState = [
            'Attributes' => [],
        ];
        $this->expectException(Error\Exception::class);
        $this->expectExceptionMessage("AttributeAddExternal: invalid path 'xx'");
        $this->processFilter($config, $initialState);
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
            ],
        ];
        $initialState = [
            'Attributes' => [],
        ];
        $this->expectException(Error\Exception::class);
        $msg = "AttributeAddExternal: failed to decode response from 'https://www.google.es'";
        $this->expectExceptionMessage($msg);
        $this->processFilter($config, $initialState);
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
                    '_seed' => 'userSeed',
                ],
            ],
        ];
        $initialState = [
            'Attributes' => [
                'userSeed' => '1',
            ],
        ];
        $result = $this->processFilter($config, $initialState);
        self::assertNotEquals($initialState, $result);
        self::assertArrayHasKey("test", $result['Attributes']);
        self::assertEquals("zpineiro", $result['Attributes']['test'][0]);
    }


    /**
     * Test obtain a new external attribute with parameters.
     */
    public function testExternalAttributeParameterAdd(): void
    {
        $config = [
            'test' => [
                'url' => 'https://fakerapi.it/api/v1/',
                'jsonpath' => 'data.0.username',
                'parameters' => [
                    '' => 'field',
                    '_seed' => 'userSeed',
                    '_quantity' => 'quantity',
                    '_locale' => 'locale',
                ],
            ],
        ];
        $initialState = [
            'Attributes' => [
                'userSeed' => '1',
                'quantity' => '1',
                'locale' => 'es_ES',
                'field' => ['users'],
            ],
        ];
        $result = $this->processFilter($config, $initialState);
        self::assertNotEquals($initialState, $result);
        self::assertArrayHasKey("test", $result['Attributes']);
        self::assertEquals("zpineiro", $result['Attributes']['test'][0]);
    }


    /**
     * Test obtain a new external attribute with parameters not in attributes.
     */
    public function testParameterNotInAttrubites(): void
    {
        $config = [
            'test' => [
                'url' => $this->url,
                'jsonpath' => 'data.0.username',
                'parameters' => [
                    '_seed' => 'userSeed',
                ],
            ],
        ];
        $initialState = [
            'Attributes' => [
                'user' => '1',
            ],
        ];
        $this->expectException(Error\Exception::class);
        $msg = "AttributeAddExternal: parameter not found in attributes 'userSeed'";
        $this->expectExceptionMessage($msg);
        $this->processFilter($config, $initialState);
    }


    /**
     * Test obtain a new external attribute with context.
     */
    public function testExternalAttributeContext(): void
    {
        $config = [
            'test' => [
                'url' => $this->url,
                'jsonpath' => 'data.0.username',
                'context' => ['http' => [
                    'method' => 'GET',
                    'header' => 'Authorization: Bearer yourApiKey',
                ]],
            ],
        ];
        $initialState = [
            'Attributes' => [],
        ];
        $result = $this->processFilter($config, $initialState);
        self::assertNotEquals($initialState, $result);
        self::assertArrayHasKey("test", $result['Attributes']);
        self::assertEquals("zpineiro", $result['Attributes']['test'][0]);
    }
}
