<?php

/**
 * Project: AgodaScrapper
 *
 * @author Amado Martinez <amado@projectivemotion.com>
 */
class AgodaScrapper
{
    protected   $domain = 'www.agoda.com';
    protected   $last_url =   '';
    protected   $HotelFilter    =   '';

    public      $curl_verbose   = false;
    public      $use_cache      = false;

    public      $page_size  =   30;


    protected function getCurl($url, $post = NULL, $JSON = false)
    {
        if($url[0] == '/')
            $url = "http://$this->domain$url";

        $curl = curl_init($url);
        $headers = 	array(
            'Origin: http://' . $this->domain,
//            'Accept-Encoding: gzip, deflate',
            'Accept-Language: en-US,en;q=0.8,es;q=0.6',
            'Upgrade-Insecure-Requests: 1',
            'User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.2526.106 Safari/537.36',
            'Cache-Control: max-age=0',
            'Connection: keep-alive', 'Expect: '
        );

        $is_payload =   is_string($post);

        if($JSON)
        {
            $headers[]  =   'Accept: application/json, text/javascript, */*; q=0.01';
            $headers[]  =   'X-Requested-With: XMLHttpRequest';
            if($is_payload)
                $headers[]  =   'Content-Type: application/json';
        }else{
            $headers[]  =   'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8';
        }
        if($this->curl_verbose) {
            curl_setopt($curl, CURLOPT_STDERR, fopen('php://output', 'w+'));
            curl_setopt($curl, CURLOPT_VERBOSE, 1);
        }
        if($post){
            $string = $is_payload ? $post : http_build_query($post);
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $string);
        }

