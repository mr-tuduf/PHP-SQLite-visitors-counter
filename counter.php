<?php
	if(session_status() == PHP_SESSION_NONE || session_id() == '') session_start();

    // подключаем библиотеку для работы с базой данных SQLite
    $dbfolder = $_SERVER["DOCUMENT_ROOT"]."/private/db/";
    $dbname = "hits.sqlite";

// создаем таблицу для хранения статистики
if(!file_exists($dbfolder.$dbname))
    {
        $db = new PDO("sqlite:".$dbfolder.$dbname);
        $dbcreates = ['CREATE TABLE IF NOT EXISTS visitors (
                            id INTEGER PRIMARY KEY AUTOINCREMENT,
                            ip TEXT,
                            total_hits_per_day INTEGER,
                            country TEXT,
                            browser TEXT,
                            is_bot INTEGER,
                            robot TEXT,
                            timestamp DATE,
                            http_user_agent TEXT,
                            hash_client_ip VARCHAR(255))',
                        'CREATE TABLE IF NOT EXISTS visitors_archive (
                            date_time DATE,
                            unique_hits INTEGER,
                            total_hits INTEGER,
                            UNIQUE(date_time))',
                        'CREATE TABLE IF NOT EXISTS visitors_request (
                            id INTEGER PRIMARY KEY AUTOINCREMENT,
                            request_uri TEXT,
                            total_hits INTEGER)'];
        
        foreach ($dbcreates as $dbcreate) {
            $db->exec($dbcreate);

            // check for success
            if (file_exists($dbfolder.$dbname)) {
                echo "Database $dbfolder.$dbname was created, installation was successful.";
            } else {
                echo "Database $dbfolder.$dbname was not created, installation was NOT successful. Missing folder write rights?";
            }

        }
    } else {
        $db = new PDO("sqlite:".$dbfolder.$dbname);
    }

// определяем IP-адрес посетителя
$client_ip = $_SERVER['REMOTE_ADDR'];

// определяем страну посетителя через сайт ipinfo.io
$info = json_decode(file_get_contents("http://ipinfo.io/{$client_ip}/json")); 

// определяем браузер посетителя
$user_agent = $_SERVER['HTTP_USER_AGENT'];
$browser = '';

// определяем запрашиваемую страницу
$requested_uri = $_SERVER['REQUEST_URI'];

$user_hash = hash_hmac('sha256', "$client_ip", "$user_agent");

if (empty($user_agent)) {$user_agent = "Unknown";}
if (empty($info->country)) {$info->country = "Unknown";} 
if (empty($client_ip)) {$client_ip = "Not Resolved";} 

// определяем, является ли посетитель ботом
$is_bot = 0;

$robot = '';
$robots = array(
            "YandexBot"             => "Yandex Bot",
            "YandexBlogs"           => "Yandex Blogs Bot",
            "YandexMedia"           => "Yandex Media Bot",
            "YandexVideo"           => "Yandex Video Bot",
            "YandexMedia"           => "Yandex Media Bot",
            "YandexImages"          => "Yandex Images Bot",
            "rambler"               => "Rambler Bot",
            "mail.ru"               => "Mail.Ru Bot",
            "GoogleBot"             => "Googlebot",
            "msnbot"                => "MSNBot",
            "slurp"                 => "Inktomi Slurp",
            "yahoo"                 => "Yahoo",
            "askjeeves"             => "AskJeeves",
            "fastcrawler"           => "FastCrawler",
            "infoseek"              => "InfoSeek Robot 1.0",
            "lycos"                 => "Lycos",
            "PetalBot"              => "Petal Bot (c) Huawei",
            "Exabot"                => "Exaled (France)",
            "Barkrowler"            => "eXenSa",
            "DomainCrawler"         => "Domain Crawler",
            "Nimbostratus-Bot"      => "Cogent Communications",
            "Bingbot"               => "Bingbot",
            "Yahoo"                 => "Yahoo!",
            "AdsBot-Google"         => "Google Ads Bot",
            "Mediapartners-Google"  => "Google Adsense Bot",
            "PIs-Google"            => "APIs Google Bot",
            "CensysInspect"         => "Censys Inspect Bot",
            "facebookexternalhit"   => "Facebook Crawler",
            "InternetMeasurement"   => "internet-measurement.com",
            "K7MLWCBot"             => "K7 Antivirus",
            "ALittle Client"        => "ALittle Client (bad)",
            "BSbot"                 => "Robot BSbot 11 monthly copyright check",
            "BLEXBot"               => "BLEXBot site crawler",
            "Semrush"               => "Semrush SEO",
            "DataForSeoBot"         => "DataForSEO Link Bot",
            "AhrefsBot"             => "Ahrefs Bot",
            "NetcraftSurveyAgent"   => "Netcraft Ltd.",
            "Palo Alto Networks company" => "Palo Alto Networks company",
            "Konturbot"             => "Kontur bot",
            "StatOnlineRuBot"       => "Stat Online Ru Bot",
            
    );

