<?php

/**
 * HTTP/1.1 200 OK
 * HTTP/1.1 302 Found
 * HTTP/1.0 404 Not Found
 */

class URLChecker
{
    
    private $domain = 'http://www.llgc.org.uk/';
    private $numPages = 100;
    private $ignorePage = array(1, 6, 51, 242);
    
    public $xml;
    
    public function __construct()
    {
        // Create a new XML document
        $this->xml = new SimpleXMLElement('<llgc-pages/>');
        $this->curlWebPage();
        
        // Format our XML 
        $dom                     = new DOMDocument('1.0');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput       = true;
        $dom->loadXML($this->xml->asXML());
        
        // Open our XMl file
        $current = file_get_contents('res.xml');
        // Save the contents to the file
        file_put_contents('res.xml', $dom->saveXML());
        
        // Header('Content-type: text/xml');
        print($this->xml->asXML());
    }
    
    /**
     * Function to loop through each page of the website
     */
    public function curlWebPage()
    {
        // Loop through all the pages in the site
        for ($i = 1; $i <= $this->numPages; $i++) {
            
            // ignore the pages we don't want to check
            if (!in_array($i, $this->ignorePage)) {
                $httpResponse = $this->httpResponse($this->domain . 'index.php?id=' . $i);
                
                echo "page $i $httpResponse \n";
                $xml      = $this->xml->addChild('page');
                $pageId   = $xml->addChild('pageId', $i);
                $response = $xml->addChild('response', $httpResponse);
                
                if ($httpResponse == 200) {
                    $this->getPageContent($this->domain . 'index.php?id=' . $i, $xml);
                }
            }
        }
    }
    
    /**
     * Function to curl a webpage and return a http response
     * @param $url string - the url of the webpage
     * @return int - http response of the curl request
     */
    public function httpResponse($url)
    {
        // Create a curl handle
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        // Execute
        $response = curl_exec($ch);
        
        // Then, after your curl_exec call:
        $header_size = curl_getinfo($ch);
        // Close handle
        curl_close($ch);
        
        return $header_size['http_code'];
    }
    
    /**
     * Function to get the content on the page we're looping through
     * @param $page string - the HTML content of the page
     * @param $xml xml - dom element to create a new child
     */
    public function getPageContent($page, $xml)
    {
        $url = file_get_contents($page);
        $dom = new DOMDocument();
        @$dom->loadHTML($url);
        $xpath = new DOMXPath($dom);
        $div   = $xpath->query('//div[@id="llgc_main_content"]');
        $div   = $div->item(0);
        $file  = $dom->saveXML($div);
        
        // Create XML child for broken links
        $brokenLink = $xml->addChild('links');
        // Get all links on the page
        $this->getPageLinks($file, $brokenLink);
        
        //Create XML child for broken images
        $brokenImage = $xml->addChild('images');
        // Get all images on the page
        $this->getPageImages($file, $brokenImage);
    }
    
    /**
     * Function to check the http response of the links on the page
     * @param $file string - html of the page
     * @param $brokenLink - dom element to create a new child
     */
    public function getPageLinks($file, $brokenLink)
    {
        //echo $file;
        $dom = new DOMDocument();
        @$dom->loadHTML($file);
        $links = $dom->getElementsByTagName('a');
        
        //Iterate over the extracted links and display their URLs
        foreach ($links as $link) {
            
            $linkURL = $link->getAttribute('href');
            
            if (preg_match("/http(.?)/", $linkURL)) {
                
                $response = $this->httpResponse($linkURL);
                
                $outputURL = str_replace('&', '&amp;', $linkURL);
                
                $page = $brokenLink->addChild('page_link', $outputURL);
                $page->addAttribute('http_response', $response);
            }
        }
    }
    
    /**
     * Function to check the http response of the images on the page
     * @param $file string - html of the page
     * @param $brokenLink - dom element to create a new child
     */
    public function getPageImages($file, $brokenImage)
    {
        $dom = new DOMDocument();
        @$dom->loadHTML($file);
        $images = $dom->getElementsByTagName('img');
        foreach ($images as $image) {
            
            $linkURL = $image->getAttribute('src');
            
            // Check to see if the images are relative
            if (substr_compare($linkURL, "typo3temp", 0, 10)) {
                $linkURL = $this->domain . $linkURL;
            }
            
            elseif (substr_compare($linkURL, "uploads", 0, 7)) {
                $linkURL = $this->domain . $linkURL;
            } elseif (substr_compare($linkURL, "fileadmin", 0, 10)) {
                $linkURL = $this->domain . $linkURL;
            }
            
            $response = $this->httpResponse($linkURL);
            
            $outputURL = str_replace('&', '&amp;', $linkURL);
            
            $page = $brokenImage->addChild('page_image', $outputURL);
            $page->addAttribute('http_response', $response);
        }
    }
}

new URLChecker();
