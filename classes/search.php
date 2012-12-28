<?
namespace JayPS\Search;

    /** @class Search
     *
     *  @brief Moteur de recherche générique
     */
    class Search {
        /** @brief config */
        public $config = array();

        /** @brief Contructeur
         *
         */
        function __construct($config = array()) {
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

            //\Config::load('jayps_search::config', 'config');


            $this->config = array_merge($default_config, $config);

            //self::Log($this->config);
        }

        /** @brief Pour debugger facilement l'application
         *
         */
        protected function log($o) {
            if (!empty($this->config['debug'])) {
                \Log::debug(print_r($o, true));
            }
        }

        function add_to_index($res) {
            $primary_key = $res[$this->config['table_primary_key']];
            if (!$primary_key) {
                return;
            }

            self::log('add_to_index: '.$this->config['table'].':'.$primary_key);

            $this->remove_from_index($primary_key);

            foreach ($this->config['table_fields_to_index'] as $field) {

                if (strpos($field, 'wysiwyg_') === 0) {
                    // it contains HTML tags
                    $txt = $res[$field];
                    $txt = str_replace('&nbsp;', ' ', $txt);
                    // example: &amp;
                    $txt = html_entity_decode($txt);
                    //$txt = str_replace('>', '> ', $txt); // if we want 'test<sting>test2</strong>' => 'test test2'
                    $txt = strip_tags($txt);
                    $words = $this->split($txt);
                } else {
                    $words = $this->split($res[$field]);
                }
                //self::log($words);
                self::log(count($words).' words');
                $this->insert_keywords($primary_key, $words, $field);
            }
        }
        function remove_from_index($primary_key)
        {
            // suppression des mots clés générés précédemment
            $sql  = "DELETE FROM " . $this->config['table_liaison'];
            $sql .= " WHERE " . $this->config['table_liaison_prefixe'] . "join_table = " . \Db::quote($this->config['table']);
            $sql .= " AND " . $this->config['table_liaison_prefixe'] . "foreign_id = $primary_key";
            \Db::query($sql)->execute();
        }


        /** @brief coupe une chaîne en mots
         *
         */
        function split($txt) {
            // scinde la phrase grâce aux virgules et espacements
            // inclus les " ", \r, \t, \n et \f
            $words = preg_split("/[\s,'`�\"\(\)\.:;!\?*%-]+/", $txt);

            foreach($words as $k => $tmp) {
                // string lowercase
                $words[$k] = mb_strtolower($words[$k]);
                // remove words shorter than $this->config['min_word_len'] caracters
                if (mb_strlen($words[$k]) < $this->config['min_word_len']) {
                    unset($words[$k]);
                }
            }
            return $words;
        }

        protected function insert_keywords($primary_key, $words, $field) {

            if (!$primary_key) {
                return;
            }
            if (!count($words)) {
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
            $sqli .= ', ' . $this->config['table_liaison_prefixe'] . 'ordre';
            $sqli .= ') VALUES';

            // Chunks $words into smaller arrays. The last chunk may contain less elements.
            $words_by_insert = intval($this->config['words_by_insert']) > 0 ? intval($this->config['words_by_insert']) : 100;
            foreach (array_chunk($words, $words_by_insert) as $words2) {
                $sql = $sqli;
                $ordre = 1;
                foreach ($words2 as $word) {
                    if ($ordre > 1) {
                        $sql .= ',';
                    }
                    $sql .= ' (' . \Db::quote($word);
                    $sql .= ', ' . \Db::quote($this->config['table']);
                    $sql .= ', ' . \Db::quote($primary_key);
                    $sql .= ', ' . \Db::quote($field);
                    $sql .= ', ' . \Db::quote($ordre);
                    $sql .= ')';
                    $ordre++;
                }
                //self::log($sql);
                \DB::query($sql)->execute();
            }

            if ($this->config['transaction']) {
                \Db::query('COMMIT')->execute();
            }
        }
    }
