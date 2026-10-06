<?php
/**
 * The queries of the search tests of the packages (tests/Packaging/search.sh), as the OpenSimSearch module of a simulator
 * makes them to the web service of the grid (query.php of the helpers): the same XML-RPC calls, with the keys the module
 * sends (all strings, as the module sends them), and the answers read the way the module reads them.
 *
 *   php queries.php GROUP URL      GROUP: places, popular, land, events, classifieds, changed, gone, sim
 *
 * It prints one line per check, as the scenarios do, and exits with 1 when one failed. What the groups expect is what the
 * fixtures (collector-1.xml, collector-2.xml, seed.sql) put in the tables.
 */

declare(strict_types=1);

[, $group, $url] = $argv + [1 => '', 2 => ''];
require dirname(__DIR__) . '/xmlrpc.php';
$contract = require __DIR__ . '/contract.php';
$failed = false;

// Ratings and sorts of the viewer (OpenMetaverse DirFindFlags)
const PG = 1 << 24;
const MATURE = 1 << 25;
const ADULT = 1 << 26;
const ALL = PG | MATURE | ADULT;
const DWELL_SORT = 1 << 10;
const WITH_PICTURE = 1 << 12;
const MAX_PRICE = 1 << 20;
const MIN_AREA = 1 << 21;

// What the module sends, by call: every key of the contract, with a value that asks for everything
$base = [
    'DirPlacesQuery/dir_places_query' => ['text' => '', 'flags' => ALL, 'category' => -1, 'sim_name' => '', 'query_start' => 0],
    'DirPopularQuery/dir_popular_query' => ['flags' => ALL],
    'DirLandQuery/dir_land_query' => ['flags' => 0, 'type' => 4294967295, 'price' => 0, 'area' => 0, 'query_start' => 0],
    'HandleMapItemRequest/dir_land_query' => ['flags' => 163840, 'type' => 4294967295, 'price' => 0, 'area' => 0, 'query_start' => 0],
    'DirEventsQuery/dir_events_query' => ['text' => 'u|0', 'flags' => ALL, 'query_start' => 0],
    'HandleMapItemRequest/dir_events_query' => ['text' => 'u|0', 'flags' => ALL | 163840, 'query_start' => 0],
    'DirClassifiedQuery/dir_classified_query' => ['text' => '', 'flags' => 0, 'category' => 0, 'query_start' => 0],
    'EventInfoRequest/event_info_query' => ['eventID' => 1],
    'ClassifiedInfoRequest/classifieds_info_query' => ['classifiedID' => ''],
];

/** A call of the module by its name in the contract, the values of $base unless given. */
function ask(string $site, array $values = []): array
{
    global $base, $contract, $url;
    $request = $values + $base[$site];
    $keys = array_keys($request);
    sort($keys);
    if ($keys !== $contract[$site]['request']) {
        check("$site: the request has the keys the module sends", false, implode(' ', $keys));
    }

    return rpc($url, explode('/', $site)[1], $request);
}

function rows(array $answer): array
{
    return $answer['value']['data'] ?? [];
}

/** The names of the rows an answer gives, in their order. */
function names(array $answer): array
{
    return array_map(fn($row) => (string) ($row['name'] ?? ''), rows($answer));
}

function succeeded(array $answer): bool
{
    return $answer['status'] === 200 && ($answer['value']['success'] ?? false) === true;
}

/** An answer that has results: success, and in every row what the module reads of it. */
function shaped(string $site, array $answer, string $what): void
{
    global $contract;
    $missing = [];
    foreach (rows($answer) as $row) {
        $missing = array_merge($missing, array_diff($contract[$site]['response'], array_keys($row)));
    }
    check("$what: answered, with everything the module reads", succeeded($answer) && rows($answer) && !$missing, implode(' ', array_unique($missing)) ?: substr($answer['raw'], 0, 200));
}

/** An answer without results, as the module can read it: a success with an empty list, or a failure with its message. */
function empty_answer(array $answer, string $what): void
{
    $value = $answer['value'];
    $empty_list = ($value['success'] ?? false) === true && array_key_exists('data', $value) && $value['data'] === [];
    $failure = ($value['success'] ?? true) === false && is_string($value['errorMessage'] ?? null) && $value['errorMessage'] !== '';
    check("$what: no result is said in a way the module reads", $answer['status'] === 200 && ($empty_list || $failure), substr($answer['raw'], 0, 200));
}

