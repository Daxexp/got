<?php
/**
 * source.php — Episode data source for Game of Thrones
 * Pure data file — no HTML output. Included by index.php and api.php via require.
 *
 * All video URLs route through proxy.php to bypass CORS restrictions.
 * proxy.php?f=S01E01  →  fetches from origin server and streams to browser.
 *
 * NOTE: This file is server-side only. The 'url' field is never sent
 * to the browser directly — it is returned by api.php only after
 * session token validation.
 *
 * Structure per episode: ep, title, date, thumb, url
 * Structure per season:  year, thumb, episodes[]
 */

$T = 'https://upload.wikimedia.org/wikipedia/en/d/d8/Game_of_Thrones_title_card.jpg';
$P = 'proxy.php?f=';

$GOT_DATA = [

  1 => ['year'=>2011,'thumb'=>'https://image.tmdb.org/t/p/w500/qsEfWqGSYCeqHxj0XeGfxELRCXe.jpg','episodes'=>[
    ['ep'=>1,  'title'=>'Winter Is Coming',                      'date'=>'April 17, 2011',  'thumb'=>$T, 'url'=>$P.'S01E01'],
    ['ep'=>2,  'title'=>'The Kingsroad',                         'date'=>'April 24, 2011',  'thumb'=>$T, 'url'=>$P.'S01E02'],
    ['ep'=>3,  'title'=>'Lord Snow',                             'date'=>'May 1, 2011',     'thumb'=>$T, 'url'=>$P.'S01E03'],
    ['ep'=>4,  'title'=>'Cripples, Bastards, and Broken Things', 'date'=>'May 8, 2011',     'thumb'=>$T, 'url'=>$P.'S01E04'],
    ['ep'=>5,  'title'=>'The Wolf and the Lion',                 'date'=>'May 15, 2011',    'thumb'=>$T, 'url'=>$P.'S01E05'],
    ['ep'=>6,  'title'=>'A Golden Crown',                        'date'=>'May 22, 2011',    'thumb'=>$T, 'url'=>$P.'S01E06'],
    ['ep'=>7,  'title'=>'You Win or You Die',                    'date'=>'May 29, 2011',    'thumb'=>$T, 'url'=>$P.'S01E07'],
    ['ep'=>8,  'title'=>'The Pointy End',                        'date'=>'June 5, 2011',    'thumb'=>$T, 'url'=>$P.'S01E08'],
    ['ep'=>9,  'title'=>'Baelor',                                'date'=>'June 12, 2011',   'thumb'=>$T, 'url'=>$P.'S01E09'],
    ['ep'=>10, 'title'=>'Fire and Blood',                        'date'=>'June 19, 2011',   'thumb'=>$T, 'url'=>$P.'S01E10'],
  ]],

  2 => ['year'=>2012,'thumb'=>'https://image.tmdb.org/t/p/w500/suopoADq0k8YZr4dQXcU6pToj6s.jpg','episodes'=>[
    ['ep'=>1,  'title'=>'The North Remembers',        'date'=>'April 1, 2012',  'thumb'=>$T, 'url'=>$P.'S02E01'],
    ['ep'=>2,  'title'=>'The Night Lands',            'date'=>'April 8, 2012',  'thumb'=>$T, 'url'=>$P.'S02E02'],
    ['ep'=>3,  'title'=>'What Is Dead May Never Die', 'date'=>'April 15, 2012', 'thumb'=>$T, 'url'=>$P.'S02E03'],
    ['ep'=>4,  'title'=>'Garden of Bones',            'date'=>'April 22, 2012', 'thumb'=>$T, 'url'=>$P.'S02E04'],
    ['ep'=>5,  'title'=>'The Ghost of Harrenhal',     'date'=>'April 29, 2012', 'thumb'=>$T, 'url'=>$P.'S02E05'],
    ['ep'=>6,  'title'=>'The Old Gods and the New',   'date'=>'May 6, 2012',    'thumb'=>$T, 'url'=>$P.'S02E06'],
    ['ep'=>7,  'title'=>'A Man Without Honor',        'date'=>'May 13, 2012',   'thumb'=>$T, 'url'=>$P.'S02E07'],
    ['ep'=>8,  'title'=>'The Prince of Winterfell',   'date'=>'May 20, 2012',   'thumb'=>$T, 'url'=>$P.'S02E08'],
    ['ep'=>9,  'title'=>'Blackwater',                 'date'=>'May 27, 2012',   'thumb'=>$T, 'url'=>$P.'S02E09'],
    ['ep'=>10, 'title'=>'Valar Morghulis',             'date'=>'June 3, 2012',   'thumb'=>$T, 'url'=>$P.'S02E10'],
  ]],

  3 => ['year'=>2013,'thumb'=>'https://image.tmdb.org/t/p/w500/7d3vRgbmnrRQ3vfZ8TTxKBGTFhz.jpg','episodes'=>[
    ['ep'=>1,  'title'=>'Valar Dohaeris',                'date'=>'March 31, 2013', 'thumb'=>$T, 'url'=>$P.'S03E01'],
    ['ep'=>2,  'title'=>'Dark Wings, Dark Words',        'date'=>'April 7, 2013',  'thumb'=>$T, 'url'=>$P.'S03E02'],
    ['ep'=>3,  'title'=>'Walk of Punishment',            'date'=>'April 14, 2013', 'thumb'=>$T, 'url'=>$P.'S03E03'],
    ['ep'=>4,  'title'=>'And Now His Watch Is Ended',    'date'=>'April 21, 2013', 'thumb'=>$T, 'url'=>$P.'S03E04'],
    ['ep'=>5,  'title'=>'Kissed by Fire',                'date'=>'April 28, 2013', 'thumb'=>$T, 'url'=>$P.'S03E05'],
    ['ep'=>6,  'title'=>'The Climb',                     'date'=>'May 5, 2013',    'thumb'=>$T, 'url'=>$P.'S03E06'],
    ['ep'=>7,  'title'=>'The Bear and the Maiden Fair',  'date'=>'May 12, 2013',   'thumb'=>$T, 'url'=>$P.'S03E07'],
    ['ep'=>8,  'title'=>'Second Sons',                   'date'=>'May 19, 2013',   'thumb'=>$T, 'url'=>$P.'S03E08'],
    ['ep'=>9,  'title'=>'The Rains of Castamere',        'date'=>'June 2, 2013',   'thumb'=>$T, 'url'=>$P.'S03E09'],
    ['ep'=>10, 'title'=>'Mhysa',                         'date'=>'June 9, 2013',   'thumb'=>$T, 'url'=>$P.'S03E10'],
  ]],

  4 => ['year'=>2014,'thumb'=>'https://image.tmdb.org/t/p/w500/dvsRzP3gy7Psv0UPGIPcVJv0TbD.jpg','episodes'=>[
    ['ep'=>1,  'title'=>'Two Swords',                 'date'=>'April 6, 2014',  'thumb'=>$T, 'url'=>$P.'S04E01'],
    ['ep'=>2,  'title'=>'The Lion and the Rose',      'date'=>'April 13, 2014', 'thumb'=>$T, 'url'=>$P.'S04E02'],
    ['ep'=>3,  'title'=>'Breaker of Chains',          'date'=>'April 20, 2014', 'thumb'=>$T, 'url'=>$P.'S04E03'],
    ['ep'=>4,  'title'=>'Oathkeeper',                 'date'=>'April 27, 2014', 'thumb'=>$T, 'url'=>$P.'S04E04'],
    ['ep'=>5,  'title'=>'First of His Name',          'date'=>'May 4, 2014',    'thumb'=>$T, 'url'=>$P.'S04E05'],
    ['ep'=>6,  'title'=>'The Laws of Gods and Men',   'date'=>'May 11, 2014',   'thumb'=>$T, 'url'=>$P.'S04E06'],
    ['ep'=>7,  'title'=>'Mockingbird',                'date'=>'May 18, 2014',   'thumb'=>$T, 'url'=>$P.'S04E07'],
    ['ep'=>8,  'title'=>'The Mountain and the Viper', 'date'=>'June 1, 2014',   'thumb'=>$T, 'url'=>$P.'S04E08'],
    ['ep'=>9,  'title'=>'The Watchers on the Wall',   'date'=>'June 8, 2014',   'thumb'=>$T, 'url'=>$P.'S04E09'],
    ['ep'=>10, 'title'=>'The Children',               'date'=>'June 15, 2014',  'thumb'=>$T, 'url'=>$P.'S04E10'],
  ]],

  5 => ['year'=>2015,'thumb'=>'https://image.tmdb.org/t/p/w500/527sR9hNDcgVDKNUE3QYra9BPYS.jpg','episodes'=>[
    ['ep'=>1,  'title'=>'The Wars to Come',               'date'=>'April 12, 2015', 'thumb'=>$T, 'url'=>$P.'S05E01'],
    ['ep'=>2,  'title'=>'The House of Black and White',   'date'=>'April 19, 2015', 'thumb'=>$T, 'url'=>$P.'S05E02'],
    ['ep'=>3,  'title'=>'High Sparrow',                   'date'=>'April 26, 2015', 'thumb'=>$T, 'url'=>$P.'S05E03'],
    ['ep'=>4,  'title'=>'Sons of the Harpy',              'date'=>'May 3, 2015',    'thumb'=>$T, 'url'=>$P.'S05E04'],
    ['ep'=>5,  'title'=>'Kill the Boy',                   'date'=>'May 10, 2015',   'thumb'=>$T, 'url'=>$P.'S05E05'],
    ['ep'=>6,  'title'=>'Unbowed, Unbent, Unbroken',      'date'=>'May 17, 2015',   'thumb'=>$T, 'url'=>$P.'S05E06'],
    ['ep'=>7,  'title'=>'The Gift',                       'date'=>'May 24, 2015',   'thumb'=>$T, 'url'=>$P.'S05E07'],
    ['ep'=>8,  'title'=>'Hardhome',                       'date'=>'May 31, 2015',   'thumb'=>$T, 'url'=>$P.'S05E08'],
    ['ep'=>9,  'title'=>'The Dance of Dragons',           'date'=>'June 7, 2015',   'thumb'=>$T, 'url'=>$P.'S05E09'],
    ['ep'=>10, 'title'=>"Mother's Mercy",                 'date'=>'June 14, 2015',  'thumb'=>$T, 'url'=>$P.'S05E10'],
  ]],

  6 => ['year'=>2016,'thumb'=>'https://image.tmdb.org/t/p/w500/jTge9nWFiOFLHvb6E2gLlJIKZPm.jpg','episodes'=>[
    ['ep'=>1,  'title'=>'The Red Woman',          'date'=>'April 24, 2016', 'thumb'=>$T, 'url'=>$P.'S06E01'],
    ['ep'=>2,  'title'=>'Home',                   'date'=>'May 1, 2016',    'thumb'=>$T, 'url'=>$P.'S06E02'],
    ['ep'=>3,  'title'=>'Oathbreaker',            'date'=>'May 8, 2016',    'thumb'=>$T, 'url'=>$P.'S06E03'],
    ['ep'=>4,  'title'=>'Book of the Stranger',   'date'=>'May 15, 2016',   'thumb'=>$T, 'url'=>$P.'S06E04'],
    ['ep'=>5,  'title'=>'The Door',               'date'=>'May 22, 2016',   'thumb'=>$T, 'url'=>$P.'S06E05'],
    ['ep'=>6,  'title'=>'Blood of My Blood',      'date'=>'May 29, 2016',   'thumb'=>$T, 'url'=>$P.'S06E06'],
    ['ep'=>7,  'title'=>'The Broken Man',         'date'=>'June 5, 2016',   'thumb'=>$T, 'url'=>$P.'S06E07'],
    ['ep'=>8,  'title'=>'No One',                 'date'=>'June 12, 2016',  'thumb'=>$T, 'url'=>$P.'S06E08'],
    ['ep'=>9,  'title'=>'Battle of the Bastards', 'date'=>'June 19, 2016',  'thumb'=>$T, 'url'=>$P.'S06E09'],
    ['ep'=>10, 'title'=>'The Winds of Winter',    'date'=>'June 26, 2016',  'thumb'=>$T, 'url'=>$P.'S06E10'],
  ]],

  7 => ['year'=>2017,'thumb'=>'https://image.tmdb.org/t/p/w500/3dqzU3F3biS1wVSix6QMzm1lBwH.jpg','episodes'=>[
    ['ep'=>1,  'title'=>'Dragonstone',             'date'=>'July 16, 2017',   'thumb'=>$T, 'url'=>$P.'S07E01'],
    ['ep'=>2,  'title'=>'Stormborn',               'date'=>'July 23, 2017',   'thumb'=>$T, 'url'=>$P.'S07E02'],
    ['ep'=>3,  'title'=>"The Queen's Justice",     'date'=>'July 30, 2017',   'thumb'=>$T, 'url'=>$P.'S07E03'],
    ['ep'=>4,  'title'=>'The Spoils of War',       'date'=>'August 6, 2017',  'thumb'=>$T, 'url'=>$P.'S07E04'],
    ['ep'=>5,  'title'=>'Eastwatch',               'date'=>'August 13, 2017', 'thumb'=>$T, 'url'=>$P.'S07E05'],
    ['ep'=>6,  'title'=>'Beyond the Wall',         'date'=>'August 20, 2017', 'thumb'=>$T, 'url'=>$P.'S07E06'],
    ['ep'=>7,  'title'=>'The Dragon and the Wolf', 'date'=>'August 27, 2017', 'thumb'=>$T, 'url'=>$P.'S07E07'],
  ]],

  8 => ['year'=>2019,'thumb'=>'https://image.tmdb.org/t/p/w500/u3B2NN1zEl4kPxA0g4bHhAQ7DOg.jpg','episodes'=>[
    ['ep'=>1,  'title'=>'Winterfell',                         'date'=>'April 14, 2019', 'thumb'=>$T, 'url'=>$P.'S08E01'],
    ['ep'=>2,  'title'=>'A Knight of the Seven Kingdoms',     'date'=>'April 21, 2019', 'thumb'=>$T, 'url'=>$P.'S08E02'],
    ['ep'=>3,  'title'=>'The Long Night',                     'date'=>'April 28, 2019', 'thumb'=>$T, 'url'=>$P.'S08E03'],
    ['ep'=>4,  'title'=>'The Last of the Starks',             'date'=>'May 5, 2019',    'thumb'=>$T, 'url'=>$P.'S08E04'],
    ['ep'=>5,  'title'=>'The Bells',                          'date'=>'May 12, 2019',   'thumb'=>$T, 'url'=>$P.'S08E05'],
    ['ep'=>6,  'title'=>'The Iron Throne',                    'date'=>'May 19, 2019',   'thumb'=>$T, 'url'=>$P.'S08E06'],
  ]],

];

// Flat list for prev/next navigation
$GOT_ALL_EPS = [];
foreach ($GOT_DATA as $sNum => $sData) {
    foreach ($sData['episodes'] as $ep) {
        $GOT_ALL_EPS[] = array_merge($ep, ['season' => $sNum]);
    }
}
$GOT_TOTAL = count($GOT_ALL_EPS);