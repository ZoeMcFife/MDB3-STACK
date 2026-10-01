<?php
// Moderne Datenbanken WS 2026/27
// Imports the Animal Crossing catalogue (data/AnimalCrossing/*.csv) into MongoDB
// as the two collections modelled in "Document Modelling":
//   catalogue.items      one document per item, variations and recipe embedded, _id = Internal ID
//   catalogue.villagers  one document per villager, home embedded, furniture as an array of item _ids
// Run inside the stack:  docker compose exec php php import_catalogue.php
require __DIR__ . '/vendor/autoload.php';

$client = new MongoDB\Client('mongodb://mongodb:27017/?replicaSet=rs0');
$db = $client->catalogue;
$dir = __DIR__ . '/data/AnimalCrossing';

// file name -> type field of the item documents
$types = [
    'accessories' => 'accessory', 'art' => 'art', 'bags' => 'bag', 'bottoms' => 'bottoms',
    'dress-up' => 'dress', 'fencing' => 'fence', 'fish' => 'fish', 'floors' => 'floor',
    'fossils' => 'fossil', 'headwear' => 'headwear', 'housewares' => 'houseware', 'insects' => 'insect',
    'miscellaneous' => 'misc', 'music' => 'music', 'other' => 'other', 'photos' => 'photo',
    'posters' => 'poster', 'rugs' => 'rug', 'shoes' => 'shoes', 'socks' => 'socks', 'tools' => 'tool',
    'tops' => 'top', 'umbrellas' => 'umbrella', 'wall-mounted' => 'wallmounted', 'wallpaper' => 'wallpaper',
];
$months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$dropColumns = ['Filename', 'Unique Entry ID', 'Variant ID', 'Icon Filename', 'Critterpedia Filename',
    'Furniture Filename', 'Internal ID', 'Variation', 'Color 1', 'Color 2', 'Name'];

function readCsv(string $path): array {
    $rows = [];
    $fh = fopen($path, 'r');
    $header = fgetcsv($fh, null, ",", "\"", "\\");
    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);   // strip the BOM
    while (($line = fgetcsv($fh, null, ",", "\"", "\\")) !== false) {
        if (count($line) !== count($header)) continue;
        $rows[] = array_combine($header, $line);
    }
    fclose($fh);
    return $rows;
}

function camel(string $column): string {
    if ($column === '#') return 'number';                      // critterpedia number of fish and insects
    $c = preg_replace('/[^A-Za-z0-9 ]/', ' ', $column);
    $words = preg_split('/\s+/', trim($c));
    $out = strtolower(array_shift($words));
    foreach ($words as $w) $out .= ucfirst(strtolower($w));
    return $out;
}

// "NA", "NFS", "" -> null; "Yes"/"No" -> bool; numeric strings -> int/float; else string
function value(string $v): mixed {
    $v = trim($v);
    if ($v === '' || $v === 'NA' || $v === 'NFS') return null;
    if ($v === 'Yes') return true;
    if ($v === 'No') return false;
    if (preg_match('/^-?\d+$/', $v)) return (int) $v;
    if (preg_match('/^-?\d+\.\d+$/', $v)) return (float) $v;
    return $v;
}

function colors(array $row): array {
    $c = [];
    foreach (['Color 1', 'Color 2'] as $k) {
        $v = value($row[$k] ?? '');
        if ($v !== null && !in_array($v, $c, true)) $c[] = $v;
    }
    return $c;
}

// ---------------------------------------------------------------- recipes (embedded later)
$recipes = [];
foreach (readCsv("$dir/recipes.csv") as $r) {
    $materials = [];
    for ($i = 1; $i <= 6; $i++) {
        $item = value($r["Material $i"] ?? '');
        $count = value($r["#$i"] ?? '');
        if ($item !== null) $materials[] = ['item' => $item, 'count' => $count];
    }
    $recipe = ['materials' => $materials];
    foreach (['Source', 'Source Notes', 'Recipes to Unlock', 'Category'] as $k) {
        $v = value($r[$k] ?? '');
        if ($v !== null) $recipe[camel($k)] = $v;
    }
    $recipes[$r['Name']] = $recipe;
}

