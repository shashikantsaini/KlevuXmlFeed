<?php

namespace Bluethink\KlevuXmlFeed\Model;

use \DOMDocument;

/**
 *  Class DOMDocumentFactory
 */
class DOMDocumentFactory
{
    /**
     * DOMDocumentFactory Constructor
     *
     * @param string $version
     * @param string $encoding
     * @return \DOMDocument
     */
    public function create(string $version, string $encoding): DOMDocument
    {
        return new DOMDocument($version, $encoding);
    }
}
