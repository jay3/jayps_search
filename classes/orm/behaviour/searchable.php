<?php

namespace JayPS\Search;

\Package::load('orm');

class Orm_Behaviour_Searchable extends \Nos\Orm_Behaviour
{
    protected static $_jaypssearch_config = array();

    public static function init($add_relations = false)
    {
        static::$_jaypssearch_config = \Config::load('jayps_search::config', true);

        if (!empty(static::$_jaypssearch_config['observed_models']) && is_array(static::$_jaypssearch_config['observed_models'])) {
            foreach (static::$_jaypssearch_config['observed_models'] as $name => $data) {

                \Event::register_function('config|'.$name, function(&$config) use ($data, $add_relations) {
                    $config['behaviours']['JayPS\Search\Orm_Behaviour_Searchable'] = $data['config_behaviour'];

                    if ($add_relations) {
                        \JayPS\Search\Orm_Behaviour_Searchable::add_relations($config, $data['primary_key']);
                    }
                });

            }
        }
    }

    public static function init_relations($config_name = '')
    {
        if ($config_name) {
            // add relations to a specific model
            if (!empty(static::$_jaypssearch_config['observed_models'][$config_name])) {
                $data = static::$_jaypssearch_config['observed_models'][$config_name];
                \Event::register_function('config|'.$config_name, function(&$config) use ($data) {
                    \JayPS\Search\Orm_Behaviour_Searchable::add_relations($config, $data['primary_key']);
                });
            }
        } else {
            // add relations to every observed models
            if (!empty(static::$_jaypssearch_config['observed_models']) && is_array(static::$_jaypssearch_config['observed_models'])) {
                foreach (static::$_jaypssearch_config['observed_models'] as $name => $data) {

                    \Event::register_function('config|'.$name, function(&$config) use ($data) {
                        \JayPS\Search\Orm_Behaviour_Searchable::add_relations($config, $data['primary_key']);
                    });
                }
            }
        }
    }

    public static function add_relations(&$config, $primary_key)
    {
        $has_many = array(
            'key_from'       => $primary_key,
            'model_to'       => 'JayPS\Search\Model_Keyword',
            'key_to'         => 'mooc_foreign_id',
            'cascade_save'   => false,
            'cascade_delete' => false,
        );
        for ($i = 1; $i <= static::$_jaypssearch_config['max_join']; $i++) {
            $config['has_many']['jayps_search_word_occurence'.$i] = $has_many;
        }
    }

    public function after_save(\Nos\Orm\Model $item)
    {
        self::__index($item);
    }

    // public function before_save(\Nos\Orm\Model $item)
    // Idea: try to use $item->get_diff() and save time if there is no changes
    // but get_diff() won't return differences on relations...

    public function force_reindex(\Nos\Orm\Model $item)
    {
        self::__index($item);
    }
    private function __index(\Nos\Orm\Model $item)
    {
        if (!empty($this->_properties['fields'])) {

            $config = $this->get_config($item);

            $res = array();
            $res[$config['table_primary_key']] = $item->id;
            foreach ($this->_properties['fields'] as $field) {
                if (strpos($field, 'wysiwyg_') === 0) {
                    // backward compatibility
                    $field = preg_replace('/^wysiwyg_/', 'wysiwygs->', $field);
                }
                if (strpos($field, '->') > 0) {
                    // a property of a relation of the model
                    $arr_names = explode('->', $field);
                    $res[$field] = $item;
                    self::__build_pseudo_fields($res, $field, $arr_names);
                } else {
                    // a simple property
                    $res[$field] = $item->{$field};
                }
            }

            $search = new Search($config);
            $changed = $search->add_to_index($res);
            if (!empty($this->_properties['field_date_indexation'])) {
                // the model has a 'field_date_indexation' field
                if ($changed || empty($item->{$this->_properties['field_date_indexation']})) {
                    // some keywords have changed OR 'field_date_indexation' was not yet setted
                    $sql  = "UPDATE " . $config['table'];
                    $sql .= " SET " . $this->_properties['field_date_indexation'] . " = NOW() ";
                    $sql .= " WHERE " . $config['table_primary_key'] . " = " . \Db::quote($item->id);
                    \DB::query($sql)->execute();
                }
            }
        }
    }
    private static function __build_pseudo_fields(&$res, $field, $arr_names)
    {

        if (count($arr_names) == 0) {
            // last level of recursion
            return;
        }

        // remove first name
        $name = array_shift($arr_names);

        if (isset($res[$field]->{$name})) {
            if (is_array($res[$field]->{$name})) {
                // example: _many_many
                $tmp = '';
                foreach ($res[$field]->{$name} as $relation) {
                    $res[$field] = $relation;
                    self::__build_pseudo_fields($res, $field, $arr_names);
                    $tmp .= ($tmp ? ' ' : '') . $res[$field];
                }
                $res[$field] = $tmp;
            } else {
                $res[$field] = $res[$field]->{$name};
                self::__build_pseudo_fields($res, $field, $arr_names);
            }
        } else {
            // the relation does not exist for the current item
            // we do not go deeper in the chain '->'
            $res[$field] = '';
        }
    }

