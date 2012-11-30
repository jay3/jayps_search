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
            );

            \Config::load('jayps_search::config', 'config');

            $this->config = array_merge($default_config, \Config::get('config'), $config);
            //self::Log($this->config);
        }

        /** @brief Pour debugger facilement l'application
         *
         */
        static function log($o) {
             \Log::debug(print_r($o, true));
        }

        function add_to_index($res) {
            $primary_key = $res[$this->config['table_primary_key']];
            if (!$primary_key) {
                return;
            }

            self::log('add_to_index: '.$this->config['table'].':'.$primary_key);

            // suppression des mots clés générés précédemment
            $sql  = "DELETE FROM " . $this->config['table_liaison'];
            $sql .= " WHERE " . $this->config['table_liaison_prefixe'] . "join_table = " . \Db::quote($this->config['table']);
            $sql .= " AND " . $this->config['table_liaison_prefixe'] . "foreign_id = $primary_key";
            \Db::query($sql)->execute();

            foreach ($this->config['table_fields_to_index'] as $field) {
                $words = $this->split($res[$field]);
                //self::log($words);
                $this->extract_keywords($primary_key, $words, $field);
            }
        }

        /** @brief coupe une chaîne en mots
         *
         */
        function split($txt) {
            // scinde la phrase grâce aux virgules et espacements
            // inclus les " ", \r, \t, \n et \f
            $words = preg_split("/[\s,'`�\"\(\)\.:;-]+/", $txt);

            foreach($words as $k => $tmp) {
                // en minuscule
                $words[$k] = strtolower($words[$k]);
                // on enlève les mots de moins de $this->config['min_word_len'] lettres
                if (strlen($words[$k]) < $this->config['min_word_len']) {
                    unset($words[$k]);
                }
            }
            return $words;
        }

        function extract_keywords($primary_key, $words, $field) {

            if (!$primary_key) {
                return;
            }
            if (!count($words)) {
                return;
            }

            if ($this->config['transaction']) {
                \Db::query('START TRANSACTION')->execute();
            }

            $sql  = 'INSERT INTO ' . $this->config['table_liaison'];
            $sql .= ' (' . $this->config['table_liaison_prefixe'] . 'word';
            $sql .= ', ' . $this->config['table_liaison_prefixe'] . 'join_table';
            $sql .= ', ' . $this->config['table_liaison_prefixe'] . 'foreign_id';
            $sql .= ', ' . $this->config['table_liaison_prefixe'] . 'field';
            $sql .= ', ' . $this->config['table_liaison_prefixe'] . 'ordre';
            $sql .= ') VALUES';
            $sql .= ' (:word';
            $sql .= ', ' . \Db::quote($this->config['table']);
            $sql .= ', ' . \Db::quote($primary_key);
            $sql .= ', ' . \Db::quote($field);
            $sql .= ', :ordre)';
            $query = \DB::query($sql);
            //self::log($sql);

            $ordre = 1;

            $query->bind('ordre', $ordre);
            $query->bind('word',  $word);

            foreach ($words as $word) {
                $query->execute();
                $ordre++;
            }

            if ($this->config['transaction']) {
                \Db::query('COMMIT')->execute();
            }
        }
    }
