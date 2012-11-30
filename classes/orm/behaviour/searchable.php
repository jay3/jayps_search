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
}