    public function before_delete(\Nos\Orm\Model $item)
    {
        $config = $this->get_config($item);
        $search = new Search($config);
        $search->remove_from_index($item->id);
    }

    protected function get_config(\Nos\Orm\Model $item)
    {
        $config = array(
            'table'                     => $item->table(),
            'table_primary_key'         => self::get_first_primary_key($item),
            'table_fields_to_index'     => $this->_properties['fields'],
        );

        $config = array_merge(static::$_jaypssearch_config, $config);

        if (isset($this->_properties['debug'])) {
            // if the propertie 'debug' is set in the configuration of the behaviour, we use it
            $config['debug'] = $this->_properties['debug'];
        }

        return $config;
    }

    protected static function get_first_primary_key($instance)
    {
        $primary_key = '';
        if (is_object($instance)) {
            $primary_key = $instance->primary_key();
        } else {
            // should be a class
            $primary_key = $instance::primary_key();
        }
        if (is_array($primary_key)) {
            $primary_key = \Arr::get($primary_key, 0);
        }
        return $primary_key;
    }


    private function d($o)
    {
        if (!empty($this->_properties['debug']) || !empty(static::$_jaypssearch_config['debug'])) {
            print('<pre style="border:1px solid #0000FF; background-color: #CCCCFF; width:95%; height: auto; overflow: auto">');
            print_r($o);
            print('</pre>');
        }
    }

    protected function _search_keywords(&$where, &$options, &$used_keywords) {

        foreach ($where as $k => $w) {
            if (is_array($w) && isset($w[0])) {
                if ($w[0] == 'keywords' || $w[0] == 'keywords_fields') {

                    //self::d('before_query');
                    //self::d($w);

                    $class = $this->_class;
                    $table = $class::table();

                    if ($w[0] == 'keywords') {
                        $keywords_fields = array('*' => $w[1]);
                    } else {
                        $keywords_fields = $w[1];
                    }
                    if (!empty($keywords_fields)) {
                        $keywords_fields = Search::generate_keywords_fields($keywords_fields, array(
                            'min_word_len' => static::$_jaypssearch_config['min_word_len'],
                            // note: we remove "used" relations to prevent sql errors
                            // this could end to "no results"
                            // increase 'max_join' in your config in you really need to do that
                            'max_keywords' => static::$_jaypssearch_config['max_join'] - count($used_keywords),
                        ), $nb_keywords);
                        //self::d($keywords_fields);
                        if ($nb_keywords > 0) {
                            //erase $where[$k] in a clean way before setting it
                            $where[$k] = array();
                            $i = 1;
                            foreach($keywords_fields as $field => $keywords) {
                                foreach ($keywords as $keyword) {
                                    $keyword = str_replace('%', '', $keyword);
                                    if (mb_strpos($keyword, '*') !== false) {
                                        $keyword = str_replace('*', '', $keyword) . '%';
                                        $operator = 'LIKE';
                                    } else {
                                        $operator = '=';
                                    }
                                    //replace where clause by the search conditions
                                    $clause = array(
                                        array(static::$_jaypssearch_config['table_liaison'] . (count($used_keywords)+$i) . '.mooc_word', $operator,  $keyword),
                                        array(static::$_jaypssearch_config['table_liaison'] . (count($used_keywords)+$i) . '.mooc_join_table', $table)
                                    );
                                    if ($field != '*') {
                                        // restrict search to a specific field
                                        $clause[] = array(static::$_jaypssearch_config['table_liaison'] . (count($used_keywords)+$i) . '.mooc_field', $field);
                                    }
                                    //in case there is more than 1 keyword, ensure it's an AND between them by adding another level with an array
                                    if (count($keywords) > 1) {
                                        $where[$k][] = $clause;
                                    } else {
                                        $where[$k] = $clause;
                                    }

                                    $options['related'][] = static::$_jaypssearch_config['table_liaison'].(count($used_keywords)+$i);
                                    $i++;
                                }
                            }
                            $used_keywords = array_merge($used_keywords, $keywords);
                        } else {
                            // all keywords provided where removed (possible raison: too short)
                            // add an impossible case to return zero results and replace the keyword clause
                            // Note: a better solution should returns 1=0, but the ORM doesn't understand it
                            $pk = $class::primary_key();
                            $where[$k] = array(array($pk[0], '!=', \Db::expr('t0.'.$pk[0])));
                        }
                    }
                } elseif (is_array($w[0])) {
                    $this->_search_keywords($where[$k], $options, $used_keywords);
                }
            }
        }
    }

