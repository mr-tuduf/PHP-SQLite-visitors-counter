<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('UTC');

session_name('SESSID');
session_start();

  // подключаем библиотеку для работы с базой данных SQLite
  $dbfolder = $_SERVER["DOCUMENT_ROOT"]."/private/db/";
	$dbname = "hits.sqlite";
	
  $db = new SQLite3($dbfolder.$dbname);

  // Получение статистики посещенных страниц
  $query = 'SELECT * FROM visitors_request ORDER BY total_hits DESC';
  $result = $db->query($query);

  // получаем общее количество посетителей
  $total_visitors = $db->querySingle('SELECT SUM(total_hits_per_day) FROM visitors');

  // получаем общее количество уникальных посетителей за текущий месяц
  $current_month = date('Y-m');
  $unique_visitors = $db->querySingle("SELECT COUNT(DISTINCT hash_client_ip) FROM visitors WHERE strftime('%Y-%m', timestamp) = '{$current_month}'");

  // получаем статистику браузеров
  //$browser_stats = $db->query('SELECT browser, COUNT(*) as count FROM visitors GROUP BY browser ORDER BY count DESC');

  // получаем статистику ботов
  $bot_stats = $db->querySingle('SELECT COUNT(*) FROM visitors WHERE is_bot = "1"');

  // определяем период для отображения графика
  $period = isset($_POST['period']) ? $_POST['period'] : 'week';

  // определяем начало и конец периода для выборки данных из базы
  switch ($period) {
    case 'week':
      $start_date = date('Y-m-d', strtotime('-1 week'));
      $end_date = date('Y-m-d');
      break;
    case 'month':
      $start_date = date('Y-m-d', strtotime('-1 month'));
      $end_date = date('Y-m-d');
      break;
    case 'year':
      $start_date = date('Y-m-d', strtotime('-1 year'));
      $end_date = date('Y-m-d');
      break;
    default:
      $start_date = date('Y-m-d', strtotime('-1 week'));
      $end_date = date('Y-m-d');
      break;
  
  // получаем данные для графика
$chart_data = $db->query("SELECT
    date(timestamp) as date,
    SUM(total_hits_per_day) as total_visitors,
    COUNT(DISTINCT hash_client_ip) as unique_visitors,
    SUM(is_bot) as bots
    FROM visitors
    WHERE date(timestamp) BETWEEN '{$start_date}' AND '{$end_date}'
    GROUP BY date
    ORDER BY date ASC");
  }

// проверяем, была ли отправлена форма
if (isset($_POST['start_date']) && isset($_POST['end_date'])) {
    // определяем начальную и конечную даты
    $start_date = date('Y-m-d', strtotime($_POST['start_date']));
    $end_date = date('Y-m-d', strtotime($_POST['end_date']));

    // выполняем запрос к базе данных SQLite
    $query = "SELECT
    date(timestamp) as date,
    SUM(total_hits_per_day) as total_visitors,
    COUNT(DISTINCT hash_client_ip) as unique_visitors,
    SUM(is_bot) as bots
    FROM visitors
    WHERE date(timestamp) BETWEEN '{$start_date}' AND '{$end_date}'
    GROUP BY date
    ORDER BY date ASC";
    $stmt = $db->prepare($query);
    $chart_data = $stmt->execute();

    // получаем данные о количестве посетителей за каждый день в выбранном периоде
    $date_stats = array();
    while ($row = $chart_data->fetchArray()) {
        $date_stats[$row['date']] = $row['total_visitors'];
    }
} else {
    // если форма не была отправлена, определяем начальную и конечную даты по умолчанию
    $end_date = date('Y-m-d');
    $start_date = date('Y-m-d', strtotime('-1 week', strtotime($end_date)));

    // выполняем запрос к базе данных SQLite
    $query = "SELECT
    date(timestamp) as date,
    SUM(total_hits_per_day) as total_visitors,
    COUNT(DISTINCT hash_client_ip) as unique_visitors,
    SUM(is_bot) as bots
    FROM visitors
    WHERE date(timestamp) BETWEEN '{$start_date}' AND '{$end_date}'
    GROUP BY date
    ORDER BY date ASC";
    $stmt = $db->prepare($query);
    $chart_data = $stmt->execute();

    // получаем данные о количестве посетителей за каждый день в выбранном периоде
    $date_stats = array();
    while ($row = $chart_data->fetchArray()) {
        $date_stats[$row['date']] = $row['total_visitors'];
    }
}

// Выводим на экран статистику посещенных страниц
//echo '<h2>Статистика посещенных страниц:</h2>';
//echo '<table>';
//echo '<tr><th>URI</th><th>Количество просмотров</th></tr>';
//while ($page = $result->fetchArray()) {
//    echo '<tr><td>' . htmlspecialchars($page['request_uri']) . '</td><td>' . $page['total_hits'] . '</td></tr>';
//}
//echo '</table>';

// формируем данные для отображения графика
$chart_labels = array();
$chart_total_visitors = array();
$chart_unique_visitors = array();
$chart_bots = array();

while ($row = $chart_data->fetchArray(SQLITE3_ASSOC)) {
  $unique = $row["unique_visitors"];
  $total = $row["total_visitors"];
  $bots = $row["bots"];
  $name = $row["date"];

    array_push($chart_unique_visitors, $unique);
    array_push($chart_total_visitors, $total);
    array_push($chart_bots, $bots);
    array_push($chart_labels, $name);
}

// получаем данные о браузерах посетителей
// формируем данные для отображения графика
$chart_browser_name = array();
$chart_browser_count = array();

$browser_query = 'SELECT browser, COUNT(*) AS count FROM visitors WHERE browser != "Unknown" GROUP BY browser ORDER BY count DESC';
$browser_results = $db->query($browser_query);

while ($row = $browser_results->fetchArray(SQLITE3_ASSOC)) {
  $browser_name = $row["browser"];
  $browser_count = $row["count"];

    array_push($chart_browser_name, $browser_name);
    array_push($chart_browser_count, $browser_count);
}

// получаем данные о странах посетителей
// формируем данные для отображения графика
$chart_country_name = array();
$chart_country_count = array();

$country_query = 'SELECT country, COUNT(*) AS count FROM visitors GROUP BY country ORDER BY count DESC LIMIT 15';
$country_results = $db->query($country_query);

while ($row = $country_results->fetchArray(SQLITE3_ASSOC)) {
  $browser_name = $row["country"];
  $browser_count = $row["count"];

    array_push($chart_country_name, $browser_name);
    array_push($chart_country_count, $browser_count);
}

// получаем данные о странах посетителей
// формируем данные для отображения графика
$chart_bot_name = array();
$chart_bot_count = array();

// получение списка ботов из таблицы visitors
  $bot_query = "SELECT robot, COUNT(*) as total FROM visitors WHERE is_bot = '1' GROUP BY robot ORDER BY total DESC";
  $bot_result = $db->query($bot_query);

while ($row = $bot_result->fetchArray(SQLITE3_ASSOC)) {
  $bot_name = $row["robot"];
  $bot_count = $row["total"];

    array_push($chart_bot_name, $bot_name);
    array_push($chart_bot_count, $bot_count);
}
// закрываем соединение с базой данных
//$db->close();

$unknownPages = (isset($unknownPages)) ? $unknownPages : 'Unknown Page';
$visitors_online = $db->query('SELECT page_title, page_url, COUNT(page_url) AS count FROM visitors_online GROUP BY page_url ORDER BY count DESC');

?>

<body>
<h4><?php  if ($visitors_online)
    {
        while ($result = $visitors_online->fetchArray(SQLITE3_ASSOC))
        {
			$page_url = $result["page_url"];
			$page_title = $result["page_title"];
			$page_count = $result["count"];
			
            if (empty($result['page_title']))
            {
                $result['page_title'] = $unknownPages;
            }

            echo "Visitors online: <b> $page_count[count]</b><a href='$page_url' target='_top'>$page_count</a>";
			echo "</br>";
			var_dump($result);
        }

    }?> </h4>
<div id="demo" style="display: block; box-sizing: border-box; height: 400px; width: 800px;">
<th><center><h3>Visitors stats:</h3></center></th>
<center><h4><?php echo "Shows from $start_date to $end_date "?> </h4></center>
<canvas id="chart"></canvas></br></br>
<table>
<tr>
<th><center><h3>Country stats:</h3></center></th>
<th><center><h3>Browser stats:</h3></center></th>
<th><center><h3>Crawl stats:</h3></center></th>
</tr>

<tr>
<th><canvas id="country-chart" width="500" height="300"></canvas></th>
<th><canvas id="browser-chart" width="300" height="300"></canvas></th>
<th><canvas id="bot-polar-area-chart" width="350" height="350"></canvas></th>
</tr>
</table>
</div>

<br/><br/>
<!-- общий график посетитетелй -->
<script>
    var unique_array = [<?php echo '"'.implode('","', $chart_unique_visitors).'"' ?>];
    var total_array = [<?php echo '"'.implode('","', $chart_total_visitors).'"' ?>];
    var bot_array = [<?php echo '"'.implode('","', $chart_bots).'"' ?>];
    var name_array = [<?php echo '"'.implode('","', $chart_labels).'"' ?>];

    var colour_array = [];

    var dynamicColour = function() {
    var r = Math.floor(Math.random() * 255);
    var g = Math.floor(Math.random() * 255);
    var b = Math.floor(Math.random() * 255);
      return "rgb(" + r + "," + g + "," + b + ")";
    };

  for (var i in unique_array) {
      colour_array.push(dynamicColour());
    }
  
  for (var i in total_array) {
      colour_array.push(dynamicColour());
    }

  for (var i in bot_array) {
      colour_array.push(dynamicColour());
    }
	
var ctx = document.getElementById("chart").getContext("2d");
var myChart = new Chart(ctx, {
	type: 'line',
	data: 
	{labels: name_array,
		datasets: [
			{
			label: "Total: <?php echo $total_visitors; ?>",
			data: total_array,
			fill: false,
			backgroundColor: "rgba(255, 99, 132, 0.2)",
			pointStyle: 'circle',
			pointRadius: 6,
			borderColor: "rgba(255, 99, 132, 1)",
			pointHoverRadius: 10,
			borderWidth: 2,
			hoverBorderWidth: 2
			},{
			label: "Unique: <?php echo $unique_visitors; ?>",
			data: unique_array,
			fill: false,
			backgroundColor: "rgba(54, 162, 235, 0.2)",
			pointStyle: 'rectRounded',
			pointRadius: 6,
			borderColor: "rgba(54, 162, 235, 1)",
			pointHoverRadius: 10,
			borderWidth: 2,
			hoverBorderWidth: 2
			},{
			label: "Bots: <?php echo $bot_stats; ?>",
			data: bot_array,
			fill: false,
			backgroundColor: "rgba(255, 206, 86, 0.2)",
			pointStyle: 'triangle',
			pointRadius: 6,
			borderColor: "rgba(255, 206, 86, 1)",
			pointHoverRadius: 10,
			borderWidth: 2,
			hoverBorderWidth: 2}]
	},
	options: {
		tension: 0.8,
		scales: {
            x: {
                beginAtZero: true
            },
            y: {
                beginAtZero: true
            },
		},
}});
</script>

<!-- график статистики стран -->
<script>
var name_country_array = [<?php echo '"'.implode('","', $chart_country_name).'"' ?>];
var count_country_array = [<?php echo '"'.implode('","', $chart_country_count).'"' ?>];

var colour_array = [];

      var dynamicColour = function() {
      var r = Math.floor(Math.random() * 255);
      var g = Math.floor(Math.random() * 255);
      var b = Math.floor(Math.random() * 255);
      return "rgba(" + r + "," + g + "," + b + "," + 0.2 + ")";
    };

for (var i in count_country_array) {
      colour_array.push(dynamicColour());
    }

for (var i in name_country_array) {
      colour_array.push(dynamicColour());
    }

var ctx = document.getElementById("country-chart").getContext("2d");
var countryChart = new Chart(ctx, {
	type: "bar",
	data: {
		labels: name_country_array,
			datasets: [{
				label: "Visitors by Country",
				data: count_country_array,
				backgroundColor: colour_array,
				borderColor: "rgba(54, 162, 235, 1)",
				borderWidth: 1}]
	},
	options: {
		scales: {
			y: {
				beginAtZero: true
			}
		}
	}
});
</script>

<!-- график статистики браузеров -->
<script>
var name_browser_array = [<?php echo '"'.implode('","', $chart_browser_name).'"' ?>];
var count_browser_array = [<?php echo '"'.implode('","', $chart_browser_count).'"' ?>];

var colour_array = [];

      var dynamicColour = function() {
      var r = Math.floor(Math.random() * 255);
      var g = Math.floor(Math.random() * 255);
      var b = Math.floor(Math.random() * 255);
      return "rgb(" + r + "," + g + "," + b + ")";
    };

for (var i in count_browser_array) {
      colour_array.push(dynamicColour());
    }

for (var i in name_browser_array) {
      colour_array.push(dynamicColour());
    }

var ctx = document.getElementById("browser-chart").getContext("2d");
var browserChart = new Chart(ctx, {
	type: "doughnut",
	data: {
		labels: name_browser_array,
		datasets: [{
			data: count_browser_array,
			backgroundColor: [
				'rgba(255, 99, 132, 0.2)',
				'rgba(255, 159, 64, 0.2)',
				'rgba(255, 205, 86, 0.2)',
				'rgba(75, 192, 192, 0.2)',
				'rgba(54, 162, 235, 0.2)',
				'rgba(153, 102, 255, 0.2)',
				'rgba(201, 203, 207, 0.2)'],
			borderColor: [
				'rgb(255, 99, 132)',
				'rgb(255, 159, 64)',
				'rgb(255, 205, 86)',
				'rgb(75, 192, 192)',
				'rgb(54, 162, 235)',
				'rgb(153, 102, 255)',
				'rgb(201, 203, 207)'],
			borderWidth: 1}]
	}
});
</script>

<!-- график статистики ботов -->
<script>
var name_bot_array = [<?php echo '"'.implode('","', $chart_bot_name).'"' ?>];
var count_bot_array = [<?php echo '"'.implode('","', $chart_bot_count).'"' ?>];

var ctx = document.getElementById('bot-polar-area-chart').getContext('2d');
var myChart = new Chart(ctx, {
    type: 'pie',
    data: {
        labels: name_bot_array,
        datasets: [{
            data: count_bot_array,
            backgroundColor: [
                'rgba(255, 99, 132, 0.5)',
                'rgba(54, 162, 235, 0.5)',
                'rgba(255, 206, 86, 0.5)',
                'rgba(75, 192, 192, 0.5)',
                'rgba(153, 102, 255, 0.5)',
                'rgba(255, 159, 64, 0.5)'],
            borderWidth: 1}]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'right',
            }
        }
    }
});
</script>

<!-- форма выбора предустановленного периода -->
<form method="POST" style="display: inline; padding-left: 50px;">
	<select name="period" id="period">
		<option value="week" ' . ($period == 'week' ? 'selected' : '') . '>this week</option>
		<option value="month" ' . ($period == 'month' ? 'selected' : '') . '>this month</option>
		<option value="year" ' . ($period == 'year' ? 'selected' : '') . '>this year</option>
	</select>
	<input type="submit" value="show">
</form>

<!-- форма выбора периода -->
<form method="post" style="display: inline; padding-left: 200px;">
    <input type="date" name="start_date" value="<?php echo $start_date; ?>">
    <label for="end_date">:</label>
    <input type="date" name="end_date" value="<?php echo $end_date; ?>">
    <button type="submit">show</button>
</form>
<br/><br/>
</body>
</html>