        if($this->last_url)
            $headers[]  =   'Referer: ' . $this->last_url;

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);


        $cookiefile = $this->getCookieFile();
        curl_setopt($curl, CURLOPT_COOKIEJAR, $cookiefile);
        curl_setopt($curl, CURLOPT_COOKIEFILE, $cookiefile);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        $response = curl_exec($curl);

        $this->last_url =   $url;

        curl_close($curl);
        return $response;
    }

    public function getCookieFile()
    {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cookie-agoda.txt';
    }

    public function getCacheDir()
    {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR;
//        return './';
    }

    public function cache_get($url, $post = NULL, $JSON = false, $disable_cache = false)
    {
        if(!$this->use_cache || $disable_cache) return $this->getCurl($url, $post, $JSON);

        $cachefile = $this->getCacheDir() . "agoda-" .  md5($url . print_r($post, true)) . '.html';
        if(!file_exists($cachefile))
        {
            $content = $this->getCurl($url, $post, $JSON);
            if($content)
                file_put_contents($cachefile, $content);
        }else {
            if($this->curl_verbose)
            {
                echo "Cache: $url\nFile: $cachefile\n";
            }
            $content = file_get_contents($cachefile);
        }

        return $content;
    }

    public function submit_search($action, $city, $checkin, $checkout)
    {
        $date_time  =   strtotime($checkin);
        $ci_monthyear   =   date("m-Y", $date_time);
        $ci_day         =   date("d", $date_time);
        $num_nights     =   date_create($checkin)->diff(date_create($checkout))->format("%a");

        $post = array (
            'SearchInput' => $city,
            'SeachDefaultText' => 'Moscow',
            'CheckInDay' => $ci_day,
            'CheckInMonthYear' => $ci_monthyear,
            'NightCount' => $num_nights,
            'SelectedGuestOption' => '1',
            'SelectedRoomOption' => '1',
            'SelectedAdultOption' => '1',
            'SelectedChildrenOption' => '0',
            'search-submit' => 'Search',
            'CurrentCountryID' => '0',
            'IsAutoCompleteEnabled' => 'True',
            'ActionForm' => '',
            'CityId' => '0',
            'CityTranslatedName' => '0',
            'CountryId' => '0',
            'ObjectId' => '0',
            'PageTypeId' => '0',
            'MappingTypeId' => '0',
            'LastSearchInput' => '',
            'UserLatitude' => '0',
            'UserLongtitude' => '0',
            'UserCityID' => '0',
            'IsHotel' => 'False',
        );

        $response = $this->cache_get($action, $post);

//        file_put_contents('test.html', $response);
        return $response;
    }

    public function doSearchInit($city, $checkin, $checkout, $currencyCode = 'EUR')
    {
        $page = $this->cache_get('http://' . $this->domain . '/');

        $m  =   preg_match('#<form action="([^"]*?)" class="oneline-sb-form"#', $page, $matches);

        if(!$m)
            throw new Exception('Unable to find search form.');

        $action = preg_replace("#[\r\n\t\\s]+#", '', $matches[1]);

        $this->setCurrency($currencyCode);

        $doc    =   $this->submit_search($action, $city, $checkin, $checkout);

        $data   =   $this->extractPageData($doc);
        $data['initialResults']['SearchCriteria']['PageSize']   =   $this->page_size;

        if($this->HotelFilter)
            $data['initialResults']['SearchCriteria']['Filters']['HotelName']   =   $this->HotelFilter;

        return $data;
    }

    public function doSearchAPI($data)
    {
        $url    =   '/api/en-us/Main/GetSearchResultList';
        $response   =   $this->cache_get($url, $data['initialResults']['SearchCriteria'], true);

        $data_new   =   json_decode($response, true);

//        file_put_contents('last.html', $response);
        return array('initialResults' => $data_new);
    }

    function doSearch($data = NULL)
    {
        if(!$data)
        {
            throw new Exception("Must call doSearchInit.");
        }else{
            $data   =   $this->doSearchAPI($data);
        }
        return $data;
    }

    function doSearchAll($callback, $data = NULL)
    {
        do{
            $data = $this->doSearch($data);
            if(!$data) break;

            if($this->curl_verbose)
                echo "Printing Page #", $data['initialResults']['PageNumber'], "\n";

            $hotels =   $this->getHotels($data);

            $continue   =   $callback($hotels, $data['initialResults']['PageNumber']);

            if(!$continue)
                break;
        }while($this->hasMorePages($data) && $this->gotoNextPage($data));
    }

    public function hasMorePages($data)
    {
        if(!is_array($data)) return false;

        $f = (object)$data['initialResults'];
        if($f->PageNumber < $f->TotalPage)
        {
            return true;
        }

        return false;
    }

    public function gotoNextPage(&$data)
    {
        $searchCriteria =   &$data['initialResults']['SearchCriteria'];

        $searchCriteria['PageNumber']++;

        return true;
    }

    public function getHotels($data)
    {
        return $data['initialResults']['ResultList'];
    }

    public function extractPageData($html)
    {
        $page_info  =   array('header' => "Failed", 'initialResults' => NULL, 'items' => NULL);
        $m = preg_match('#<script>[\s\r\n]*var initialResults = ({[\s\S]*?});[\s\r\n]*var params = {};#', $html, $matches);
        if(!$m)
        {
            throw new Exception('Failed to receive a valid results page.');
        }

        $page_info['initialResults']    =   json_decode($matches[1], true);

        $doc = phpQuery::newDocument($html);

        $page_info['header']   =   $doc['.searchlist-header']->text();

        $items  =   $doc['.ssr-search-result'];

        $all_items  =   array();
        foreach($items as $item_el)
        {
            $el = pq($item_el);
            $data = array();
            $data['name']   = $el->find('.hotel-info h3')->text();
            $data['rate']   = $el->find('.price-exclude h5')->text();

            $all_items[]    =   $data;
        }

        $page_info['items'] =   $all_items;

        return $page_info;
    }

    public function setCurrency($CurrencyCode)
    {
        $result =   $this->getCurl('/exptest/Master/SetCurrencyLabel', array('value'=> strtoupper($CurrencyCode)), false);

        $json   =   json_decode($result);
        return $json && ($json->success == 'true');
    }

    public function getNetHotelPrice($hotel)
    {
        $HotelURL   =   $hotel['HotelUrl'];
        $referrer = "http://$this->domain$HotelURL";
        $page_result    =   $this->cache_get($referrer);

        $doc = phpQuery::newDocument($page_result);

        $prebookURL =   $doc['table#room-grid-table tbody']->attr('data-prebook-url');

        $roomVARS   =   $doc['tr#room-1']->attr('data-bargs');

        $post_vars  =   array('bargs' => $roomVARS, 'exbed' => array(), 'rooms' => 1);

        $checkout_page    =   $this->cache_get($prebookURL, json_encode($post_vars), true);

        $checkout_redir  =   json_decode($checkout_page);

        if(!$checkout_redir)
            throw new Exception("Failed to recieve info.");

        $this->last_url =   $referrer;

        $checkout_info  =   $this->cache_get(html_entity_decode($checkout_redir->action), array('arg' => $checkout_redir->arg));

        $m  =   preg_match('#var\s+p\s*=\s*"([^"]*?)";#', $checkout_info, $pmatch);

        if(!$m)
            die("Unable to get p.");

        $pvar = urldecode($pmatch[1]);
        $method = 'https://ash.secure.agoda.com/b/book.aspx/GetBookingDetail?p=' . $pvar  . '&nocache=' . time();

        $json   =   $this->cache_get($method, '{}', true);

        $obj    =   json_decode($json);
//
//        $doc    =   phpQuery::newDocument($checkout_info);
//        $price_str  =   $doc['#pnlTotalPrice .blackbold:last']->text();
        $finalPrice =   str_replace(',', '', $obj->d->FinalPriceIncludedExcludedTax);

        return $finalPrice;
    }

    public function setHotelFilter($HotelFilter)
    {
        $this->HotelFilter = $HotelFilter;
    }

    public function getHotelFilter()
    {
        return $this->HotelFilter;
    }
}