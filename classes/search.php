<?php
namespace JayPS\Search;

/** @class Search
 *
 *  @brief Moteur de recherche générique
 */
class Search
{
    /** @brief config */
    public $config = array();

    /** @brief Contructeur
     *
     */
    public function __construct($config = array())
    {
        $default_config = array(
            'table'                 => '',
            'table_primary_key'     => '',
            'table_fields_to_index' => array(),
            'table_liaison'         => 'jayps_search_word_occurence',
            'table_liaison_prefixe' => 'mooc_',
            'forbidden_words'       => array(),
            'min_word_len'          => 4,
            'transaction'           => false,
            'insert_delayed'        => false,
        );

        $this->config = array_merge($default_config, $config);

        //self::Log($this->config);
    }

    /** @brief Pour debugger facilement l'application
     *
     */
    protected function log($o)
    {
        if (!empty($this->config['debug'])) {
            \Log::debug(print_r($o, true));
        }
    }

    /**
     * @param $res
     * @return bool At least one changed keyword
     */
    public function add_to_index($res)
    {
        $primary_key = $res[$this->config['table_primary_key']];
        if (!$primary_key) {
            return;
        }

        self::log('add_to_index: '.$this->config['table'].':'.$primary_key);

        $old_keywords = $new_keywords = array();

        $sql  = "SELECT * FROM " . $this->config['table_liaison'];
        $sql .= " WHERE " . $this->config['table_liaison_prefixe'] . "join_table = " . \Db::quote($this->config['table']);
        $sql .= " AND " . $this->config['table_liaison_prefixe'] . "foreign_id = " . \Db::quote($primary_key);
        $keywords = \Db::query($sql)->execute()->as_array();
        if (count($keywords)) {
            foreach ($keywords as $kw) {
                $old_keywords[$kw[$this->config['table_liaison_prefixe'] . 'field']][$kw[$this->config['table_liaison_prefixe'] . 'word']] = $kw[$this->config['table_liaison_prefixe'] . 'score'];
            }
        }

        foreach ($this->config['table_fields_to_index'] as $field => $conf) {
            // txt can be prepared before retrieving words
            $prepare = !empty($conf['prepare_field']) ? $conf['prepare_field'] : (!empty($this->config['prepare_field']) ? $this->config['prepare_field'] : null);
            $txt = $res[$field];
            if (!empty($prepare) && is_callable($prepare)) {
                $txt = $prepare($txt, $field);
            }
            $new_keywords[$field] = $this->wordScoring($txt, $conf);
        }

        $diff = self::different_keywords($old_keywords, $new_keywords);
        if ($diff) {
            $this->remove_from_index($primary_key);
            foreach ($new_keywords as $field => $scores) {
                $this->insert_keywords($primary_key, $scores, $field);
            }
            return true;
        }
        return false;
    }


    /**
     * Compare 2 arrays of keywords (fields>keyword>score)
     *
     * @return boolean
     */
    private static function different_keywords($array1, $array2)
    {
        if (count($array1) != count($array2) || count(array_intersect_key($array1, $array2)) != count($array1)) {
            // not the same keys
            return true;
        }
        foreach ($array1 as $key => $value) {
            if (count($array1[$key]) != count($array2[$key]) || count(array_intersect_key($array1[$key], $array2[$key])) != count($array1[$key])) {
                // not the same keys
                return true;
            }
            $diff = array_diff_assoc($array1[$key], $array2[$key]);
            if (count($diff) != 0) {
                // not the same values
                return true;
            }
        }
        return false;
    }

    public function remove_from_index($primary_key)
    {
        // suppression des mots clés générés précédemment
        $sql  = "DELETE FROM " . $this->config['table_liaison'];
        $sql .= " WHERE " . $this->config['table_liaison_prefixe'] . "join_table = " . \Db::quote($this->config['table']);
        $sql .= " AND " . $this->config['table_liaison_prefixe'] . "foreign_id = " . \Db::quote($primary_key);
        \Db::query($sql)->execute();
    }

    private static function split_string($txt, $params = array())
    {
        $default_params = array(
            'allowable_chars' => '', // to allow specific characters, example '*' for the search
            'min_word_len' => null, // a min length can be provided to exclude words
            'forbidden_words' => array(), // exclude specific words
        );
        $params = array_merge($default_params, $params);

        // split the text with punctuations and spaces
        // include " ", \r, \t, \n et \f
        $regex = "/[\s,'`’\"\(\)\.:;!\?*%-]+/";

        if (!empty($params['allowable_chars'])) {
            // remove specific characters form the regex
            foreach (str_split($params['allowable_chars']) as $char) {
                $regex = str_replace(array('\\'.$char, $char), array('', ''), $regex);
            }
        }

        $keywords = preg_split($regex, $txt);

        // deal with short words
        if (!empty($params['min_word_len'])) {
            $min_word_len = $params['min_word_len'];
            // remove keywords shorter than 'min_word_len' characters
            $keywords = array_filter($keywords, function ($a) use ($min_word_len) {
                return mb_strlen($a) >= $min_word_len;
            });
        }

        // remove forbidden keywords
        if (!empty($params['forbidden_words'])) {
            $forbidden_words = $params['forbidden_words'];
            $keywords = array_filter($keywords, function ($a) use ($forbidden_words) {
                return !in_array($a, $forbidden_words);
            });
        }
        return $keywords;
    }

