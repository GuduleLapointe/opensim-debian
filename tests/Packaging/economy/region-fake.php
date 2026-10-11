<?php
/**
 * A simulator with a money module, for the economy tests (tests/Packaging/economy-scenario.sh): the XML-RPC calls the helpers make to
 * the region of an avatar (GetBalance, UpdateBalance, AddBankerMoney, SendMoneyBalance), answered the way the module does.
 * The balance it holds is in /tmp/fakeregion/balance, the calls it received, one JSON per line, in /tmp/fakeregion/calls.log.
 *
 *   php -S 127.0.0.1:8099 region-fake.php
 */

require dirname(__DIR__) . '/xmlrpc-lib.php';

$dir = '/tmp/fakeregion';
@mkdir($dir);
$db = new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;dbname=' . getenv('DB'), 'opensim', 'testpass', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$dom = new DOMDocument();
if (!@$dom->loadXML((string) file_get_contents('php://input'))) {
    http_response_code(400);
    exit();
}
$xpath = new DOMXPath($dom);
$method = (string) $xpath->evaluate('string(/methodCall/methodName)');
$node = $xpath->query('/methodCall/params/param/value')->item(0);
$params = $node instanceof DOMElement ? xr_value($node) : [];
file_put_contents("$dir/calls.log", json_encode(['method' => $method] + (array) $params) . "\n", FILE_APPEND);

$answer = ['success' => true];
switch ($method) {
    case 'GetBalance':
        $statement = $db->prepare('SELECT balance FROM balances WHERE user = ?');
        $statement->execute([$params['clientUUID'] ?? '']);
        $answer['balance'] = (int) $statement->fetchColumn();
        break;
    case 'AddBankerMoney':
        $db->prepare('UPDATE balances SET balance = balance + ? WHERE user = ?')->execute([
            (int) ($params['amount'] ?? 0),
            $params['clientUUID'] ?? '',
        ]);
        break;
}

$members = '';
foreach ($answer as $name => $value) {
    $type = is_bool($value) ? 'boolean' : (is_int($value) ? 'int' : 'string');
    $members .=
        "<member><name>$name</name><value><$type>" .
        (is_bool($value) ? (int) $value : $value) .
        "</$type></value></member>";
}
header('Content-Type: text/xml');
echo '<?xml version="1.0"?><methodResponse><params><param><value><struct>' .
    $members .
    '</struct></value></param></params></methodResponse>';
