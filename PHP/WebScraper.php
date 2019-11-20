<?php

class WebScraper extends \Html2Text\Html2Text
{

    private $URL, $subURLs, $matches;

    public function __construct(string $url)
    {
        $this->URL = $url;
        $this->subURLs = [];
        $this->matches = 0;

        try {
            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_ENCODING => "",
                CURLOPT_AUTOREFERER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_MAXREDIRS => 10,
            ));

            $response = curl_exec($curl);

            switch ($code = curl_getinfo($curl, CURLINFO_HTTP_CODE)) {
                case 200:
                case 301:
                case 401:
                case 403:
                    parent::__construct($response);
                    break;

                case 0:
                    $_SESSION['comment'] = 'Website can\'t be reached';
                    $this->matches = -1;
                    break;

                case 404:
                    $_SESSION['comment'] = 'Error 404: page not found';
                    $this->matches = -1;
                    break;

                default:
                    $_SESSION['comment'] = 'ERROR: HTTP STATUS CODE ' . $code;
                    $this->matches = -1;
                    break;
            }

            curl_close($curl);
        } catch (\Exception $exp) {
        }
    }

    public function getURL(): string
    {
        return $this->URL;
    }

    public function getBaseURL(): string
    {
        $url_info = parse_url($this->URL);
        return $url_info['scheme'] . '://' . $url_info['host'];
    }

    public function getHtmlAsArray(): array
    {
        /* Replace line-breaks with whitespaces */
        $text = preg_replace("/\r|\n/", " ", $this->getText());

        /* Remove strings inside of brackets [] to clean (most of the cases these are url's) */
        $text = preg_replace('/\[[^\]]*\]/', '$1 $2', $text);

        /* Remove parenthesis */
        $text = str_replace(['(', ')'], '', $text);

        /* Match parts of the text that start with a capital letter and (optional) end with a dot, question mark or exclamation mark */
        preg_match_all('/[A-Z][\w ,\'"`’‑-]+[\.\?!]?/', $text, $output);

        /* Replace all single or multiple whitespaces with a single whitespace and remove - and _ from the text */
        foreach ($output[0] as $key => $value) {
            $output[0][$key] = preg_replace(['/\s+/', '/[_-]/'], [' ', ''], $value);
        }

        return $output;
    }

    public function renderSubURLS(): void
    {
        preg_match_all('/\[[^\]]*\]/', $this->getText(), $matches);

        $return = [];
        foreach ($matches[0] as $string) {
            $string = str_replace(['[', ']'], '', $string);

            if (substr($string, 0, 1) == '/') {
                $string = rtrim($this->getBaseURL() . $string, '/');
            }

            if (filter_var($string, FILTER_VALIDATE_URL)) {
                $info = parse_url($string);
                if (str_replace('www.', '', $info['scheme'] . '://' . $info['host']) == str_replace('www.', '', $this->getBaseURL()) && $string != $this->getBaseURL()) {
                    $return[] = rtrim($string, '/');
                }
            }
        }

        $this->subURLs = array_values(array_unique($return));
    }

    public function getSubURLs(): array
    {
        return $this->subURLs;
    }

    public function subURLsCount(): int
    {
        return count($this->subURLs);
    }

    public function createCondition(string $input): string
    {
        $parts = explode('+', $input);

        foreach ($parts as $key => $part) {
            $part = strtolower($part);

            switch ($part) {
                case 'and':
                case 'andnot':
                case 'or':
                case 'ornot':
                    $parts[$key] = preg_replace(['/\band\b/', '/\bandnot\b/', '/\bor\b/', '/\bornot\b/'], ['&&', '&& !', '||', '|| !'], $part);
                    break;

                default:
                    $parts[$key] = preg_replace('/[a-zA-z0-9-]+/', 'strpos($line, " $0 ") !== false', $part);
                    break;
            }
        }

        return str_replace('-', ' ', 'return (' . implode(' ', $parts) . ');');
    }

    public function calculateMatches(string $condition): void
    {
        foreach ($this->getHtmlAsArray()[0] as $line) {
            $line = str_pad(strtolower($line), strlen($line) + 2, " ", STR_PAD_BOTH);
            if (eval($condition)) {
                $this->matches++;
            }

        }

        if ($_SESSION['hitsOnFirstPage'] == -1) {
            $_SESSION['hitsOnFirstPage'] = $this->matches;
        }

        $_SESSION['totalHits'] += $this->matches;
    }

    public function getMatchesCount(): int
    {
        return $this->matches;
    }

}
