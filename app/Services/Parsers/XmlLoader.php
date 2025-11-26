<?php

namespace App\Services\Parsers;

use RuntimeException;
use SimpleXMLElement;

/**
 * XmlLoader - Unified XML Loading Utility
 *
 * Provides consistent XML loading and error handling across all parsers.
 * Replaces the 8 different XML loading approaches that existed in the codebase.
 *
 * Features:
 * - Consistent error handling using libxml_use_internal_errors()
 * - Clear error messages with XML parsing details
 * - Support for both string and file loading
 * - Automatic file existence validation
 *
 * Used by: All XML parsers in the system
 */
class XmlLoader
{
    /**
     * Load XML from a string with consistent error handling.
     *
     * @param  string  $xml  The XML content as a string
     * @return SimpleXMLElement The parsed XML element
     *
     * @throws RuntimeException When XML parsing fails
     */
    public static function fromString(string $xml): SimpleXMLElement
    {
        // Enable internal error handling to capture XML parsing errors
        libxml_use_internal_errors(true);

        $element = simplexml_load_string($xml);

        if ($element === false) {
            $errors = libxml_get_errors();
            $message = ! empty($errors)
                ? $errors[0]->message
                : 'Unknown XML parsing error';
            libxml_clear_errors();

            throw new RuntimeException('Failed to parse XML: '.trim($message));
        }

        libxml_clear_errors();

        return $element;
    }

    /**
     * Load XML from a file path with consistent error handling.
     *
     * @param  string  $filePath  The path to the XML file
     * @return SimpleXMLElement The parsed XML element
     *
     * @throws RuntimeException When file doesn't exist or XML parsing fails
     */
    public static function fromFile(string $filePath): SimpleXMLElement
    {
        if (! file_exists($filePath)) {
            throw new RuntimeException("XML file not found: {$filePath}");
        }

        $content = file_get_contents($filePath);

        if ($content === false) {
            throw new RuntimeException("Failed to read XML file: {$filePath}");
        }

        return self::fromString($content);
    }

    /**
     * Safely attempt to load XML from string, returning null on failure.
     *
     * Use this when you want to handle errors gracefully without exceptions.
     *
     * @param  string  $xml  The XML content as a string
     * @return SimpleXMLElement|null The parsed XML element or null on failure
     */
    public static function tryFromString(string $xml): ?SimpleXMLElement
    {
        try {
            return self::fromString($xml);
        } catch (RuntimeException) {
            return null;
        }
    }

    /**
     * Safely attempt to load XML from file, returning null on failure.
     *
     * Use this when you want to handle errors gracefully without exceptions.
     *
     * @param  string  $filePath  The path to the XML file
     * @return SimpleXMLElement|null The parsed XML element or null on failure
     */
    public static function tryFromFile(string $filePath): ?SimpleXMLElement
    {
        try {
            return self::fromFile($filePath);
        } catch (RuntimeException) {
            return null;
        }
    }
}
