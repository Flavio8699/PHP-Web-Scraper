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
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_MAXREDIRS => 10,
            ));

            $response = curl_exec($curl);

            switch ($code = curl_getinfo($curl, CURLINFO_HTTP_CODE)) {
                case 200:
                case 301:
                case 401:
                case 403:
                case 302:
                case 429:
                    parent::__construct($response);
                    break;

                case 0:
                    $_SESSION['comment'] = 'Website can\'t be reached';
                    break;

                case 404:
                    $_SESSION['comment'] = 'Error 404: page not found';
                    break;

                default:
                    $_SESSION['comment'] = 'error';
                    break;
            }

            if (curl_error($curl) != "") {
                file_put_contents("../errors.txt", $url . "\n-> " . curl_error($curl) . "\n\n", FILE_APPEND);
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

        /* Remove some characters */
        $text = str_replace(['(', ')', '*', '_'], '', $text);

        /* Try to identify sentences the most accurate possible */
        preg_match_all('/[[:alnum:]][[:alnum:],\'\/ "`’%‑@&€:;#$+—-]+[\.\?!]?/u', $text, $output);

        /* Replace all single or multiple whitespaces with a single whitespace and remove - and _ from the text */
        foreach ($output[0] as $key => $value) {
            $output[0][$key] = preg_replace('/\s+/', ' ', $value);
        }

        return $output;
    }

    public function renderSubURLs(): void
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
                    $parts[$key] = preg_replace('/[\w,\'"`’%‑@&+—-]+/u', 'preg_match("/\b$0\b/i", $line, $o)', $part);
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
