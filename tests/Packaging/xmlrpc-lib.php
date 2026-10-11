<?php
/**
 * What the PHP parts of the tests of the packages share: the check lines, as the scenarios print them (the caller sets
 * $failed), and XML-RPC for the tests that call a web service of the helpers: one call and the answer read as PHP, the way
 * the callers (the search module of a simulator, the viewer) read it.
 */


function check(string $label, bool $ok, string $detail = ''): void
{
    global $failed;
    echo ($ok ? '   ok: ' : '   FAILED: ') . $label . ($ok || $detail === '' ? '' : " [$detail]") . "\n";
    $failed = $failed || !$ok;
}

/** A value of an XML-RPC answer, as PHP. */
function xr_value(DOMElement $value): mixed
{
    $type = null;
    foreach ($value->childNodes as $child) {
        if ($child instanceof DOMElement) {
            $type = $child;
            break;
        }
    }
    if ($type === null) {
        return $value->textContent;
    }
    switch ($type->nodeName) {
        case 'struct':
            $out = [];
            foreach ($type->childNodes as $member) {
                if (!$member instanceof DOMElement || $member->nodeName !== 'member') {
                    continue;
                }
                $name = '';
                $content = null;
                foreach ($member->childNodes as $part) {
                    if ($part instanceof DOMElement && $part->nodeName === 'name') {
                        $name = $part->textContent;
                    }
                    if ($part instanceof DOMElement && $part->nodeName === 'value') {
                        $content = xr_value($part);
                    }
                }
                $out[$name] = $content;
            }
            return $out;
        case 'array':
            $out = [];
            foreach ((new DOMXPath($type->ownerDocument))->query('data/value', $type) as $item) {
                $out[] = xr_value($item);
            }
            return $out;
        case 'int':
        case 'i4':
        case 'i8':
            return (int) $type->textContent;
        case 'double':
            return (float) $type->textContent;
        case 'boolean':
            return trim($type->textContent) === '1';
        case 'nil':
            return null;
        default:
            return $type->textContent;
    }
}

/**
 * One XML-RPC call: a struct of strings, as the search module makes it, or with their own types ($typed: integers and
 * booleans as such, as the viewer sends them).
 */
function rpc(string $url, string $method, array $request, bool $typed = false): array
{
    $members = '';
    foreach ($request as $key => $value) {
        $type = $typed && is_int($value) ? 'int' : ($typed && is_bool($value) ? 'boolean' : 'string');
        $text = is_bool($value) ? (string) (int) $value : htmlspecialchars((string) $value);
        $members .= "<member><name>" . htmlspecialchars((string) $key) . "</name><value><$type>$text</$type></value></member>";
    }
    $body =
        '<?xml version="1.0"?><methodCall><methodName>' .
        $method .
        '</methodName><params><param><value><struct>' .
        $members .
        '</struct></value></param></params></methodCall>';
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: text/xml\r\n",
            'content' => $body,
            'ignore_errors' => true,
            'timeout' => 60,
        ],
    ]);
    $raw = (string) @file_get_contents($url, false, $context);
    $status = 0;
    foreach ($http_response_header ?? [] as $line) {
        if (preg_match('#^HTTP/\S+ (\d+)#', $line, $found)) {
            $status = (int) $found[1];
        }
    }
    $value = null;
    $dom = new DOMDocument();
    if ($raw !== '' && @$dom->loadXML($raw)) {
        // The helpers answer as PHP encodes a value, <params> at the root, which the module reads
        $node = (new DOMXPath($dom))->query('/methodResponse/params/param/value | /params/param/value')->item(0);
        $value = $node instanceof DOMElement ? xr_value($node) : null;
    }

    return ['status' => $status, 'raw' => $raw, 'value' => is_array($value) ? $value : []];
}