    /**
     * convert different where syntaxes
     *
     * @param $where
     */
    protected static function _convert_where_syntax(&$where)
    {
        if (is_array($where)) {
            if (isset($where[0]) && isset($where[1]) && isset($where[2]) && ($where[0] == 'keywords' || $where[0] == 'keywords_fields') && ($where[1] == '=')) {
                // convert syntax array('keywords', '=', $keywords) to syntax array('keywords', $keywords)
                $where = array($where[0], $where[2]);
            } else {
                foreach ($where as $k => $w) {
                    if (is_string($k) && ($k == 'keywords' || $k == 'keywords_fields')) {
                        // convert syntax 'keywords' => $keywords to syntax array('keywords', $keywords)
                        $w = array($k, $w);
                        unset($where[$k]);
                        $where[] = $w;
                    } elseif (is_array($w)) {
                        static::_convert_where_syntax($where[$k]);
                    }
                }
            }
        }
    }
    public function before_query(&$options)
    {
        if (array_key_exists('where', $options)) {
            $where = $options['where'];
            $nb_relations_ini = isset($options['related']) && is_array($options['related']) ? count(array_unique($options['related'])) : 0;
            $group_by = !(isset($options['jayps_no_group_by']) && $options['jayps_no_group_by']);

            $keywords = array();

            static::_convert_where_syntax($where);

            $this->_search_keywords($where, $options, $keywords);

            if ($group_by && count($keywords) > 0) {
                $class = $this->_class;
                if (!isset($options['group_by'])) {
                    $options['group_by'] = array();
                }
                $options['group_by'] = (array) $options['group_by'];
                $options['group_by'][] = 't0.' . self::get_first_primary_key($class);
            }

            $options['where'] = $where;
            if (!empty($options['order_by'])) {
                //self::d($options['order_by']);
                $order_by = (array) $options['order_by'];

                foreach ($order_by as $k_ob => $v_ob) {
                    if (in_array('jayps_search_exact_match', (array) $v_ob)) {
                        $sql_expr = '';
                        for ($i = 0; $i < count($keywords); $i++) {
                            $keyword = str_replace('%', '', $keywords[$i]);
                            if (mb_strpos($keyword, '*') !== false) {
                                $keyword = str_replace('*', '', $keyword);
                                if ($sql_expr) {
                                    $sql_expr .= '+';
                                }
                                $sql_expr .= 'IF(t'.($nb_relations_ini + $i + 1).'.mooc_word='.\DB::quote($keyword).',1,0)';
                            }
                        }
                        if ($sql_expr != '') {
                            $sql_expr = '(' . $sql_expr . ')';
                            $order = 'DESC';
                            if (is_array($v_ob) && ($v_ob[1] == 'ASC')) {
                                $order = 'ASC';
                            }
                            $order_by[$k_ob] = array(\DB::expr($sql_expr), $order);
                        } else {
                            // no keywords used, remove the order_by 'jayps_search_exact_match'
                            unset($order_by[$k_ob]);
                        }
                    }
                    if (in_array('jayps_search_score', (array) $v_ob)) {
                        if (count($keywords) > 0) {
                            $sql_expr = '';
                            for ($i = 0; $i < count($keywords); $i++) {
                                if ($sql_expr) {
                                    $sql_expr .= '+';
                                }
                                $sql_expr .= 'SUM(t'.($nb_relations_ini + $i + 1).'.mooc_score)';
                            }
                            $sql_expr = '(' . $sql_expr . ')';
                            $order = 'DESC';
                            if (is_array($v_ob) && ($v_ob[1] == 'ASC')) {
                                $order = 'ASC';
                            }
                            $order_by[$k_ob] = array(\DB::expr($sql_expr), $order);
                        } else {
                            // no keywords used, remove the order_by 'jayps_search_score'
                            unset($order_by[$k_ob]);
                        }
                    }
                }
                $options['order_by'] = $order_by;
                //self::d($options['order_by']);
            }

            //self::d($options);
        }
    }

}
