<?php
/**
 * What the offline messages tests read in an XML document: php xml-probe.php FILE|- XPATH prints the text of each node the path
 * finds, one per line, count when the path is count(...), nothing when the document is not XML. Not the xmlns, which the
 * documents of OpenSimulator have.
 */

declare(strict_types=1);

[, $file, $path] = $argv + [1 => '-', 2 => '/*'];
$xml = $file === '-' ? stream_get_contents(STDIN) : file_get_contents($file);
$xml = preg_replace('/\sxmlns(:\w+)?="[^"]*"/', '', (string) $xml);
$xml = preg_replace('/^.*?(<\?xml)/s', '$1', (string) $xml);
$document = new DOMDocument();
if (!@$document->loadXML($xml)) {
    exit(3);
}
$result = (new DOMXPath($document))->evaluate($path);
if (is_scalar($result)) {
    echo $result, "\n";
} else {
    foreach ($result as $node) {
        echo $node->textContent, "\n";
    }
}