$sorted = function (array $list): array {
    sort($list);

    return $list;
};

switch ($group) {
    case 'places':
        $site = 'DirPlacesQuery/dir_places_query';
        $answer = ask($site, ['text' => 'garden']);
        shaped($site, $answer, 'places "garden"');
        check('places "garden": the parcel shown in search', names($answer) === ['Garden of Alpha']);
        $row = rows($answer)[0] ?? [];
        check('places: the parcel id is the info UUID the simulator gave, the numbers are numbers', ($row['parcel_id'] ?? '') === '1fffffff-0000-4000-8000-0000000000ff' && is_numeric($row['dwell'] ?? 'x') && in_array(strtolower((string) ($row['for_sale'] ?? '')), ['false', '0'], true));
        check('places "garden fountain": the words are found in the name and the description', names(ask($site, ['text' => 'garden fountain'])) === ['Garden of Alpha']);
        check('places "Hidden": a parcel not shown in search is not found', !succeeded(ask($site, ['text' => 'Hidden'])) || rows(ask($site, ['text' => 'Hidden'])) === []);
        empty_answer(ask($site, ['text' => 'zzzznothing']), 'places "zzzznothing"');
        check('places "Lounge": a mature region is not in the results for PG only', names(ask($site, ['text' => 'Lounge', 'flags' => PG])) === []);
        check('places "Lounge": it is in the results for mature', names(ask($site, ['text' => 'Lounge', 'flags' => MATURE])) === ['Mature Lounge']);
        check('places "garden" category 6: found in its category', names(ask($site, ['text' => 'garden', 'category' => 6])) === ['Garden of Alpha']);
        check('places "garden" category 10: not found in another one', names(ask($site, ['text' => 'garden', 'category' => 10])) === []);
        $traffic = names(ask($site, ['text' => 'ha', 'flags' => ALL | DWELL_SORT]));
        check('places "ha": sorted by traffic, the busiest first', array_slice($traffic, 0, 2) === ['Group Hall', 'Garden of Alpha'], implode(', ', $traffic));
        check('places: the first page of a hundred, and one more to know there is a next one', count(rows(ask($site, ['text' => 'Paging']))) === 101);
        check('places: the second page has the plots that remain (query_start is a string, as the module sends it)', count(rows(ask($site, ['text' => 'Paging', 'query_start' => 100]))) === 20, (string) count(rows(ask($site, ['text' => 'Paging', 'query_start' => 100]))));
        $quote = ask($site, ['text' => "o'brien\" OR 1=1 --"]);
        check('places: a quote in the words is no error', $quote['status'] === 200 && $quote['value'] !== []);
        break;

    case 'popular':
        $site = 'DirPopularQuery/dir_popular_query';
        $answer = ask($site);
        shaped($site, $answer, 'popular places');
        check('popular places: the busiest first, land for sale and hidden parcels left out', names($answer) === ['Group Hall', 'Garden of Alpha', 'Mature Lounge', 'Adult Club'], implode(', ', names($answer)));
        check('popular places: PG only', names(ask($site, ['flags' => PG])) === ['Group Hall', 'Garden of Alpha']);
        check('popular places: with a picture only', names(ask($site, ['flags' => ALL | WITH_PICTURE])) === ['Group Hall']);
        empty_answer(ask($site, ['flags' => ADULT | WITH_PICTURE]), 'popular places, adult with a picture');
        break;

    case 'land':
        $site = 'DirLandQuery/dir_land_query';
        $answer = ask($site, ['flags' => ALL]);
        shaped($site, $answer, 'land for sale');
        $row = rows($answer)[0] ?? [];
        check('land for sale: the parcel for sale, its price and its area', names($answer) === ['Sale Meadow'] && (int) ($row['sale_price'] ?? 0) === 500 && (int) ($row['area'] ?? 0) === 2048 && strtolower((string) ($row['for_sale'] ?? '')) === 'true');
        check('land for sale: under a price', names(ask($site, ['flags' => ALL | MAX_PRICE, 'price' => 1000])) === ['Sale Meadow']);
        empty_answer(ask($site, ['flags' => ALL | MAX_PRICE, 'price' => 100]), 'land for sale under 100');
        check('land for sale: from an area', names(ask($site, ['flags' => ALL | MIN_AREA, 'area' => 1000])) === ['Sale Meadow']);
        empty_answer(ask($site, ['flags' => ALL | MIN_AREA, 'area' => 4096]), 'land for sale from 4096');
        check('land for sale: an estate, not a mainland', names(ask($site, ['flags' => ALL, 'type' => 16])) === ['Sale Meadow'] && names(ask($site, ['flags' => ALL, 'type' => 8])) === []);
        $auctions = ask($site, ['flags' => ALL, 'type' => 2]);
        check('land for sale: no auctions, and says so', ($auctions['value']['success'] ?? true) === false && ($auctions['value']['errorMessage'] ?? '') === 'No auctions listed');
        $map = 'HandleMapItemRequest/dir_land_query';
        shaped($map, ask($map), 'land for sale on the map');
        break;

    case 'events':
        $site = 'DirEventsQuery/dir_events_query';
        $answer = ask($site);
        shaped($site, $answer, 'events');
        check('events: the coming ones, not the past', $sorted(names($answer)) === $sorted(['Live Jazz Night', 'Art Opening', 'Adult Party']), implode(', ', names($answer)));
        $row = rows($answer)[0] ?? [];
        check('events: the time is a number, the date is the one of the viewer (month/day hour)', is_numeric($row['unix_time'] ?? 'x') && preg_match('#^\d\d/\d\d \d\d:\d\d [AP]M$#', (string) ($row['date'] ?? '')) === 1, (string) ($row['date'] ?? ''));
        check('events: PG only', names(ask($site, ['flags' => PG])) === ['Live Jazz Night']);
        check('events: PG and mature', $sorted(names(ask($site, ['flags' => PG | MATURE]))) === $sorted(['Art Opening', 'Live Jazz Night']));
        check('events: by category', names(ask($site, ['text' => 'u|20|'])) === ['Live Jazz Night']);
        check('events: by words', names(ask($site, ['text' => 'u|0|jazz'])) === ['Live Jazz Night']);
        check('events: a day, five days ago', names(ask($site, ['text' => '-5|0|'])) === ['Past Meeting']);
        empty_answer(ask($site, ['text' => 'u|0|zzzznothing']), 'events "zzzznothing"');
        $map = 'HandleMapItemRequest/dir_events_query';
        $on_map = ask($map);
        shaped($map, $on_map, 'events on the map');
        check('events on the map: the place of each, as a position of the grid', in_array('2048512/2048512/30', array_column(rows($on_map), 'landing_point'), true));
        $info = 'EventInfoRequest/event_info_query';
        $id = (int) (array_column(rows($answer), 'event_id', 'name')['Live Jazz Night'] ?? 0);
        $detail = ask($info, ['eventID' => $id]);
        shaped($info, $detail, 'an event in detail');
        $event = rows($detail)[0] ?? [];
        check('an event in detail: its category by name, its price, its place, its length', ($event['category'] ?? '') === 'Live Music' && (int) ($event['coveramount'] ?? 0) === 50 && ($event['simname'] ?? '') === 'Alpha' && (int) ($event['duration'] ?? 0) === 120 && ($event['globalposition'] ?? '') === '2048512/2048512/30');
        check('an event in detail: the date is year-month-day hour:minute:second', preg_match('#^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$#', (string) ($event['date'] ?? '')) === 1, (string) ($event['date'] ?? ''));
        empty_answer(ask($info, ['eventID' => 99999]), 'an event that does not exist');
        break;

    case 'classifieds':
        $site = 'DirClassifiedQuery/dir_classified_query';
        $answer = ask($site);
        shaped($site, $answer, 'classifieds');
        check('classifieds: all of them, the dearest first', names($answer) === ['Night Companion', 'Furnished Loft', 'Beach Plot Rental'], implode(', ', names($answer)));
        check('classifieds: PG', names(ask($site, ['flags' => 4])) === ['Furnished Loft', 'Beach Plot Rental']);
        check('classifieds: adult', names(ask($site, ['flags' => 64])) === ['Night Companion']);
        check('classifieds: by words and by category', names(ask($site, ['text' => 'loft'])) === ['Furnished Loft'] && names(ask($site, ['category' => 3])) === ['Beach Plot Rental']);
        empty_answer(ask($site, ['flags' => 8]), 'classifieds for mature');
        $info = 'ClassifiedInfoRequest/classifieds_info_query';
        $detail = ask($info, ['classifiedID' => 'eeeeeeee-0000-4000-8000-000000000001']);
        shaped($info, $detail, 'a classified in detail');
        $classified = rows($detail)[0] ?? [];
        check('a classified in detail: its place and its position as the module reads them', ($classified['simname'] ?? '') === 'Alpha' && ($classified['posglobal'] ?? '') === '<128,128,25>' && (int) ($classified['priceforlisting'] ?? 0) === 50);
        empty_answer(ask($info, ['classifiedID' => 'eeeeeeee-0000-4000-8000-0000000000ff']), 'a classified that does not exist');
        break;

    case 'changed':
        // After the simulator told another snapshot: Alpha changed, Beta is not told any more
        $places = 'DirPlacesQuery/dir_places_query';
        check('changed: a parcel renamed is found by its new name, not by the old one', names(ask($places, ['text' => 'renewed'])) === ['Garden of Alpha (renewed)'] && names(ask($places, ['text' => 'garden'])) === ['Garden of Alpha (renewed)']);
        check('changed: a parcel now shown in search is found', names(ask($places, ['text' => 'Basement'])) === ['Hidden Basement']);
        check('changed: what is no longer told by a region is no longer there', names(ask($places, ['text' => 'Sale Meadow'])) === []);
        empty_answer(ask('DirLandQuery/dir_land_query', ['flags' => ALL]), 'land for sale, after it was sold');
        check('changed: popular places follow the traffic', names(ask('DirPopularQuery/dir_popular_query')) === ['Group Hall', 'Garden of Alpha (renewed)', 'Mature Lounge', 'Adult Club', 'Hidden Basement'], implode(', ', names(ask('DirPopularQuery/dir_popular_query'))));
        check('changed: a region the simulator stopped telling stays until it unregisters', names(ask($places, ['text' => 'Lounge', 'flags' => MATURE])) === ['Mature Lounge']);
        break;

    case 'sim':
        // The parcel of the real simulator, as OpenSimulator tells it
        $places = 'DirPlacesQuery/dir_places_query';
        $answer = ask($places, ['text' => 'Sim1']);
        shaped($places, $answer, 'the parcel of the simulator');
        $row = rows($answer)[0] ?? [];
        check('the parcel of the simulator: found by its name, with an info UUID the module can read, and its traffic', names($answer) === ['Sim1'] && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', (string) ($row['parcel_id'] ?? '')) === 1 && is_numeric($row['dwell'] ?? 'x'));
        check('the parcel of the simulator: the other parcel of the grid is not found', names(ask($places, ['text' => 'Sim1North'])) === []);
        $popular = ask('DirPopularQuery/dir_popular_query');
        shaped('DirPopularQuery/dir_popular_query', $popular, 'the popular places');
        check('the parcel of the simulator is a popular place, and nothing else of the simulator is', names($popular) === ['Sim1']);
        break;

    case 'gone':
        // After the simulator unregistered
        $places = 'DirPlacesQuery/dir_places_query';
        check('unregistered: its parcels are no longer found', names(ask($places, ['text' => 'garden'])) === [] && names(ask($places, ['text' => 'Lounge', 'flags' => MATURE])) === []);
        check('unregistered: nor its popular places', names(ask('DirPopularQuery/dir_popular_query')) === []);
        break;

    default:
        fwrite(STDERR, "usage: php queries.php places|popular|land|events|classifieds|changed|gone|sim URL\n");
        exit(2);
}

exit($failed ? 1 : 0);
