<?php
function determine_domain_id($conn){
    //SUBDOMAIN ERMITTELN
    $matches = array();

    if (substr_count($_SERVER['HTTP_HOST'], '.')==1) {
        // domain.tld
        preg_match('/^(?P<d>.+)\.(?P<tld>.+?)$/', $_SERVER['HTTP_HOST'], $matches);
    } else {
        // www.domain.tld, sub1.sub2.domain.tld, ...
        preg_match('/^(?P<sd>.+)\.(?P<d>.+?)\.(?P<tld>.+?)$/', $_SERVER['HTTP_HOST'], $matches);
    }

    $subdomain = (isset($matches['sd'])) ? $matches['sd'] : '';
    $domain = isset($matches['d']) ? $matches['d'] : '';
    $tld = isset($matches['tld']) ? $matches['tld'] : '';

    $domain_to_be_loaded = NULL;
    if($subdomain != NULL && $subdomain != '' && $subdomain != 'dev'){
        $domain_to_be_loaded = $subdomain;
        $is_subdomain = 1;
    }else{
        $domain_to_be_loaded = $domain;
        $is_subdomain = 0;
    }
    
    //echo "<div'> <i>$domain_to_be_loaded</i> wird geladen ...</div>"; //style='text-align: center;margin: 0;position: absolute;top: 50%;-ms-transform: translateY(-50%);transform: translateY(-50%);

    //Domain_ID ermitteln
    $sql = "SELECT System_Website.id as websiteID FROM System_Domain, System_Domain_Relation_Website, System_Website WHERE domain_name = '$domain_to_be_loaded' AND subdomain = '$is_subdomain' AND fk_domain = System_Domain.id AND fk_seite = System_Website.id";
    $result = $conn->query($sql);
    $website_array = array();
    while (!empty($row = $result->fetch_assoc())) {
        $websiteId = $row['websiteID'];
        array_push($website_array, $websiteId);
    }
    return $website_array;
}

?>
