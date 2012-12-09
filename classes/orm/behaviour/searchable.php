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
        /** @todo save $item->get_diff somewhere to use it in after_save() and save time if threr is no changes */
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
                //self::d($w);
                if ($w[0] == 'keywords') {

                    $class = $this->_class;
                    $table = $class::table();

                    if (!empty($w[1][0])) {
                        $where[$k] = array(
                            array($this->_config['table_liaison'] . '.mooc_word', $w[1][0]),
                            array($this->_config['table_liaison'] . '.mooc_join_table', $table)
                        );

                        $options['related'][] = $this->_config['table_liaison'];

                    } else {
                        unset($where[$k]);
                    }
                }
            }
            $options['where'] = $where;
            self::d($options);
        }
    }
}