// ---------------------------------------------------------------- items
$db->items->drop();
$items = [];
$seen = [];
foreach ($types as $file => $type) {
    $byName = [];
    foreach (readCsv("$dir/$file.csv") as $r) $byName[$r['Name']][] = $r;
    foreach ($byName as $name => $rows) {
        $first = $rows[0];
        $id = value($first['Internal ID'] ?? '');
        if ($id === null || isset($seen[$id])) continue;        // no id or a duplicate id across files
        $seen[$id] = true;
        $doc = ['_id' => $id, 'name' => $name, 'type' => $type];
        // shared scalar fields, camelCase, NA dropped
        $available = [];
        foreach ($first as $column => $raw) {
            if (in_array($column, $dropColumns, true)) continue;
            if (preg_match('/^(NH|SH) (\w{3})$/', $column, $m)) {     // fish and insects: months
                $v = value($raw);
                if ($v !== null) $available[$m[1] === 'NH' ? 'north' : 'south'][$m[2]] = $v;
                continue;
            }
            $v = value($raw);
            if ($v === null) continue;
            if ($column === 'Label Themes' || $column === 'HHA Concept 1' || $column === 'HHA Concept 2') {
                $key = $column === 'Label Themes' ? 'labelThemes' : 'hhaConcepts';
                foreach (explode(';', $v) as $part) $doc[$key][] = trim($part);
                continue;
            }
            $doc[$column === 'Type' ? 'subtype' : camel($column)] = $v;   // accessories have their own Type
        }
        $doc['type'] = $type;
        if ($available) $doc['available'] = $available;
        $c = colors($first);
        if ($c) $doc['colors'] = $c;
        // variations: one row each, only when the file has a Variation column with a value
        if (value($first['Variation'] ?? '') !== null) {
            $doc['variations'] = [];
            foreach ($rows as $r) {
                $var = ['name' => value($r['Variation'])];
                $vc = colors($r);
                if ($vc) $var['colors'] = $vc;
                foreach (['Pattern', 'Body Title', 'Pattern Title', 'Variant ID'] as $k) {
                    $v = value($r[$k] ?? '');
                    if ($v !== null) $var[camel($k)] = $v;
                }
                $doc['variations'][] = $var;
            }
        }
        if (isset($recipes[$name])) {
            $doc['recipe'] = $recipes[$name];
            unset($recipes[$name]);
        }
        $items[] = $doc;
    }
}
foreach (array_chunk($items, 1000) as $chunk) $db->items->insertMany($chunk);
printf("items:     %d documents (%d recipes embedded, %d recipes without an item)\n",
    count($items), 590, count($recipes));

// ---------------------------------------------------------------- villagers
$db->villagers->drop();
$villagers = [];
$n = 0;
foreach (readCsv("$dir/villagers.csv") as $r) {
    $n++;
    [$day, $mon] = explode('-', $r['Birthday']);
    $doc = [
        '_id' => $n,
        'name' => $r['Name'],
        'species' => $r['Species'],
        'gender' => $r['Gender'],
        'personality' => $r['Personality'],
        'hobby' => $r['Hobby'],
        'birthday' => ['month' => array_search($mon, $months) + 1, 'day' => (int) $day],
        'catchphrase' => $r['Catchphrase'],
        'favoriteSong' => $r['Favorite Song'],
        'styles' => array_values(array_unique([$r['Style 1'], $r['Style 2']])),
        'colors' => colors($r),
        'home' => ['wallpaper' => $r['Wallpaper'], 'flooring' => $r['Flooring']],
        'furniture' => array_map('intval', array_filter(explode(';', $r['Furniture List']))),
    ];
    $villagers[] = $doc;
}
$db->villagers->insertMany($villagers);
printf("villagers: %d documents\n", count($villagers));

// ---------------------------------------------------------------- a quick look
$admiral = $db->villagers->findOne(['name' => 'Admiral']);
printf("Admiral owns %d items, the first is %s\n", count($admiral->furniture),
    $db->items->findOne(['_id' => $admiral->furniture[0]])->name);
printf("items under 500 Bells: %d\n", $db->items->countDocuments(['sell' => ['$lt' => 500]]));
