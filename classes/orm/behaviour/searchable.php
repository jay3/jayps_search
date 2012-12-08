<?php

namespace JayPS\Search;

class Orm_Behaviour_Searchable extends \Nos\Orm_Behaviour
{
    protected $_properties = array();

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
        print('<h3>before_query</h3>');
        self::d($options);
        if (array_key_exists('where', $options)) {
            $where = $options['where'];

            foreach ($where as $k => $w) {
                //self::d($w);
                if ($w[0] == 'keywords') {

                    if (!empty($w[1][0])) {
                        //$this->_properties['fields'];
                        $where[$k] = array(
                            array('jayps_search_word_occurence.mooc_word', $w[1][0]),
                            array('jayps_search_word_occurence.mooc_join_table','jayps_user')
                        );
                        $where[$k] = array('jayps_search_word_occurence.mooc_word', $w[1][0]);

                        //$where[] = array('jayps_search_word_occurence.mooc_join_table','jayps_user');
                        $options['related'][] = 'jayps_search_word_occurence';

                    } else {
                        unset($where[$k]);
                    }
                }
            }
            $options['where'] = $where;
        }
        self::d($options);
    }
}
