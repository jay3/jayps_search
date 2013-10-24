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
    public function before_save(\Nos\Orm\Model $item)
    {
        /** @todo save $item->get_diff somewhere to use it in after_save() and save time if there is no changes */
    }
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
            $search->add_to_index($res);

            if (isset($this->_properties['field_date_indexation']) && $this->_properties['field_date_indexation']) {
                $sql  = "UPDATE " . $config['table'];
                $sql .= " SET " . $this->_properties['field_date_indexation'] . " = NOW() ";
                $sql .= " WHERE " . $config['table_primary_key'] . " = " . $item->id;
                \DB::query($sql)->execute();
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

    protected function _search_keywords(&$where, &$options) {

        $found = 0;
        foreach ($where as $k => $w) {

            if ($w[0] == 'keywords') {

                //self::d('before_query');
                //self::d($w);

                $class = $this->_class;
                $table = $class::table();

                $keywords = $w[1];
                if (!empty($keywords)) {

                    $keywords = Search::generate_keywords($keywords, array(
                        'min_word_len' => static::$_jaypssearch_config['min_word_len'],
                        'max_keywords' => static::$_jaypssearch_config['max_join'],
                    ));

                    //self::d($keywords);
                    $found = count($keywords);
                    if ($found > 0) {
                        //erase $where[$k] in a clean way before setting it
                        $where[$k] = array();
                        // $keywords has been modified, so keys are 0, 1, 2...
                        foreach ($keywords as $i => $keyword) {
                            $keyword = str_replace('%', '', $keyword);
                            if (mb_strpos($keyword, '*') !== false) {
                                $keyword = str_replace('*', '', $keyword) . '%';
                                $operator = 'LIKE';
                            } else {
                                $operator = '=';
                            }
                            //replace where clause by the search conditions
                            $clause = array(
                                array(static::$_jaypssearch_config['table_liaison'] . ($i+1) . '.mooc_word', $operator,  $keyword),
                                array(static::$_jaypssearch_config['table_liaison'] . ($i+1) . '.mooc_join_table', $table)
                            );
                            //in cas there is more than 1 keyword, ensure an it's an AND between them by adding another level with an array
                            if ($found > 1) {
                                $where[$k][] = $clause;
                            } else {
                                $where[$k] = $clause;
                            }

                            $options['related'][] = static::$_jaypssearch_config['table_liaison'].($i+1);
                        }
                    } else {
                        // all keywords provided where removed (possible raison: too short)
                        // add an impossible case to return zero results and replace the keyword clause
                        // Note: a better solution should returns 1=0, but the ORM doesn't understand it
                        $pk = $class::primary_key();
                        $where[$k] = array(array($pk[0], '!=', \Db::expr('t0.'.$pk[0])));
                    }
                }
            } elseif (is_array($w[0])) {
                $found += $this->_search_keywords($where[$k], $options);
            }
        }
        return $found;
    }

    public function before_query(&$options)
    {
        if (array_key_exists('where', $options)) {
            $where = $options['where'];
            $nb_relations_ini = isset($options['related']) && is_array($options['related']) ? count($options['related']) : 0;
            $group_by = !(isset($options['jayps_no_group_by']) && $options['jayps_no_group_by']);

            $found = $this->_search_keywords($where, $options);

            if ($group_by && $found) {
                $class = $this->_class;
                $options['group_by'] = 't0.' . self::get_first_primary_key($class);
            }

            $options['where'] = $where;
            if (!empty($options['order_by'])) {
                //self::d($options['order_by']);
                $order_by = (array) $options['order_by'];

                foreach ($order_by as $k_ob => $v_ob) {
                    if (in_array('jayps_search_score', (array) $v_ob)) {
                        if ($found > 0) {
                            $sql_expr = '';
                            for ($i = 1; $i <= $found; $i++) {
                                if ($sql_expr) {
                                    $sql_expr .= '+';
                                }
                                $sql_expr .= 'SUM(t'.($nb_relations_ini + $i).'.mooc_score)';
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