if (strpos($user_agent, 'Firefox') !== false) {
    $browser = 'Firefox';
} elseif (strpos($user_agent, 'Chrome') !== false) {
    $browser = 'Chrome';
} elseif (strpos($user_agent, 'Safari') !== false) {
    $browser = 'Safari';
} elseif (strpos($user_agent, 'Opera') !== false) {
    $browser = 'Opera';
} elseif (strpos($user_agent, 'Edge') !== false) {
    $browser = 'Edge';
} elseif (strpos($user_agent, "MSIE") !== false) {
    $browser = "Internet Explorer";
} else {
    $browser = 'Unknown';
}

if (is_array($robots) AND count($robots) > 0) {
            foreach ($robots as $key => $val) {
                if (preg_match("|".preg_quote($key)."|i", $user_agent)) {
                    $is_bot = 1;
                    $robot = $val;
                }
            }
        }

// проверка своих айпи, чтобы не забивать базу
$its_me = false;
$my_ip = ["127.0.0.1"];

if (in_array($client_ip, $my_ip)) {
    $its_me = true;
}
$curent_date = date('Y-m-d');

if (!$its_me AND !isset($_SESSION['user_is_logged_in'])) {

//проверяем был ли пользователь сегодня на сайте
    $query_statement = $db->query("SELECT * FROM visitors WHERE hash_client_ip = '$user_hash' AND timestamp = '$curent_date'");
    $returned_record = $query_statement->fetchAll();

if($returned_record) {
    // обновляем записи в базе данных
    $db->exec("UPDATE visitors SET total_hits_per_day = total_hits_per_day + 1 WHERE hash_client_ip='$user_hash' AND timestamp = '$curent_date'");

    $db->exec("INSERT OR IGNORE INTO visitors_archive (date_time, unique_hits, total_hits) VALUES ('$curent_date', '1', '1')");
    $db->exec("UPDATE visitors_archive SET total_hits=total_hits + 1 WHERE date_time = '$curent_date'");
} else {
    // добавляем запись в базу данных
    $db->exec("INSERT INTO visitors (ip, total_hits_per_day, country, browser, is_bot, robot, timestamp, http_user_agent, hash_client_ip) VALUES ('$client_ip', '1', '$info->country', '$browser', '$is_bot','$robot','$curent_date','$user_agent','$user_hash')");
    $db->exec("INSERT OR IGNORE INTO visitors_archive (date_time, unique_hits, total_hits) VALUES ('$curent_date', '0', '0')");
    $db->exec("UPDATE visitors_archive SET unique_hits=unique_hits + 1 WHERE date_time = '$curent_date'");
    $db->exec("UPDATE visitors_archive SET total_hits=total_hits + 1 WHERE date_time = '$curent_date'");
    
}
    // добавляем или обновляем запись о запрашиваемой странице
    // Проверка наличия записи в таблице
    $stmt = $db->query("SELECT * FROM visitors_request WHERE request_uri = '$requested_uri'");
	$page = $stmt->fetchAll();

if ($page) {
    // Если запись существует, обновляем ее
    $db->exec("UPDATE visitors_request SET total_hits=total_hits + 1 WHERE request_uri = '$requested_uri'");
    
} else {
    // Если запись не существует, добавляем новую запись
    $db->exec("INSERT INTO visitors_request (request_uri, total_hits) VALUES ('$requested_uri', '1')");
}
}
?>