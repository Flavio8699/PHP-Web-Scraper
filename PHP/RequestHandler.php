<?php

class RequestHandler extends WebScraper
{

    public $level, $nextLevel, $levels, $urlID, $condition, $URL, $results;

    public function __construct()
    {
        if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || ($_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest')) {
            die('undefined');
        }
    }

    public function startApp(string $type = 'url', array $files = []): void
    {
        unset($_SESSION['currentURL']);
        unset($_SESSION['comment']);
        $_SESSION['baseURLid'] = 0;
        $_SESSION['baseURLs'] = [];
        $_SESSION['totalHits'] = 0;
        $_SESSION['totalPages'] = 0;

        $results = fopen('../result.csv', 'w');
        switch ($type) {
            case 'url':
                $_SESSION['type'] = 'url';
                $_SESSION['baseURLs'] = [$_POST['url']];
                fputcsv($results, ['Website URL', 'Matches']);
                break;

            case 'file':
                $_SESSION['type'] = 'file';
                $this->getURLsFromCSV($files['csv']['tmp_name']);
                fputcsv($results, ['Website URL', 'Hits on first page', 'Total number of hits', 'Total number of pages', 'Hits on first page / Total number of pages', 'Total number of hits / Total number of pages', 'Comment']);
                break;
        }
        fclose($results);
    }

    public function getURLsFromCSV(string $filename): void
    {
        $urls = [];
        $file = file($filename);
        foreach ($file as $line) {
            $urls = array_merge($urls, str_getcsv($line));
        }

        foreach ($urls as $url) {
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                $_SESSION['baseURLs'][] = $url;
            }
        }
    }

    public function currentBaseURL(): string
    {
        $_SESSION['urls'] = [];
        $url = $_SESSION['baseURLs'][$_SESSION['baseURLid']];
        $_SESSION['baseURLid']++;
        $_SESSION['urls']['lvl1'] = [$url];

        return $url;
    }

    public function previousBaseURL(): string
    {
        return $_SESSION['baseURLs'][$_SESSION['baseURLid'] - 1];
    }

    public function selectURL(): void
    {
        if ($this->urlID == 0 && $this->level == 1) {
            if (isset($_SESSION['currentURL']) && $_SESSION['type'] == 'file') {
                $results = fopen("../result.csv", "a");
                fputcsv($results, [$this->previousBaseURL(), $_SESSION['hitsOnFirstPage'], $_SESSION['totalHits'], $_SESSION['totalPages'], ($_SESSION['hitsOnFirstPage'] > 0) ? number_format((float) ($_SESSION['hitsOnFirstPage'] / $_SESSION['totalHits']), 2, '.', '') : 'NaN', ($_SESSION['totalHits'] > 0) ? number_format((float) ($_SESSION['totalHits'] / $_SESSION['totalPages']), 2, '.', '') : 'NaN', isset($_SESSION['comment']) ? $_SESSION['comment'] : '']);
                fclose($results);
            }

            unset($_SESSION['comment']);
            $_SESSION['totalHits'] = 0;
            $_SESSION['hitsOnFirstPage'] = -1;
            $_SESSION['totalPages'] = 0;
            $_SESSION['currentURL'] = $this->currentBaseURL();
        } else {
            $_SESSION['currentURL'] = $_SESSION['urls']['lvl' . $this->level][$this->urlID];
        }

        $_SESSION['totalPages']++;
        $this->URL = $_SESSION['currentURL'];
    }

    public function handleURL(): void
    {
        parent::__construct($this->URL);
        $condition = $this->createCondition($this->condition);
        $this->calculateMatches($condition);

        if ($_SESSION['type'] == 'url') {
            $results = fopen("../result.csv", "a");
            fputcsv($results, [$this->URL, $this->getMatchesCount()]);
            fclose($results);
        }
    }

    public function handleLevels(): void
    {
        if ($this->level < $this->levels) {
            if (!isset($_SESSION['urls']['lvl' . $this->nextLevel])) {
                $_SESSION['urls']['lvl' . $this->nextLevel] = [];
            }

            $this->renderSubURLS();
            $_SESSION['urls']['lvl' . $this->nextLevel] = array_values(array_unique(array_merge($_SESSION['urls']['lvl' . $this->nextLevel], $this->getSubURLs())));
        }

    }

    public function nextRequest(): array
    {
        $continue = false;

        if ((isset($_SESSION['urls']['lvl' . $this->level][$this->urlID + 1])) || (isset($_SESSION['urls']['lvl' . $this->nextLevel]) && count($_SESSION['urls']['lvl' . $this->nextLevel]) > 0)) {
            $continue = true;
        }

        if (count($_SESSION['urls']['lvl' . $this->level]) == ($this->urlID + 1) && $continue) {
            $level = $this->nextLevel;
        } else {
            $level = $this->level;
        }

        if ($this->level != $level) {
            $urlID = 0;
        } else {
            $this->urlID++;
            $urlID = $this->urlID;
        }

        if ($continue == false) {
            if (count($_SESSION['baseURLs']) >= $_SESSION['baseURLid'] + 1) {
                $continue = true;
                $level = 1;
                $urlID = 0;
            } else {
                if (isset($_SESSION['currentURL']) && $_SESSION['type'] == 'file') {
                    $results = fopen("../result.csv", "a");
                    fputcsv($results, [$this->previousBaseURL(), $_SESSION['hitsOnFirstPage'], $_SESSION['totalHits'], $_SESSION['totalPages'], ($_SESSION['hitsOnFirstPage'] > 0) ? number_format((float) ($_SESSION['hitsOnFirstPage'] / $_SESSION['totalPages']), 2, '.', '') : 'NaN', ($_SESSION['totalHits'] > 0) ? number_format((float) ($_SESSION['totalHits'] / $_SESSION['totalPages']), 2, '.', '') : 'NaN', isset($_SESSION['comment']) ? $_SESSION['comment'] : '']);
                    fclose($results);
                }

            }
        }

        return ['continue' => $continue, 'nextURL' => [
            'level' => $level,
            'id' => $urlID,
        ]];
    }

    public function createOutput(): array
    {
        return array_merge(['url' => $this->URL, 'matches' => $this->getMatchesCount()], $this->nextRequest());
    }
}
