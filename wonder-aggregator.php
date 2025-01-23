<?php
global $Wcms;

$WonderAggregator = new WonderAggregator($Wcms);

class WonderAggregator {
    private $Wcms;
    private $feeds = [];
    
    public function __construct($Wcms) {
        $this->Wcms = $Wcms;
        
        if (isset($_POST['rssFeeds'])) {
            $this->Wcms->set('config', 'rssFeeds', $_POST['rssFeeds']);
        }
        
        $this->feeds = $this->getFeeds();
        
        $this->Wcms->addListener('css', [$this, 'css']);
        $this->Wcms->addListener('js', [$this, 'js']);
        $this->Wcms->addListener('settings', [$this, 'settings']);
        $this->Wcms->addListener('menu', [$this, 'menu']);
        $this->Wcms->addListener('page', [$this, 'page']);
    }
    
    public function menu(array $items): array {
        $items[] = [
            'slug' => 'timeline',
            'name' => 'Timeline',
            'content' => ''
        ];
        return $items;
    }
    
    public function page($content): string {
        if ($this->Wcms->currentPage == 'timeline') {
            return $this->display();
        }
        return $content;
    }
    
    public function settings(): string {
        if (!$this->Wcms->loggedIn) return '';
        
        $feeds = implode("\n", $this->feeds);
        return '<div class="margin-bottom-1">
            <label class="block margin-bottom-1">RSS Feeds (one per line)</label>
            <textarea name="rssFeeds" class="block width-100">' . $feeds . '</textarea>
        </div>';
    }
    
    private function getFeeds(): array {
        $feeds = $this->Wcms->get('config', 'rssFeeds');
        return $feeds ? explode("\n", $feeds) : [];
    }
    
    private function fetchAndParseFeed(string $url): array {
        $content = @file_get_contents($url);
        if (!$content) return [];
        
        $xml = @simplexml_load_string($content);
        if (!$xml) return [];
        
        $items = [];
        foreach ($xml->channel->item as $item) {
            $items[] = [
                'title' => (string)$item->title,
                'link' => (string)$item->link,
                'date' => strtotime((string)$item->pubDate),
                'description' => (string)$item->description,
                'source' => (string)$xml->channel->title
            ];
        }
        return $items;
    }
    
    public function display(): string {
        if (empty($this->feeds)) {
            return '<div class="alert alert-warning">No RSS feeds configured. Please add feeds in the admin settings.</div>';
        }

        $allItems = [];
        foreach ($this->feeds as $feed) {
            if (empty(trim($feed))) continue;
            $items = $this->fetchAndParseFeed(trim($feed));
            $allItems = array_merge($allItems, $items);
        }
        
        if (empty($allItems)) {
            return '<div class="alert alert-warning">No items found in the configured feeds.</div>';
        }

        usort($allItems, function($a, $b) {
            return $b['date'] - $a['date'];
        });
        
        $viewMode = $_COOKIE['rss_view_mode'] ?? 'list';
        
        $output = '
        <div class="view-controls">
            <button class="view-toggle" data-mode="list">List View</button>
            <button class="view-toggle" data-mode="grid">Grid View</button>
        </div>
        <div class="rss-feeds ' . $viewMode . '-view">';
        
        foreach ($allItems as $item) {
            $date = date('Y-m-d', $item['date']);
            $output .= sprintf(
                '<div class="rss-item">
                    <h3><a href="%s">%s</a></h3>
                    <div class="meta">%s - %s</div>
                    <div class="description">%s</div>
                </div>',
                $item['link'],
                $item['title'],
                $item['source'],
                $date,
                $item['description']
            );
        }
        $output .= '</div>';
        return $output;
    }
    
    public function css(): string {
        return '<link rel="stylesheet" href="' . $this->Wcms->url('plugins/wonder-aggregator/css/wagg.css') . '">';
    }
    
    public function js(): string {
        return '
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            const viewButtons = document.querySelectorAll(".view-toggle");
            const feedsContainer = document.querySelector(".rss-feeds");
            
            if (!feedsContainer) return;
            
            const currentView = document.cookie.replace(/(?:(?:^|.*;\s*)rss_view_mode\s*\=\s*([^;]*).*$)|^.*$/, "$1") || "list";
            viewButtons.forEach(btn => {
                if(btn.dataset.mode === currentView) btn.classList.add("active");
            });
            
            viewButtons.forEach(button => {
                button.addEventListener("click", function() {
                    const mode = this.dataset.mode;
                    viewButtons.forEach(btn => btn.classList.remove("active"));
                    this.classList.add("active");
                    feedsContainer.className = `rss-feeds ${mode}-view`;
                    document.cookie = `rss_view_mode=${mode};path=/;max-age=31536000`;
                });
            });
        });
        </script>';
    }
}
?>