<?php

namespace JayPS\Search;

class Model_Keyword extends \Nos\Orm\Model
{
    protected static $_table_name = 'jayps_search_word_occurence';
    protected static $_primary_key = array('mooc_word');

    public function save($cascade = null, $use_transaction = false)
    {
        // Example: called when the indexed model is deleted (through the 'has_many' relation)
        // NOTE: after_save & before_delete should perform any required operations on the table jayps_search_word_occurence,
        // there is no need to do anything here

        // return WITHOUT calling the parent
        // (this would corrupt mooc_foreign_id with 0 in the full table because mooc_join_table is not part of the key in the relation)
        return;
    }
}
