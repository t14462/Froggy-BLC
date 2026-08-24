# Fisher Yates Seeded Shuffle
Shuffle and Unshuffle strings and arrays using the Fisher Yates shuffle algorithm.

## Examples
**Shuffle & Unshuffle a string**
```PHP
require_once('seeded.shuffle.class.php');
$data       = 'this is some readable text.';
$seed       = seeded_shuffle::seed($data);
$shuffled   = seeded_shuffle::shuffle($data, $seed);
$unshuffled = seeded_shuffle::unshuffle($shuffled, $seed);
echo $shuffled   . PHP_EOL;
echo $unshuffled . PHP_EOL;
```

**Shuffle & Unshuffle an array**
```PHP
require_once('seeded.shuffle.class.php');
$data       = array('this', 'is', 'a', 'sorted', 'array');
$seed       = seeded_shuffle::seed($data);
$shuffled   = seeded_shuffle::shuffle($data, $seed);
$unshuffled = seeded_shuffle::unshuffle($shuffled, $seed);
print_r($shuffled);
print_r($unshuffled);
```
