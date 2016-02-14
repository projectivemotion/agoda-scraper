<?php
/**
 * Project: AgodaScrapper
 *
 * @author Amado Martinez <amado@projectivemotion.com>
 */
// Used for testing. Run from command line.
if(!isset($argv))
    die("Run from command line.");

// copied this from doctrine's bin/doctrine.php
$autoload_files = array( __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../autoload.php');

foreach($autoload_files as $autoload_file)
{
    if(!file_exists($autoload_file)) continue;
    require_once $autoload_file;
}
// end autoloader finder
if($argc < 2)
{
    die("Args are: $argv[0] [clean|use_cache=>0,1]");
}

if($argv[1] == 'clean')
{
    echo "Removing cache files files..";
    system("rm " . sys_get_temp_dir() . "/agoda-*.html");
    exit;
}

$Agoda = new AgodaScrapper();
$Agoda->curl_verbose    =   0;
$Agoda->use_cache       =   $argv[1] == '1';

$Agoda->setHotelFilter('Hyatt');

$data = $Agoda->doSearchInit('Paris', '2016-04-01', '2016-04-03', 'EUR');

$stdout = fopen('php://output', 'w');

$Agoda->doSearchAll(function ($hotels, $page_num) use (&$stdout, $Agoda) {
    foreach($hotels as $hotel)
    {
        $obj = (object)$hotel;
        $mydata =   array($obj->TranslatedHotelName,
                $obj->CurrencyCode,
                $obj->FormattedDisplayPrice,
                number_format($Agoda->getNetHotelPrice($hotel), 2)
                );
        fputcsv($stdout, $mydata);
    }
    sleep(3);
    if($page_num > 5)
        return false;
    return true;
}, $data);

fclose($stdout);
