<?php
$cacheFile = __DIR__ . '/feed-cache.json';
$cacheTtlSeconds = 12;

$sources = [
    [
        'label' => 'Twitter (via Nitter RSS)',
        'type' => 'rss',
        'url' => 'https://nitter.net/search/rss?f=tweets&q=goal%20OR%20red%20card%20OR%20full%20time%20OR%20half%20time',
    ],
    [
        'label' => 'Club Announcements (RSS)',
        'type' => 'rss',
        'url' => 'https://www.bbc.co.uk/sport/football/rss.xml',
    ],
    [
        'label' => 'Live Blog (JSON)',
        'type' => 'json',
        'url' => '',
    ],
];

$sampleEvents = [
    [
        'title' => 'GOAL: Harbor United 2-1 Oldbridge',
        'summary' => '84\' - Harbor United score from a quick free kick to edge ahead.',
        'source' => 'Demo feed',
        'timestamp' => date('c', strtotime('-2 minutes')),
    ],
    [
        'title' => 'RED CARD: Atlas City vs Wellington',
        'summary' => '78\' - Wellington reduced to ten men after a late challenge.',
        'source' => 'Demo feed',
        'timestamp' => date('c', strtotime('-5 minutes')),
    ],
    [
        'title' => 'HALF TIME: Northchester 1-0 Riverside',
        'summary' => 'Northchester lead with a first-half strike.',
        'source' => 'Demo feed',
        'timestamp' => date('c', strtotime('-12 minutes')),
    ],
    [
        'title' => 'FULL TIME: Southbay 0-0 Kingsport',
        'summary' => 'Points shared after a tight defensive display.',
        'source' => 'Demo feed',
        'timestamp' => date('c', strtotime('-18 minutes')),
    ],
];

function normalize_event(array $event): array
{
    $text = strtolower($event['title'] . ' ' . $event['summary']);

    if (strpos($text, 'red card') !== false) {
        $event['type'] = 'card';
    } elseif (strpos($text, 'goal') !== false) {
        $event['type'] = 'goal';
    } elseif (strpos($text, 'half time') !== false || strpos($text, 'ht') !== false) {
        $event['type'] = 'ht';
    } elseif (strpos($text, 'full time') !== false || strpos($text, 'ft') !== false) {
        $event['type'] = 'ft';
    } else {
        $event['type'] = 'update';
    }

    return $event;
}

function fetch_rss(string $url, string $label): array
{
    $items = [];
    $rss = @simplexml_load_file($url);

    if (!$rss || !isset($rss->channel->item)) {
        return $items;
    }

    foreach ($rss->channel->item as $item) {
        $items[] = [
            'title' => (string) $item->title,
            'summary' => trim(strip_tags((string) $item->description)),
            'source' => $label,
            'timestamp' => (string) $item->pubDate,
        ];
    }

    return $items;
}

function fetch_json(string $url, string $label): array
{
    $items = [];

    if ($url === '') {
        return $items;
    }

    $response = @file_get_contents($url);
    if ($response === false) {
        return $items;
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        return $items;
    }

    foreach ($data as $entry) {
        $items[] = [
            'title' => $entry['title'] ?? 'Live update',
            'summary' => $entry['summary'] ?? '',
            'source' => $label,
            'timestamp' => $entry['timestamp'] ?? date('c'),
        ];
    }

    return $items;
}

function fetch_feed(array $sources, array $fallback): array
{
    $items = [];

    foreach ($sources as $source) {
        $label = $source['label'] ?? 'Feed';
        $type = $source['type'] ?? 'rss';
        $url = $source['url'] ?? '';

        if ($url === '') {
            continue;
        }

        if ($type === 'json') {
            $items = array_merge($items, fetch_json($url, $label));
        } else {
            $items = array_merge($items, fetch_rss($url, $label));
        }
    }

    if (empty($items)) {
        $items = $fallback;
    }

    $items = array_map('normalize_event', $items);

    usort($items, function ($a, $b) {
        return strtotime($b['timestamp']) <=> strtotime($a['timestamp']);
    });

    return $items;
}

