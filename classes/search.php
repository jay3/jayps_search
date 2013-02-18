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

    public function add_to_index($res)
    {
        $primary_key = $res[$this->config['table_primary_key']];
        if (!$primary_key) {
            return;
        }

        self::log('add_to_index: '.$this->config['table'].':'.$primary_key);

        $this->remove_from_index($primary_key);

        foreach ($this->config['table_fields_to_index'] as $field) {
            if (strpos($field, 'wysiwyg_') === 0) {
                // backward compatibility
                $field = preg_replace('/^wysiwyg_/', 'wysiwygs->', $field);
            }
            if (strpos($field, 'wysiwygs->') === 0) {
                // it contains HTML tags
                $scores = $this->split($res[$field], true);
            } else {
                $scores = $this->split($res[$field]);
            }
            //self::log($scores);
            self::log(count($scores).' words');
            $this->insert_keywords($primary_key, $scores, $field);
        }
    }
    public function remove_from_index($primary_key)
    {
        // suppression des mots clés générés précédemment
        $sql  = "DELETE FROM " . $this->config['table_liaison'];
        $sql .= " WHERE " . $this->config['table_liaison_prefixe'] . "join_table = " . \Db::quote($this->config['table']);
        $sql .= " AND " . $this->config['table_liaison_prefixe'] . "foreign_id = $primary_key";
        \Db::query($sql)->execute();
    }

    public static function split_string($txt)
    {
        // split the text with punctuations and spaces
        // include " ", \r, \t, \n et \f
        return preg_split("/[\s,'`’\"\(\)\.:;!\?*%-]+/", $txt);
    }

    /** @brief coupe une chaîne en mots
     *
     */
    public function split($txt, $html = false)
    {
        $scores = array();
        if ($html) {
            $txt = str_replace('&nbsp;', ' ', $txt);
            // example: &amp;
            $txt = html_entity_decode($txt);
            //$txt = str_replace('>', '> ', $txt); // if we want 'test<sting>test2</strong>' => 'test test2'

            $txt = strip_tags($txt);
        }
        //if (count($scores)) d($scores);

        $words = self::split_string($txt);

        $i = 0;
        foreach ($words as $word) {
            $i++;
            // string lowercase
            $word = mb_strtolower($word);
            // remove words shorter than $this->config['min_word_len'] caracters
            if (mb_strlen($word) < $this->config['min_word_len']) {
                continue;
            }

            $score = 1 / (log10(($i+9) / 10) + 1);

            if (empty($scores[$word])) {
                //self::log( $i.$word.':'.$score );
                $scores[$word] = $score;
            } else {
                //self::log($i.$word.':'.$scores[$word].'+'.$score.'='.($scores[$word]+$score));
                $scores[$word] += $score;
            }
        }

        // sort words by score DESC
        arsort($scores);
        //d($scores);

        return $scores;
    }

    protected function insert_keywords($primary_key, $scores, $field)
    {

        if (!$primary_key) {
            return;
        }
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
                $sql .= ', ' . intval(10 * $score);
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
            $keywords = self::split_string($keywords);
        }

        // sort keywords by length desc
        uasort($keywords, function ($a, $b) {
            return mb_strlen($a) < mb_strlen($b);
        });

        $min_word_len = $params['min_word_len'];
        // remove keywords shorter than 'min_word_len' characters
        $keywords = array_filter($keywords, function ($a) use ($min_word_len) {
            return mb_strlen($a) >= $min_word_len;
        });

        // remove duplicates
        $keywords = array_unique($keywords);

        // truncate to 'max_keywords' keywords
        $keywords = array_slice($keywords, 0, $params['max_keywords']);

        return $keywords;
    }

}
