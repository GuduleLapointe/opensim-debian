<?php
/**
 * The calls of the economy tests of the packages (tests/Packaging/economy-scenario.sh), as the viewer and the simulators make them to
 * currency.php and landtool.php of the helpers: the same XML-RPC calls, with the keys they send, and the answers read the way
 * they read them. What the helpers wrote in the database is checked in the same run.
 *
 *   php economy-check.php GROUP URL      GROUP: plain, moneyserver, gloebit, podex
 *
 * URL is the path of the helpers on the web site. The avatars, the sessions and the regions are in the environment
 * (economy-scenario.sh sets them), the grid is the one of the database it gives. It prints one line per check, as the scenarios do,
 * and exits with 1 when one failed.
 */

declare(strict_types=1);

[, $group, $helpers] = $argv + [1 => '', 2 => ''];
require dirname(__DIR__) . '/xmlrpc-lib.php';
$failed = false;

$env = fn(string $name): string => (string) getenv($name);
[$agent, $friend, $secure, $friend_secure] = [$env('AGENT'), $env('FRIEND'), $env('SECURE'), $env('FRIEND_SECURE')];
[$region, $secret, $region2, $secret2] = [$env('REGION'), $env('SECRET'), $env('REGION2'), $env('SECRET2')];
$helper_url = $env('HELPER_URL');
$key = $env('SCRIPT_KEY') ?: '1234567883789';
$confirm = md5($key . '_127.0.0.1');
$none = '00000000-0000-0000-0000-000000000000';

$db = new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;dbname=' . $env('DB'), 'opensim', 'testpass', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
function sql(string $query, array $values = []): array
{
    global $db;
    $statement = $db->prepare($query);
    $statement->execute($values);

    return $statement->columnCount() ? $statement->fetchAll(PDO::FETCH_NUM) : [];
}
function cell(string $query, array $values = []): string
{
    return (string) (sql($query, $values)[0][0] ?? '');
}

/** One call to a script of the helpers, with the types of the viewer (integers are integers). */
function call(string $script, string $method, array $values): array
{
    global $helpers;

    return rpc("$helpers/$script", $method, $values, true);
}
function succeeded(array $answer): bool
{
    return $answer['status'] === 200 && ($answer['value']['success'] ?? false) === true;
}
/** What the viewer shows when it fails: the message, and where it sends the user. */
function refused(array $answer, string $message): bool
{
    $value = $answer['value'];

    return $answer['status'] === 200 && ($value['success'] ?? true) === false && stripos((string) ($value['errorMessage'] ?? ''), $message) !== false;
}
function region_log(): array
{
    $lines = array_filter(explode("\n", (string) @file_get_contents('/tmp/fakeregion/calls.log')));

    return array_map(fn($line) => json_decode($line, true), array_values($lines));
}
function region_calls(string $method): array
{
    return array_values(array_filter(region_log(), fn($call) => $call['method'] === $method));
}
function funds(string $who, string $session): int
{
    return (int) (call('currency.php', 'simulatorUserBalanceRequest', ['agentId' => $who, 'secureSessionId' => $session])['value']['funds'] ?? -1);
}

$quote = fn(string $session, int $amount) => call('currency.php', 'getCurrencyQuote', ['agentId' => $GLOBALS['agent'], 'secureSessionId' => $session, 'currencyBuy' => $amount]);
$buy = fn(string $session, int $amount, string $with) => call('currency.php', 'buyCurrency', ['agentId' => $GLOBALS['agent'], 'secureSessionId' => $session, 'currencyBuy' => $amount, 'confirm' => $with]);