if (isset($_GET['action']) && $_GET['action'] === 'feed') {
    header('Content-Type: application/json');
    $cached = null;

    if (file_exists($cacheFile) && time() - filemtime($cacheFile) < $cacheTtlSeconds) {
        $cached = json_decode(file_get_contents($cacheFile), true);
    }

    if (is_array($cached)) {
        echo json_encode(['items' => $cached]);
        exit;
    }

    $items = fetch_feed($sources, $sampleEvents);
    file_put_contents($cacheFile, json_encode($items));
    echo json_encode(['items' => $items]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Live Vidiprinter</title>
  <style>
    :root {
      color-scheme: dark;
      font-family: "Courier New", Courier, monospace;
      background: #0b0f14;
      color: #e6edf3;
    }

    body {
      margin: 0;
      padding: 32px 20px 56px;
      display: flex;
      justify-content: center;
      align-items: flex-start;
      min-height: 100vh;
      background: radial-gradient(circle at top, #151b23 0%, #0b0f14 55%, #07090c 100%);
    }

    main {
      width: min(960px, 94vw);
      background: rgba(9, 12, 16, 0.92);
      border: 1px solid #1e2633;
      box-shadow: 0 20px 55px rgba(0, 0, 0, 0.5);
      border-radius: 16px;
      padding: 28px 28px 34px;
    }

    header {
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      gap: 16px;
      margin-bottom: 20px;
    }

    h1 {
      font-size: 1.6rem;
      margin: 0;
      letter-spacing: 0.06em;
      text-transform: uppercase;
    }

    header p {
      margin: 8px 0 0;
      color: #9db0c5;
      max-width: 420px;
    }

    .status {
      display: flex;
      flex-direction: column;
      gap: 6px;
      font-size: 0.9rem;
      color: #9db0c5;
    }

    .status span {
      color: #59d185;
      font-weight: 600;
    }

    .feed {
      background: #05070a;
      border: 1px solid #1b2330;
      border-radius: 12px;
      padding: 18px;
      height: 420px;
      overflow-y: auto;
      scrollbar-width: thin;
    }

    .entry {
      padding: 8px 12px;
      border-left: 3px solid transparent;
      margin-bottom: 8px;
      background: rgba(12, 15, 20, 0.8);
      border-radius: 8px;
      animation: slideIn 0.3s ease;
    }

    .entry.goal {
      border-left-color: #f9c74f;
      color: #ffe19c;
    }

    .entry.card {
      border-left-color: #ef233c;
      color: #ffb3bd;
    }

    .entry.ht,
    .entry.ft {
      border-left-color: #4895ef;
      color: #b6d4ff;
    }

    .entry.update {
      border-left-color: #56cfe1;
      color: #c7f4ff;
    }

    .entry .meta {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      font-size: 0.75rem;
      color: #778aa2;
      margin-bottom: 4px;
    }

    .entry .line {
      font-size: 1rem;
      letter-spacing: 0.03em;
    }

    .config {
      margin-top: 16px;
      display: grid;
      gap: 12px;
      background: rgba(7, 10, 14, 0.8);
      border-radius: 12px;
      border: 1px solid #1b2330;
      padding: 14px 16px;
      font-size: 0.85rem;
      color: #8fa1b7;
    }

    .config ul {
      margin: 0;
      padding-left: 18px;
    }

    .footer {
      margin-top: 20px;
      font-size: 0.85rem;
      color: #6f8197;
      display: flex;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 8px;
    }

    button {
      background: #20293a;
      color: #e6edf3;
      border: 1px solid #2b3647;
      border-radius: 8px;
      padding: 8px 14px;
      cursor: pointer;
      font-family: inherit;
    }

    button:hover {
      border-color: #3b4b63;
    }

    @keyframes slideIn {
      from {
        opacity: 0;
        transform: translateY(6px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
  </style>
</head>
<body>
  <main>
    <header>
      <div>
        <h1>Live Vidiprinter</h1>
        <p>Auto-updating scoreline feed driven by live RSS/JSON sources (Twitter via Nitter) with goal, card, and result alerts.</p>
      </div>
      <div class="status">
        <div>Feed status: <span id="feed-status">Connecting</span></div>
        <div>Last update: <span id="last-update">Waiting...</span></div>
      </div>
    </header>

    <section class="feed" id="feed" aria-live="polite"></section>

    <section class="config">
      <strong>Live sources</strong>
      <ul>
        <?php foreach ($sources as $source): ?>
          <li><?php echo htmlspecialchars($source['label']); ?><?php if (!empty($source['url'])): ?> — <?php echo htmlspecialchars($source['url']); ?><?php endif; ?></li>
        <?php endforeach; ?>
      </ul>
      <div>Update <code>$sources</code> in <code>index.php</code> with your own RSS/JSON endpoints or authenticated feed proxy.</div>
    </section>

    <div class="footer">
      <div>Entries are classified by keywords (goal, red card, half time, full time).</div>
      <div>
        <button id="pause-btn" type="button">Pause feed</button>
        <button id="refresh-btn" type="button">Refresh now</button>
      </div>
    </div>
  </main>

  <script>
    const feed = document.getElementById("feed");
    const lastUpdate = document.getElementById("last-update");
    const feedStatus = document.getElementById("feed-status");
    const pauseButton = document.getElementById("pause-btn");
    const refreshButton = document.getElementById("refresh-btn");

    let intervalId = null;
    let latestTimestamp = 0;

    const formatTime = () => {
      const now = new Date();
      return now.toLocaleTimeString("en-GB", {
        hour: "2-digit",
        minute: "2-digit",
        second: "2-digit",
      });
    };

    const addEntry = (entry) => {
      const container = document.createElement("div");
      container.className = `entry ${entry.type}`;
      container.innerHTML = `
        <div class="meta">
          <span>${entry.displayTime}</span>
          <span>${entry.source}</span>
        </div>
        <div class="line">${entry.title}</div>
        ${entry.summary ? `<div class="line">${entry.summary}</div>` : ""}
      `;
      feed.prepend(container);
      lastUpdate.textContent = entry.displayTime;
    };

    const renderFeed = (items) => {
      const freshItems = items
        .map((item) => ({
          ...item,
          timestamp: Date.parse(item.timestamp || new Date().toISOString()),
        }))
        .filter((item) => !Number.isNaN(item.timestamp))
        .filter((item) => item.timestamp > latestTimestamp)
        .sort((a, b) => a.timestamp - b.timestamp);

      freshItems.forEach((item) => {
        latestTimestamp = Math.max(latestTimestamp, item.timestamp);
        addEntry({
          ...item,
          displayTime: formatTime(),
        });
      });
    };

    const fetchFeed = async () => {
      try {
        feedStatus.textContent = "Connected";
        const response = await fetch("?action=feed");
        const data = await response.json();
        renderFeed(data.items || []);
      } catch (error) {
        feedStatus.textContent = "Offline";
      }
    };

    const startFeed = () => {
      if (intervalId) return;
      feedStatus.textContent = "Connected";
      intervalId = setInterval(fetchFeed, 8000);
      fetchFeed();
    };

    const stopFeed = () => {
      clearInterval(intervalId);
      intervalId = null;
      feedStatus.textContent = "Paused";
    };

    pauseButton.addEventListener("click", () => {
      if (intervalId) {
        stopFeed();
        pauseButton.textContent = "Resume feed";
      } else {
        startFeed();
        pauseButton.textContent = "Pause feed";
      }
    });

    refreshButton.addEventListener("click", () => {
      fetchFeed();
    });

    startFeed();
  </script>
</body>
</html>
