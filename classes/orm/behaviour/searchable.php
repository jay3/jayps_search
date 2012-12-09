<?php

namespace JayPS\Search;

class Orm_Behaviour_Searchable extends \Nos\Orm_Behaviour
{
    protected $_properties = array();

    protected $_config = array();

    public function __construct($class)
    {
        \Config::load('jayps_search::config', 'config');
        $this->_config = \Config::get('config');


        $primary_key = $class::primary_key();
        if (is_array($primary_key)) {
            $primary_key = \Arr::get($primary_key, 0);
        }

        $has_many = array(
            'key_from' => $primary_key,
            'model_to' => 'JayPS\Search\\Model_Keyword',
            'key_to' => 'mooc_foreign_id',
            'cascade_save' => false,
            'cascade_delete' => false,
        );

        // $class::$_has_many need to be changed to public
        $class::$_has_many['jayps_search_word_occurence'] = $has_many;
        $class::$_has_many['jayps_search_word_occurence2'] = $has_many;
        //d($class::$_has_many);

        // if we add a static method add_has_many() in Nos\Orm\Model
        //$class::add_has_many('jayps_search_word_occurence', $has_many);
        //$class::add_has_many('jayps_search_word_occurence2', $has_many);

        parent::__construct($class);
    }


    public function after_save(\Nos\Orm\Model $item)
    {
        $primary_key = $item->primary_key();
        if (is_array($primary_key)) {
            $primary_key = \Arr::get($primary_key, 0);
        }

        if (!empty($this->_properties['fields'])) {
            $res = array();
            $res[$primary_key] = $item->id;
            foreach($this->_properties['fields'] as $field) {
                $res[$field] = $item->{$field};
            }
            $config = array(
                'table'                     => $item->table(),
                'table_primary_key'         => $primary_key,
                'table_fields_to_index'     => $this->_properties['fields'],
            );

            $config = array_merge($config, $this->_config);

            $search = new Search($config);
            $search->add_to_index($res);
        }
    }

    public function before_save(\Nos\Orm\Model $item)
    {
        /** @todo save $item->get_diff somewhere to use it in after_save() and save time if there is no changes */
    }

    private function d($o) {
        if (!empty($this->_properties['debug'])) {
            print('<pre style="border:1px solid #0000FF; background-color: #CCCCFF; width:95%; height: auto; overflow: auto">');
            print_r($o);
            print('</pre>');
        }
    }

    public function before_query(&$options)
    {
        if (array_key_exists('where', $options)) {
            self::d('before_query');
            self::d($options);
            $where = $options['where'];

            foreach ($where as $k => $w) {

                if ($w[0] == 'keywords') {
                    self::d($w);

                    $class = $this->_class;
                    $table = $class::table();

                    /** @todo sort keywords by length, use only the first ones (5?) */

                    if (!empty($w[1]) && is_array($w[1])) {
                        /** @todo $w[1] can contain more than one element */
                        $where[] = array(
                            array($this->_config['table_liaison'] . '.mooc_word', $w[1][0]),
                            array($this->_config['table_liaison'] . '.mooc_join_table', $table)
                        );
                        $options['related'][] = $this->_config['table_liaison'];

                        if (!empty($w[1][1])) {
                            $where[] = array(
                                array($this->_config['table_liaison'] . '2.mooc_word', $w[1][1]),
                                array($this->_config['table_liaison'] . '2.mooc_join_table', $table)
                            );
                            $options['related'][] = $this->_config['table_liaison'].'2';
                        }

                    }
                    unset($where[$k]);
                }
            }
            $options['where'] = $where;
            self::d($options);
        }
    }

}