switch ($group) {
    // The economy of the helpers alone: the balances are the transactions of the database, nothing asks a money server
    case 'plain':
        $answer = $quote($secure, 500);
        check('quote: the viewer is told the cost, in cents, of the amount it asks, and the confirm value to send back', succeeded($answer) && ($answer['value']['currency']['estimatedCost'] ?? null) === 500 && ($answer['value']['currency']['currencyBuy'] ?? null) === 500 && ($answer['value']['confirm'] ?? '') === $confirm, substr($answer['raw'], 0, 300));
        check('quote: the cost follows the rate of the grid', ($quote($secure, 1000)['value']['currency']['estimatedCost'] ?? null) === 1000);
        $answer = $quote('99999999-9999-4999-8999-999999999999', 500);
        check('quote: a wrong secure session is refused, with the page that tells why', refused($answer, 'Unable to Authenticate') && ($answer['value']['errorURI'] ?? '') === $helper_url, substr($answer['raw'], 0, 300));
        check('quote: so is something that is not a session', refused($quote('nothing', 500), 'Unable to Authenticate'));
        check('quote: the session of another avatar does not do', refused(call('currency.php', 'getCurrencyQuote', ['agentId' => $friend, 'secureSessionId' => $secure, 'currencyBuy' => 500]), 'Unable to Authenticate'));

        check('balance: an avatar who owns nothing has none', funds($agent, $secure) === 0);
        $answer = call('currency.php', 'simulatorUserBalanceRequest', ['agentId' => $agent, 'secureSessionId' => $secure]);
        check('balance: answered with the avatar and its funds', succeeded($answer) && ($answer['value']['agentId'] ?? '') === $agent && ($answer['value']['funds'] ?? null) === 0, substr($answer['raw'], 0, 300));
        check('balance: not given to who has not the secure session of the avatar', refused(call('currency.php', 'simulatorUserBalanceRequest', ['agentId' => $agent, 'secureSessionId' => $none]), 'authenticate'));
        check('balance: nor for something that is not an avatar', refused(call('currency.php', 'simulatorUserBalanceRequest', ['agentId' => 'nobody', 'secureSessionId' => $secure]), 'authenticate'));

        check('buy: a wrong confirm value is refused', refused($buy($secure, 500, 'wrong'), 'Confirm'));
        check('buy: so is a wrong session, with the right confirm value', refused($buy($none, 500, $confirm), 'Secure Session'));
        check('buy: nothing was bought', funds($agent, $secure) === 0 && cell('SELECT COUNT(*) FROM transactions') === '0');
        $answer = $buy($secure, 500, $confirm);
        check('buy: the currency is added to the avatar', succeeded($answer) && funds($agent, $secure) === 500, substr($answer['raw'], 0, 300));
        check('buy: the transaction is kept, as an addition of money, from nobody', cell('SELECT CONCAT(sourceId, " ", destId, " ", amount, " ", transactionType) FROM transactions') === "$none $agent 500 5010");
        check('buy: the confirm value is the one of the address of the caller', $confirm === md5($key . '_127.0.0.1'));

        $move = fn(string $session, string $with, string $to, int $cash, ?string $of = null) => call('currency.php', 'regionMoveMoney', ['agentId' => $GLOBALS['agent'], 'destId' => $to, 'secureSessionId' => $session, 'regionId' => $GLOBALS['region'], 'secret' => $with, 'currencySecret' => ' ', 'cash' => $cash, 'aggregatePermInventory' => 0, 'aggregatePermNextOwner' => 0, 'flags' => 0, 'transactionType' => 5001, 'description' => $of ?? 'Gift']);
        check('move: a region that is not the one it says is refused', refused($move($secure, 'not-its-secret', $friend, 100), 'not authorized'));
        check('move: so is a wrong session of the avatar', refused($move($none, $secret, $friend, 100), 'authenticate'));
        check('move: more than the avatar owns is refused, nothing moved', refused($move($secure, $secret, $friend, 100000), 'sufficient funds') && funds($agent, $secure) === 500 && funds($friend, $friend_secure) === 0);
        $answer = $move($secure, $secret, $friend, 200);
        check('move: answered with success', succeeded($answer) && ($answer['value']['agentId'] ?? '') === $agent, substr($answer['raw'], 0, 300));
        check('move: the avatar gives what it pays, the other one receives it', funds($agent, $secure) === 300 && funds($friend, $friend_secure) === 200);
        check('move: the transaction is kept, with its kind and what it was for', cell('SELECT CONCAT(sourceId, " ", destId, " ", amount, " ", transactionType, " ", description) FROM transactions WHERE transactionType = 5001') === "$agent $friend 200 5001 Gift");
        check('move: the region where it happened is kept with it', cell('SELECT RegionGenerated FROM transactions WHERE transactionType = 5001') === $region);
        $updates = region_calls('UpdateBalance');
        $new = array_column(array_filter($updates, fn($call) => ($call['clientUUID'] ?? '') === $agent), 'Balance');
        check('move: the region of the avatar is told its new balance, with its secure session', ($new ? end($new) : null) === 300 && ($updates[0]['clientSecureSessionID'] ?? '') === $secure, json_encode($updates));
        check('move: and the region of the other one, its own', in_array(200, array_column(array_filter($updates, fn($call) => ($call['clientUUID'] ?? '') === $friend), 'Balance'), true), json_encode($updates));
        $answer = $move($secure, $secret, $none, 50, 'Purchase');
        check('move: money can be paid to nobody (a purchase, a fee)', succeeded($answer) && funds($agent, $secure) === 250);
        $answer = $move($secure, $secret, $friend, 20, "O'Brien's \"gift\"; DROP TABLE transactions");
        check('move: what the description holds is no harm', succeeded($answer) && cell('SELECT COUNT(*) FROM transactions WHERE description LIKE ?', ["O'Brien%"]) === '1');

        $claim = fn(string $session, string $in, string $with) => call('currency.php', 'simulatorClaimUserRequest', ['agentId' => $GLOBALS['agent'], 'secureSessionId' => $session, 'regionId' => $in, 'secret' => $with]);
        check('claim: a region that is not the one it says is refused', refused($claim($secure, $region2, 'wrong'), 'not authorized'));
        check('claim: so is a wrong session', refused($claim($none, $region2, $secret2), 'authenticate'));
        check('claim: the avatar is still where it was', cell('SELECT RegionID FROM Presence WHERE UserID = ?', [$agent]) === $region);
        $answer = $claim($secure, $region2, $secret2);
        check('claim: the region takes the avatar, who is told its funds', succeeded($answer) && ($answer['value']['funds'] ?? null) === 230 && ($answer['value']['agentId'] ?? '') === $agent, substr($answer['raw'], 0, 300));
        check('claim: the avatar is now in that region', cell('SELECT RegionID FROM Presence WHERE UserID = ?', [$agent]) === $region2);
        $claim($secure, $region, $secret);

        // Land: the viewer asks what the land costs, then buys the currency for it
        $prep = fn(string $session, int $amount) => call('landtool.php', 'preflightBuyLandPrep', ['agentId' => $GLOBALS['agent'], 'secureSessionId' => $session, 'currencyBuy' => $amount, 'billableArea' => 256]);
        $answer = $prep($secure, 500);
        check('land preflight: the cost, the confirm value, no upgrade to propose', succeeded($answer) && ($answer['value']['currency']['estimatedCost'] ?? null) === 500 && ($answer['value']['confirm'] ?? '') === $confirm && ($answer['value']['membership']['upgrade'] ?? true) === false && ($answer['value']['landUse']['upgrade'] ?? true) === false, substr($answer['raw'], 0, 300));
        check('land preflight: a wrong session is refused', refused($prep($none, 500), 'Unable to Authenticate'));
        $buyland = fn(string $session, int $amount, string $with) => call('landtool.php', 'buyLandPrep', ['agentId' => $GLOBALS['agent'], 'secureSessionId' => $session, 'currencyBuy' => $amount, 'estimatedCost' => 0, 'billableArea' => 256, 'confirm' => $with]);
        check('land: a wrong confirm value is refused', refused($buyland($secure, 100, 'wrong'), 'Confirm'));
        check('land: so is a wrong session', refused($buyland($none, 100, $confirm), 'Authenticate'));
        $before = funds($agent, $secure);
        $answer = $buyland($secure, 100, $confirm);
        check('land: the currency for the land is added to the avatar', succeeded($answer) && funds($agent, $secure) === $before + 100, substr($answer['raw'], 0, 300) . ' ' . funds($agent, $secure) . " (was $before)");
        $answer = $buyland($secure, -100, $confirm);
        check('land: a negative amount is refused, in a way the viewer reads', $answer['status'] === 200 && ($answer['value']['success'] ?? null) === false, substr($answer['raw'], 0, 300));
        break;

    // With a money server in the simulators: the helpers write the transactions, and ask the region for the rest
    case 'moneyserver':
        $answer = $quote($secure, 500);
        check('quote: the same as without a money server', succeeded($answer) && ($answer['value']['currency']['estimatedCost'] ?? null) === 500);
        check('balance: the avatar is asked to its region, which holds it', funds($agent, $secure) === 700, (string) funds($agent, $secure));
        check('balance: the region was asked with the sessions of the avatar', (region_calls('GetBalance')[0]['clientSecureSessionID'] ?? '') === $secure && (region_calls('GetBalance')[0]['clientSessionID'] ?? '') === $env('SESSION'), json_encode(region_calls('GetBalance')));
        $answer = $buy($secure, 300, $confirm);
        check('buy: the region adds the currency (the money server does it)', succeeded($answer) && (region_calls('AddBankerMoney')[0]['amount'] ?? null) === 300 && (region_calls('AddBankerMoney')[0]['clientUUID'] ?? '') === $agent, substr($answer['raw'], 0, 300));
        check('buy: the avatar has it', funds($agent, $secure) === 1000);
        $answer = call('currency.php', 'regionMoveMoney', ['agentId' => $agent, 'destId' => $friend, 'secureSessionId' => $secure, 'regionId' => $region, 'secret' => $secret, 'currencySecret' => ' ', 'cash' => 250, 'aggregatePermInventory' => 0, 'aggregatePermNextOwner' => 0, 'flags' => 0, 'transactionType' => 5001, 'description' => 'Rent']);
        check('move: answered with success', succeeded($answer), substr($answer['raw'], 0, 300));
        check('move: the helpers keep the transaction in the table of the money server', cell('SELECT CONCAT(sender, " ", receiver, " ", amount, " ", type, " ", description) FROM transactions') === "$agent $friend 250 5001 Rent");
        check('move: and update the balances of both, in its table', cell('SELECT balance FROM balances WHERE user = ?', [$agent]) === '750' && cell('SELECT balance FROM balances WHERE user = ?', [$friend]) === '250');
        check('move: the region of the avatar is told its balance', in_array(750, array_column(region_calls('UpdateBalance'), 'Balance'), true), json_encode(region_calls('UpdateBalance')));
        $answer = call('currency.php', 'regionMoveMoney', ['agentId' => $agent, 'destId' => $friend, 'secureSessionId' => $secure, 'regionId' => $region, 'secret' => $secret, 'currencySecret' => ' ', 'cash' => 100000, 'aggregatePermInventory' => 0, 'aggregatePermNextOwner' => 0, 'flags' => 0, 'transactionType' => 5001, 'description' => 'Too much']);
        check('move: more than the region says the avatar owns is refused', refused($answer, 'sufficient funds'));
        $answer = call('landtool.php', 'buyLandPrep', ['agentId' => $agent, 'secureSessionId' => $secure, 'currencyBuy' => 100, 'estimatedCost' => 0, 'billableArea' => 256, 'confirm' => $confirm]);
        check('land: the currency for the land is added by the region', succeeded($answer) && count(region_calls('AddBankerMoney')) === 2, substr($answer['raw'], 0, 300));
        break;

    // What the grid sets in helpers.ini: the key that makes the confirm value, the rate of the currency
    case 'settings':
        $answer = $quote($secure, 1000);
        check('settings: the confirm value is made with the key of the grid', succeeded($answer) && ($answer['value']['confirm'] ?? '') === $confirm && $confirm !== md5('1234567883789_127.0.0.1'), substr($answer['raw'], 0, 300));
        check('settings: the cost follows the rate of the grid', ($answer['value']['currency']['estimatedCost'] ?? null) === 400, substr($answer['raw'], 0, 300));
        check('settings: the confirm value of the default key is no longer good', refused($buy($secure, 100, md5('1234567883789_127.0.0.1')), 'Confirm'));
        check('settings: the one of the grid is', succeeded($buy($secure, 100, $confirm)));
        break;

    // The region of the avatar does not answer: nothing is bought, and the viewer is told in a way it reads
    case 'moneyserver-down':
        $answer = $buy($secure, 300, $confirm);
        check('buy: a region that does not answer is a refused purchase, with a message and a page', $answer['status'] === 200 && ($answer['value']['success'] ?? null) === false && ($answer['value']['errorMessage'] ?? '') !== '' && ($answer['value']['errorURI'] ?? '') !== '', substr($answer['raw'], 0, 300));
        check('balance: the avatar is told its balance is not available, not that it owns nothing', refused(call('currency.php', 'simulatorUserBalanceRequest', ['agentId' => $agent, 'secureSessionId' => $secure]), 'authenticate'));
        break;

    // Gloebit sells fixed packs: the viewer is offered the one that covers what it asks for
    case 'gloebit':
        foreach ([[300, 199, 400], [400, 199, 400], [500, 499, 1050], [2000, 999, 2150], [50000, 4999, 11500]] as [$asked, $cost, $pack]) {
            $answer = $quote($secure, $asked);
            check("gloebit quote $asked: the pack of $pack, for $cost cents", succeeded($answer) && ($answer['value']['currency']['estimatedCost'] ?? null) === $cost && ($answer['value']['currency']['currencyBuy'] ?? null) === $pack, substr($answer['raw'], 0, 300));
        }
        $answer = $buy($secure, 500, $confirm);
        check('gloebit buy: no money server, no purchase here: the viewer is sent to the Gloebit page, with where to come back', $answer['status'] === 200 && ($answer['value']['success'] ?? null) === false && str_starts_with((string) ($answer['value']['errorURI'] ?? ''), 'https://www.gloebit.com/purchase') && str_contains((string) ($answer['value']['errorURI'] ?? ''), "agentId=$agent") && stripos((string) ($answer['value']['errorMessage'] ?? ''), 'Gloebit') !== false, substr($answer['raw'], 0, 400));
        break;

    // Podex sells in-world: the viewer is sent to the terminals
    case 'podex':
        $answer = $buy($secure, 500, $confirm);
        check('podex buy: the viewer is told to use the terminals, with where they are', $answer['status'] === 200 && ($answer['value']['success'] ?? null) === false && stripos((string) ($answer['value']['errorMessage'] ?? ''), 'terminals') !== false && str_contains((string) ($answer['value']['errorMessage'] ?? ''), 'secondlife://'), substr($answer['raw'], 0, 400));
        break;
}

exit($failed ? 1 : 0);
