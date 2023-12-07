<?php

declare(strict_types=1);

namespace SimpleSAML\Module\attributeaddexternal\Auth\Process;

use SimpleSAML\Assert\Assert;
use SimpleSAML\Auth;
use SimpleSAML\Error;
use SimpleSAML\Assert\AssertionFailedException;
use SimpleSAML\Utils\HTTP;

use function array_key_exists;
use function array_merge;
use function is_array;
use function is_null;
use function var_export;

/**
 * Filter to add attributes.
 *
 * This filter allows you to add attributes to the attribute set being processed.
 *
 * @package SimpleSAMLphp
 */
class AttributeAddExternal extends Auth\ProcessingFilter
{
    /**
     * Attributes which should be added/appended.
     *
     * Associative array of arrays.
     * @var array
     */
    private array $attributesToAdd = [];

    /**
     * Parse external origin for an attribute
     * @param array origin external origin for an attribute
     * @return string whit url of origin
     */
    private function parseOrigin(array $origin): string
    {
        $url = "";
        foreach ($origin as $name => $value) {
            switch ($name) {
                case 'url':
                    Assert::stringNotEmpty($value);
                    Assert::validURL($value);
                    $url = $value;
                    break;
                case 'replace':
                    Assert::boolean($value, 'replace should be boolean');
                    break;
                case 'jsonpath':
                    Assert::stringNotEmpty($value);
                    break;
                default:
                    throw new AssertionFailedException('Unknown flag in origin: ' . var_export($name, true));
                    break;
            }
        }
        return $url;
    }

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
                throw new AssertionFailedException('external origin should be an array');
            }
            $this->parseOrigin($origin);
            $this->attributesToAdd[$attr] = $origin;
        }
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
            $url = $this->parseOrigin($origin);
            $path = $origin["jsonpath"];
            $response = $this->fetchInformation($url, $path);
            $replace = !empty($origin["replace"]);
            if ($replace === true || !array_key_exists($name, $attributes)) {
                $attributes[$name] = $response;
            } else {
                $attributes[$name] = array_merge([$attributes[$name]], [$response]);
            }
        }
    }

    /**
     * Fetch information from an external URL and return data in jsonPath.
     * @param string url url to obtain information from
     * @param string jsonPath reponse path to obtain data from
     * @return string in jsonPath from response
     */
    public function fetchInformation(string $url, string $jsonPath): string
    {
        $http = new HTTP();
        // no getHeaderss
        try {
            $response = $http->fetch($url);
        } catch (Error\Exception $ex) {
            throw new Error\Exception('AttributeAddExternal: failed to fetch ' . var_export($url, true));
        }
        settype($response, "string");
        $responseArray = json_decode($response, true);
        if (is_null($responseArray)) {
            throw new Error\Exception('AttributeAddExternal: failed to decode response from ' . var_export($url, true));
        }
        $flattened = $this->flatten($responseArray);
        if (!array_key_exists($jsonPath, $flattened)) {
            throw new Error\Exception('AttributeAddExternal: invalid path ' . var_export($jsonPath, true));
        }
        return $flattened[$jsonPath];
    }

    /**
     * Obtain an unidimensional array with all data, indexed by path.
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