    /**
     * @deprecated use wordScoring($txt, $html)
     * @param $txt string : word
     * @param $html bool : is_html ?
     */
    public function split($txt, $html = false) {
        \Log::deprecated('->split() is deprecated, use ->wordScoring($txt, array(\'is_html\' => $html, \'boost\' => 0)) instead');
        return $this->wordScoring($txt, array('is_html' => $html, 'boost' => 0));
    }

    public function wordScoring($txt, $conf, $scores_ini = null)
    {
        $scores = is_array($scores_ini) ? $scores_ini : array();
        if (!empty($conf['is_html'])) {
            $txt = str_replace('&nbsp;', ' ', $txt);
            // example: &amp;
            $txt = html_entity_decode($txt);
            //$txt = str_replace('>', '> ', $txt); // if we want 'test<sting>test2</strong>' => 'test test2'
            if (is_array($conf['is_html'])) {
                //if a specific score must be applied on html fields
                foreach ($conf['is_html'] as $markup => $boost) {
                    $pattern = '`<'.preg_quote($markup, '`').'[^>]*>(.+)</'.preg_quote($markup, '`').'>`Uis';
                    if (preg_match_all($pattern, $txt, $m)) {
                        foreach ($m[1] as $sub) {
                            $scores = $this->wordScoring($sub, array(
                                'boost' => $boost,
                                'is_html' => false,
                            ),
                            $scores);
                        }
                    };
                }
            }
            $txt = strip_tags($txt);
        }
        //if (count($scores)) d($scores);

        $words = self::split_string($txt, array(
            'min_word_len' => $this->config['min_word_len'],
        ));

        $i = 0;
        foreach ($words as $word) {
            $i++;
            // string lowercase
            $word = mb_strtolower($word);

            $score = is_array($scores_ini) ? $conf['boost'] : 1 / (log10(($i+9) / 10) + 1);

            if (empty($scores[$word])) {
                //self::log( $i.$word.':'.$score );
                $scores[$word] = $score;
            } else {
                //self::log($i.$word.':'.$scores[$word].'+'.$score.'='.($scores[$word]+$score));
                $scores[$word] += $score;
            }
        }

        if (!is_array($scores_ini)) {
            // sort words by score DESC
            arsort($scores);
            //d($scores);
            $scores = array_map(function ($score) {
                return intval(10 * $score);
            }, $scores);
        }

        return $scores;
    }

    protected function insert_keywords($primary_key, $scores, $field)
    {

        if (!$primary_key) {
            return;
        }
        $scores = array_filter($scores, function($score) {
            return $score > 0;
        });
        if (!count($scores)) {
            return;
        }

        if ($this->config['transaction']) {
            \Db::query('START TRANSACTION')->execute();
        }

        $sqli  = 'INSERT '.($this->config['insert_delayed'] ? 'DELAYED' : '').' INTO ' . $this->config['table_liaison'];
        $sqli .= ' (' . $this->config['table_liaison_prefixe'] . 'word';
        $sqli .= ', ' . $this->config['table_liaison_prefixe'] . 'join_table';
        $sqli .= ', ' . $this->config['table_liaison_prefixe'] . 'foreign_id';
        $sqli .= ', ' . $this->config['table_liaison_prefixe'] . 'field';
        $sqli .= ', ' . $this->config['table_liaison_prefixe'] . 'score';
        $sqli .= ') VALUES';

        // Chunks $words into smaller arrays. The last chunk may contain less elements.
        $words_by_insert = intval($this->config['words_by_insert']) > 0 ? intval($this->config['words_by_insert']) : 100;
        foreach (array_chunk($scores, $words_by_insert, true) as $scores2) {
            $sql = $sqli;
            $i = 1;
            foreach ($scores2 as $word => $score) {
                if ($i++ > 1) {
                    $sql .= ',';
                }
                $sql .= ' (' . \Db::quote($word);
                $sql .= ', ' . \Db::quote($this->config['table']);
                $sql .= ', ' . \Db::quote($primary_key);
                $sql .= ', ' . \Db::quote($field);
                $sql .= ', ' . intval($score);
                $sql .= ')';
            }
            self::log($sql);
            \DB::query($sql)->execute();
        }

        if ($this->config['transaction']) {
            \Db::query('COMMIT')->execute();
        }
    }

    public static function generate_keywords($keywords, $params)
    {
        $default_params = array(
            'min_word_len' => 2,
            'max_keywords' => 5,
        );
        $params = array_merge($default_params, $params);

        if (!is_array($keywords)) {
            $keywords = self::split_string($keywords, array(
                'allowable_chars' => '*?',
                'min_word_len' => $params['min_word_len'],
            ));
        }

        // sort keywords by length desc
        uasort($keywords, function ($a, $b) {
            return mb_strlen($a) < mb_strlen($b);
        });

        // remove duplicates
        $keywords = array_unique($keywords);

        // truncate to 'max_keywords' keywords
        $keywords = array_slice($keywords, 0, $params['max_keywords']);

        return $keywords;
    }

}
