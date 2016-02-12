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

    public      $curl_verbose = false;
    public   $use_cache        = TRUE;

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
            'Connection: keep-alive'
        );

        if($JSON)
        {
            $headers[]  =   'Accept: application/json, text/javascript, */*; q=0.01';
        }else{
            $headers[]  =   'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8';
        }
        if($this->curl_verbose) {
            curl_setopt($curl, CURLOPT_STDERR, fopen('php://output', 'w+'));
            curl_setopt($curl, CURLOPT_VERBOSE, 1);
        }
        if($post){
            $string = http_build_query($post);
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
        return '/tmp/cookie-agoda.txt';
    }

    public function cache_get($url, $post = NULL, $JSON = false, $disable_cache = false)
    {
        if(!$this->use_cache || $disable_cache) return $this->getCurl($url, $post, $JSON);

        $cachefile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "agoda-" .  md5($url . print_r($post, true)) . '.html';
        if(!file_exists($cachefile))
        {
            $content = $this->getCurl($url, $post, $JSON);
            if($content)
                file_put_contents($cachefile, $content);
        }else {
            if($this->curl_verbose)
            {
                echo "Cache: $url " . print_r($post, true), "\n";
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

    public function submit_search_old($action, $params)
    {
        $post = array(
            'SearchInput' => 'Paris',
            'SeachDefaultText' => 'Bali',
            'SearchCheckIn' => '03/11/2016',
            'SearchCheckOut' => '03/13/2016',
            'IsAutoCompleteEnabled' => 'True',
            'ActionForm' => '',
            'CityId' => '17193',
            'CityTranslatedName' => '0',
            'CountryId' => '0',
            'ObjectId' => '0',
            'PageTypeId' => '0',
            'MappingTypeId' => '0',
            'LastSearchInput' => 'Bali',
            'UserLatitude' => '0',
            'UserLongtitude' => '0',
            'UserCityID' => '0',
            'IsHotel' => 'False',
            'Rooms' => '1',
            'Adults' => '2',
            'Children' => '0',
            'OneRoomText' => '1 Room',
            'OneAdultText' => '1 Adult ',
            'OneChildText' => '1 Child',
            'XRoomsText' => '{0} Rooms',
            'XAdultsText' => '{0} Adults',
            'XChildrenText' => '{0} Children',
            'CheckInDay' => '11',
            'CheckInMonthYear' => '03-2016',
            'NightCount' => '2',
        );

        $response = $this->cache_get($action, $post);

        file_put_contents('test.html', $response);
        return phpQuery::newDocument($response);
    }

    public function doSearchInit()
    {
        $data = $this->cache_get('http://' . $this->domain . '/');

        $m  =   preg_match('#<form action="([^"]*?)" class="oneline-sb-form"#', $data, $matches);

        $action = preg_replace("#[\r\n\t\s]+#", '', $matches[1]);

        $doc    =   $this->submit_search($action, "Paris", "2016-03-11", "2016-03-13");

        $data   =   $this->extractPageData($doc);

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
            $data = $this->doSearchInit();
        }else{
            $data   =   $this->doSearchAPI($data);
        }
        return $data;
    }

    function doSearchAll($callback)
    {
        $data = NULL;
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
        if($f->PageNumber < $f->PageSize)
        {
            return true;
        }

        return false;
    }

    public function gotoNextPage(&$data)
    {
        $searchCriteria =   &$data['initialResults']['SearchCriteria'];

        if($searchCriteria['PageNumber']    ==  1)
        {
            $searchCriteria['PageNumber']   =   3;
        }else{
            $searchCriteria['PageNumber']++;
        }

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

}