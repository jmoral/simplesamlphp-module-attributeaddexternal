<?php

declare(strict_types=1);

namespace SimpleSAML\Module\attributeaddexternal\Auth\Process;

use SimpleSAML\Assert\Assert;
use SimpleSAML\Auth;
use SimpleSAML\Error;
use SimpleSAML\Assert\AssertionFailedException;
use SimpleSAML\Utils\HTTP;
use SimpleSAML\Logger;

use function array_key_exists;
use function array_merge;
use function is_array;
use function is_null;
use function var_export;

/**
 * Filter to add attributes.
 *
 * This filter allows you to add attributes to the attribute set being processed.
 * @psalm-api
 * @package SimpleSAMLphp
 */
class AttributeAddExternal extends Auth\ProcessingFilter
{
    /**
     * Attributes which should be added/appended.
     *
     * Associative array of arrays.
     * @var array<string, mixed>
     */
    private array $attributesToAdd = [];

    /**
     * Initialize this filter.
     *
     * @param array &$config  Configuration information about this filter.
     * @param mixed $reserved  For future use.
     */
    public function __construct(array &$config, $reserved)
    {
        parent::__construct($config, $reserved);

        Assert::isArray($config, "config should be an array");
        foreach ($config as $attr => $origin) {
            if (!is_array($origin)) {
                $msg = 'external origin should be an array';
                throw new AssertionFailedException($msg);
            }
            $this->parseOrigin($origin);
            $this->attributesToAdd[$attr] = $origin;
        }
    }

    /**
     * Parse external origin for an attribute
     * @param array<string, mixed> $origin external origin for an attribute
     */
    private function parseOrigin(array $origin): void
    {
        foreach ($origin as $name => $value) {
            $this->parseOriginParam($name, $value);
        }
    }

    /**
     * Parse external origin parameter.
     * @param string name of origin parameter
     * @param mixed value of origin parameter
     */
    private function parseOriginParam(string $name, mixed $value): void
    {
        switch ($name) {
            case 'url':
                Assert::stringNotEmpty($value);
                Assert::validURL($value);
                break;
            case 'replace':
                Assert::boolean($value, 'replace should be boolean');
                break;
            case 'jsonpath':
                Assert::stringNotEmpty($value);
                break;
            case 'parameters':
                $msg = 'parameters should be an associative array';
                Assert::nullOrIsArray($value, $msg);
                break;
            default:
                $msg = 'Unknown origin param: ' . var_export($name, true);
                throw new AssertionFailedException($msg);
        }
    }

    /**
     * get an associative array with parameteres name and value from attributes.
     * @param array<string, string> $parametersTemplate parameter pointing to atribute
     * @param array $attributes attributes from user
     * @return array associative array with paramaters an actual values
     */
    private function getParameters(array $parametersTemplate, array $attributes): array
    {
        $parameters = [];
        foreach ($parametersTemplate as $name => $template) {
            if (array_key_exists($template, $attributes)) {
                $parameters[$name] = $attributes[$template];
            } else {
                $msg = 'AttributeAddExternal: parameter not found in attributes ' . var_export($template, true);
                throw new Error\Exception($msg);
            }
        }
        return $parameters;
    }

    /**
     * Apply filter to add or replace attributes.
     *
     * Add or replace existing attributes with the configured values.
     *
     * @param array &$state  The current request
     */
    public function process(array &$state): void
    {
        Assert::keyExists($state, 'Attributes');
        $attributes = &$state['Attributes'];
        foreach ($this->attributesToAdd as $name => $origin) {
            $url = $origin["url"];
            $path = $origin["jsonpath"];
            if (array_key_exists("parameters", $origin)) {
                $http = new HTTP();
                $parameters = $this->getParameters($origin['parameters'], $attributes);
                $url = $http->addURLParameters($url, $parameters);
            }
            Logger::debug('AttributeAddExternal: obtaining attribute from ' . $url . ' jsonpath ' . $path);
            $response = $this->fetchInformation($url, $path);
            $replace = !empty($origin["replace"]);
            if ($replace === true || !array_key_exists($name, $attributes)) {
                $attributes[$name] = [$response];
            } else {
                $attributes[$name] = array_merge([$attributes[$name]], [$response]);
            }
        }
    }

    /**
     * Fetch information from an external URL and return data in jsonPath.
     * @param string url url to obtain information from
     * @param string jsonPath reponse path to obtain data from
     * @throws Error\Exception If the information from url or jsonPath cannot be retrieved.
     * @return string in jsonPath from response
     */
    public function fetchInformation(string $url, string $jsonPath): string
    {
        $http = new HTTP();
        // no getHeaderss
        try {
            $response = $http->fetch($url);
        } catch (Error\Exception | \InvalidArgumentException $ex) {
            $msg = 'AttributeAddExternal: failed to fetch ' . var_export($url, true);
            throw new Error\Exception($msg);
        }
        settype($response, "string");
        $responseArray = json_decode($response, true);
        if (is_null($responseArray)) {
            $msg = 'AttributeAddExternal: failed to decode response from ' . var_export($url, true);
            throw new Error\Exception($msg);
        }
        $flattened = $this->flatten($responseArray);
        if (!array_key_exists($jsonPath, $flattened)) {
            $msg = 'AttributeAddExternal: invalid path ' . var_export($jsonPath, true);
            throw new Error\Exception($msg);
        }
        Logger::debug('AttributeAddExternal: response from ' . $url . ' is ' . $flattened[$jsonPath]);
        return $flattened[$jsonPath];
    }

    /**
     * Obtain an unidimensional array with all data, indexed by path.
     * @param array<string, string|array> $array
     */
    private function flatten(array $array, string $prefix = ''): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result = $result + $this->flatten($value, $prefix . $key . '.');
            } else {
                $result[$prefix . $key] = $value;
            }
        }
        return $result;
    }
}
